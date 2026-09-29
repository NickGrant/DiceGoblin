import Phaser from 'phaser';
import { readDebugCaptureRequest } from '../../core/debug/debug-capture';
import { AcademyState, GameStore } from '../runtime/game-store';
import { AcademyUpgradePayload, AcademyUpgradeReadModel, AcademyUpgradeResult } from '../runtime/academy-contracts';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { RetainedMutationAttempt, RetainedMutationState } from '../runtime/retained-mutation-attempt';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { createEconomyLayout, EconomyLayout } from './economy-layout';
import { GameSceneScreen } from './game-screen-navigation';
import { progressionBackground, progressionButton, progressionText } from './progression-surface';

export function academyStatus(upgrade: AcademyUpgradeReadModel, rawChaos: number): string {
  return upgrade.owned ? 'OWNED' : !upgrade.available ? 'LOCKED / PREREQUISITE REQUIRED'
    : rawChaos < upgrade.price.amount ? 'INSUFFICIENT RAW CHAOS' : 'AVAILABLE';
}

export class AcademyScreen implements GameSceneScreen {
  readonly key = 'academy' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribeAcademy: (() => void) | null = null;
  private unsubscribePlayer: (() => void) | null = null;
  private readonly attempt: RetainedMutationAttempt<AcademyUpgradePayload, AcademyUpgradeResult>;
  private selectedId: string | null = null;
  private confirming = false;
  private page = 0;
  private message = '';
  private activeLayout: EconomyLayout | null = null;

  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore, private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry, private readonly viewport: RuntimeViewport, private readonly back: () => void,
    createKey: () => string = () => crypto.randomUUID()) {
    this.attempt = new RetainedMutationAttempt(createKey);
  }

  get layout(): EconomyLayout | null { return this.activeLayout; }
  get actionState(): RetainedMutationState { return this.attempt.state; }
  get attemptIdentity(): Readonly<{ identity: string; request: AcademyUpgradePayload; key: string }> | null { return this.attempt.identity; }
  get selectedUpgradeId(): string | null { return this.selectedId; }
  get pageIndex(): number { return this.page; }

  create(): void {
    const debug = readDebugCaptureRequest();
    const requestedPage = debug?.scene.toLowerCase() === 'academy' ? debug.sceneData['page']
      : window.__DG_DEBUG__?.requestedScene.toLowerCase() === 'academy' ? window.__DG_DEBUG__.sceneData['page'] : null;
    if (typeof requestedPage === 'number' && Number.isSafeInteger(requestedPage) && requestedPage >= 0)
      this.page = requestedPage;
    this.unsubscribeAcademy = this.store.subscribeAcademy(() => this.reflow(this.viewport.snapshot));
    this.unsubscribePlayer = this.store.subscribePlayer(() => this.reflow(this.viewport.snapshot));
    this.reflow(this.viewport.snapshot);
    void this.store.loadAcademy(this.api, this.content);
  }

  destroy(): void {
    this.unsubscribeAcademy?.(); this.unsubscribeAcademy = null;
    this.unsubscribePlayer?.(); this.unsubscribePlayer = null;
    this.root?.destroy(true); this.root = null;
    this.publishReady(false);
  }

  requestBack(): void {
    if (this.blocked) { this.message = 'Resolve the uncertain upgrade before leaving.'; this.reflow(this.viewport.snapshot); return; }
    this.back();
  }

  selectUpgrade(id: string): void {
    if (this.blocked || this.store.academy.status !== 'fresh'
      || !this.store.academy.data?.upgrades.some((upgrade) => upgrade.upgrade.id === id)) return;
    this.selectedId = id; this.confirming = false; this.message = ''; this.reflow(this.viewport.snapshot);
  }

  changePage(delta: number): void {
    if (this.blocked) return;
    const count = this.store.academy.data?.upgrades.length ?? 0;
    const pages = Math.max(1, Math.ceil(count / this.pageSize));
    this.page = Math.min(pages - 1, Math.max(0, this.page + delta));
    this.reflow(this.viewport.snapshot);
  }

  confirmSelected(): void {
    if (this.blocked || !this.actionableSelected()) return;
    this.confirming = true; this.reflow(this.viewport.snapshot);
  }

  cancelConfirmation(): void { if (!this.blocked) { this.confirming = false; this.reflow(this.viewport.snapshot); } }
  retryRead(): void { void this.store.retryAcademy(this.api, this.content); }

  async submitUpgrade(): Promise<void> {
    const bootstrap = this.store.bootstrap;
    if (!bootstrap || this.attempt.state === 'submitting') return;
    if (this.attempt.state !== 'retryable') {
      const selected = this.actionableSelected();
      if (!this.confirming || !selected) return;
      const payload: AcademyUpgradePayload = { upgrade_id: selected.upgrade.id,
        expected_price: { currency_id: 'raw_chaos', amount: selected.price.amount } };
      this.attempt.begin(`${selected.upgrade.id}:${selected.price.amount}`, payload);
    }
    this.message = 'Submitting upgrade…'; this.reflow(this.viewport.snapshot);
    const outcome = await this.attempt.submit((payload, key) =>
      this.api.upgradeAcademy(payload, bootstrap.session.csrf_token, key));
    if (outcome.kind === 'success') {
      this.confirming = false;
      try {
        this.store.reconcileAcademyUpgrade(outcome.result);
        this.message = 'Upgrade complete.';
        await this.store.retryAcademy(this.api, this.content);
        if (this.store.academy.status === 'error') this.message = 'Upgrade committed. Retry the Academy read to recover the catalog.';
      } catch { this.message = 'Upgrade committed. Reload to recover local progression safely.'; }
    } else if (outcome.kind === 'ambiguous') this.message = 'Outcome uncertain. RETRY uses the same upgrade attempt.';
    else if (outcome.kind === 'rejected') { this.confirming = false; this.message = 'Upgrade rejected. Review the current catalog.'; }
    this.reflow(this.viewport.snapshot);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.root?.destroy(true);
    this.activeLayout = createEconomyLayout(snapshot);
    this.render(snapshot, this.activeLayout, this.store.academy);
    this.publishReady(this.store.academy.status === 'fresh');
  }

  private publishReady(ready: boolean): void {
    const parent = (this.scene.sys as (Phaser.Scenes.Systems & { game?: Phaser.Game }) | undefined)?.game?.canvas.parentElement;
    if (parent) parent.dataset['academyReady'] = ready ? 'true' : 'false';
  }

  private get blocked(): boolean { return this.attempt.state === 'submitting' || this.attempt.state === 'retryable'; }
  private get pageSize(): number { return this.viewport.snapshot.layoutClass === 'compact' ? 3 : 5; }
  private actionableSelected(): AcademyUpgradeReadModel | null {
    const selected = this.store.academy.status === 'fresh' ? this.store.academy.data?.upgrades
      .find((upgrade) => upgrade.upgrade.id === this.selectedId) : null;
    const wallet = this.store.bootstrap?.player.raw_chaos;
    return selected && !selected.owned && selected.available && wallet !== undefined
      && wallet >= selected.price.amount ? selected : null;
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: EconomyLayout, state: AcademyState): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    progressionBackground(this.scene, root, snapshot, layout.content);
    progressionButton(this.scene, root, layout.back, 'RETURN', () => this.requestBack(), !this.blocked);
    progressionText(this.scene, root, layout.back.right + 24, layout.header.y + 6, 'ACADEMY', 38, '#f5e8c8');
    progressionText(this.scene, root, layout.wallet.x, layout.wallet.y + 15,
      `${this.store.bootstrap?.player.raw_chaos ?? '—'} RAW CHAOS`, layout.mode === 'compact' ? 27 : 20, '#d0a5ef');
    if (state.status === 'not-loaded' || state.status === 'loading') {
      progressionText(this.scene, root, layout.content.x + 25, layout.content.y + 30, 'Loading Academy…', 24); return;
    }
    if (state.status === 'error' || state.status === 'stale') {
      progressionText(this.scene, root, layout.content.x + 25, layout.content.y + 30,
        state.error === 'integrity' ? 'Academy data could not be verified. Reload if retry fails.' : 'Academy catalog needs a fresh read.', 22);
      progressionButton(this.scene, root, layout.action, 'RETRY READ', () => this.retryRead()); return;
    }
    const upgrades = state.data?.upgrades ?? [];
    if (!upgrades.length) {
      progressionText(this.scene, root, layout.content.x + 25, layout.content.y + 30, 'No Academy upgrades are available.', 24); return;
    }
    const pages = Math.ceil(upgrades.length / this.pageSize);
    this.page = Math.min(this.page, pages - 1);
    const footer = layout.mode === 'compact' ? 104 : 92;
    const gap = 10;
    const rowHeight = Math.min(layout.mode === 'compact' ? 145 : 112,
      (layout.content.height - footer - 28 - gap * (this.pageSize - 1)) / this.pageSize);
    upgrades.slice(this.page * this.pageSize, (this.page + 1) * this.pageSize).forEach((upgrade, index) => {
      const x = layout.content.x + 18; const y = layout.content.y + 16 + index * (rowHeight + gap);
      const selected = upgrade.upgrade.id === this.selectedId;
      const card = this.scene.add.graphics(); card.fillStyle(selected ? 0xcfb77e : 0xe5d4ad, 1);
      card.fillRoundedRect(x, y, layout.content.width - 36, rowHeight, 10);
      card.setInteractive(new Phaser.Geom.Rectangle(x, y, layout.content.width - 36, rowHeight),
        Phaser.Geom.Rectangle.Contains).on('pointerup', () => this.selectUpgrade(upgrade.upgrade.id));
      root.add(card);
      progressionText(this.scene, root, x + 14, y + 10, upgrade.upgrade.display_name,
        layout.mode === 'compact' ? 30 : 20, '#3a2a1a', layout.content.width * .54);
      progressionText(this.scene, root, x + 14, y + (layout.mode === 'compact' ? 51 : 40),
        `${upgrade.upgrade.category.replace('_', ' ').toUpperCase()} · ${upgrade.upgrade.description}`,
        layout.mode === 'compact' ? 25 : 14, '#5b351f', layout.content.width * .56);
      progressionText(this.scene, root, x + layout.content.width * .62, y + 12,
        `${upgrade.price.amount} RAW CHAOS`, layout.mode === 'compact' ? 25 : 16, '#5b351f');
      progressionText(this.scene, root, x + layout.content.width * .62, y + (layout.mode === 'compact' ? 52 : 42),
        academyStatus(upgrade, this.store.bootstrap?.player.raw_chaos ?? 0), layout.mode === 'compact' ? 25 : 15,
        upgrade.available && !upgrade.owned ? '#315947' : '#9a4434');
    });
    const footerY = layout.content.bottom - footer + 12;
    const controlHeight = layout.mode === 'compact' ? 54 : 42;
    const controlWidth = layout.mode === 'compact' ? 115 : 90;
    const box = (x: number, y: number, w: number, h: number) => ({ x, y, width: w, height: h, right: x + w, bottom: y + h });
    progressionButton(this.scene, root, box(layout.content.x + 20, footerY, controlWidth, controlHeight), 'PREV',
      () => this.changePage(-1), this.page > 0 && !this.blocked);
    progressionText(this.scene, root, layout.content.x + controlWidth + 34, footerY + 13, `${this.page + 1} / ${pages}`,
      layout.mode === 'compact' ? 23 : 15);
    progressionButton(this.scene, root, box(layout.content.x + controlWidth + 104, footerY, controlWidth, controlHeight), 'NEXT',
      () => this.changePage(1), this.page + 1 < pages && !this.blocked);
    const action = box(layout.content.right - (layout.mode === 'compact' ? 282 : 230), footerY,
      layout.mode === 'compact' ? 260 : 210, controlHeight);
    progressionButton(this.scene, root, action,
      this.attempt.state === 'retryable' ? 'RETRY UPGRADE' : this.confirming ? 'CONFIRM UPGRADE' : 'UPGRADE',
      () => this.attempt.state === 'retryable' ? void this.submitUpgrade()
        : this.confirming ? void this.submitUpgrade() : this.confirmSelected(),
      this.attempt.state === 'retryable' || (!!this.actionableSelected() && this.attempt.state !== 'submitting'));
    if (this.confirming && this.attempt.state !== 'retryable') {
      const selected = this.actionableSelected();
      if (selected) progressionText(this.scene, root, layout.content.x + 20, footerY - 30,
        `Confirm ${selected.upgrade.display_name} for ${selected.price.amount} Raw Chaos?`, layout.mode === 'compact' ? 22 : 15);
    } else if (this.message) progressionText(this.scene, root, layout.content.x + 20, footerY - 30,
      this.message, layout.mode === 'compact' ? 22 : 15);
  }
}
