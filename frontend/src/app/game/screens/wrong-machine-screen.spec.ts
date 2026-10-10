import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { ReconstructionResult, WrongMachineCatalog, WrongMachineRecipe } from '../runtime/wrong-machine-contracts';
import { WrongMachineScreen, wrongMachineStatus } from './wrong-machine-screen';

describe('Wrong Machine GameScene screen', () => {
  const stats = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
  const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: {}, kin: { 'kin.pig': { id: 'kin.pig', display_name: 'Pig', description: 'Pig.', art_key: 'pig',
      trait_summary: 'Pig.', stat_modifiers: stats } },
    unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Type.',
      art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: stats, growth_per_level: stats,
      ability_ids: ['ability.bash'] } },
    abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash',
      description: 'Hit.', icon_key: 'bash', dice_slot_count: 1 } },
    items: { 'item.pig_ear': { id: 'item.pig_ear', display_name: 'Pig Ear', description: 'Material.',
      category: 'material', rarity: 'common', icon_key: 'pig_ear', stackable: true } },
    dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {}, unit_promotions: {},
    academy_upgrades: {}, shop_offers: {} } });
  const bootstrap = (owned = false): GameBootstrapData => ({ account: { id: '1', display_name: 'Goblin', role: 'user' },
    player: { teeth: 20, raw_chaos: 10, player_revision: 4, energy: { current: 10, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z',
      next_regeneration_at: '2026-01-01T00:05:00Z', fully_regenerated_at: '2026-01-01T03:20:00Z' } },
    session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z',
    content_revision: 'a'.repeat(64), progression: { unlock_ids: ['unlock.capability.wrong_machine_access',
      ...(owned ? ['unlock.kin.pig'] : [])], available_region_ids: ['region.the_farm'] },
    active_squad: null, active_run: null });
  const recipe = (owned = false, reconstructable = true): WrongMachineRecipe => ({
    recipeId: 'reconstruction_recipe.reconstruct_pig_kin', displayName: 'Restore Pig Kin', description: 'Pig remains.',
    kin: content.getKin('kin.pig')!, kinRestored: owned,
    mode: owned ? 'repeat_reconstruction' : 'first_restoration',
    unitTypeSelection: owned ? 'chosen_unlocked' : 'random_unlocked',
    eligibleUnitTypes: [content.getUnitType('unit_type.bruiser')!],
    prerequisites: [{ unlockId: 'unlock.capability.wrong_machine_access', owned: true }], prerequisitesMet: true,
    price: { currencyId: 'raw_chaos', amount: 5 },
    ingredients: [{ item: content.getItem('item.pig_ear')!, quantity: 1, owned: reconstructable ? 2 : 0 }],
    reconstructable,
  });
  const catalog = (owned = false, wallet = 10, revision = 4, available = true): WrongMachineCatalog => ({
    rawChaos: wallet, playerRevision: revision, recipes: [recipe(owned, available)] });
  const result = (owned = false): ReconstructionResult => ({ recipeId: recipe().recipeId,
    mode: owned ? 'repeat_reconstruction' : 'first_restoration',
    unit: { id: '109', displayName: 'Snout', unitTypeId: 'unit_type.bruiser', kinId: 'kin.pig',
      level: 1, xp: 0, lifecycleStatus: 'active' },
    spend: { currencyId: 'raw_chaos', amount: 5, balanceBefore: 10, balanceAfter: 5 },
    consumedItems: [{ itemId: 'item.pig_ear', quantity: 1, ownedAfter: 1 }],
    kinRestoration: { kinId: 'kin.pig', unlockId: 'unlock.kin.pig', outcome: owned ? 'already_owned' : 'granted' },
    playerRevision: 5 });
  function api(): jasmine.SpyObj<RuntimeApiClient> {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getWrongMachine', 'reconstructKin', 'getUnits', 'getItems']);
    client.getWrongMachine.and.resolveTo(catalog());
    client.getUnits.and.resolveTo({ ok: true, data: { units: [] } });
    client.getItems.and.resolveTo({ ok: true, data: { items: [{ item_id: 'item.pig_ear', quantity: 2 }] } });
    return client;
  }
  function scene(values: string[]): Phaser.Scene {
    const chain = (): Record<string, unknown> => { const object: Record<string, unknown> = {};
      for (const name of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect',
        'setInteractive', 'on', 'setOrigin']) object[name] = jasmine.createSpy(name).and.returnValue(object);
      object['add'] = jasmine.createSpy('add').and.returnValue(object); return object; };
    return { add: { container: () => chain(), graphics: () => chain(),
      text: (_x: number, _y: number, value: string) => { values.push(value); return chain(); } } } as unknown as Phaser.Scene;
  }
  function setup(owned = false) {
    const client = api(); const store = new GameStore(); store.hydrateBootstrap(bootstrap(owned));
    client.getWrongMachine.and.resolveTo(catalog(owned));
    const values: string[] = []; const back = jasmine.createSpy('back');
    let number = 0; const screen = new WrongMachineScreen(scene(values), store, client, content,
      new RuntimeViewport(), back, () => `key-${++number}`);
    return { client, store, values, back, screen };
  }

  it('loads lazily, presents first restoration without a choice, and keeps disabled actions inert', async () => {
    const { client, store, values, screen } = setup();
    expect(store.wrongMachine.status).toBe('not-loaded'); expect(client.getWrongMachine).not.toHaveBeenCalled();
    screen.create(); await store.loadWrongMachine(client, content);
    expect(values).toContain('Restore Pig Kin');
    expect(values.some((value) => value.includes('server selects one unlocked unit type'))).toBeTrue();
    expect(screen.selectedTypeId).toBeNull();
    screen.confirmSelected(); expect(screen.actionState).toBe('idle');
    client.getWrongMachine.and.resolveTo(catalog(false, 10, 4, false));
    await store.retryWrongMachine(client, content);
    expect(wrongMachineStatus(store.wrongMachine.data!.recipes[0], 10)).toBe('MATERIALS REQUIRED');
    screen.confirmSelected(); await screen.submitReconstruction();
    expect(client.reconstructKin).not.toHaveBeenCalled(); screen.destroy();
  });

  it('requires explicit repeat selection and retains one request/key through ambiguous retry', async () => {
    const { client, store, back, screen } = setup(true);
    screen.create(); await store.loadWrongMachine(client, content);
    screen.confirmSelected(); await screen.submitReconstruction(); expect(client.reconstructKin).not.toHaveBeenCalled();
    screen.selectUnitType('unit_type.bruiser'); screen.confirmSelected();
    client.reconstructKin.and.rejectWith(new RuntimeApiError('network'));
    await screen.submitReconstruction(); expect(screen.actionState).toBe('retryable');
    const first = screen.attemptIdentity!;
    screen.requestBack(); expect(back).not.toHaveBeenCalled();
    client.reconstructKin.and.resolveTo(result(true)); client.getWrongMachine.and.resolveTo(catalog(true, 5, 5));
    await screen.submitReconstruction();
    expect(client.reconstructKin.calls.count()).toBe(2);
    expect(client.reconstructKin.calls.allArgs().map((args) => args[2])).toEqual([first.key, first.key]);
    expect(client.reconstructKin.calls.mostRecent().args[0].unit_type_id).toBe('unit_type.bruiser');
    expect(store.bootstrap?.player.raw_chaos).toBe(5);
    expect(store.bootstrap?.player.player_revision).toBe(5);
    expect(store.warband.units.status).toBe('not-loaded');
    expect(store.wrongMachine.status).toBe('fresh');
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1); screen.destroy();
  });

  it('reconciles loaded inventory/Warband, Kin and revision only after authoritative success', async () => {
    const { client, store, screen } = setup();
    await store.loadUnits(client, content); await store.loadItems(client, content);
    screen.create(); await store.loadWrongMachine(client, content); screen.confirmSelected();
    let complete!: (value: ReconstructionResult) => void;
    client.reconstructKin.and.returnValue(new Promise((resolve) => { complete = resolve; }));
    const pending = screen.submitReconstruction();
    expect(store.bootstrap?.player.raw_chaos).toBe(10);
    expect(store.warband.units.data?.length).toBe(0);
    await screen.submitReconstruction(); expect(client.reconstructKin).toHaveBeenCalledTimes(1);
    client.getWrongMachine.and.resolveTo(catalog(true, 5, 5));
    complete(result()); await pending;
    expect(store.bootstrap?.progression.unlock_ids).toContain('unlock.kin.pig');
    expect(store.inventory.data?.find((row) => row.item.id === 'item.pig_ear')?.quantity).toBe(1);
    expect(store.warband.units.data?.map((row) => row.id)).toEqual(['109']);
    expect(store.wrongMachine.data?.recipes[0].mode).toBe('repeat_reconstruction');
    screen.destroy();
  });

  it('refreshes after changed intent, releases Back, and issues a new key for a new intention', async () => {
    const { client, store, back, screen } = setup();
    screen.create(); await store.loadWrongMachine(client, content); screen.confirmSelected();
    client.reconstructKin.and.rejectWith(new RuntimeApiError('http', 409, 'reconstruction_changed'));
    client.getWrongMachine.and.resolveTo(catalog(false, 10, 4));
    await screen.submitReconstruction(); expect(screen.actionState).toBe('rejected');
    expect(client.getWrongMachine.calls.count()).toBe(2);
    const old = screen.attemptIdentity!.key;
    screen.confirmSelected(); client.reconstructKin.and.resolveTo(result());
    client.getWrongMachine.and.resolveTo(catalog(true, 5, 5));
    await screen.submitReconstruction();
    expect(screen.attemptIdentity!.key).not.toBe(old);
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1); screen.destroy();
  });

  it('retries read errors and refreshes a cached read when re-entering', async () => {
    const { client, store, values, screen } = setup();
    client.getWrongMachine.and.rejectWith(new RuntimeApiError('network'));
    screen.create(); await store.loadWrongMachine(client, content);
    expect(store.wrongMachine.status).toBe('error');
    expect(values.some((value) => value.includes('fresh read'))).toBeTrue();
    client.getWrongMachine.and.resolveTo(catalog());
    screen.retryRead(); await store.loadWrongMachine(client, content);
    expect(store.wrongMachine.status).toBe('fresh');
    screen.destroy();
    const second = new WrongMachineScreen(scene([]), store, client, content, new RuntimeViewport(), () => undefined);
    second.create(); await store.loadWrongMachine(client, content);
    expect(client.getWrongMachine.calls.count()).toBe(3);
    second.destroy();
  });
});
