import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { EnergyRestoreResult } from '../runtime/consumable-contracts';
import { GameStore, WarbandDomainState } from '../runtime/game-store';
import { OwnedItemStack } from '../runtime/inventory-contracts';
import { RetainedMutationAttempt, RetainedMutationState } from '../runtime/retained-mutation-attempt';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { actionCursor } from './action-cursor';
import { createEconomyLayout, EconomyLayout } from './economy-layout';
import { GameSceneScreen } from './game-screen-navigation';

export function itemEffectLabel(stack: OwnedItemStack): string {
  if (!stack.item.effect) return 'Crafting material · no direct use';
  if (stack.item.effect.type === 'energy_restore') return `Restores ${stack.item.effect.amount} Energy`;
  return `Restores ${stack.item.effect.amount} HP · usable during an active run`;
}

export class InventoryScreen implements GameSceneScreen {
  readonly key = 'inventory' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribe: (() => void) | null = null;
  private activeLayout: EconomyLayout | null = null;
  private selectedItemId: string | null = null;
  private message = '';
  private readonly useAttempt: RetainedMutationAttempt<{ readonly itemId: string }, EnergyRestoreResult>;
  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore, private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry, private readonly viewport: RuntimeViewport, private readonly back: () => void,
    createKey: () => string = () => crypto.randomUUID()) { this.useAttempt = new RetainedMutationAttempt(createKey); }
  get layout(): EconomyLayout | null { return this.activeLayout; }
  get actionState(): RetainedMutationState { return this.useAttempt.state; }
  get attemptIdentity(): Readonly<{ identity: string; request: { readonly itemId: string }; key: string }> | null { return this.useAttempt.identity; }
  requestBack(): void {
    if (this.mutationBlocksNavigation()) {
      this.message = 'Resolve or retry the uncertain Energy use before leaving Supplies.';
      this.reflow(this.viewport.snapshot);
      return;
    }
    this.back();
  }
  create(): void { this.unsubscribe = this.store.subscribeItems(() => this.reflow(this.viewport.snapshot)); this.reflow(this.viewport.snapshot); void this.store.loadItems(this.api, this.content); }
  destroy(): void { this.unsubscribe?.(); this.unsubscribe = null; this.root?.destroy(true); this.root = null; }
  reflow(snapshot: RuntimeViewportSnapshot): void { this.root?.destroy(true); this.activeLayout = createEconomyLayout(snapshot); this.render(snapshot, this.activeLayout, this.store.inventory); }
  selectItem(itemId: string): void { if (this.useAttempt.state === 'submitting' || this.useAttempt.state === 'retryable') return; this.selectedItemId = itemId; this.message = ''; this.reflow(this.viewport.snapshot); }
  retryRead(): void { void this.store.retryItems(this.api, this.content); }
  async useSelectedEnergy(): Promise<void> {
    const stack = this.store.inventory.data?.find((candidate) => candidate.item.id === this.selectedItemId);
    const bootstrap = this.store.bootstrap;
    if (!stack || stack.item.effect?.type !== 'energy_restore' || !bootstrap || this.useAttempt.state === 'submitting') return;
    this.useAttempt.begin(stack.item.id, { itemId: stack.item.id }); this.message = 'Using supply…'; this.reflow(this.viewport.snapshot);
    const outcome = await this.useAttempt.submit((request, key) => this.api.restoreEnergy(request.itemId, bootstrap.session.csrf_token, key, this.content));
    if (outcome.kind === 'success') { try { this.store.reconcileEnergyRestore(outcome.result); this.message = 'Energy restored.'; } catch { this.message = 'Use committed, but local state disagrees. Reload to recover safely.'; } }
    else if (outcome.kind === 'ambiguous') this.message = 'The result is uncertain. Retry this same use attempt.';
    else if (outcome.kind === 'rejected') this.message = 'This supply cannot be used right now.';
    this.reflow(this.viewport.snapshot);
  }
  private render(snapshot: RuntimeViewportSnapshot, layout: EconomyLayout, state: WarbandDomainState<OwnedItemStack>): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    const bg = this.scene.add.graphics(); bg.fillGradientStyle(0x171c20, 0x24382e, 0x0b1718, 0x12272a, 1); bg.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight); root.add(bg);
    this.button(root, layout.back, 'RETURN', () => this.requestBack(), !this.mutationBlocksNavigation());
    root.add(this.scene.add.text(layout.header.x + layout.back.width + 28, layout.header.y + 5, 'SUPPLIES', { color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '44px' : '42px', fontStyle: 'bold' }));
    const energy = this.store.bootstrap?.player.energy; root.add(this.scene.add.text(layout.wallet.right, layout.wallet.y + layout.wallet.height / 2, `ENERGY ${energy?.current ?? 0} / ${energy?.normal_max ?? 0}`, { color: '#8db341', fontFamily: 'system-ui', fontSize: '18px', fontStyle: 'bold' }).setOrigin(1, .5));
    const panel = this.scene.add.graphics(); panel.fillStyle(0xf2e4c1, .97); panel.fillRoundedRect(layout.content.x, layout.content.y, layout.content.width, layout.content.height, 16); root.add(panel);
    if (state.status === 'loading' || state.status === 'not-loaded') return void this.center(root, layout, 'Loading supplies…', 'Reading owned item stacks.');
    if (state.status === 'error') { this.center(root, layout, state.error === 'integrity' ? 'Inventory could not be verified' : 'Supplies unavailable', 'Retry the authoritative inventory read.'); this.button(root, layout.action, 'RETRY', () => this.retryRead(), true); return; }
    if (!state.data?.length) return void this.center(root, layout, 'No supplies owned', 'Items and materials you acquire will appear here.');
    const gap = 10; const top = layout.content.y + 18; const height = Math.min(92, (layout.content.height - 36 - gap * (state.data.length - 1)) / state.data.length);
    state.data.forEach((stack, index) => this.row(root, layout, stack, top + index * (height + gap), height));
    if (this.message) root.add(this.scene.add.text(layout.content.x + 20, layout.content.bottom - 28, this.message, { color: '#6a321f', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold' }));
    const selected = state.data.find((stack) => stack.item.id === this.selectedItemId);
    if (selected?.item.effect?.type === 'energy_restore') this.button(root, layout.action, this.useAttempt.state === 'retryable' ? 'RETRY USE' : 'USE', () => void this.useSelectedEnergy(), this.useAttempt.state !== 'submitting');
  }
  private row(root: Phaser.GameObjects.Container, layout: EconomyLayout, stack: OwnedItemStack, y: number, height: number): void { const selected = stack.item.id === this.selectedItemId; const g = this.scene.add.graphics(); g.fillStyle(selected ? 0xcfb77e : 0xe5d4ad, 1); g.fillRoundedRect(layout.content.x + 18, y, layout.content.width - 36, height, 10); g.setInteractive(new Phaser.Geom.Rectangle(layout.content.x + 18, y, layout.content.width - 36, height), Phaser.Geom.Rectangle.Contains).on('pointerup', () => this.selectItem(stack.item.id)); actionCursor(g); root.add(g); root.add(this.scene.add.text(layout.content.x + 34, y + 10, `${stack.item.display_name} ×${stack.quantity}`, { color: '#3a2a1a', fontFamily: 'Georgia, serif', fontSize: '21px', fontStyle: 'bold' })); root.add(this.scene.add.text(layout.content.x + 34, y + 42, `${stack.item.rarity.toUpperCase()} ${stack.item.category} · ${itemEffectLabel(stack)}\n${stack.item.description}`, { color: '#74664b', fontFamily: 'system-ui', fontSize: '13px', wordWrap: { width: layout.content.width - 80 } })); }
  private center(root: Phaser.GameObjects.Container, layout: EconomyLayout, title: string, detail: string): void { root.add(this.scene.add.text(layout.content.x + layout.content.width / 2, layout.content.y + layout.content.height / 2 - 20, title, { color: '#5b351f', fontFamily: 'Georgia, serif', fontSize: '28px', fontStyle: 'bold' }).setOrigin(.5)); root.add(this.scene.add.text(layout.content.x + layout.content.width / 2, layout.content.y + layout.content.height / 2 + 20, detail, { color: '#74664b', fontFamily: 'system-ui', fontSize: '16px' }).setOrigin(.5)); }
  private mutationBlocksNavigation(): boolean { return this.useAttempt.state === 'submitting' || this.useAttempt.state === 'retryable'; }
  private button(root: Phaser.GameObjects.Container, bounds: EconomyLayout['back'], label: string, action: () => void, enabled: boolean): void { const g = this.scene.add.graphics(); g.fillStyle(enabled ? 0x273e35 : 0x6c6658, 1); g.fillRoundedRect(bounds.x, bounds.y, bounds.width, bounds.height, 10); if (enabled) { g.setInteractive(new Phaser.Geom.Rectangle(bounds.x, bounds.y, bounds.width, bounds.height), Phaser.Geom.Rectangle.Contains).on('pointerup', action); actionCursor(g); } root.add([g, this.scene.add.text(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2, label, { color: '#fff4d3', fontFamily: 'system-ui', fontSize: '15px', fontStyle: 'bold' }).setOrigin(.5)]); }
}
