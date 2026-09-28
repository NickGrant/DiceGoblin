import { ClientContentRegistry } from './client-content-registry';
import { GameBootstrapData, GameStore } from './game-store';
import { RuntimeApiClient } from './runtime-api-client';

describe('GameStore Package 7 reconciliation', () => {
  const content = () => new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'Farm', art_key: 'farm' } },
    kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Goblin.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
    unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Bruiser.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: { hp: 10, attack: 1, defense: 1, precision: 1, resolve: 1 }, growth_per_level: { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 }, ability_ids: ['ability.bash'] } },
    abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bash.', icon_key: 'bash', dice_slot_count: 1 } }, dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } }, dice_aspects: {},
    dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Bone', material_id: 'dice_material.bone', rarity: 'common', aspect_ids: [], allowed_sizes: [6] } },
    run_node_types: { 'run_node_type.combat': { id: 'run_node_type.combat', display_name: 'Combat', description: 'Fight.', icon_key: 'combat' } },
    items: { 'item.test.heal': { id: 'item.test.heal', display_name: 'Poultice', description: 'Heal.', category: 'consumable', rarity: 'common', icon_key: 'heal', stackable: true, effect: { type: 'unit_heal', amount: 5 } },
      'item.test.spark': { id: 'item.test.spark', display_name: 'Spark', description: 'Energy.', category: 'consumable', rarity: 'common', icon_key: 'spark', stackable: true, effect: { type: 'energy_restore', amount: 5 } } },
    unit_promotions: {}, academy_upgrades: {}, shop_offers: {
      'shop_offer.test.die': { id: 'shop_offer.test.die', grant: { type: 'die', dice_profile_id: 'dice_profile.bone', size: 6 } },
      'shop_offer.test.spark': { id: 'shop_offer.test.spark', grant: { type: 'item', item_id: 'item.test.spark', quantity: 1 } },
      'shop_offer.test.unit': { id: 'shop_offer.test.unit', grant: { type: 'unit', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin' } },
    } } });
  const bootstrap = (revision = 4): GameBootstrapData => ({ account: { id: '1', display_name: 'Goblin', role: 'user' }, player: { teeth: 100, raw_chaos: 2, player_revision: revision, energy: { current: 10, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: '2026-01-01T00:05:00Z', fully_regenerated_at: '2026-01-01T03:20:00Z' } }, session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] }, active_squad: { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null], units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] }, active_run: null });
  function api(): jasmine.SpyObj<RuntimeApiClient> { const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getUnits', 'getDice', 'getSquads', 'getItems', 'getShop', 'getCurrentRun']); client.getUnits.and.resolveTo({ ok: true, data: { units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] } }); client.getDice.and.resolveTo({ ok: true, data: { dice: [{ id: '21', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [] }] } }); client.getSquads.and.resolveTo({ ok: true, data: { squads: [{ id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] }] } }); client.getItems.and.resolveTo({ ok: true, data: { items: [{ item_id: 'item.test.heal', quantity: 2 }] } }); return client; }

  it('adds purchased die and unit outputs only to already-loaded caches while the global player owns Teeth', async () => {
    const registry = content();
    for (const output of [
      { type: 'die' as const, die: { id: '22', size: 6 as const, profileId: 'dice_profile.bone', lifecycleStatus: 'active' as const } },
      { type: 'unit' as const, unit: { id: '12', displayName: 'Bruiser', unitTypeId: 'unit_type.bruiser', kinId: 'kin.goblin' as const, level: 1 as const, xp: 0 as const, lifecycleStatus: 'active' as const } },
    ]) {
      const store = new GameStore(); store.hydrateBootstrap(bootstrap()); const client = api(); await store.loadWarbandDomains(client, registry);
      client.getShop.and.resolveTo({ ok: true, data: { teeth: 100, player_revision: 4, offers: [
        { offer_id: 'shop_offer.test.die', price: { currency_id: 'teeth', amount: 10 }, available: true, can_afford: true },
        { offer_id: 'shop_offer.test.spark', price: { currency_id: 'teeth', amount: 95 }, available: true, can_afford: true },
        { offer_id: 'shop_offer.test.unit', price: { currency_id: 'teeth', amount: 10 }, available: true, can_afford: true }] } });
      await store.loadShop(client, registry); const offerId = output.type === 'die' ? 'shop_offer.test.die' : 'shop_offer.test.unit';
      store.reconcileShopPurchase({ offerId, spend: { currencyId: 'teeth', amount: 10, balanceBefore: 100, balanceAfter: 90 }, playerRevision: 5, output }, registry);
      expect(output.type === 'die' ? store.warband.dice.data?.some((die) => die.id === '22') : store.warband.units.data?.some((unit) => unit.id === '12')).toBeTrue();
      expect(store.bootstrap?.player.teeth).toBe(90);
      expect(store.shop.data && 'teeth' in store.shop.data).toBeFalse();
    }
  });

  it('removes a terminal unbound die and preserves unrelated unit/loadout state', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap()); const client = api(); await store.loadWarbandDomains(client, content());
    const units = store.warband.units.data;
    store.reconcileDiceLifecycle({ diceId: '21', lifecycleStatus: 'sold', teethAwarded: 5, teeth: 105, playerRevision: 5 });
    expect(store.warband.dice.data).toEqual([]); expect(store.warband.units.data).toBe(units); expect(store.bootstrap?.player.teeth).toBe(105);
  });

  it('heals exactly one run participant without changing nodes or permanent unit state', async () => {
    const data = bootstrap(); const store = new GameStore(); store.hydrateBootstrap({ ...data, active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    const client = api(); client.getCurrentRun.and.resolveTo({ run: { id: '41', regionId: 'region.the_farm', squadId: '31', status: 'active', createdAt: '2026-01-01T00:00:00Z', nodes: [{ id: '51', nodeIndex: 0, nodeTypeId: 'run_node_type.combat', status: 'available', completedAt: null, battleId: null, position: { column: 0, row: 0 } }], edges: [], units: [{ unitId: '11', currentHp: 0 }] }, playerRevision: 4 });
    await store.loadCurrentRun(client, content()); await store.loadItems(client, content()); const nodes = store.currentRun.data?.nodes; const permanent = store.bootstrap?.active_squad?.units;
    store.reconcileRunUnitHeal({ itemId: 'item.test.heal', quantityConsumed: 1, ownedQuantityAfter: 1, runId: '41', unit: { unitId: '11', hpBefore: 0, hpAfter: 5, maxHp: 10 }, playerRevision: 5 });
    expect(store.currentRun.data?.units[0].currentHp).toBe(5); expect(store.currentRun.data?.nodes).toBe(nodes); expect(store.bootstrap?.active_squad?.units).toBe(permanent); expect(store.inventory.data?.[0].quantity).toBe(1);
  });

  it('rejects revision regression without adopting speculative state', () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap(8));
    expect(() => store.reconcileEnergyRestore({ itemId: 'item.test.spark', quantityConsumed: 1, ownedQuantityAfter: 0, playerRevision: 7, energy: { current: 15, normalMax: 50, regenerationPerHour: 12, regenerationIntervalSeconds: 300, lastRegenerationAt: '2026-01-01T00:00:00Z', nextRegenerationAt: '2026-01-01T00:05:00Z', fullyRegeneratedAt: '2026-01-01T02:55:00Z' } })).toThrow();
    expect(store.bootstrap?.player.player_revision).toBe(8); expect(store.bootstrap?.player.energy.current).toBe(10);
  });
});
