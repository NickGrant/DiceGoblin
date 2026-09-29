import Phaser from 'phaser';
import { GameStore, UnitPromotionOptionsState } from '../runtime/game-store';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { RetainedMutationAttempt, RetainedMutationState } from '../runtime/retained-mutation-attempt';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { UnitPromotionPayload, UnitPromotionResult } from '../runtime/unit-promotion-mutation-contracts';
import { UnitPromotionOption } from '../runtime/unit-promotion-contracts';
import { RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { WarbandDieSummary } from '../runtime/warband-contracts';
import { createEconomyLayout, EconomyLayout } from './economy-layout';
import { GameSceneScreen } from './game-screen-navigation';
import { progressionBackground, progressionButton, progressionText } from './progression-surface';

export function promotionStatus(option: UnitPromotionOption, locked: boolean, rawChaos: number): string {
  return locked ? 'LOCKED · ACTIVE RUN' : !option.levelMet ? `REQUIRES LEVEL ${option.requiredLevel}`
    : !option.available ? 'LOCKED' : rawChaos < option.price ? 'INSUFFICIENT RAW CHAOS' : 'AVAILABLE';
}

export class UnitPromotionScreen implements GameSceneScreen {
  readonly key = 'unit-promotion' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribePromotion: (() => void) | null = null;
  private unsubscribeWarband: (() => void) | null = null;
  private unsubscribePlayer: (() => void) | null = null;
  private readonly attempt: RetainedMutationAttempt<UnitPromotionPayload, UnitPromotionResult>;
  private selectedId: string | null = null;
  private confirming = false;
  private message = '';
  private activeLayout: EconomyLayout | null = null;
  private disposed = false;
  private attemptDice: readonly WarbandDieSummary[] | null = null;

  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore, private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry, private readonly viewport: RuntimeViewport,
    readonly unitId: string, private readonly back: () => void,
    createKey: () => string = () => crypto.randomUUID()) {
    this.attempt = new RetainedMutationAttempt(createKey);
  }

  get layout(): EconomyLayout | null { return this.activeLayout; }
  get actionState(): RetainedMutationState { return this.attempt.state; }
  get attemptIdentity(): Readonly<{ identity: string; request: UnitPromotionPayload; key: string }> | null { return this.attempt.identity; }
  get selectedPromotionId(): string | null { return this.selectedId; }

  create(): void {
    this.disposed = false;
    this.unsubscribePromotion = this.store.subscribePromotionOptions((unitId) => {
      if (unitId === this.unitId) this.reflow(this.viewport.snapshot);
    });
    this.unsubscribeWarband = this.store.subscribeWarband(() => this.reflow(this.viewport.snapshot));
    this.unsubscribePlayer = this.store.subscribePlayer(() => this.reflow(this.viewport.snapshot));
    this.reflow(this.viewport.snapshot);
    void this.load();
  }

  private async load(): Promise<void> {
    await this.store.loadWarbandDomains(this.api, this.content);
    if (this.disposed) return;
    await this.store.loadUnitDetail(this.unitId, this.api, this.content);
    if (!this.disposed && this.store.unitDetail(this.unitId).status === 'fresh')
      await this.store.loadPromotionOptions(this.unitId, this.api, this.content);
  }

  destroy(): void {
    this.disposed = true;
    this.unsubscribePromotion?.(); this.unsubscribePromotion = null;
    this.unsubscribeWarband?.(); this.unsubscribeWarband = null;
    this.unsubscribePlayer?.(); this.unsubscribePlayer = null;
    this.root?.destroy(true); this.root = null;
    this.publishReady(false);
  }

  requestBack(): void {
    if (this.blocked) { this.message = 'Resolve the uncertain promotion before leaving.'; this.reflow(this.viewport.snapshot); return; }
    this.back();
  }

  selectOption(id: string): void {
    if (this.blocked || this.store.promotionOptions(this.unitId).status !== 'fresh'
      || !this.store.promotionOptions(this.unitId).data?.options.some((option) => option.promotion.id === id)) return;
    this.selectedId = id; this.confirming = false; this.message = ''; this.reflow(this.viewport.snapshot);
  }

  confirmSelected(): void {
    if (this.blocked || !this.actionableSelected()) return;
    this.confirming = true; this.reflow(this.viewport.snapshot);
  }

  cancelConfirmation(): void { if (!this.blocked) { this.confirming = false; this.reflow(this.viewport.snapshot); } }

  retryRead(): void {
    void this.store.retryUnitDetail(this.unitId, this.api, this.content).then(() =>
      this.store.retryPromotionOptions(this.unitId, this.api, this.content));
  }

  async submitPromotion(): Promise<void> {
    const bootstrap = this.store.bootstrap;
    if (!bootstrap || this.attempt.state === 'submitting') return;
    if (this.attempt.state !== 'retryable') {
      const option = this.actionableSelected();
      if (!this.confirming || !option) return;
      const payload: UnitPromotionPayload = { promotion_id: option.promotion.id,
        expected_price: { currency_id: 'raw_chaos', amount: option.price } };
      this.attempt.begin(`${this.unitId}:${option.promotion.id}:${option.price}`, payload);
      this.attemptDice = this.store.warband.dice.data;
    }
    const dice = this.attemptDice;
    if (!dice) { this.message = 'Dice context unavailable. Reload to recover.'; this.reflow(this.viewport.snapshot); return; }
    this.message = 'Submitting promotion…'; this.reflow(this.viewport.snapshot);
    const outcome = await this.attempt.submit((payload, key) => this.api.promoteUnit(
      this.unitId, payload, bootstrap.session.csrf_token, key, this.content, dice));
    if (outcome.kind === 'success') {
      this.confirming = false; this.selectedId = null;
      try {
        this.store.reconcileUnitPromotion(outcome.result);
        this.message = 'Promotion complete.';
        await this.store.retryPromotionOptions(this.unitId, this.api, this.content);
        if (this.store.promotionOptions(this.unitId).status === 'error')
          this.message = 'Promotion committed. Retry the promotion-options read.';
      } catch { this.message = 'Promotion committed. Reload to recover the unit safely.'; }
    } else if (outcome.kind === 'ambiguous') this.message = 'Outcome uncertain. RETRY uses this exact promotion attempt.';
    else if (outcome.kind === 'rejected') { this.confirming = false; this.message = 'Promotion rejected. Review current eligibility.'; }
    this.reflow(this.viewport.snapshot);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.root?.destroy(true);
    this.activeLayout = createEconomyLayout(snapshot);
    this.render(snapshot, this.activeLayout, this.store.promotionOptions(this.unitId));
    this.publishReady(this.store.promotionOptions(this.unitId).status === 'fresh');
  }

  private publishReady(ready: boolean): void {
    const parent = (this.scene.sys as (Phaser.Scenes.Systems & { game?: Phaser.Game }) | undefined)?.game?.canvas.parentElement;
    if (parent) parent.dataset['unitPromotionReady'] = ready ? 'true' : 'false';
  }

  private get blocked(): boolean { return this.attempt.state === 'submitting' || this.attempt.state === 'retryable'; }
  private actionableSelected(): UnitPromotionOption | null {
    const state = this.store.promotionOptions(this.unitId);
    const selected = state.status === 'fresh' ? state.data?.options.find((option) => option.promotion.id === this.selectedId) : null;
    const wallet = this.store.bootstrap?.player.raw_chaos;
    return selected && selected.available && !state.data?.configurationLocked && wallet !== undefined && wallet >= selected.price
      ? selected : null;
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: EconomyLayout, state: UnitPromotionOptionsState): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    progressionBackground(this.scene, root, snapshot, layout.content);
    progressionButton(this.scene, root, layout.back, 'RETURN TO UNIT', () => this.requestBack(), !this.blocked);
    progressionText(this.scene, root, layout.back.right + 24, layout.header.y + 6, 'UNIT PROMOTION', 36, '#f5e8c8');
    progressionText(this.scene, root, layout.wallet.x, layout.wallet.y + 15,
      `${this.store.bootstrap?.player.raw_chaos ?? '—'} RAW CHAOS`, layout.mode === 'compact' ? 27 : 20, '#d0a5ef');
    const detail = this.store.unitDetail(this.unitId);
    if (detail.status !== 'fresh' || !detail.data) {
      progressionText(this.scene, root, layout.content.x + 24, layout.content.y + 28,
        detail.status === 'error' ? 'Unit detail unavailable. Retry the authoritative read.' : 'Loading unit detail…', 22);
      if (detail.status === 'error') progressionButton(this.scene, root, layout.action, 'RETRY', () => this.retryRead());
      return;
    }
    const unit = detail.data;
    progressionText(this.scene, root, layout.content.x + 22, layout.content.y + 18,
      `${unit.displayName} · ${unit.unitType.display_name}`, layout.mode === 'compact' ? 34 : 26);
    progressionText(this.scene, root, layout.content.x + 22, layout.content.y + 52,
      `LEVEL ${unit.level} · ${unit.xp} / ${unit.xpToNextLevel} XP`, layout.mode === 'compact' ? 24 : 17, '#315947');
    if (state.status === 'not-loaded' || state.status === 'loading') {
      progressionText(this.scene, root, layout.content.x + 22, layout.content.y + 100, 'Loading promotion options…', 20); return;
    }
    if (state.status === 'error' || state.status === 'stale') {
      progressionText(this.scene, root, layout.content.x + 22, layout.content.y + 100,
        state.error === 'integrity' ? 'Promotion data disagrees with the current unit. Reload if retry fails.'
          : 'Promotion options need a fresh read.', 20);
      progressionButton(this.scene, root, layout.action, 'RETRY READ', () => this.retryRead()); return;
    }
    const options = state.data?.options ?? [];
    if (state.data?.configurationLocked) progressionText(this.scene, root, layout.content.x + 22,
      layout.content.y + 82, 'ACTIVE RUN · This participating unit cannot promote yet.',
      layout.mode === 'compact' ? 25 : 16, '#9a4434');
    if (!options.length) {
      progressionText(this.scene, root, layout.content.x + 22, layout.content.y + 118,
        'No further promotions. This goblin has reached its final tier.', 23); return;
    }
    const top = layout.content.y + 118;
    const footer = layout.mode === 'compact' ? 86 : 80;
    const rowHeight = Math.min(layout.mode === 'compact' ? 150 : 158,
      (layout.content.bottom - footer - top - 14 * (options.length - 1) - 15) / options.length);
    options.forEach((option, index) => {
      const x = layout.content.x + 18; const y = top + index * (rowHeight + 14);
      const card = this.scene.add.graphics(); card.fillStyle(option.promotion.id === this.selectedId ? 0xcfb77e : 0xe5d4ad, 1);
      card.fillRoundedRect(x, y, layout.content.width - 36, rowHeight, 10);
      card.setInteractive(new Phaser.Geom.Rectangle(x, y, layout.content.width - 36, rowHeight),
        Phaser.Geom.Rectangle.Contains).on('pointerup', () => this.selectOption(option.promotion.id)); root.add(card);
      progressionText(this.scene, root, x + 16, y + 10, option.targetUnitType.display_name,
        layout.mode === 'compact' ? 30 : 22);
      progressionText(this.scene, root, x + 16, y + 42,
        `LEVEL ${option.requiredLevel} · ${option.price} RAW CHAOS`, layout.mode === 'compact' ? 27 : 16, '#5b351f');
      progressionText(this.scene, root, x + 16, y + (layout.mode === 'compact' ? 75 : 68),
        `NEW ABILITIES: ${option.newAbilities.map((ability) => ability.display_name).join(', ') || 'None'}`,
        layout.mode === 'compact' ? 25 : 15, '#5b351f', layout.content.width * .55);
      progressionText(this.scene, root, x + layout.content.width * .62, y + 42,
        promotionStatus(option, state.data?.configurationLocked ?? false, this.store.bootstrap?.player.raw_chaos ?? 0),
        layout.mode === 'compact' ? 26 : 16, option.available ? '#315947' : '#9a4434');
    });
    const action = { ...layout.action, y: layout.content.bottom - footer + 8,
      bottom: layout.content.bottom - footer + 8 + layout.action.height };
    progressionButton(this.scene, root, action,
      this.attempt.state === 'retryable' ? 'RETRY PROMOTION' : this.confirming ? 'CONFIRM PROMOTION' : 'PROMOTE',
      () => this.attempt.state === 'retryable' ? void this.submitPromotion()
        : this.confirming ? void this.submitPromotion() : this.confirmSelected(),
      this.attempt.state === 'retryable' || (!!this.actionableSelected() && this.attempt.state !== 'submitting'));
    if (this.confirming && this.attempt.state !== 'retryable') {
      const selected = this.actionableSelected();
      if (selected) progressionText(this.scene, root, layout.content.x + 20, layout.content.bottom - footer + 20,
        `Confirm ${unit.unitType.display_name} → ${selected.targetUnitType.display_name} for ${selected.price} Raw Chaos?`,
        layout.mode === 'compact' ? 21 : 15, '#5b351f', layout.content.width - action.width - 50);
    } else if (this.message) progressionText(this.scene, root, layout.content.x + 20,
      layout.content.bottom - footer + 20, this.message, layout.mode === 'compact' ? 21 : 15, '#5b351f', layout.content.width - action.width - 50);
  }
}
