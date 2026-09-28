import Phaser from 'phaser';
import { GameStore, ShopState } from '../runtime/game-store';
import { ClientContentRegistry, ClientShopOfferDefinition } from '../runtime/client-content-registry';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { ShopOfferReadModel, ShopPurchasePayload, ShopPurchaseResult } from '../runtime/shop-contracts';
import { RetainedMutationAttempt, RetainedMutationState } from '../runtime/retained-mutation-attempt';
import { RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { GameSceneScreen } from './game-screen-navigation';
import { createEconomyLayout, EconomyLayout } from './economy-layout';
import { actionCursor } from './action-cursor';

export function shopGrantLabel(offer: ClientShopOfferDefinition, content: ClientContentRegistry): string {
  if (offer.grant.type === 'item') {
    const item = content.getItem(offer.grant.item_id);
    return `${item?.display_name ?? offer.grant.item_id} ×${offer.grant.quantity}`;
  }
  if (offer.grant.type === 'die') {
    const profile = content.getDiceProfile(offer.grant.dice_profile_id);
    return `d${offer.grant.size} ${profile?.display_name ?? offer.grant.dice_profile_id}`;
  }
  const unit = content.getUnitType(offer.grant.unit_type_id);
  const kin = content.getKin(offer.grant.kin_id);
  return `${unit?.display_name ?? offer.grant.unit_type_id} · ${kin?.display_name ?? offer.grant.kin_id}`;
}

export class ShopScreen implements GameSceneScreen {
  readonly key = 'shop' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribeShop: (() => void) | null = null;
  private unsubscribePlayer: (() => void) | null = null;
  private selectedOfferId: string | null = null;
  private message = '';
  private activeLayout: EconomyLayout | null = null;
  private readonly purchaseAttempt: RetainedMutationAttempt<ShopPurchasePayload, ShopPurchaseResult>;

  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore, private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry, private readonly viewport: RuntimeViewport, private readonly back: () => void,
    createKey: () => string = () => crypto.randomUUID()) {
    this.purchaseAttempt = new RetainedMutationAttempt(createKey);
  }

  get layout(): EconomyLayout | null { return this.activeLayout; }
  get actionState(): RetainedMutationState { return this.purchaseAttempt.state; }
  get attemptIdentity(): Readonly<{ identity: string; request: ShopPurchasePayload; key: string }> | null { return this.purchaseAttempt.identity; }
  requestBack(): void {
    if (this.mutationBlocksNavigation()) {
      this.message = 'Resolve or retry the uncertain purchase before leaving the Shop.';
      this.reflow(this.viewport.snapshot);
      return;
    }
    this.back();
  }

  create(): void {
    this.unsubscribeShop = this.store.subscribeShop(() => this.reflow(this.viewport.snapshot));
    this.unsubscribePlayer = this.store.subscribePlayer(() => this.reflow(this.viewport.snapshot));
    this.reflow(this.viewport.snapshot);
    void this.store.loadShop(this.api, this.content);
  }
  destroy(): void {
    this.unsubscribeShop?.(); this.unsubscribeShop = null;
    this.unsubscribePlayer?.(); this.unsubscribePlayer = null;
    this.root?.destroy(true); this.root = null;
  }
  reflow(snapshot: RuntimeViewportSnapshot): void { this.root?.destroy(true); this.activeLayout = createEconomyLayout(snapshot); this.render(snapshot, this.activeLayout, this.store.shop); }
  selectOffer(offerId: string): void { if (this.purchaseAttempt.state === 'submitting' || this.purchaseAttempt.state === 'retryable') return; this.selectedOfferId = offerId; this.message = ''; this.reflow(this.viewport.snapshot); }
  retryRead(): void { void this.store.retryShop(this.api, this.content); }

  async purchaseSelected(): Promise<void> {
    const offer = this.store.shop.data?.offers.find((candidate) => candidate.offer.id === this.selectedOfferId);
    const bootstrap = this.store.bootstrap;
    if (!offer || !bootstrap || !offer.available || bootstrap.player.teeth < offer.price.amount
      || this.purchaseAttempt.state === 'submitting') return;
    const request: ShopPurchasePayload = { offer_id: offer.offer.id,
      expected_price: { currency_id: 'teeth', amount: offer.price.amount } };
    this.purchaseAttempt.begin(`${offer.offer.id}:${offer.price.amount}`, request);
    this.message = 'Purchasing…'; this.reflow(this.viewport.snapshot);
    const outcome = await this.purchaseAttempt.submit((payload, key) =>
      this.api.purchaseShopOffer(payload, bootstrap.session.csrf_token, key, this.content));
    if (outcome.kind === 'success') {
      try { this.store.reconcileShopPurchase(outcome.result, this.content); this.message = 'Purchase complete.'; }
      catch { this.message = 'Purchase committed, but local state disagrees. Reload to recover safely.'; }
    } else if (outcome.kind === 'ambiguous') this.message = 'The result is uncertain. Retry this same purchase attempt.';
    else if (outcome.kind === 'rejected') this.message = 'Purchase rejected. Review the offer and try again.';
    this.reflow(this.viewport.snapshot);
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: EconomyLayout, state: ShopState): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    const bg = this.scene.add.graphics(); bg.fillGradientStyle(0x171c20, 0x24382e, 0x0b1718, 0x12272a, 1);
    bg.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight); root.add(bg);
    this.button(root, layout.back, 'RETURN', () => this.requestBack(), !this.mutationBlocksNavigation());
    root.add(this.scene.add.text(layout.header.x + layout.back.width + 28, layout.header.y + 5, 'GOBLIN SHOP',
      { color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '44px' : '42px', fontStyle: 'bold' }));
    root.add(this.scene.add.text(layout.wallet.right, layout.wallet.y + layout.wallet.height / 2,
      `${this.store.bootstrap?.player.teeth ?? 0} TEETH`, { color: '#f2c14e', fontFamily: 'system-ui', fontSize: '20px', fontStyle: 'bold' }).setOrigin(1, .5));
    const panel = this.scene.add.graphics(); panel.fillStyle(0xf2e4c1, .97); panel.fillRoundedRect(layout.content.x, layout.content.y, layout.content.width, layout.content.height, 16); root.add(panel);
    if (state.status === 'loading' || state.status === 'not-loaded') return void this.center(root, layout, 'Loading Shop…', 'Reading the current authoritative catalog.');
    if (state.status === 'error') { this.center(root, layout, state.error === 'integrity' ? 'Shop data could not be verified' : 'Shop unavailable', 'Retry the authoritative catalog read.');
      this.button(root, layout.action, 'RETRY', () => this.retryRead(), true); return; }
    if (!state.data?.offers.length) return void this.center(root, layout, 'No offers available', 'The Shop catalog is currently empty.');
    const gap = 10; const top = layout.content.y + 18; const height = Math.min(88, (layout.content.height - 36 - gap * (state.data.offers.length - 1)) / state.data.offers.length);
    const teeth = this.store.bootstrap?.player.teeth ?? 0;
    state.data.offers.forEach((offer, index) => this.offerRow(root, layout, offer, teeth, top + index * (height + gap), height));
    if (this.message) root.add(this.scene.add.text(layout.content.x + 20, layout.content.bottom - 28, this.message,
      { color: '#6a321f', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold' }));
    const selected = state.data.offers.find((offer) => offer.offer.id === this.selectedOfferId);
    this.button(root, layout.action, this.purchaseAttempt.state === 'retryable' ? 'RETRY PURCHASE' : 'PURCHASE',
      () => void this.purchaseSelected(), !!selected?.available && teeth >= (selected?.price.amount ?? Number.POSITIVE_INFINITY)
        && this.purchaseAttempt.state !== 'submitting');
  }

  private offerRow(root: Phaser.GameObjects.Container, layout: EconomyLayout, offer: ShopOfferReadModel, teeth: number, y: number, height: number): void {
    const selected = offer.offer.id === this.selectedOfferId; const card = this.scene.add.graphics();
    card.fillStyle(selected ? 0xcfb77e : 0xe5d4ad, 1); card.fillRoundedRect(layout.content.x + 18, y, layout.content.width - 36, height, 10);
    card.setInteractive(new Phaser.Geom.Rectangle(layout.content.x + 18, y, layout.content.width - 36, height), Phaser.Geom.Rectangle.Contains).on('pointerup', () => this.selectOffer(offer.offer.id)); actionCursor(card); root.add(card);
    root.add(this.scene.add.text(layout.content.x + 34, y + 12, shopGrantLabel(offer.offer, this.content), { color: '#3a2a1a', fontFamily: 'Georgia, serif', fontSize: '21px', fontStyle: 'bold' }));
    const canAfford = teeth >= offer.price.amount;
    const status = !offer.available ? 'UNAVAILABLE' : !canAfford ? 'INSUFFICIENT TEETH' : `${offer.price.amount} TEETH`;
    root.add(this.scene.add.text(layout.content.right - 34, y + height / 2, status, { color: canAfford && offer.available ? '#356b43' : '#9a4434', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold' }).setOrigin(1, .5));
  }
  private center(root: Phaser.GameObjects.Container, layout: EconomyLayout, title: string, detail: string): void { root.add(this.scene.add.text(layout.content.x + layout.content.width / 2, layout.content.y + layout.content.height / 2 - 20, title, { color: '#5b351f', fontFamily: 'Georgia, serif', fontSize: '28px', fontStyle: 'bold' }).setOrigin(.5)); root.add(this.scene.add.text(layout.content.x + layout.content.width / 2, layout.content.y + layout.content.height / 2 + 20, detail, { color: '#74664b', fontFamily: 'system-ui', fontSize: '16px' }).setOrigin(.5)); }
  private mutationBlocksNavigation(): boolean { return this.purchaseAttempt.state === 'submitting' || this.purchaseAttempt.state === 'retryable'; }
  private button(root: Phaser.GameObjects.Container, bounds: EconomyLayout['back'], label: string, action: () => void, enabled: boolean): void { const g = this.scene.add.graphics(); g.fillStyle(enabled ? 0x273e35 : 0x6c6658, 1); g.fillRoundedRect(bounds.x, bounds.y, bounds.width, bounds.height, 10); if (enabled) { g.setInteractive(new Phaser.Geom.Rectangle(bounds.x, bounds.y, bounds.width, bounds.height), Phaser.Geom.Rectangle.Contains).on('pointerup', action); actionCursor(g); } root.add([g, this.scene.add.text(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2, label, { color: '#fff4d3', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold' }).setOrigin(.5)]); }
}
