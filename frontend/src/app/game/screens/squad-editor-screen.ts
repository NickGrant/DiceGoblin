import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { actionCursor } from './action-cursor';
import { Bounds, RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { WarbandUnitSummary } from '../runtime/warband-contracts';
import { GameSceneScreen } from './game-screen-navigation';
import { CreateSquadIdempotency, SquadEditorDraft } from './squad-editor-model';

export interface SquadEditorLayout {
  readonly mode: RuntimeViewportSnapshot['layoutClass'];
  readonly title: Bounds;
  readonly name: Bounds;
  readonly formation: Bounds;
  readonly roster: Bounds;
  readonly actions: Bounds;
  readonly rosterPageSize: number;
}

function box(x: number, y: number, width: number, height: number): Bounds {
  return { x, y, width, height, right: x + width, bottom: y + height };
}

export function createSquadEditorLayout(snapshot: RuntimeViewportSnapshot): SquadEditorLayout {
  const { safeBounds, layoutClass: mode } = snapshot;
  const margin = mode === 'compact' ? 24 : 42;
  const gap = mode === 'compact' ? 18 : 24;
  const left = safeBounds.x + margin;
  const width = safeBounds.width - margin * 2;
  const titleHeight = mode === 'compact' ? 86 : 76;
  const title = box(left, safeBounds.y + margin, width, titleHeight);
  const name = box(left, title.bottom + gap, width, mode === 'compact' ? 92 : 72);
  const actionsHeight = mode === 'compact' ? 104 : 72;
  const actions = box(left, safeBounds.bottom - margin - actionsHeight, width, actionsHeight);
  const workTop = name.bottom + gap;
  const workHeight = actions.y - gap - workTop;
  const formationWidth = Math.min(mode === 'wide' ? 850 : 700, width * (mode === 'compact' ? 0.52 : 0.55));
  return {
    mode, title, name,
    formation: box(left, workTop, formationWidth, workHeight),
    roster: box(left + formationWidth + gap, workTop, width - formationWidth - gap, workHeight),
    actions,
    rosterPageSize: mode === 'compact' ? 4 : mode === 'wide' ? 7 : 5,
  };
}

type EditorCommand = 'idle' | 'saving' | 'activating' | 'deleting';
type EditorConfirmation = 'discard' | 'delete' | null;
export type SquadEditorInitialAction = 'none' | 'activate' | 'delete';

export class SquadEditorScreen implements GameSceneScreen {
  readonly key = 'squad-editor' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private nameInput: HTMLInputElement | null = null;
  private layout: SquadEditorLayout | null = null;
  private rosterPage = 0;
  private command: EditorCommand = 'idle';
  private confirmation: EditorConfirmation = null;
  private message = '';
  private integrityBlocked = false;
  private portraitGateActive = false;
  private readonly idempotency = new CreateSquadIdempotency();

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly store: GameStore,
    private readonly api: RuntimeApiClient,
    private readonly viewport: RuntimeViewport,
    readonly draft: SquadEditorDraft,
    private readonly returnToWarband: () => void,
    private readonly initialAction: SquadEditorInitialAction = 'none',
  ) {}

  create(): void {
    this.createNameInput();
    this.reflow(this.viewport.snapshot);
    if (this.initialAction === 'activate') void this.activate();
    else if (this.initialAction === 'delete') this.requestDelete();
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.portraitGateActive = snapshot.portraitGateActive;
    this.root?.destroy(true);
    this.root = null;
    this.layout = createSquadEditorLayout(snapshot);
    this.render(snapshot, this.layout);
    this.positionNameInput(snapshot, this.layout);
    this.publishReadyState(true);
  }

  destroy(): void {
    this.root?.destroy(true);
    this.root = null;
    this.nameInput?.remove();
    this.nameInput = null;
    this.publishReadyState(false);
  }

  requestBack(): void {
    if (this.command !== 'idle') return;
    if (this.draft.dirty) {
      this.confirmation = 'discard';
      this.reflow(this.viewport.snapshot);
    } else {
      this.returnToWarband();
    }
  }

  confirmDiscard(): void {
    if (this.confirmation === 'discard') this.returnToWarband();
  }

  cancelConfirmation(): void {
    if (!this.confirmation) return;
    this.confirmation = null;
    this.reflow(this.viewport.snapshot);
  }

  async save(): Promise<void> {
    if (this.command !== 'idle' || this.integrityBlocked) return;
    if (this.formationLocked && this.draft.formationDirty) {
      this.message = 'This squad is in an active Farm run. Discard formation edits before saving its name.';
      this.reflow(this.viewport.snapshot);
      return;
    }
    const validation = this.draft.validationError;
    if (validation) {
      this.message = validation;
      this.reflow(this.viewport.snapshot);
      return;
    }
    const bootstrap = this.store.bootstrap;
    if (!bootstrap) return;
    const payload = this.draft.payload();
    this.command = 'saving';
    this.message = this.draft.mode === 'create' ? 'Creating squad...' : 'Saving complete formation...';
    this.reflow(this.viewport.snapshot);
    try {
      const result = this.draft.mode === 'create'
        ? await this.api.createSquad(payload, bootstrap.session.csrf_token, this.idempotency.keyFor(payload))
        : await this.api.updateSquad(this.draft.squadId!, payload, bootstrap.session.csrf_token);
      this.store.reconcileSquadMutation(result, this.draft.mode === 'create' ? 'create' : 'update');
      this.idempotency.clear();
      this.returnToWarband();
    } catch (error) {
      if (this.draft.mode === 'create' && error instanceof RuntimeApiError) this.idempotency.recordFailure(error.kind);
      this.showFailure(error);
    }
  }

  async activate(): Promise<void> {
    if (this.command !== 'idle' || this.integrityBlocked || !this.draft.squadId) return;
    if (this.store.activeRunLock) return;
    const bootstrap = this.store.bootstrap;
    if (!bootstrap) return;
    this.command = 'activating';
    this.message = 'Activating squad...';
    this.reflow(this.viewport.snapshot);
    try {
      const result = await this.api.activateSquad(this.draft.squadId, bootstrap.session.csrf_token);
      this.store.reconcileSquadMutation(result, 'activate');
      this.returnToWarband();
    } catch (error) {
      this.showFailure(error);
    }
  }

  requestDelete(): void {
    if (this.command !== 'idle' || this.integrityBlocked || !this.draft.squadId) return;
    if (this.formationLocked) return;
    this.confirmation = 'delete';
    this.reflow(this.viewport.snapshot);
  }

  async confirmDelete(): Promise<void> {
    if (this.command !== 'idle' || this.integrityBlocked || this.confirmation !== 'delete' || !this.draft.squadId) return;
    if (this.formationLocked) return;
    const bootstrap = this.store.bootstrap;
    if (!bootstrap) return;
    this.confirmation = null;
    this.command = 'deleting';
    this.message = 'Deleting squad...';
    this.reflow(this.viewport.snapshot);
    try {
      const result = await this.api.deleteSquad(this.draft.squadId, bootstrap.session.csrf_token);
      this.store.reconcileSquadDelete(result);
      this.returnToWarband();
    } catch (error) {
      this.showFailure(error);
    }
  }

  private showFailure(error: unknown): void {
    this.command = 'idle';
    if (error instanceof RuntimeApiError) {
      if (error.code === 'active_squad_delete_forbidden') {
        this.message = 'Activate another squad before deleting this active squad.';
      } else if (error.code === 'active_run_configuration_locked') {
        this.message = 'This squad\'s formation is locked while it is being used in an active Farm run. Your draft is preserved.';
      } else if (error.kind === 'unauthorized') {
        this.message = 'Your session expired. Your draft is still here.';
      } else if (error.kind === 'malformed-response') {
        this.message = 'The server response failed an integrity check. Nothing was committed locally.';
      } else if (error.status === 422) {
        this.message = 'The server rejected this squad configuration. Review it and retry.';
      } else {
        this.message = 'The command could not be completed. Your draft was preserved.';
      }
    } else {
      this.integrityBlocked = true;
      this.message = 'State integrity check failed. Return to Warband and reload the squads ledger.';
    }
    this.reflow(this.viewport.snapshot);
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: SquadEditorLayout): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale);
    this.root = root;
    const background = this.scene.add.graphics();
    background.fillGradientStyle(0x0d2422, 0x18362d, 0x09191b, 0x10252a, 1);
    background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    root.add(background);
    root.add(this.scene.add.text(layout.title.x, layout.title.y, this.draft.mode === 'create' ? 'ASSEMBLE NEW SQUAD' : `EDIT - ${this.draft.name || 'SQUAD'}`, {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '42px' : '46px', fontStyle: 'bold', stroke: '#302015', strokeThickness: 5,
    }));
    root.add(this.scene.add.text(layout.title.right, layout.title.y + 14, this.draft.isActive ? 'ACTIVE SQUAD' : this.draft.mode === 'create' ? 'UNSAVED DRAFT' : 'SAVED SQUAD', {
      color: this.draft.isActive ? '#f2c14e' : '#c8b98f', fontFamily: 'system-ui, sans-serif', fontSize: '17px', fontStyle: 'bold',
    }).setOrigin(1, 0));
    if (this.formationLocked) root.add(this.scene.add.text(layout.title.x, layout.title.y + 58,
      'IN ACTIVE FARM RUN · Formation locked until the run ends. You can still rename this squad.', {
        color: '#ffd69b', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '19px' : '16px', fontStyle: 'bold',
        wordWrap: { width: layout.title.width },
      }));
    else if (this.store.activeRunLock && this.draft.mode === 'edit') root.add(this.scene.add.text(layout.title.x, layout.title.y + 58,
      'Another squad is in a Farm run. This formation is editable, but activation is unavailable.', {
        color: '#ffd69b', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '19px' : '16px', fontStyle: 'bold',
        wordWrap: { width: layout.title.width },
      }));
    root.add(this.scene.add.text(layout.name.x, layout.name.y, `NAME${this.draft.dirty ? ' - UNSAVED CHANGES' : ''}`, {
      color: '#c8b98f', fontFamily: 'system-ui, sans-serif', fontSize: '15px', fontStyle: 'bold',
    }));
    this.renderFormation(root, layout);
    this.renderRoster(root, layout);
    this.renderActions(root, layout);
    if (this.confirmation) this.renderConfirmation(root, snapshot, layout);
  }

  private renderFormation(root: Phaser.GameObjects.Container, layout: SquadEditorLayout): void {
    this.addPanel(root, layout.formation);
    root.add(this.scene.add.text(layout.formation.x + 20, layout.formation.y + 15, this.formationLocked ? '3 x 3 FORMATION · LOCKED' : '3 x 3 FORMATION', {
      color: '#6a321f', fontFamily: 'system-ui, sans-serif', fontSize: '18px', fontStyle: 'bold',
    }));
    const gap = layout.mode === 'compact' ? 12 : 16;
    const inset = 22;
    const top = layout.formation.y + 54;
    const cellWidth = (layout.formation.width - inset * 2 - gap * 2) / 3;
    const cellHeight = Math.min((layout.formation.bottom - top - inset - gap * 2) / 3, 126);
    const names = new Map((this.store.warband.units.data ?? []).map((unit) => [unit.id, unit.displayName]));
    this.draft.formation.forEach((unitId, index) => {
      const region = box(layout.formation.x + inset + (index % 3) * (cellWidth + gap), top + Math.floor(index / 3) * (cellHeight + gap), cellWidth, cellHeight);
      const selected = unitId !== null && unitId === this.draft.selectedUnitId;
      const graphic = this.scene.add.graphics();
      graphic.fillStyle(this.formationLocked ? (unitId ? 0x65766b : 0xcdbfa4) : unitId ? 0x315947 : 0xd7c7a3, 1);
      graphic.fillRoundedRect(region.x, region.y, region.width, region.height, 12);
      graphic.lineStyle(selected ? 5 : 2, selected ? 0xf2c14e : unitId ? 0xc9972b : 0xaa9670, 1);
      graphic.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 12);
      if (!this.formationLocked && !this.nameInputBlocked) {
        graphic.setInteractive(new Phaser.Geom.Rectangle(region.x, region.y, region.width, region.height), Phaser.Geom.Rectangle.Contains);
        actionCursor(graphic);
        graphic.on('pointerup', () => {
          if (this.command !== 'idle' || this.confirmation || this.formationLocked) return;
          if (this.draft.selectedUnitId !== null && this.draft.selectedUnitId !== unitId) this.draft.placeSelected(index);
          else this.draft.clearPosition(index);
          this.message = '';
          this.reflow(this.viewport.snapshot);
        });
      }
      root.add(graphic);
      root.add(this.scene.add.text(region.x + region.width / 2, region.y + region.height / 2, unitId ? (names.get(unitId) ?? 'Unknown unit') : `${index + 1}\nOPEN`, {
        align: 'center', color: unitId ? '#fff4d3' : '#74664b', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '21px' : '17px', fontStyle: unitId ? 'bold' : 'normal', wordWrap: { width: region.width - 10 },
      }).setOrigin(0.5));
    });
  }

  private renderRoster(root: Phaser.GameObjects.Container, layout: SquadEditorLayout): void {
    this.addPanel(root, layout.roster);
    root.add(this.scene.add.text(layout.roster.x + 20, layout.roster.y + 15, this.formationLocked ? 'FORMATION ROSTER · VIEW ONLY' : 'SELECT A GOBLIN', {
      color: '#6a321f', fontFamily: 'system-ui, sans-serif', fontSize: '18px', fontStyle: 'bold',
    }));
    const units = this.store.warband.units.data ?? [];
    const pages = Math.max(1, Math.ceil(units.length / layout.rosterPageSize));
    this.rosterPage = Math.min(this.rosterPage, pages - 1);
    const visible = units.slice(this.rosterPage * layout.rosterPageSize, (this.rosterPage + 1) * layout.rosterPageSize);
    const footer = pages > 1 ? 60 : 14;
    const gap = 9;
    const top = layout.roster.y + 52;
    const height = Math.max(44, (layout.roster.bottom - top - footer - gap * Math.max(0, visible.length - 1)) / Math.max(visible.length, 1));
    visible.forEach((unit, index) => this.renderRosterUnit(root, layout, unit, box(layout.roster.x + 14, top + index * (height + gap), layout.roster.width - 28, height)));
    if (pages > 1) {
      this.addButton(root, box(layout.roster.x + 14, layout.roster.bottom - 52, 94, 40), '< PREV', () => this.changeRosterPage(-1, pages), false, !this.nameInputBlocked);
      this.addButton(root, box(layout.roster.right - 108, layout.roster.bottom - 52, 94, 40), 'NEXT >', () => this.changeRosterPage(1, pages), false, !this.nameInputBlocked);
      root.add(this.scene.add.text(layout.roster.x + layout.roster.width / 2, layout.roster.bottom - 32, `${this.rosterPage + 1} / ${pages}`, { color: '#5b351f', fontFamily: 'system-ui', fontSize: '14px', fontStyle: 'bold' }).setOrigin(0.5));
    }
  }

  private renderRosterUnit(root: Phaser.GameObjects.Container, layout: SquadEditorLayout, unit: WarbandUnitSummary, region: Bounds): void {
    const selected = unit.id === this.draft.selectedUnitId;
    const card = this.scene.add.graphics();
    card.fillStyle(this.formationLocked ? 0xcdbfa4 : selected ? 0x315947 : 0xe2d2ab, 1);
    card.fillRoundedRect(region.x, region.y, region.width, region.height, 10);
    card.lineStyle(selected ? 4 : 2, selected ? 0xf2c14e : 0xb69a65, 1);
    card.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 10);
    if (!this.formationLocked && !this.nameInputBlocked) {
      card.setInteractive(new Phaser.Geom.Rectangle(region.x, region.y, region.width, region.height), Phaser.Geom.Rectangle.Contains);
      actionCursor(card);
      card.on('pointerup', () => {
        if (this.command !== 'idle' || this.confirmation || this.formationLocked) return;
        this.draft.selectUnit(selected ? null : unit.id);
        this.reflow(this.viewport.snapshot);
      });
    }
    root.add(card);
    root.add(this.scene.add.text(region.x + 14, region.y + (region.height < 58 ? 5 : 10), unit.displayName, { color: selected ? '#fff4d3' : '#3a2a1a', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '25px' : '20px', fontStyle: 'bold' }));
    root.add(this.scene.add.text(region.x + 14, region.y + (region.height < 58 ? 27 : 38), `${unit.unitType.display_name} - ${unit.kin.display_name} - L${unit.level}`, { color: selected ? '#dfd1aa' : '#74664b', fontFamily: 'system-ui', fontSize: layout.mode === 'compact' ? '17px' : '13px' }));
  }

  private renderActions(root: Phaser.GameObjects.Container, layout: SquadEditorLayout): void {
    const gap = 12;
    const buttonWidth = Math.min(190, (layout.actions.width - gap * 4) / 5);
    let x = layout.actions.x;
    this.addButton(root, box(x, layout.actions.y, buttonWidth, layout.actions.height), 'CANCEL', () => this.requestBack(), false, !this.nameInputBlocked); x += buttonWidth + gap;
    this.addButton(root, box(x, layout.actions.y, buttonWidth, layout.actions.height), this.integrityBlocked ? 'STATE ERROR' : this.command === 'saving' ? 'SAVING...' : 'SAVE', () => { void this.save(); }, !this.integrityBlocked,
      !this.nameInputBlocked && (!this.formationLocked || (this.draft.dirty && !this.draft.formationDirty))); x += buttonWidth + gap;
    if (this.draft.mode === 'edit') {
      this.addButton(root, box(x, layout.actions.y, buttonWidth, layout.actions.height), this.command === 'activating' ? 'ACTIVATING...' : this.draft.isActive ? 'ACTIVE' : 'ACTIVATE', () => { void this.activate(); }, this.draft.isActive, !this.nameInputBlocked && !this.store.activeRunLock && !this.draft.isActive); x += buttonWidth + gap;
      this.addButton(root, box(x, layout.actions.y, buttonWidth, layout.actions.height), this.command === 'deleting' ? 'DELETING...' : 'DELETE', () => this.requestDelete(), false, !this.nameInputBlocked && !this.formationLocked);
    }
    if (this.message) root.add(this.scene.add.text(layout.actions.right, layout.actions.y - 11, this.message, { color: '#ffd69b', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold' }).setOrigin(1, 1));
  }

  private renderConfirmation(root: Phaser.GameObjects.Container, snapshot: RuntimeViewportSnapshot, layout: SquadEditorLayout): void {
    const blocker = this.scene.add.graphics();
    blocker.fillStyle(0x07110f, 0.78); blocker.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    blocker.setInteractive(new Phaser.Geom.Rectangle(0, 0, snapshot.logicalWidth, snapshot.logicalHeight), Phaser.Geom.Rectangle.Contains);
    root.add(blocker);
    const region = box(snapshot.logicalWidth / 2 - 300, snapshot.logicalHeight / 2 - 120, 600, 240);
    this.addPanel(root, region);
    const deleting = this.confirmation === 'delete';
    root.add(this.scene.add.text(region.x + region.width / 2, region.y + 45, deleting ? 'DELETE THIS SQUAD?' : 'DISCARD UNSAVED CHANGES?', { color: '#5b351f', fontFamily: 'Georgia, serif', fontSize: '27px', fontStyle: 'bold' }).setOrigin(0.5));
    root.add(this.scene.add.text(region.x + region.width / 2, region.y + 91, deleting ? 'The backend will enforce active-squad rules.' : 'Your local draft will be destroyed.', { color: '#74664b', fontFamily: 'system-ui', fontSize: '16px' }).setOrigin(0.5));
    this.addButton(root, box(region.x + 42, region.bottom - 82, 220, 54), 'KEEP EDITING', () => this.cancelConfirmation(), false);
    this.addButton(root, box(region.right - 262, region.bottom - 82, 220, 54), deleting ? 'CONFIRM DELETE' : 'DISCARD', () => {
      if (deleting) void this.confirmDelete(); else this.confirmDiscard();
    }, true);
    void layout;
  }

  private createNameInput(): void {
    const parent = (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement;
    if (!parent) return;
    const input = document.createElement('input');
    input.type = 'text'; input.value = this.draft.name; input.autocomplete = 'off'; input.spellcheck = false;
    input.setAttribute('aria-label', 'Squad name'); input.dataset['squadNameInput'] = 'true';
    Object.assign(input.style, { position: 'absolute', zIndex: '4', boxSizing: 'border-box', border: '3px solid #b69a65', borderRadius: '10px', background: '#f2e4c1', color: '#3a2a1a', fontFamily: 'Georgia, serif', fontWeight: '700', outline: 'none' });
    input.addEventListener('input', () => {
      if (this.nameInputBlocked) {
        input.value = this.draft.name;
        return;
      }
      this.draft.setName(input.value);
      this.message = '';
      this.reflow(this.viewport.snapshot);
    });
    parent.appendChild(input);
    this.nameInput = input;
  }

  private positionNameInput(snapshot: RuntimeViewportSnapshot, layout: SquadEditorLayout): void {
    if (!this.nameInput) return;
    const top = layout.name.y + 26;
    const blocked = this.nameInputBlocked;
    this.nameInput.disabled = blocked;
    this.nameInput.readOnly = blocked;
    this.nameInput.tabIndex = blocked ? -1 : 0;
    this.nameInput.setAttribute('aria-disabled', blocked ? 'true' : 'false');
    if (blocked && document.activeElement === this.nameInput) this.nameInput.blur();
    Object.assign(this.nameInput.style, {
      left: `${(layout.name.x * snapshot.gameScale)}px`, top: `${(top * snapshot.gameScale)}px`,
      width: `${layout.name.width * snapshot.gameScale}px`, height: `${(layout.name.bottom - top) * snapshot.gameScale}px`,
      padding: `0 ${18 * snapshot.gameScale}px`, fontSize: `${Math.max(16, 24 * snapshot.gameScale)}px`,
      display: this.confirmation ? 'none' : 'block', pointerEvents: blocked ? 'none' : 'auto',
    });
  }

  private get nameInputBlocked(): boolean {
    return this.command !== 'idle' || this.confirmation !== null
      || this.portraitGateActive || this.integrityBlocked;
  }

  private get formationLocked(): boolean {
    return this.draft.squadId !== null && this.store.activeRunLock?.squadId === this.draft.squadId;
  }

  private changeRosterPage(direction: -1 | 1, pages: number): void {
    this.rosterPage = (this.rosterPage + direction + pages) % pages;
    this.reflow(this.viewport.snapshot);
  }

  private addPanel(root: Phaser.GameObjects.Container, region: Bounds): void {
    const panel = this.scene.add.graphics();
    panel.fillStyle(0xf2e4c1, 0.97); panel.fillRoundedRect(region.x, region.y, region.width, region.height, 16);
    panel.lineStyle(4, 0x8a5a34, 1); panel.strokeRoundedRect(region.x + 2, region.y + 2, region.width - 4, region.height - 4, 16);
    root.add(panel);
  }

  private addButton(root: Phaser.GameObjects.Container, region: Bounds, label: string, action: () => void, active: boolean, enabled = true): void {
    const button = this.scene.add.graphics();
    button.fillStyle(!enabled ? 0x6c6658 : active ? 0x8a5a34 : 0x273e35, 1); button.fillRoundedRect(region.x, region.y, region.width, region.height, 12);
    button.lineStyle(3, active ? 0xf2c14e : 0x8a6a45, 1); button.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 12);
    if (enabled) {
      button.setInteractive(new Phaser.Geom.Rectangle(region.x, region.y, region.width, region.height), Phaser.Geom.Rectangle.Contains);
      actionCursor(button);
      button.on('pointerup', action);
    }
    root.add([button, this.scene.add.text(region.x + region.width / 2, region.y + region.height / 2, label, { align: 'center', color: enabled ? '#fff4d3' : '#d4c8ae', fontFamily: 'system-ui', fontSize: region.height >= 80 ? '20px' : '14px', fontStyle: 'bold' }).setOrigin(0.5)]);
  }

  private publishReadyState(ready: boolean): void {
    const parent = (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement;
    if (parent) parent.dataset['squadEditorReady'] = ready ? 'true' : 'false';
  }
}
