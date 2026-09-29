import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { AcademyScreen, academyStatus } from './academy-screen';
import { UnitPromotionScreen, promotionStatus } from './unit-promotion-screen';
import { GameScreenNavigator } from './game-screen-navigation';

describe('Package 5 progression screens', () => {
  const stat = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
  const registry = () => new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
    gameplay: { run_energy_cost: 10 }, regions: {},
    kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Goblin.', art_key: 'goblin',
      trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
    unit_types: {
      'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Strong.', art_key: 'bruiser',
        role: 'frontline', tier: 1, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.bash'] },
      'unit_type.enforcer': { id: 'unit_type.enforcer', display_name: 'Enforcer', description: 'Stronger.', art_key: 'enforcer',
        role: 'frontline', tier: 2, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.bash', 'ability.smash'] },
    },
    abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Hit.', icon_key: 'bash', dice_slot_count: 1 },
      'ability.smash': { id: 'ability.smash', kind: 'active', display_name: 'Smash', description: 'Hit.', icon_key: 'smash', dice_slot_count: 1 } },
    dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {}, items: {}, shop_offers: {},
    academy_upgrades: { 'academy_upgrade.one': { id: 'academy_upgrade.one', display_name: 'First Research',
      description: 'An upgrade.', category: 'energy' } },
    unit_promotions: { 'unit_promotion.bruiser.enforcer': { id: 'unit_promotion.bruiser.enforcer',
      from_unit_type_id: 'unit_type.bruiser', to_unit_type_id: 'unit_type.enforcer' } },
  } });
  const bootstrap = (): GameBootstrapData => ({ account: { id: '1', display_name: 'Goblin', role: 'user' },
    player: { teeth: 20, raw_chaos: 10, player_revision: 4, energy: { current: 10, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z',
      next_regeneration_at: '2026-01-01T00:05:00Z', fully_regenerated_at: '2026-01-01T03:20:00Z' } },
    session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z',
    content_revision: 'a'.repeat(64), progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] },
    active_squad: { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null],
      units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin',
        level: 3, xp: 44, lifecycle_status: 'active' }] }, active_run: null });
  const academyRead = (wallet = 10, revision = 4) => ({ rawChaos: wallet, playerRevision: revision,
    upgrades: [{ upgrade: registry().getAcademyUpgrade('academy_upgrade.one')!,
      price: { currencyId: 'raw_chaos' as const, amount: 5 }, owned: false, available: true }] });
  const detailRead = () => ({ ok: true, data: { unit: { id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser',
    kin_id: 'kin.goblin', level: 3, xp: 44, xp_to_next_level: 300, lifecycle_status: 'active', promotion_history: [],
    owned_ability_ids: ['ability.bash'], ability_loadout: [], dice_bindings: [] } } });
  function api(): jasmine.SpyObj<RuntimeApiClient> {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getAcademy', 'upgradeAcademy', 'getShop', 'getUnits', 'getDice',
      'getSquads', 'getUnitDetail', 'getUnitPromotionOptions', 'promoteUnit']);
    client.getAcademy.and.resolveTo(academyRead());
    client.getShop.and.resolveTo({ ok: true, data: { teeth: 20, player_revision: 4, offers: [] } });
    client.getUnits.and.resolveTo({ ok: true, data: { units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser',
      kin_id: 'kin.goblin', level: 3, xp: 44, lifecycle_status: 'active' }] } });
    client.getDice.and.resolveTo({ ok: true, data: { dice: [] } });
    client.getSquads.and.resolveTo({ ok: true, data: { squads: [{ id: '31', name: 'Raiders', is_active: true,
      formation: ['11', null, null, null, null, null, null, null, null] }] } });
    client.getUnitDetail.and.resolveTo(detailRead());
    client.getUnitPromotionOptions.and.callFake(async () => ({ unitId: '11', unitType: registry().getUnitType('unit_type.bruiser')!,
      level: 3, xp: 44, xpToNextLevel: 300, rawChaos: 10, playerRevision: 4, configurationLocked: false,
      options: [{ promotion: registry().getUnitPromotion('unit_promotion.bruiser.enforcer')!,
        targetUnitType: registry().getUnitType('unit_type.enforcer')!, requiredLevel: 3, price: 5,
        levelMet: true, canAfford: true, available: true, newAbilities: [registry().getAbility('ability.smash')!] }] }));
    return client;
  }
  function scene(): Phaser.Scene {
    const chain = (): Record<string, unknown> => { const object: Record<string, unknown> = {};
      for (const name of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect',
        'setInteractive', 'on', 'setOrigin']) object[name] = jasmine.createSpy(name).and.returnValue(object);
      object['add'] = jasmine.createSpy('add').and.returnValue(object); return object; };
    return { add: { container: () => chain(), graphics: () => chain(), text: () => chain() } } as unknown as Phaser.Scene;
  }

  it('keeps Academy lazy, confirms before spending, and retries the same key after an ambiguous outcome', async () => {
    const client = api(); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    expect(store.academy.status).toBe('not-loaded'); expect(client.getAcademy).not.toHaveBeenCalled();
    const back = jasmine.createSpy('back'); const screen = new AcademyScreen(scene(), store, client, registry(),
      new RuntimeViewport(), back, () => 'one-key');
    screen.create(); await store.loadAcademy(client, registry());
    screen.selectUpgrade('academy_upgrade.one');
    await screen.submitUpgrade(); expect(client.upgradeAcademy).not.toHaveBeenCalled();
    screen.confirmSelected(); client.upgradeAcademy.and.rejectWith(new RuntimeApiError('network'));
    await screen.submitUpgrade(); expect(screen.actionState).toBe('retryable');
    screen.requestBack(); expect(back).not.toHaveBeenCalled();
    client.upgradeAcademy.and.resolveTo({ upgradeId: 'academy_upgrade.one', spend: { currencyId: 'raw_chaos', amount: 5,
      balanceBefore: 10, balanceAfter: 5 }, grant: { unlockId: 'unlock.capability.energy_max_75' },
      energy: null, playerRevision: 5 });
    client.getAcademy.and.resolveTo({ ...academyRead(5, 5), upgrades: [{ ...academyRead().upgrades[0], owned: true, available: false }] });
    await screen.submitUpgrade();
    expect(client.upgradeAcademy.calls.allArgs().map((args) => [args[0], args[2]])).toEqual([
      [{ upgrade_id: 'academy_upgrade.one', expected_price: { currency_id: 'raw_chaos', amount: 5 } }, 'one-key'],
      [{ upgrade_id: 'academy_upgrade.one', expected_price: { currency_id: 'raw_chaos', amount: 5 } }, 'one-key']]);
    expect(store.bootstrap?.player.raw_chaos).toBe(5); expect(store.academy.status).toBe('fresh');
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1); screen.destroy();
  });

  it('requires read wallet/revision agreement and preserves committed wallet on reconciliation contradiction', async () => {
    const client = api(); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    client.getAcademy.and.resolveTo(academyRead(9)); await store.loadAcademy(client, registry());
    expect(store.academy.status).toBe('error'); expect(store.academy.error).toBe('integrity');
    const before = bootstrap(); store.hydrateBootstrap({ ...before, progression: { ...before.progression,
      unlock_ids: ['unlock.capability.energy_max_75'] } });
    expect(() => store.reconcileAcademyUpgrade({ upgradeId: 'academy_upgrade.one', spend: { currencyId: 'raw_chaos',
      amount: 5, balanceBefore: 10, balanceAfter: 5 }, grant: { unlockId: 'unlock.capability.energy_max_75' },
      energy: null, playerRevision: 5 })).toThrow();
    expect(store.bootstrap?.player.raw_chaos).toBe(5); expect(store.bootstrap?.player.player_revision).toBe(5);
    expect(store.academy.status).toBe('error');
  });

  it('loads one unit lazily and offers progression through a stable Camp navigation history', async () => {
    const client = api(); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    expect(store.promotionOptions('11').status).toBe('not-loaded');
    const nav = new GameScreenNavigator(); nav.start('camp'); nav.navigate('warband'); nav.navigate('unit-configuration');
    nav.navigate('unit-promotion'); expect(nav.back()).toBe('unit-configuration'); expect(nav.back()).toBe('warband');
    const screen = new UnitPromotionScreen(scene(), store, client, registry(), new RuntimeViewport(), '11', () => undefined);
    screen.create(); await store.loadWarbandDomains(client, registry()); await store.loadUnitDetail('11', client, registry());
    await store.loadPromotionOptions('11', client, registry());
    expect(store.promotionOptions('11').status).toBe('fresh');
    expect(promotionStatus(store.promotionOptions('11').data!.options[0], false, 2)).toBe('INSUFFICIENT RAW CHAOS');
    expect(academyStatus(academyRead().upgrades[0], 2)).toBe('INSUFFICIENT RAW CHAOS');
    screen.destroy();
  });

  it('reconciles promotion to the same unit while preserving permanent state and invalidating options', async () => {
    const client = api(); const store = new GameStore(); const content = registry(); store.hydrateBootstrap(bootstrap());
    await store.loadWarbandDomains(client, content); await store.loadUnitDetail('11', client, content);
    await store.loadPromotionOptions('11', client, content);
    const previous = store.unitDetail('11').data!;
    const result = { promotion: content.getUnitPromotion('unit_promotion.bruiser.enforcer')!,
      spend: { currencyId: 'raw_chaos' as const, amount: 5, balanceBefore: 10, balanceAfter: 5 },
      playerRevision: 5, grantedAbilities: [content.getAbility('ability.smash')!],
      unit: { ...previous, unitType: content.getUnitType('unit_type.enforcer')!,
        ownedAbilities: [...previous.ownedAbilities, content.getAbility('ability.smash')!],
        promotionHistory: [...previous.promotionHistory, { fromUnitType: previous.unitType,
          toUnitType: content.getUnitType('unit_type.enforcer')!, promotedAt: '2026-09-28T00:00:00Z' }] } };
    store.reconcileUnitPromotion(result);
    expect(store.bootstrap?.player.raw_chaos).toBe(5);
    expect(store.bootstrap?.player.player_revision).toBe(5);
    expect(store.bootstrap?.active_squad?.units[0].unit_type_id).toBe('unit_type.enforcer');
    expect(store.warband.units.data?.[0].unitType.id).toBe('unit_type.enforcer');
    expect(store.unitDetail('11').data?.displayName).toBe('Grub');
    expect(store.unitDetail('11').data?.level).toBe(3);
    expect(store.unitDetail('11').data?.xp).toBe(44);
    expect(store.unitDetail('11').data?.ownedAbilities.map((ability) => ability.id)).toEqual(['ability.bash', 'ability.smash']);
    expect(store.promotionOptions('11').status).toBe('stale');
  });

  it('updates Energy and invalidates loaded Academy and Shop without creating a second wallet', async () => {
    const client = api(); const store = new GameStore(); const content = registry(); store.hydrateBootstrap(bootstrap());
    await store.loadAcademy(client, content); await store.loadShop(client, content);
    const energy = { current: 10, normalMax: 75, regenerationPerHour: 12, regenerationIntervalSeconds: 300,
      lastRegenerationAt: '2026-01-01T00:00:00Z', nextRegenerationAt: '2026-01-01T00:05:00Z',
      fullyRegeneratedAt: '2026-01-01T05:00:00Z' };
    store.reconcileAcademyUpgrade({ upgradeId: 'academy_upgrade.one', spend: { currencyId: 'raw_chaos',
      amount: 5, balanceBefore: 10, balanceAfter: 5 }, grant: { unlockId: 'unlock.capability.energy_max_75' },
      energy, playerRevision: 5 });
    expect(store.bootstrap?.player.energy.normal_max).toBe(75);
    expect(store.bootstrap?.player.energy.current).toBe(10);
    expect(store.bootstrap?.progression.unlock_ids).toEqual(['unlock.capability.energy_max_75']);
    expect(store.academy.status).toBe('stale'); expect(store.shop.status).toBe('stale');
    expect(store.academy.data && 'rawChaos' in store.academy.data).toBeFalse();
  });

  it('retains the exact unit promotion attempt across uncertainty and refreshes terminal options after success', async () => {
    const client = api(); const store = new GameStore(); const content = registry(); store.hydrateBootstrap(bootstrap());
    await store.loadWarbandDomains(client, content); await store.loadUnitDetail('11', client, content);
    await store.loadPromotionOptions('11', client, content);
    const back = jasmine.createSpy('back');
    const screen = new UnitPromotionScreen(scene(), store, client, content, new RuntimeViewport(), '11', back, () => 'promotion-key');
    screen.create(); screen.selectOption('unit_promotion.bruiser.enforcer');
    await screen.submitPromotion(); expect(client.promoteUnit).not.toHaveBeenCalled();
    screen.confirmSelected(); client.promoteUnit.and.rejectWith(new RuntimeApiError('network'));
    await screen.submitPromotion(); expect(screen.actionState).toBe('retryable');
    screen.requestBack(); expect(back).not.toHaveBeenCalled();
    const prior = store.unitDetail('11').data!;
    client.promoteUnit.and.resolveTo({ promotion: content.getUnitPromotion('unit_promotion.bruiser.enforcer')!,
      spend: { currencyId: 'raw_chaos', amount: 5, balanceBefore: 10, balanceAfter: 5 }, playerRevision: 5,
      grantedAbilities: [content.getAbility('ability.smash')!],
      unit: { ...prior, unitType: content.getUnitType('unit_type.enforcer')!,
        ownedAbilities: [...prior.ownedAbilities, content.getAbility('ability.smash')!],
        promotionHistory: [{ fromUnitType: prior.unitType, toUnitType: content.getUnitType('unit_type.enforcer')!,
          promotedAt: '2026-09-28T00:00:00Z' }] } });
    client.getUnitPromotionOptions.and.resolveTo({ unitId: '11', unitType: content.getUnitType('unit_type.enforcer')!,
      level: 3, xp: 44, xpToNextLevel: 300, rawChaos: 5, playerRevision: 5, configurationLocked: false, options: [] });
    await screen.submitPromotion();
    expect(client.promoteUnit.calls.allArgs().map((args) => [args[0], args[1], args[3]])).toEqual([
      ['11', { promotion_id: 'unit_promotion.bruiser.enforcer', expected_price: { currency_id: 'raw_chaos', amount: 5 } }, 'promotion-key'],
      ['11', { promotion_id: 'unit_promotion.bruiser.enforcer', expected_price: { currency_id: 'raw_chaos', amount: 5 } }, 'promotion-key']]);
    expect(store.unitDetail('11').data?.unitType.id).toBe('unit_type.enforcer');
    expect(store.promotionOptions('11').data?.options).toEqual([]);
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1); screen.destroy();
  });

  it('releases Academy navigation after a definitive rejection', async () => {
    const client = api(); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const back = jasmine.createSpy('back'); const screen = new AcademyScreen(scene(), store, client, registry(),
      new RuntimeViewport(), back, () => 'rejected-key');
    screen.create(); await store.loadAcademy(client, registry()); screen.selectUpgrade('academy_upgrade.one');
    screen.confirmSelected(); client.upgradeAcademy.and.rejectWith(new RuntimeApiError('http', 409, 'upgrade_unavailable'));
    await screen.submitUpgrade(); expect(screen.actionState).toBe('rejected');
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1); screen.destroy();
  });
});
