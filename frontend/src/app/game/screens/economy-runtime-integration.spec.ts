import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { GameScreenNavigator } from './game-screen-navigation';
import { InventoryScreen } from './inventory-screen';
import { ShopScreen } from './shop-screen';

describe('Package 8 persistent economy runtime integration', () => {
  it('composes Camp, Shop, Supplies, mutations, navigation, and authoritative reload in one store', async () => {
    const registry = content(); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const api = jasmine.createSpyObj<RuntimeApiClient>('api', [
      'getShop', 'getItems', 'purchaseShopOffer', 'restoreEnergy', 'getUnits', 'getDice', 'getSquads',
    ]);
    api.getShop.and.resolveTo({ ok: true, data: { teeth: 20, player_revision: 4, offers: [
      { offer_id: 'shop_offer.test.spark', price: { currency_id: 'teeth', amount: 7 }, available: true, can_afford: true },
    ] } });
    api.purchaseShopOffer.and.resolveTo({ offerId: 'shop_offer.test.spark', spend: { currencyId: 'teeth', amount: 7,
      balanceBefore: 20, balanceAfter: 13 }, playerRevision: 5,
      output: { type: 'item', itemId: 'item.test.spark', quantityGranted: 2, ownedQuantityAfter: 2 } });
    api.getItems.and.resolveTo({ ok: true, data: { items: [{ item_id: 'item.test.spark', quantity: 2 }] } });
    api.restoreEnergy.and.resolveTo({ itemId: 'item.test.spark', quantityConsumed: 1, ownedQuantityAfter: 1,
      playerRevision: 6, energy: { current: 15, normalMax: 50, regenerationPerHour: 12,
        regenerationIntervalSeconds: 300, lastRegenerationAt: '2026-01-01T00:00:00Z',
        nextRegenerationAt: '2026-01-01T00:05:00Z', fullyRegeneratedAt: '2026-01-01T02:55:00Z' } });

    const navigation = new GameScreenNavigator(); navigation.start('camp');
    const shop = new ShopScreen(scene(), store, api, registry, new RuntimeViewport(), () => navigation.back('camp'), () => 'purchase-key');
    navigation.navigate('shop'); shop.create(); await store.loadShop(api, registry);
    shop.selectOffer('shop_offer.test.spark'); await shop.purchaseSelected();
    expect(store.bootstrap?.player).toEqual(jasmine.objectContaining({ teeth: 13, player_revision: 5 }));
    expect(store.inventory.status).toBe('not-loaded');
    shop.requestBack(); shop.destroy(); expect(navigation.current).toBe('camp');

    const inventory = new InventoryScreen(scene(), store, api, registry, new RuntimeViewport(), () => navigation.back('camp'), () => 'energy-key');
    navigation.navigate('inventory'); inventory.create(); await store.loadItems(api, registry);
    inventory.selectItem('item.test.spark'); await inventory.useSelectedEnergy();
    expect(store.bootstrap?.player).toEqual(jasmine.objectContaining({ teeth: 13, player_revision: 6 }));
    expect(store.bootstrap?.player.energy.current).toBe(15);
    expect(store.inventory.data).toEqual([jasmine.objectContaining({ quantity: 1 })]);
    inventory.reflow(new RuntimeViewport().snapshot); inventory.requestBack(); inventory.destroy();
    expect(navigation.current).toBe('camp');
    expect(api.purchaseShopOffer).toHaveBeenCalledWith(
      { offer_id: 'shop_offer.test.spark', expected_price: { currency_id: 'teeth', amount: 7 } },
      'csrf', 'purchase-key', registry,
    );
    expect(api.restoreEnergy).toHaveBeenCalledWith('item.test.spark', 'csrf', 'energy-key', registry);
    expect(api.getUnits).not.toHaveBeenCalled(); expect(api.getDice).not.toHaveBeenCalled(); expect(api.getSquads).not.toHaveBeenCalled();

    const reloaded = new GameStore(); reloaded.hydrateBootstrap({ ...bootstrap(), player: {
      ...bootstrap().player, teeth: 13, player_revision: 6, energy: { ...bootstrap().player.energy, current: 15 },
    } });
    api.getItems.and.resolveTo({ ok: true, data: { items: [{ item_id: 'item.test.spark', quantity: 1 }] } });
    await reloaded.loadItems(api, registry);
    expect(reloaded.bootstrap?.player.player_revision).toBe(6);
    expect(reloaded.inventory.data).toEqual([jasmine.objectContaining({ quantity: 1 })]);
  });

  function bootstrap(): GameBootstrapData {
    return { account: { id: '1', display_name: 'Goblin', role: 'user' }, player: { teeth: 20, raw_chaos: 0,
      player_revision: 4, energy: { current: 10, normal_max: 50, regeneration_per_hour: 12,
        regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z',
        next_regeneration_at: '2026-01-01T00:05:00Z', fully_regenerated_at: '2026-01-01T03:20:00Z' } },
      session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z',
      content_revision: 'a'.repeat(64), progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] },
      active_squad: null, active_run: null };
  }

  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {}, kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {},
      items: { 'item.test.spark': { id: 'item.test.spark', display_name: 'Spark', description: 'Bright.',
        category: 'consumable', rarity: 'common', icon_key: 'spark', stackable: true,
        effect: { type: 'energy_restore', amount: 5 } } },
      shop_offers: { 'shop_offer.test.spark': { id: 'shop_offer.test.spark',
        grant: { type: 'item', item_id: 'item.test.spark', quantity: 2 } } } } });
  }

  function scene(): Phaser.Scene {
    const chain = (): Record<string, unknown> => {
      const object: Record<string, unknown> = {};
      for (const name of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect',
        'setInteractive', 'on', 'setOrigin']) object[name] = jasmine.createSpy(name).and.returnValue(object);
      object['add'] = jasmine.createSpy('add').and.returnValue(object); return object;
    };
    return { add: { container: () => chain(), graphics: () => chain(), text: () => chain() } } as unknown as Phaser.Scene;
  }
});
