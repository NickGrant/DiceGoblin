import {
  ClientAbilityDefinition,
  ClientContentRegistry,
  ClientKinDefinition,
  ClientUnitTypeDefinition,
} from './client-content-registry';
import { WarbandDieSummary, WarbandUnitSummary } from './warband-contracts';

export interface UnitPromotionHistoryEntry {
  readonly fromUnitType: ClientUnitTypeDefinition;
  readonly toUnitType: ClientUnitTypeDefinition;
  readonly promotedAt: string;
}

export interface UnitAbilityLoadoutEntry {
  readonly ability: ClientAbilityDefinition;
  readonly equipOrder: number;
}

export interface UnitDiceBinding {
  readonly ability: ClientAbilityDefinition;
  readonly slotIndex: number;
  readonly die: WarbandDieSummary;
}

export interface UnitDetail {
  readonly id: string;
  readonly displayName: string;
  readonly unitType: ClientUnitTypeDefinition;
  readonly kin: ClientKinDefinition;
  readonly level: number;
  readonly xp: number;
  readonly lifecycleStatus: 'active';
  readonly promotionHistory: readonly UnitPromotionHistoryEntry[];
  readonly ownedAbilities: readonly ClientAbilityDefinition[];
  readonly abilityLoadout: readonly UnitAbilityLoadoutEntry[];
  readonly diceBindings: readonly UnitDiceBinding[];
}

export interface UnitLoadoutPayload {
  readonly abilities: readonly {
    readonly ability_id: string;
    readonly dice_instance_ids: readonly string[];
  }[];
}

export interface UnitMutationResult {
  readonly unit: UnitDetail;
  readonly playerRevision: number;
}

export class UnitDetailContractError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'UnitDetailContractError';
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function exact(record: Record<string, unknown>, fields: readonly string[]): boolean {
  return Object.keys(record).sort().join('\0') === [...fields].sort().join('\0');
}

function positiveId(value: unknown): value is string {
  return typeof value === 'string' && /^[1-9][0-9]*$/.test(value);
}

function nonblank(value: unknown, maximum = Number.POSITIVE_INFINITY): value is string {
  return typeof value === 'string' && value.trim() !== '' && Array.from(value.trim()).length <= maximum;
}

function nonNegativeInteger(value: unknown): value is number {
  return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}

function unitFromEnvelope(value: unknown, mutation: boolean): { unit: unknown; playerRevision?: number } {
  if (!isRecord(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !isRecord(value['data'])) {
    throw new UnitDetailContractError('Unit response must be a successful API envelope.');
  }
  const data = value['data'];
  const fields = mutation ? ['unit', 'player_revision'] : ['unit'];
  if (!exact(data, fields)) throw new UnitDetailContractError('Unit response contains an invalid field set.');
  if (mutation && !nonNegativeInteger(data['player_revision'])) {
    throw new UnitDetailContractError('Unit mutation revision is malformed.');
  }
  return { unit: data['unit'], ...(mutation ? { playerRevision: data['player_revision'] as number } : {}) };
}

function parseUnit(
  candidate: unknown,
  content: ClientContentRegistry,
  dice: readonly WarbandDieSummary[],
  summary: WarbandUnitSummary | undefined,
  bindingMode: 'exact' | 'replacement',
): UnitDetail {
  if (!isRecord(candidate) || !exact(candidate, [
    'id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status',
    'promotion_history', 'owned_ability_ids', 'ability_loadout', 'dice_bindings',
  ])) throw new UnitDetailContractError('Unit detail is malformed.');

  const id = candidate['id'];
  const name = candidate['display_name'];
  const unitTypeId = candidate['unit_type_id'];
  const kinId = candidate['kin_id'];
  const level = candidate['level'];
  const xp = candidate['xp'];
  if (!positiveId(id) || !nonblank(name, 128) || !nonblank(unitTypeId) || !nonblank(kinId)
    || !nonNegativeInteger(level) || level < 1 || !nonNegativeInteger(xp)
    || candidate['lifecycle_status'] !== 'active') {
    throw new UnitDetailContractError('Unit detail identity is malformed.');
  }
  const unitType = content.getUnitType(unitTypeId);
  const kin = content.getKin(kinId);
  if (!unitType || !kin) throw new UnitDetailContractError('Unit detail references unavailable authored identity.');
  if (summary && (summary.id !== id || summary.displayName !== name.trim()
    || summary.unitType.id !== unitTypeId || summary.kin.id !== kinId
    || summary.level !== level || summary.xp !== xp || summary.lifecycleStatus !== 'active')) {
    throw new UnitDetailContractError('Unit detail disagrees with the roster summary.');
  }

  if (!Array.isArray(candidate['promotion_history'])) throw new UnitDetailContractError('Promotion history is malformed.');
  const promotionHistory = candidate['promotion_history'].map((entry): UnitPromotionHistoryEntry => {
    if (!isRecord(entry) || !exact(entry, ['from_unit_type_id', 'to_unit_type_id', 'promoted_at'])
      || !nonblank(entry['from_unit_type_id']) || !nonblank(entry['to_unit_type_id'])
      || !nonblank(entry['promoted_at']) || Number.isNaN(Date.parse(entry['promoted_at']))) {
      throw new UnitDetailContractError('Promotion history is malformed.');
    }
    const fromUnitType = content.getUnitType(entry['from_unit_type_id']);
    const toUnitType = content.getUnitType(entry['to_unit_type_id']);
    if (!fromUnitType || !toUnitType) throw new UnitDetailContractError('Promotion history references unavailable content.');
    return Object.freeze({ fromUnitType, toUnitType, promotedAt: entry['promoted_at'] });
  });

  if (!Array.isArray(candidate['owned_ability_ids'])) throw new UnitDetailContractError('Owned abilities are malformed.');
  const ownedIds = new Set<string>();
  const ownedAbilities = candidate['owned_ability_ids'].map((abilityId): ClientAbilityDefinition => {
    if (!nonblank(abilityId) || ownedIds.has(abilityId)) throw new UnitDetailContractError('Owned abilities are malformed.');
    const ability = content.getAbility(abilityId);
    if (!ability) throw new UnitDetailContractError('Owned abilities reference unavailable content.');
    ownedIds.add(abilityId);
    return ability;
  });

  if (!Array.isArray(candidate['ability_loadout'])) throw new UnitDetailContractError('Ability loadout is malformed.');
  const equippedIds = new Set<string>();
  const abilityLoadout = candidate['ability_loadout'].map((entry, index): UnitAbilityLoadoutEntry => {
    if (!isRecord(entry) || !exact(entry, ['ability_id', 'equip_order'])
      || !nonblank(entry['ability_id']) || entry['equip_order'] !== index
      || !ownedIds.has(entry['ability_id']) || equippedIds.has(entry['ability_id'])) {
      throw new UnitDetailContractError('Ability loadout is malformed.');
    }
    const ability = content.getAbility(entry['ability_id']);
    if (!ability || ability.kind !== 'active') throw new UnitDetailContractError('Ability loadout contains an unavailable or passive ability.');
    equippedIds.add(ability.id);
    return Object.freeze({ ability, equipOrder: index });
  });
  if (abilityLoadout.length === 0) throw new UnitDetailContractError('Ability loadout cannot be empty.');

  if (!Array.isArray(candidate['dice_bindings'])) throw new UnitDetailContractError('Dice bindings are malformed.');
  const diceById = new Map(dice.map((die) => [die.id, die]));
  const boundDice = new Set<string>();
  const boundSlots = new Set<string>();
  const diceBindings = candidate['dice_bindings'].map((entry): UnitDiceBinding => {
    if (!isRecord(entry) || !exact(entry, ['ability_id', 'slot_index', 'dice_instance_id'])
      || !nonblank(entry['ability_id']) || !nonNegativeInteger(entry['slot_index'])
      || !positiveId(entry['dice_instance_id'])) {
      throw new UnitDetailContractError('Dice binding is malformed.');
    }
    const ability = content.getAbility(entry['ability_id']);
    const die = diceById.get(entry['dice_instance_id']);
    const slotKey = `${entry['ability_id']}:${entry['slot_index']}`;
    if (!ability || !equippedIds.has(ability.id) || entry['slot_index'] >= ability.dice_slot_count
      || !die || boundDice.has(die.id) || boundSlots.has(slotKey)) {
      throw new UnitDetailContractError('Dice binding references unavailable or duplicate state.');
    }
    const summaryBinding = die.bindings[0];
    if (summaryBinding && (summaryBinding.unitId !== id
      || (bindingMode === 'exact' && (summaryBinding.ability.id !== ability.id || summaryBinding.slotIndex !== entry['slot_index'])))) {
      throw new UnitDetailContractError('Dice binding conflicts with the owned-dice summary.');
    }
    if (bindingMode === 'exact' && (!summaryBinding || summaryBinding.unitId !== id
      || summaryBinding.ability.id !== ability.id || summaryBinding.slotIndex !== entry['slot_index'])) {
      throw new UnitDetailContractError('Dice binding disagrees with the owned-dice summary.');
    }
    boundDice.add(die.id);
    boundSlots.add(slotKey);
    return Object.freeze({ ability, slotIndex: entry['slot_index'], die });
  });

  for (const equipped of abilityLoadout) {
    for (let slot = 0; slot < equipped.ability.dice_slot_count; slot += 1) {
      if (!boundSlots.has(`${equipped.ability.id}:${slot}`)) {
        throw new UnitDetailContractError('Dice bindings do not fill every required ability slot.');
      }
    }
  }
  if (bindingMode === 'exact') {
    for (const die of dice) {
      const binding = die.bindings[0];
      if (binding?.unitId === id && !boundDice.has(die.id)) {
        throw new UnitDetailContractError('Owned-dice summary contains an extra binding for this unit.');
      }
    }
  }

  return Object.freeze({
    id, displayName: name.trim(), unitType, kin, level, xp, lifecycleStatus: 'active',
    promotionHistory: Object.freeze(promotionHistory), ownedAbilities: Object.freeze(ownedAbilities),
    abilityLoadout: Object.freeze(abilityLoadout), diceBindings: Object.freeze(diceBindings),
  });
}

export function parseUnitDetailEnvelope(
  value: unknown,
  content: ClientContentRegistry,
  dice: readonly WarbandDieSummary[],
  summary?: WarbandUnitSummary,
): UnitDetail {
  return parseUnit(unitFromEnvelope(value, false).unit, content, dice, summary, 'exact');
}

export function parseUnitMutationEnvelope(
  value: unknown,
  content: ClientContentRegistry,
  dice: readonly WarbandDieSummary[],
  allowBindingReplacement = true,
): UnitMutationResult {
  const envelope = unitFromEnvelope(value, true);
  return Object.freeze({
    unit: parseUnit(envelope.unit, content, dice, undefined, allowBindingReplacement ? 'replacement' : 'exact'),
    playerRevision: envelope.playerRevision!,
  });
}
