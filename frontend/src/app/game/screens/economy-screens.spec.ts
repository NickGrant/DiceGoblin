import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { InventoryScreen, itemEffectLabel } from './inventory-screen';
import { ShopScreen, shopGrantLabel } from './shop-screen';

describe('Package 7 Shop and Supplies screens', () => {
  const bootstrap = (): GameBootstrapData => ({ account: { id: '1', display_name: 'Goblin', role: 'user' },
    player: { teeth: 20, raw_chaos: 0, player_revision: 4, energy: { current: 10, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z',
      next_regeneration_at: '2026-01-01T00:05:00Z', fully_regenerated_at: '2026-01-01T03:20:00Z' } },
    session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z',
    content_revision: 'a'.repeat(64), progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] }, active_squad: null, active_run: null });
  const content = () => new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: {}, kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {},
    items: { 'item.test.spark': { id: 'item.test.spark', display_name: 'Spark', description: 'Bright.', category: 'consumable', rarity: 'common', icon_key: 'spark', stackable: true, effect: { type: 'energy_restore', amount: 5 } },
      'item.test.ore': { id: 'item.test.ore', display_name: 'Ore', description: 'Chunky.', category: 'material', rarity: 'uncommon', icon_key: 'ore', stackable: true } },
    shop_offers: { 'shop_offer.test.spark': { id: 'shop_offer.test.spark', grant: { type: 'item', item_id: 'item.test.spark', quantity: 2 } } } } });
  function scene(): { value: Phaser.Scene; texts: string[] } { const texts: string[] = []; const chain = (): Record<string, unknown> => { const object: Record<string, unknown> = {}; for (const name of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect', 'setInteractive', 'on', 'setOrigin']) object[name] = jasmine.createSpy(name).and.returnValue(object); object['add'] = jasmine.createSpy('add').and.returnValue(object); return object; }; return { texts, value: { add: { container: () => chain(), graphics: () => chain(), text: (_x: number, _y: number, text: string) => { texts.push(text); return chain(); } } } as unknown as Phaser.Scene }; }
  function api(): jasmine.SpyObj<RuntimeApiClient> { return jasmine.createSpyObj<RuntimeApiClient>('api', ['getShop', 'getItems', 'purchaseShopOffer', 'restoreEnergy']); }

  it('shows authored grants and authoritative availability/affordability states', async () => {
    const client = api(); client.getShop.and.resolveTo({ ok: true, data: { teeth: 20, player_revision: 4, offers: [
      { offer_id: 'shop_offer.test.spark', price: { currency_id: 'teeth', amount: 25 }, available: false, can_afford: false }] } });
    const store = new GameStore(); store.hydrateBootstrap(bootstrap()); const harness = scene();
    const screen = new ShopScreen(harness.value, store, client, content(), new RuntimeViewport(), () => undefined);
    screen.create(); await store.loadShop(client, content());
    expect(harness.texts).toContain('Spark ×2'); expect(harness.texts).toContain('UNAVAILABLE');
    expect(shopGrantLabel(content().listShopOffers()[0], content())).toBe('Spark ×2');
  });

  it('submits the shown offer/price and retains its key across an ambiguous retry', async () => {
    const client = api(); client.getShop.and.resolveTo({ ok: true, data: { teeth: 20, player_revision: 4, offers: [
      { offer_id: 'shop_offer.test.spark', price: { currency_id: 'teeth', amount: 7 }, available: true, can_afford: true }] } });
    client.purchaseShopOffer.and.rejectWith(new RuntimeApiError('network'));
    const back = jasmine.createSpy('back');
    const store = new GameStore(); store.hydrateBootstrap(bootstrap()); const screen = new ShopScreen(scene().value, store, client, content(), new RuntimeViewport(), back, () => 'purchase-key');
    screen.create(); await store.loadShop(client, content()); screen.selectOffer('shop_offer.test.spark'); await screen.purchaseSelected();
    expect(screen.actionState).toBe('retryable');
    screen.requestBack(); // Both the visible Back control and GameScene Escape delegate here.
    expect(back).not.toHaveBeenCalled();
    expect(client.purchaseShopOffer).toHaveBeenCalledWith({ offer_id: 'shop_offer.test.spark', expected_price: { currency_id: 'teeth', amount: 7 } }, 'csrf', 'purchase-key', jasmine.any(ClientContentRegistry));
    client.purchaseShopOffer.and.resolveTo({ offerId: 'shop_offer.test.spark', spend: { currencyId: 'teeth', amount: 7, balanceBefore: 20, balanceAfter: 13 }, playerRevision: 5, output: { type: 'item', itemId: 'item.test.spark', quantityGranted: 2, ownedQuantityAfter: 2 } });
    await screen.purchaseSelected();
    expect(client.purchaseShopOffer.calls.allArgs().map((args) => [args[0], args[2]])).toEqual([
      [{ offer_id: 'shop_offer.test.spark', expected_price: { currency_id: 'teeth', amount: 7 } }, 'purchase-key'],
      [{ offer_id: 'shop_offer.test.spark', expected_price: { currency_id: 'teeth', amount: 7 } }, 'purchase-key'],
    ]);
    expect(store.bootstrap?.player.teeth).toBe(13);
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1);
  });

  it('releases Shop navigation after a definitive purchase rejection', async () => {
    const client = api(); client.getShop.and.resolveTo({ ok: true, data: { teeth: 20, player_revision: 4, offers: [
      { offer_id: 'shop_offer.test.spark', price: { currency_id: 'teeth', amount: 7 }, available: true, can_afford: true }] } });
    client.purchaseShopOffer.and.rejectWith(new RuntimeApiError('http', 409, 'offer_unavailable'));
    const back = jasmine.createSpy('back'); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const screen = new ShopScreen(scene().value, store, client, content(), new RuntimeViewport(), back, () => 'rejected-key');
    screen.create(); await store.loadShop(client, content()); screen.selectOffer('shop_offer.test.spark'); await screen.purchaseSelected();
    expect(screen.actionState).toBe('rejected'); screen.requestBack(); expect(back).toHaveBeenCalledTimes(1);
  });

  it('retains Energy use across blocked Back/Escape and releases navigation after retry success', async () => {
    const client = api(); client.getItems.and.resolveTo({ ok: true, data: { items: [{ item_id: 'item.test.spark', quantity: 1 }] } });
    client.restoreEnergy.and.rejectWith(new RuntimeApiError('network'));
    const back = jasmine.createSpy('back'); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const screen = new InventoryScreen(scene().value, store, client, content(), new RuntimeViewport(), back, () => 'energy-key');
    screen.create(); await store.loadItems(client, content()); screen.selectItem('item.test.spark'); await screen.useSelectedEnergy();
    expect(screen.actionState).toBe('retryable'); screen.requestBack(); expect(back).not.toHaveBeenCalled();
    client.restoreEnergy.and.resolveTo({ itemId: 'item.test.spark', quantityConsumed: 1, ownedQuantityAfter: 0, playerRevision: 5,
      energy: { current: 15, normalMax: 50, regenerationPerHour: 12, regenerationIntervalSeconds: 300,
        lastRegenerationAt: '2026-01-01T00:00:00Z', nextRegenerationAt: '2026-01-01T00:05:00Z', fullyRegeneratedAt: '2026-01-01T02:55:00Z' } });
    await screen.useSelectedEnergy();
    expect(client.restoreEnergy.calls.allArgs().map((args) => [args[0], args[2]])).toEqual([
      ['item.test.spark', 'energy-key'], ['item.test.spark', 'energy-key'],
    ]);
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1);
  });

  it('distinguishes materials and run-use healing while reconciling Energy use', async () => {
    const client = api(); client.getItems.and.resolveTo({ ok: true, data: { items: [{ item_id: 'item.test.ore', quantity: 2 }, { item_id: 'item.test.spark', quantity: 1 }] } });
    client.restoreEnergy.and.resolveTo({ itemId: 'item.test.spark', quantityConsumed: 1, ownedQuantityAfter: 0, playerRevision: 5,
      energy: { current: 15, normalMax: 50, regenerationPerHour: 12, regenerationIntervalSeconds: 300,
        lastRegenerationAt: '2026-01-01T00:00:00Z', nextRegenerationAt: '2026-01-01T00:05:00Z', fullyRegeneratedAt: '2026-01-01T02:55:00Z' } });
    const store = new GameStore(); store.hydrateBootstrap(bootstrap()); const screen = new InventoryScreen(scene().value, store, client, content(), new RuntimeViewport(), () => undefined, () => 'use-key');
    screen.create(); await store.loadItems(client, content()); screen.selectItem('item.test.spark'); await screen.useSelectedEnergy();
    expect(client.restoreEnergy).toHaveBeenCalledWith('item.test.spark', 'csrf', 'use-key', jasmine.any(ClientContentRegistry));
    expect(store.bootstrap?.player.energy.current).toBe(15); expect(store.inventory.data?.map((stack) => stack.item.id)).toEqual(['item.test.ore']);
    expect(itemEffectLabel({ item: content().getItem('item.test.ore')!, quantity: 1 })).toContain('no direct use');
  });
});
