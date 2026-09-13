import Phaser from 'phaser';
import { GameStore, UnitDetailState } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { Bounds, RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { WarbandDieSummary } from '../runtime/warband-contracts';
import { GameSceneScreen } from './game-screen-navigation';
import { UnitConfigurationDraft } from './unit-configuration-model';

export type UnitConfigurationSection = 'loadout' | 'abilities' | 'dice';
type UnitCommand = 'idle' | 'renaming' | 'saving-loadout';

export interface UnitConfigurationLayout {
  readonly mode: RuntimeViewportSnapshot['layoutClass'];
  readonly header: Bounds;
  readonly back: Bounds;
  readonly name: Bounds;
  readonly identity: Bounds;
  readonly configuration: Bounds;
  readonly actions: Bounds;
  readonly pageSize: number;
}

function box(x: number, y: number, width: number, height: number): Bounds {
  return { x, y, width, height, right: x + width, bottom: y + height };
}

export function createUnitConfigurationLayout(snapshot: RuntimeViewportSnapshot): UnitConfigurationLayout {
  const { safeBounds, layoutClass: mode } = snapshot;
  const margin = mode === 'compact' ? 24 : mode === 'wide' ? 58 : 42;
  const gap = mode === 'compact' ? 14 : 20;
  const left = safeBounds.x + margin;
  const width = safeBounds.width - margin * 2;
  const header = box(left, safeBounds.y + margin, width, mode === 'compact' ? 126 : 116);
  const back = box(left, header.y + 5, mode === 'compact' ? 188 : 160, mode === 'compact' ? 86 : 54);
  const nameWidth = Math.min(mode === 'compact' ? 650 : 520, width * 0.4);
  const name = box(header.right - nameWidth, header.y + 3, nameWidth, mode === 'compact' ? 94 : 86);
  const actions = box(left, safeBounds.bottom - margin - (mode === 'compact' ? 82 : 62), width, mode === 'compact' ? 82 : 62);
  const mainY = header.bottom + gap;
  const mainHeight = Math.max(230, actions.y - gap - mainY);
  const identityWidth = Math.min(mode === 'wide' ? 520 : 430, width * (mode === 'compact' ? 0.28 : 0.3));
  return {
    mode, header, back, name,
    identity: box(left, mainY, identityWidth, mainHeight),
    configuration: box(left + identityWidth + gap, mainY, width - identityWidth - gap, mainHeight),
    actions,
    pageSize: mode === 'compact' ? 3 : mode === 'wide' ? 6 : 4,
  };
}

/** Phaser-owned per-instance unit detail, rename, and atomic loadout editor. */
export class UnitConfigurationScreen implements GameSceneScreen {
  readonly key = 'unit-configuration' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private nameInput: HTMLInputElement | null = null;
  private unsubscribeStore: (() => void) | null = null;
  private activeLayout: UnitConfigurationLayout | null = null;
  private draftValue: UnitConfigurationDraft | null = null;
  private activeSection: UnitConfigurationSection = 'loadout';
  private command: UnitCommand = 'idle';
  private confirmation = false;
  private portraitGateActive = false;
  private integrityBlocked = false;
  private message = '';
  private pages: Record<UnitConfigurationSection, number> = { loadout: 0, abilities: 0, dice: 0 };

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly store: GameStore,
    private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry,
    private readonly viewport: RuntimeViewport,
    readonly unitId: string,
    private readonly returnToWarband: () => void,
  ) {}

  get draft(): UnitConfigurationDraft | null { return this.draftValue; }
  get section(): UnitConfigurationSection { return this.activeSection; }
  get layout(): UnitConfigurationLayout | null { return this.activeLayout; }

  create(): void {
    this.unsubscribeStore = this.store.subscribeWarband(() => this.reflow(this.viewport.snapshot));
    this.reflow(this.viewport.snapshot);
    void this.store.loadUnitDetail(this.unitId, this.api, this.content);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.portraitGateActive = snapshot.portraitGateActive;
    const state = this.store.unitDetail(this.unitId);
    if (!this.draftValue && state.status === 'fresh' && state.data) {
      this.draftValue = new UnitConfigurationDraft(this.unitId, state.data.ownedAbilities, state.data);
      this.createNameInput();
    }
    this.root?.destroy(true);
    this.root = null;
    this.activeLayout = createUnitConfigurationLayout(snapshot);
    this.render(snapshot, this.activeLayout, state);
    this.positionNameInput(snapshot, this.activeLayout);
    this.publishReadyState(state.status === 'fresh' && !!this.draftValue);
  }

  destroy(): void {
    this.unsubscribeStore?.();
    this.unsubscribeStore = null;
    this.root?.destroy(true);
    this.root = null;
    this.nameInput?.remove();
    this.nameInput = null;
    this.publishReadyState(false);
  }

  requestBack(): void {
    if (this.command !== 'idle') return;
    if (this.draftValue?.dirty) {
      this.confirmation = true;
      this.reflow(this.viewport.snapshot);
    } else this.returnToWarband();
  }

  confirmDiscard(): void { if (this.confirmation) this.returnToWarband(); }
  cancelConfirmation(): void {
    if (!this.confirmation) return;
    this.confirmation = false;
    this.reflow(this.viewport.snapshot);
  }

  selectSection(section: UnitConfigurationSection): void {
    if (this.interactionBlocked) return;
    this.activeSection = section;
    this.reflow(this.viewport.snapshot);
  }

  addAbility(abilityId: string): void {
    if (!this.interactionBlocked && this.draftValue?.addAbility(abilityId)) {
      this.activeSection = 'loadout'; this.message = ''; this.reflow(this.viewport.snapshot);
    }
  }

  removeAbility(abilityId: string): void {
    if (!this.interactionBlocked && this.draftValue?.removeAbility(abilityId)) {
      this.message = ''; this.reflow(this.viewport.snapshot);
    }
  }

  moveAbility(abilityId: string, direction: -1 | 1): void {
    if (!this.interactionBlocked && this.draftValue?.moveAbility(abilityId, direction)) {
      this.message = ''; this.reflow(this.viewport.snapshot);
    }
  }

  selectSlot(abilityId: string, slotIndex: number): void {
    if (!this.interactionBlocked && this.draftValue?.selectSlot(abilityId, slotIndex)) {
      this.activeSection = 'dice'; this.pages.dice = 0; this.reflow(this.viewport.snapshot);
    }
  }

  assignDie(dieId: string): void {
    const dice = this.store.warband.dice.data ?? [];
    if (!this.interactionBlocked && this.draftValue?.assignSelectedDie(dieId, dice)) {
      this.message = ''; this.reflow(this.viewport.snapshot);
    }
  }

  async saveRename(): Promise<void> {
    const draft = this.draftValue;
    if (!draft || this.interactionBlocked || !draft.renameDirty) return;
    const validation = draft.renameValidationError;
    if (validation) { this.message = validation; this.reflow(this.viewport.snapshot); return; }
    const bootstrap = this.store.bootstrap;
    const dice = this.store.warband.dice.data;
    if (!bootstrap || this.store.warband.dice.status !== 'fresh' || !dice) return;
    const submitted = draft.renamePayload().name;
    this.command = 'renaming'; this.message = 'Renaming goblin...'; this.reflow(this.viewport.snapshot);
    try {
      const result = await this.api.renameUnit(this.unitId, submitted, bootstrap.session.csrf_token, this.content, dice);
      this.store.reconcileUnitRename(result);
      draft.acceptRename(result.unit);
      if (this.nameInput) this.nameInput.value = draft.name;
      this.command = 'idle'; this.message = 'Name saved.'; this.reflow(this.viewport.snapshot);
    } catch (error) { this.showFailure(error, 'rename'); }
  }

  async saveLoadout(): Promise<void> {
    const draft = this.draftValue;
    if (!draft || this.interactionBlocked || !draft.loadoutDirty) return;
    const validation = draft.loadoutValidationError;
    if (validation) { this.message = validation; this.reflow(this.viewport.snapshot); return; }
    const bootstrap = this.store.bootstrap;
    const dice = this.store.warband.dice.data;
    if (!bootstrap || this.store.warband.dice.status !== 'fresh' || !dice) return;
    const payload = draft.loadoutPayload();
    this.command = 'saving-loadout'; this.message = 'Saving complete loadout...'; this.reflow(this.viewport.snapshot);
    try {
      const result = await this.api.replaceUnitLoadout(this.unitId, payload, bootstrap.session.csrf_token, this.content, dice);
      this.store.reconcileUnitLoadout(result);
      draft.acceptLoadout(result.unit);
      this.command = 'idle'; this.message = 'Loadout saved.'; this.reflow(this.viewport.snapshot);
    } catch (error) { this.showFailure(error, 'loadout'); }
  }

  private showFailure(error: unknown, operation: 'rename' | 'loadout'): void {
    this.command = 'idle';
    if (error instanceof RuntimeApiError) {
      if (error.kind === 'unauthorized') this.message = 'Your session expired. Your local draft is preserved.';
      else if (error.kind === 'malformed-response') this.message = 'The response failed an integrity check. Nothing was committed locally.';
      else if (error.status === 404) this.message = 'This goblin is no longer available.';
      else if (error.status === 422) this.message = operation === 'rename'
        ? 'The server rejected this name. Review it and retry.'
        : error.code === 'die_already_bound' ? 'A selected die is now used by another goblin.' : 'The server rejected this complete loadout.';
      else this.message = `The ${operation} command failed. Your local draft is preserved.`;
    } else {
      this.integrityBlocked = true;
      this.message = 'State reconciliation failed. Return to Warband and deliberately reload stale records.';
    }
    this.reflow(this.viewport.snapshot);
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: UnitConfigurationLayout, state: UnitDetailState): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale);
    this.root = root;
    const background = this.scene.add.graphics();
    background.fillGradientStyle(0x0d2422, 0x18362d, 0x09191b, 0x10252a, 1);
    background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    root.add(background);
    this.addButton(root, layout.back, 'BACK TO WARBAND', () => this.requestBack(), false);
    root.add(this.scene.add.text(layout.back.right + 28, layout.header.y + 4, 'GOBLIN CONFIGURATION', {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '42px' : '44px',
      fontStyle: 'bold', stroke: '#302015', strokeThickness: 5,
    }));
    if (!this.draftValue || state.status !== 'fresh' || !state.data) {
      this.renderState(root, snapshot, state);
      return;
    }
    root.add(this.scene.add.text(layout.name.x, layout.name.y, `NAME${this.draftValue.renameDirty ? ' - UNSAVED' : ''}`, {
      color: '#c8b98f', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold',
    }));
    this.renderIdentity(root, layout, state.data);
    this.renderConfiguration(root, layout);
    this.renderActions(root, layout);
    if (this.confirmation) this.renderConfirmation(root, snapshot);
  }

  private renderState(root: Phaser.GameObjects.Container, snapshot: RuntimeViewportSnapshot, state: UnitDetailState): void {
    const title = state.status === 'error'
      ? state.error === 'unauthorized' ? 'YOUR SESSION EXPIRED' : state.error === 'integrity' ? 'UNIT DATA DISAGREES' : 'UNIT UNAVAILABLE'
      : 'OPENING GOBLIN RECORD...';
    const detail = state.status === 'error'
      ? 'Committed Warband state was preserved. Retry after the relevant ledgers are fresh.'
      : 'Loading authoritative abilities and exact die bindings.';
    root.add(this.scene.add.text(snapshot.logicalWidth / 2, snapshot.logicalHeight / 2 - 26, title, {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: '34px', fontStyle: 'bold',
    }).setOrigin(0.5));
    root.add(this.scene.add.text(snapshot.logicalWidth / 2, snapshot.logicalHeight / 2 + 24, detail, {
      color: '#c8b98f', fontFamily: 'system-ui', fontSize: '18px', align: 'center',
    }).setOrigin(0.5));
    if (state.status === 'error') this.addButton(root, box(snapshot.logicalWidth / 2 - 90, snapshot.logicalHeight / 2 + 70, 180, 54), 'RETRY DETAIL', () => {
      void this.store.retryUnitDetail(this.unitId, this.api, this.content);
    }, true);
  }

  private renderIdentity(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout, detail: NonNullable<UnitDetailState['data']>): void {
    this.addPanel(root, layout.identity);
    const x = layout.identity.x + 22;
    let y = layout.identity.y + 20;
    root.add(this.scene.add.text(x, y, detail.displayName, { color: '#532f1f', fontFamily: 'Georgia, serif', fontSize: '30px', fontStyle: 'bold', wordWrap: { width: layout.identity.width - 44 } })); y += 48;
    root.add(this.scene.add.text(x, y, `${detail.unitType.display_name} · ${detail.kin.display_name}`, { color: '#6a321f', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '21px' : '18px', fontStyle: 'bold', wordWrap: { width: layout.identity.width - 44 } })); y += 35;
    root.add(this.scene.add.text(x, y, `LEVEL ${detail.level}  ·  ${detail.xp} XP`, { color: '#315947', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '21px' : '18px', fontStyle: 'bold' })); y += 42;
    root.add(this.scene.add.text(x, y, detail.unitType.description, { color: '#74664b', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '18px' : '15px', wordWrap: { width: layout.identity.width - 44 } })); y += 76;
    root.add(this.scene.add.text(x, y, `OWNED: ${detail.ownedAbilities.filter((ability) => ability.kind === 'active').length} ACTIVE · ${detail.ownedAbilities.filter((ability) => ability.kind === 'passive').length} PASSIVE`, { color: '#6a321f', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '17px' : '14px', fontStyle: 'bold', wordWrap: { width: layout.identity.width - 44 } })); y += 38;
    const passiveNames = detail.ownedAbilities.filter((ability) => ability.kind === 'passive').map((ability) => ability.display_name);
    root.add(this.scene.add.text(x, y, `PASSIVES\n${passiveNames.join('\n') || 'None owned'}`, { color: '#3a2a1a', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '17px' : '15px', lineSpacing: 7, wordWrap: { width: layout.identity.width - 44 } }));
    if (detail.promotionHistory.length > 0) root.add(this.scene.add.text(x, layout.identity.bottom - 52, `PROMOTIONS · ${detail.promotionHistory.map((entry) => entry.toUnitType.display_name).join(' → ')}`, { color: '#74664b', fontFamily: 'system-ui', fontSize: '13px', wordWrap: { width: layout.identity.width - 44 } }));
  }

  private renderConfiguration(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout): void {
    this.addPanel(root, layout.configuration);
    const tabs: readonly [UnitConfigurationSection, string][] = [['loadout', 'ORDERED LOADOUT'], ['abilities', 'OWNED ABILITIES'], ['dice', 'DICE FOR SLOT']];
    const tabGap = 10;
    const tabWidth = (layout.configuration.width - 40 - tabGap * 2) / 3;
    const tabHeight = layout.mode === 'compact' ? 80 : 48;
    tabs.forEach(([section, label], index) => this.addButton(root, box(layout.configuration.x + 20 + index * (tabWidth + tabGap), layout.configuration.y + 18, tabWidth, tabHeight), label, () => this.selectSection(section), this.activeSection === section));
    if (this.activeSection === 'loadout') this.renderLoadout(root, layout);
    else if (this.activeSection === 'abilities') this.renderAbilities(root, layout);
    else this.renderDice(root, layout);
  }

  private renderLoadout(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout): void {
    const draft = this.draftValue!;
    const visible = this.page('loadout', draft.loadout, layout.pageSize);
    const top = layout.configuration.y + (layout.mode === 'compact' ? 114 : 82);
    const footer = draft.loadout.length > layout.pageSize ? 56 : 12;
    const gap = 10;
    const height = Math.min(layout.mode === 'compact' ? 190 : 150, Math.max(96, (layout.configuration.bottom - top - footer - gap * Math.max(0, visible.length - 1)) / Math.max(1, visible.length)));
    visible.forEach((entry, index) => {
      const absoluteIndex = this.pages.loadout * layout.pageSize + index;
      const region = box(layout.configuration.x + 18, top + index * (height + gap), layout.configuration.width - 36, height);
      this.addRow(root, region, `${absoluteIndex + 1}. ${entry.ability.display_name}`, entry.ability.description, false);
      const actionHeight = layout.mode === 'compact' ? 64 : 34;
      const moveWidth = layout.mode === 'compact' ? 60 : 40;
      const removeWidth = layout.mode === 'compact' ? 105 : 70;
      let x = region.right - 12;
      this.addButton(root, box(x - removeWidth, region.y + 8, removeWidth, actionHeight), 'REMOVE', () => this.removeAbility(entry.ability.id), false); x -= removeWidth + 8;
      this.addButton(root, box(x - moveWidth, region.y + 8, moveWidth, actionHeight), '▼', () => this.moveAbility(entry.ability.id, 1), false); x -= moveWidth + 8;
      this.addButton(root, box(x - moveWidth, region.y + 8, moveWidth, actionHeight), '▲', () => this.moveAbility(entry.ability.id, -1), false);
      const slotWidth = Math.min(104, Math.max(64, (region.width - 36) / Math.max(1, entry.diceInstanceIds.length + 2)));
      entry.diceInstanceIds.forEach((dieId, slotIndex) => {
        const die = (this.store.warband.dice.data ?? []).find((candidate) => candidate.id === dieId);
        const label = die ? `S${slotIndex + 1} · d${die.size}` : `S${slotIndex + 1} · EMPTY`;
        const slotHeight = layout.mode === 'compact' ? 64 : 34;
        this.addButton(root, box(region.x + 16 + slotIndex * (slotWidth + 8), region.bottom - slotHeight - 8, slotWidth, slotHeight), label, () => this.selectSlot(entry.ability.id, slotIndex), draft.selectedSlot?.abilityId === entry.ability.id && draft.selectedSlot.slotIndex === slotIndex);
      });
    });
    this.renderPager(root, layout, 'loadout', draft.loadout.length);
  }

  private renderAbilities(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout): void {
    const draft = this.draftValue!;
    const abilities = draft.ownedAbilities;
    const visible = this.page('abilities', abilities, layout.pageSize);
    const top = layout.configuration.y + (layout.mode === 'compact' ? 114 : 82);
    const gap = 10;
    const footer = abilities.length > layout.pageSize ? 56 : 12;
    const height = Math.min(layout.mode === 'compact' ? 135 : 112, Math.max(78, (layout.configuration.bottom - top - footer - gap * Math.max(0, visible.length - 1)) / Math.max(1, visible.length)));
    visible.forEach((ability, index) => {
      const region = box(layout.configuration.x + 18, top + index * (height + gap), layout.configuration.width - 36, height);
      const equipped = draft.loadout.some((entry) => entry.ability.id === ability.id);
      this.addRow(root, region, ability.display_name, `${ability.kind.toUpperCase()} · ${ability.kind === 'active' ? `${ability.dice_slot_count} die slot${ability.dice_slot_count === 1 ? '' : 's'}` : 'Always visible, never scheduled'} · ${ability.description}`, ability.kind === 'passive');
      if (ability.kind === 'active' && !equipped) {
        const buttonHeight = layout.mode === 'compact' ? 64 : 42;
        this.addButton(root, box(region.right - (layout.mode === 'compact' ? 142 : 112), region.y + region.height / 2 - buttonHeight / 2, layout.mode === 'compact' ? 124 : 94, buttonHeight), 'ADD', () => this.addAbility(ability.id), true);
      }
      else root.add(this.scene.add.text(region.right - 22, region.y + region.height / 2, ability.kind === 'passive' ? 'PASSIVE' : 'EQUIPPED', { color: ability.kind === 'passive' ? '#6d4a85' : '#356b43', fontFamily: 'system-ui', fontSize: '13px', fontStyle: 'bold' }).setOrigin(1, 0.5));
    });
    this.renderPager(root, layout, 'abilities', abilities.length);
  }

  private renderDice(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout): void {
    const draft = this.draftValue!;
    const selected = draft.selectedSlot;
    if (!selected) {
      root.add(this.scene.add.text(layout.configuration.x + layout.configuration.width / 2, layout.configuration.y + layout.configuration.height / 2, 'SELECT AN ABILITY SLOT\nChoose a slot in Ordered Loadout, then assign one exact physical die.', { color: '#5b351f', fontFamily: 'Georgia, serif', fontSize: '24px', align: 'center', lineSpacing: 10 }).setOrigin(0.5));
      return;
    }
    const dice = this.store.warband.dice.data ?? [];
    const visible = this.page('dice', dice, layout.pageSize);
    const ability = draft.loadout.find((entry) => entry.ability.id === selected.abilityId)?.ability;
    const labelY = layout.configuration.y + (layout.mode === 'compact' ? 112 : 78);
    root.add(this.scene.add.text(layout.configuration.x + 22, labelY, `${ability?.display_name ?? 'Ability'} · SLOT ${selected.slotIndex + 1}`, { color: '#6a321f', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '20px' : '16px', fontStyle: 'bold' }));
    const top = layout.configuration.y + (layout.mode === 'compact' ? 148 : 110);
    const gap = 9;
    const footer = dice.length > layout.pageSize ? 56 : 12;
    const height = Math.min(layout.mode === 'compact' ? 120 : 94, Math.max(72, (layout.configuration.bottom - top - footer - gap * Math.max(0, visible.length - 1)) / Math.max(1, visible.length)));
    visible.forEach((die, index) => {
      const region = box(layout.configuration.x + 18, top + index * (height + gap), layout.configuration.width - 36, height);
      const availability = draft.dieAvailability(die);
      const aspect = die.aspects.map((item) => item.display_name).join(', ') || 'Unaspected';
      this.addRow(root, region, `d${die.size} ${die.profile.display_name}`, `${die.material.display_name} · ${aspect} · ${die.profile.rarity.toUpperCase()} · ${availability === 'other-unit' ? 'BOUND TO ANOTHER GOBLIN' : availability === 'this-unit' ? 'MOVABLE ON THIS GOBLIN' : 'AVAILABLE'}`, availability === 'other-unit');
      if (availability !== 'other-unit') {
        const buttonHeight = layout.mode === 'compact' ? 64 : 42;
        this.addButton(root, box(region.right - (layout.mode === 'compact' ? 142 : 112), region.y + region.height / 2 - buttonHeight / 2, layout.mode === 'compact' ? 124 : 94, buttonHeight), 'ASSIGN', () => this.assignDie(die.id), true);
      }
    });
    this.renderPager(root, layout, 'dice', dice.length);
  }

  private renderActions(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout): void {
    const draft = this.draftValue!;
    const gap = 12;
    const width = 190;
    this.addButton(root, box(layout.actions.x, layout.actions.y, width, layout.actions.height), 'CANCEL', () => this.requestBack(), false);
    this.addButton(root, box(layout.actions.x + width + gap, layout.actions.y, width, layout.actions.height), this.command === 'renaming' ? 'RENAMING...' : 'SAVE NAME', () => { void this.saveRename(); }, draft.renameDirty);
    this.addButton(root, box(layout.actions.x + (width + gap) * 2, layout.actions.y, width + 30, layout.actions.height), this.command === 'saving-loadout' ? 'SAVING...' : 'SAVE LOADOUT', () => { void this.saveLoadout(); }, draft.loadoutDirty);
    const status = this.integrityBlocked ? 'STATE INTEGRITY BLOCK' : draft.loadoutValidationError ?? this.message;
    if (status) root.add(this.scene.add.text(layout.actions.right, layout.actions.y + layout.actions.height / 2, status, { color: this.integrityBlocked || draft.loadoutValidationError ? '#ffd09b' : '#d8e6bd', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold', wordWrap: { width: Math.max(280, layout.actions.width - 640) }, align: 'right' }).setOrigin(1, 0.5));
  }

  private renderConfirmation(root: Phaser.GameObjects.Container, snapshot: RuntimeViewportSnapshot): void {
    const blocker = this.scene.add.graphics();
    blocker.fillStyle(0x07110f, 0.82); blocker.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    blocker.setInteractive(new Phaser.Geom.Rectangle(0, 0, snapshot.logicalWidth, snapshot.logicalHeight), Phaser.Geom.Rectangle.Contains);
    root.add(blocker);
    const region = box(snapshot.logicalWidth / 2 - 300, snapshot.logicalHeight / 2 - 120, 600, 240);
    this.addPanel(root, region);
    root.add(this.scene.add.text(region.x + region.width / 2, region.y + 48, 'DISCARD UNIT CHANGES?', { color: '#5b351f', fontFamily: 'Georgia, serif', fontSize: '28px', fontStyle: 'bold' }).setOrigin(0.5));
    root.add(this.scene.add.text(region.x + region.width / 2, region.y + 94, 'Unsaved name and loadout edits will be lost.', { color: '#74664b', fontFamily: 'system-ui', fontSize: '16px' }).setOrigin(0.5));
    this.addButton(root, box(region.x + 42, region.bottom - 80, 220, 54), 'KEEP EDITING', () => this.cancelConfirmation(), false);
    this.addButton(root, box(region.right - 262, region.bottom - 80, 220, 54), 'DISCARD', () => this.confirmDiscard(), true);
  }

  private createNameInput(): void {
    if (this.nameInput || !this.draftValue) return;
    const parent = (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement;
    if (!parent) return;
    const input = document.createElement('input');
    input.type = 'text'; input.value = this.draftValue.name; input.maxLength = 128; input.autocomplete = 'off'; input.spellcheck = false;
    input.setAttribute('aria-label', 'Goblin name'); input.dataset['unitNameInput'] = 'true';
    Object.assign(input.style, { position: 'absolute', zIndex: '4', boxSizing: 'border-box', border: '3px solid #b69a65', borderRadius: '10px', background: '#f2e4c1', color: '#3a2a1a', fontFamily: 'Georgia, serif', fontWeight: '700', outline: 'none' });
    input.addEventListener('input', () => {
      if (this.nativeInputBlocked || !this.draftValue) { input.value = this.draftValue?.name ?? ''; return; }
      this.draftValue.setName(input.value); this.message = '';
    });
    parent.appendChild(input); this.nameInput = input;
  }

  private positionNameInput(snapshot: RuntimeViewportSnapshot, layout: UnitConfigurationLayout): void {
    if (!this.nameInput) return;
    const blocked = this.nativeInputBlocked;
    this.nameInput.disabled = blocked; this.nameInput.readOnly = blocked; this.nameInput.tabIndex = blocked ? -1 : 0;
    this.nameInput.setAttribute('aria-disabled', blocked ? 'true' : 'false');
    if (blocked && document.activeElement === this.nameInput) this.nameInput.blur();
    Object.assign(this.nameInput.style, {
      left: `${layout.name.x * snapshot.gameScale}px`, top: `${(layout.name.y + 25) * snapshot.gameScale}px`,
      width: `${layout.name.width * snapshot.gameScale}px`, height: `${(layout.name.height - 25) * snapshot.gameScale}px`,
      padding: `0 ${16 * snapshot.gameScale}px`, fontSize: `${Math.max(16, 23 * snapshot.gameScale)}px`,
      display: this.confirmation || !this.draftValue ? 'none' : 'block', pointerEvents: blocked ? 'none' : 'auto',
    });
  }

  private get interactionBlocked(): boolean {
    return this.command !== 'idle' || this.confirmation || this.portraitGateActive || this.integrityBlocked;
  }
  private get nativeInputBlocked(): boolean { return this.interactionBlocked || !this.draftValue; }

  private page<T>(section: UnitConfigurationSection, items: readonly T[], size: number): readonly T[] {
    const count = Math.max(1, Math.ceil(items.length / size));
    this.pages[section] = Math.min(this.pages[section], count - 1);
    return items.slice(this.pages[section] * size, (this.pages[section] + 1) * size);
  }

  private renderPager(root: Phaser.GameObjects.Container, layout: UnitConfigurationLayout, section: UnitConfigurationSection, count: number): void {
    const pages = Math.max(1, Math.ceil(count / layout.pageSize));
    if (pages <= 1) return;
    const height = layout.mode === 'compact' ? 60 : 38;
    const width = layout.mode === 'compact' ? 122 : 92;
    const y = layout.configuration.bottom - height - 10;
    this.addButton(root, box(layout.configuration.right - (width * 2 + 64), y, width, height), '< PREV', () => this.changePage(section, -1, pages), false);
    this.addButton(root, box(layout.configuration.right - width - 20, y, width, height), 'NEXT >', () => this.changePage(section, 1, pages), false);
    root.add(this.scene.add.text(layout.configuration.right - width - 42, y + height / 2, `${this.pages[section] + 1} / ${pages}`, { color: '#5b351f', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '17px' : '13px', fontStyle: 'bold' }).setOrigin(1, 0.5));
  }

  private changePage(section: UnitConfigurationSection, direction: -1 | 1, pages: number): void {
    if (this.interactionBlocked) return;
    this.pages[section] = (this.pages[section] + direction + pages) % pages;
    this.reflow(this.viewport.snapshot);
  }

  private addPanel(root: Phaser.GameObjects.Container, region: Bounds): void {
    const panel = this.scene.add.graphics(); panel.fillStyle(0xf2e4c1, 0.98); panel.fillRoundedRect(region.x, region.y, region.width, region.height, 16);
    panel.lineStyle(4, 0x8a5a34, 1); panel.strokeRoundedRect(region.x + 2, region.y + 2, region.width - 4, region.height - 4, 16); root.add(panel);
  }

  private addRow(root: Phaser.GameObjects.Container, region: Bounds, title: string, detail: string, unavailable: boolean): void {
    const compact = this.activeLayout?.mode === 'compact';
    const card = this.scene.add.graphics(); card.fillStyle(unavailable ? 0xcdbfa4 : 0xe2d2ab, 1); card.fillRoundedRect(region.x, region.y, region.width, region.height, 10);
    card.lineStyle(2, unavailable ? 0x8c7e68 : 0xb69a65, 1); card.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 10); root.add(card);
    root.add(this.scene.add.text(region.x + 16, region.y + 9, title, { color: unavailable ? '#766b5b' : '#3a2a1a', fontFamily: 'Georgia, serif', fontSize: compact ? '25px' : '19px', fontStyle: 'bold' }));
    root.add(this.scene.add.text(region.x + 16, region.y + (compact ? 43 : 36), detail, { color: unavailable ? '#766b5b' : '#74664b', fontFamily: 'system-ui', fontSize: compact ? '18px' : '13px', wordWrap: { width: region.width - 150 } }));
  }

  private addButton(root: Phaser.GameObjects.Container, region: Bounds, label: string, action: () => void, active: boolean): void {
    const compact = this.activeLayout?.mode === 'compact';
    const button = this.scene.add.graphics(); button.fillStyle(active ? 0x8a5a34 : 0x273e35, 1); button.fillRoundedRect(region.x, region.y, region.width, region.height, 10);
    button.lineStyle(2, active ? 0xf2c14e : 0x8a6a45, 1); button.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 10);
    button.setInteractive(new Phaser.Geom.Rectangle(region.x, region.y, region.width, region.height), Phaser.Geom.Rectangle.Contains); button.on('pointerup', action); root.add(button);
    root.add(this.scene.add.text(region.x + region.width / 2, region.y + region.height / 2, label, { color: '#fff4d3', fontFamily: 'system-ui', fontSize: compact ? (region.height > 60 ? '19px' : '17px') : (region.height > 60 ? '16px' : '13px'), fontStyle: 'bold', align: 'center' }).setOrigin(0.5));
  }

  private publishReadyState(ready: boolean): void {
    const parent = (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement;
    if (parent) parent.dataset['unitConfigurationReady'] = ready ? 'true' : 'false';
  }
}
