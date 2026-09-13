import { ClientAbilityDefinition } from '../runtime/client-content-registry';
import { UnitDetail, UnitLoadoutPayload } from '../runtime/unit-detail-contracts';
import { WarbandDieSummary } from '../runtime/warband-contracts';

export interface UnitLoadoutDraftEntry {
  readonly ability: ClientAbilityDefinition;
  readonly diceInstanceIds: readonly (string | null)[];
}

export interface UnitDraftSlot {
  readonly abilityId: string;
  readonly slotIndex: number;
}

function loadoutFromDetail(detail: UnitDetail): UnitLoadoutDraftEntry[] {
  return detail.abilityLoadout.map(({ ability }) => ({
    ability,
    diceInstanceIds: Array.from({ length: ability.dice_slot_count }, (_, slotIndex) =>
      detail.diceBindings.find((binding) => binding.ability.id === ability.id && binding.slotIndex === slotIndex)?.die.id ?? null),
  }));
}

function signature(entries: readonly UnitLoadoutDraftEntry[]): string {
  return JSON.stringify(entries.map((entry) => [entry.ability.id, entry.diceInstanceIds]));
}

/** Independent local rename and whole-loadout draft for one authoritative unit detail. */
export class UnitConfigurationDraft {
  private committedName: string;
  private committedLoadout: string;
  private nameValue: string;
  private entries: UnitLoadoutDraftEntry[];
  private slot: UnitDraftSlot | null = null;

  constructor(readonly unitId: string, readonly ownedAbilities: readonly ClientAbilityDefinition[], detail: UnitDetail) {
    this.committedName = detail.displayName;
    this.nameValue = detail.displayName;
    this.entries = loadoutFromDetail(detail);
    this.committedLoadout = signature(this.entries);
  }

  get name(): string { return this.nameValue; }
  get loadout(): readonly UnitLoadoutDraftEntry[] { return this.entries; }
  get selectedSlot(): UnitDraftSlot | null { return this.slot; }
  get renameDirty(): boolean { return this.nameValue.trim() !== this.committedName; }
  get loadoutDirty(): boolean { return signature(this.entries) !== this.committedLoadout; }
  get dirty(): boolean { return this.renameDirty || this.loadoutDirty; }

  get activeOwnedAbilities(): readonly ClientAbilityDefinition[] {
    return this.ownedAbilities.filter((ability) => ability.kind === 'active');
  }

  get passiveOwnedAbilities(): readonly ClientAbilityDefinition[] {
    return this.ownedAbilities.filter((ability) => ability.kind === 'passive');
  }

  get renameValidationError(): string | null {
    const normalized = this.nameValue.trim();
    if (!normalized) return 'Enter a goblin name.';
    if (Array.from(normalized).length > 128) return 'Goblin names may contain at most 128 characters.';
    return null;
  }

  get loadoutValidationError(): string | null {
    if (this.entries.length === 0) return 'Equip at least one active ability.';
    const abilities = new Set<string>();
    const dice = new Set<string>();
    for (const entry of this.entries) {
      if (entry.ability.kind !== 'active' || !this.activeOwnedAbilities.some((ability) => ability.id === entry.ability.id)
        || abilities.has(entry.ability.id) || entry.diceInstanceIds.length !== entry.ability.dice_slot_count) {
        return 'The active ability order is invalid.';
      }
      abilities.add(entry.ability.id);
      for (const dieId of entry.diceInstanceIds) {
        if (!dieId) return `Assign every die slot for ${entry.ability.display_name}.`;
        if (dice.has(dieId)) return 'One physical die cannot fill multiple slots.';
        dice.add(dieId);
      }
    }
    return null;
  }

  setName(name: string): void { this.nameValue = name; }

  addAbility(abilityId: string): boolean {
    const ability = this.activeOwnedAbilities.find((candidate) => candidate.id === abilityId);
    if (!ability || this.entries.some((entry) => entry.ability.id === abilityId)) return false;
    this.entries = [...this.entries, { ability, diceInstanceIds: Array(ability.dice_slot_count).fill(null) }];
    return true;
  }

  removeAbility(abilityId: string): boolean {
    if (!this.entries.some((entry) => entry.ability.id === abilityId)) return false;
    this.entries = this.entries.filter((entry) => entry.ability.id !== abilityId);
    if (this.slot?.abilityId === abilityId) this.slot = null;
    return true;
  }

  moveAbility(abilityId: string, direction: -1 | 1): boolean {
    const from = this.entries.findIndex((entry) => entry.ability.id === abilityId);
    const to = from + direction;
    if (from < 0 || to < 0 || to >= this.entries.length) return false;
    const next = [...this.entries];
    [next[from], next[to]] = [next[to], next[from]];
    this.entries = next;
    return true;
  }

  selectSlot(abilityId: string, slotIndex: number): boolean {
    const entry = this.entries.find((candidate) => candidate.ability.id === abilityId);
    if (!entry || slotIndex < 0 || slotIndex >= entry.diceInstanceIds.length) return false;
    this.slot = Object.freeze({ abilityId, slotIndex });
    return true;
  }

  assignSelectedDie(dieId: string, dice: readonly WarbandDieSummary[]): boolean {
    if (!this.slot) return false;
    const die = dice.find((candidate) => candidate.id === dieId);
    if (!die || die.bindings.some((binding) => binding.unitId !== this.unitId)) return false;
    const next = this.entries.map((entry) => ({ ...entry, diceInstanceIds: [...entry.diceInstanceIds] }));
    for (const entry of next) {
      entry.diceInstanceIds = entry.diceInstanceIds.map((current) => current === dieId ? null : current);
    }
    const target = next.find((entry) => entry.ability.id === this.slot!.abilityId);
    if (!target) return false;
    target.diceInstanceIds[this.slot.slotIndex] = dieId;
    this.entries = next;
    return true;
  }

  clearSelectedDie(): boolean {
    if (!this.slot) return false;
    const next = this.entries.map((entry) => ({ ...entry, diceInstanceIds: [...entry.diceInstanceIds] }));
    const target = next.find((entry) => entry.ability.id === this.slot!.abilityId);
    if (!target) return false;
    target.diceInstanceIds[this.slot.slotIndex] = null;
    this.entries = next;
    return true;
  }

  dieAvailability(die: WarbandDieSummary): 'available' | 'this-unit' | 'other-unit' {
    const binding = die.bindings[0];
    if (!binding) return 'available';
    return binding.unitId === this.unitId ? 'this-unit' : 'other-unit';
  }

  renamePayload(): { readonly name: string } {
    if (this.renameValidationError) throw new Error(this.renameValidationError);
    return { name: this.nameValue.trim() };
  }

  loadoutPayload(): UnitLoadoutPayload {
    if (this.loadoutValidationError) throw new Error(this.loadoutValidationError);
    return {
      abilities: this.entries.map((entry) => ({
        ability_id: entry.ability.id,
        dice_instance_ids: entry.diceInstanceIds as readonly string[],
      })),
    };
  }

  acceptRename(detail: UnitDetail): void {
    this.committedName = detail.displayName;
    this.nameValue = detail.displayName;
  }

  acceptLoadout(detail: UnitDetail): void {
    this.entries = loadoutFromDetail(detail);
    this.committedLoadout = signature(this.entries);
    if (this.slot && !this.entries.some((entry) => entry.ability.id === this.slot!.abilityId)) this.slot = null;
  }
}
