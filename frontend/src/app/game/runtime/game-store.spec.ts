import { ClientContentRegistry } from './client-content-registry';
import { GameBootstrapData, GameStore } from './game-store';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';

describe('GameStore Warband cache', () => {
  function bootstrap(activeSquadId: string | null = '31'): GameBootstrapData {
    return {
      account: { id: '1', display_name: 'Goblin', role: 'user' },
      player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 5, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } },
      session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
      active_squad: activeSquadId ? { id: activeSquadId, name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null], units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] } : null,
      active_run: null,
    };
  }

  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      regions: {},
      kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Goblin.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Bruiser.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 }, growth_per_level: { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 }, ability_ids: ['ability.bash'] } },
      abilities: {
        'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bash.', icon_key: 'bash', dice_slot_count: 1 },
        'ability.smash': { id: 'ability.smash', kind: 'active', display_name: 'Smash', description: 'Smash.', icon_key: 'smash', dice_slot_count: 1 },
      },
      dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } },
      dice_aspects: {},
      dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Bone Die', material_id: 'dice_material.bone', rarity: 'common', aspect_ids: [], allowed_sizes: [6] } },
      run_node_types: {},
    } });
  }

  function api(): jasmine.SpyObj<RuntimeApiClient> {
    const result = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap', 'getUnits', 'getUnitDetail', 'getDice', 'getSquads', 'renameUnit', 'replaceUnitLoadout']);
    result.getUnits.and.resolveTo({ ok: true, data: { units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] } });
    result.getDice.and.resolveTo({ ok: true, data: { dice: [
      { id: '21', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.bash', slot_index: 0 }] },
      { id: '22', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [] },
      { id: '23', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '99', ability_id: 'ability.smash', slot_index: 0 }] },
    ] } });
    result.getSquads.and.resolveTo({ ok: true, data: { squads: [{ id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] }] } });
    result.getUnitDetail.and.resolveTo({ ok: true, data: { unit: {
      id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active', promotion_history: [],
      owned_ability_ids: ['ability.bash', 'ability.smash'], ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }],
      dice_bindings: [{ ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '21' }],
    } } });
    return result;
  }

  it('begins not-loaded after bootstrap and lazily loads each domain once while fresh', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const client = api();
    expect(store.warband.units.status).toBe('not-loaded');
    expect(store.warband.dice.status).toBe('not-loaded');
    expect(store.warband.squads.status).toBe('not-loaded');

    await store.loadWarbandDomains(client, content());
    await store.loadWarbandDomains(client, content());

    expect(store.warband.units.status).toBe('fresh');
    expect(store.warband.dice.status).toBe('fresh');
    expect(store.warband.squads.status).toBe('fresh');
    expect(client.getUnits).toHaveBeenCalledTimes(1);
    expect(client.getDice).toHaveBeenCalledTimes(1);
    expect(client.getSquads).toHaveBeenCalledTimes(1);

    store.markWarbandDomainStale('units');
    expect(store.warband.units.status).toBe('stale');
    await store.retryWarbandDomain('units', client, content());
    expect(store.warband.units.status).toBe('fresh');
    expect(client.getUnits).toHaveBeenCalledTimes(2);
  });

  it('deduplicates concurrent requests for one loading domain', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const client = api();
    let resolve!: (value: unknown) => void;
    client.getUnits.and.returnValue(new Promise((done) => { resolve = done; }));
    const first = store.loadUnits(client, content());
    const second = store.loadUnits(client, content());
    expect(first).toBe(second);
    expect(client.getUnits).toHaveBeenCalledTimes(1);
    resolve({ ok: true, data: { units: [] } });
    await first;
    expect(store.warband.units.status).toBe('fresh');
  });

  it('keeps full unit detail lazy, deduplicates first open, reuses fresh cache, and clears it', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    expect(client.getUnitDetail).not.toHaveBeenCalled();
    let resolve!: (value: unknown) => void;
    client.getUnitDetail.and.returnValue(new Promise((done) => { resolve = done; }));
    const first = store.loadUnitDetail('11', client, registry);
    const second = store.loadUnitDetail('11', client, registry);
    expect(first).toBe(second);
    expect(store.unitDetail('11').status).toBe('loading');
    expect(client.getUnitDetail).toHaveBeenCalledTimes(1);
    resolve({ ok: true, data: { unit: {
      id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active', promotion_history: [], owned_ability_ids: ['ability.bash', 'ability.smash'], ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }], dice_bindings: [{ ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '21' }],
    } } });
    await first;
    await store.loadUnitDetail('11', client, registry);
    expect(client.getUnitDetail).toHaveBeenCalledTimes(1);
    expect(store.unitDetail('11').status).toBe('fresh');
    store.clear();
    expect(store.unitDetail('11').status).toBe('not-loaded');
  });

  it('isolates detail parser and dice-integrity failures without erasing collection caches', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    const units = store.warband.units.data;
    client.getUnitDetail.and.resolveTo({ ok: true, data: { unit: {
      id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active', promotion_history: [], owned_ability_ids: ['ability.bash'], ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }], dice_bindings: [{ ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '22' }],
    } } });
    await store.loadUnitDetail('11', client, registry);
    expect(store.unitDetail('11')).toEqual(jasmine.objectContaining({ status: 'error', error: 'integrity' }));
    expect(store.warband.units.data).toBe(units);
    expect(store.warband.dice.status).toBe('fresh');
    expect(store.warband.squads.status).toBe('fresh');
  });

  it('isolates failures and deliberately retries only the errored domain', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const client = api();
    client.getDice.and.rejectWith(new RuntimeApiError('network'));
    await store.loadWarbandDomains(client, content());
    expect(store.warband.units.status).toBe('fresh');
    expect(store.warband.dice).toEqual(jasmine.objectContaining({ status: 'error', error: 'network' }));
    expect(store.warband.squads.status).toBe('fresh');

    client.getDice.and.resolveTo({ ok: true, data: { dice: [] } });
    await store.retryWarbandDomain('dice', client, content());
    expect(store.warband.dice.status).toBe('fresh');
    expect(client.getDice).toHaveBeenCalledTimes(2);
    expect(client.getUnits).toHaveBeenCalledTimes(1);
  });

  it('fails squad disagreement as integrity and clears all lazy state with bootstrap', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap('31'));
    const client = api();
    client.getSquads.and.resolveTo({ ok: true, data: { squads: [{ id: '32', name: 'Others', is_active: true, formation: Array(9).fill(null) }] } });
    await store.loadSquads(client);
    expect(store.warband.squads).toEqual(jasmine.objectContaining({ status: 'error', error: 'integrity' }));
    expect(store.playerRevision).toBe(7);

    store.clear();
    expect(store.bootstrap).toBeNull();
    expect(store.warband.units.status).toBe('not-loaded');
    expect(store.warband.dice.status).toBe('not-loaded');
    expect(store.warband.squads.status).toBe('not-loaded');
  });

  it('reconciles authoritative squad replacement, active state, revision, and bootstrap without disturbing units or dice', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const client = api();
    client.getSquads.and.resolveTo({ ok: true, data: { squads: [
      { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] },
      { id: '32', name: 'Scouts', is_active: false, formation: Array(9).fill(null) },
    ] } });
    await store.loadWarbandDomains(client, content());
    const unitsBefore = store.warband.units;
    const diceBefore = store.warband.dice;

    store.reconcileSquadMutation({
      squad: { id: '32', name: 'Night Scouts', isActive: true, formation: ['11', null, null, null, null, null, null, null, null] },
      activeSquadId: '32', playerRevision: 8,
    }, 'activate');

    expect(store.playerRevision).toBe(8);
    expect(store.warband.squads.data?.map((squad) => [squad.id, squad.isActive])).toEqual([['31', false], ['32', true]]);
    expect(store.bootstrap?.active_squad).toEqual(jasmine.objectContaining({ id: '32', name: 'Night Scouts' }));
    expect(store.bootstrap?.active_squad?.units[0]).toEqual(jasmine.objectContaining({ id: '11', display_name: 'Grub' }));
    expect(store.warband.units).toBe(unitsBefore);
    expect(store.warband.dice).toBe(diceBefore);

    store.reconcileSquadMutation({
      squad: { id: '32', name: 'Night Scouts', isActive: true, formation: ['11', null, null, null, null, null, null, null, null] },
      activeSquadId: '32', playerRevision: 8,
    }, 'activate');
    expect(store.playerRevision).toBe(8);

    store.reconcileSquadDelete({ deletedSquadId: '31', activeSquadId: '32', playerRevision: 9 });
    expect(store.warband.squads.data?.map((squad) => squad.id)).toEqual(['32']);
    expect(store.bootstrap?.active_squad?.id).toBe('32');
  });

  it('reconciles rename into detail, roster, active-squad copy, and revision without disturbing dice or squads', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    await store.loadUnitDetail('11', client, registry);
    const current = store.unitDetail('11').data!;
    const diceBefore = store.warband.dice;
    const squadsBefore = store.warband.squads;

    store.reconcileUnitRename({ unit: Object.freeze({ ...current, displayName: 'New Grub' }), playerRevision: 8 });

    expect(store.unitDetail('11').data?.displayName).toBe('New Grub');
    expect(store.warband.units.data?.[0].displayName).toBe('New Grub');
    expect(store.bootstrap?.active_squad?.units[0].display_name).toBe('New Grub');
    expect(store.playerRevision).toBe(8);
    expect(store.warband.dice).toBe(diceBefore);
    expect(store.warband.squads).toBe(squadsBefore);
    store.reconcileUnitRename({ unit: Object.freeze({ ...store.unitDetail('11').data! }), playerRevision: 8 });
    expect(store.playerRevision).toBe(8);
  });

  it('atomically reconciles a complete loadout into exact dice summaries while preserving other domains', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    await store.loadUnitDetail('11', client, registry);
    const current = store.unitDetail('11').data!;
    const smash = registry.getAbility('ability.smash')!;
    const die22 = store.warband.dice.data!.find((die) => die.id === '22')!;
    const unitsBefore = store.warband.units;
    const squadsBefore = store.warband.squads;
    const resultUnit = Object.freeze({
      ...current,
      abilityLoadout: Object.freeze([{ ability: smash, equipOrder: 0 }]),
      diceBindings: Object.freeze([{ ability: smash, slotIndex: 0, die: die22 }]),
    });

    store.reconcileUnitLoadout({ unit: resultUnit, playerRevision: 8 });

    expect(store.playerRevision).toBe(8);
    expect(store.unitDetail('11').data?.abilityLoadout[0].ability.id).toBe('ability.smash');
    expect(store.warband.dice.data?.find((die) => die.id === '21')?.bindings).toEqual([]);
    expect(store.warband.dice.data?.find((die) => die.id === '22')?.bindings[0]).toEqual(jasmine.objectContaining({ unitId: '11', ability: smash, slotIndex: 0 }));
    expect(store.warband.dice.data?.find((die) => die.id === '23')?.bindings[0].unitId).toBe('99');
    expect(store.warband.units).toBe(unitsBefore);
    expect(store.warband.squads).toBe(squadsBefore);
  });

  it('rejects impossible unit reconciliation and marks affected caches instead of fabricating bindings', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    await store.loadUnitDetail('11', client, registry);
    const current = store.unitDetail('11').data!;
    const smash = registry.getAbility('ability.smash')!;
    const occupied = store.warband.dice.data!.find((die) => die.id === '23')!;
    const diceBefore = store.warband.dice.data;
    expect(() => store.reconcileUnitLoadout({ unit: Object.freeze({
      ...current,
      abilityLoadout: Object.freeze([{ ability: smash, equipOrder: 0 }]),
      diceBindings: Object.freeze([{ ability: smash, slotIndex: 0, die: occupied }]),
    }), playerRevision: 8 })).toThrow();
    expect(store.playerRevision).toBe(7);
    expect(store.warband.dice.data).toBe(diceBefore);
    expect(store.warband.dice.status).toBe('stale');
    expect(store.unitDetail('11')).toEqual(jasmine.objectContaining({ status: 'error', error: 'integrity' }));
  });

  it('appends first create and removes non-active or last-active squads from authoritative results', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap(null));
    const client = api();
    client.getSquads.and.resolveTo({ ok: true, data: { squads: [] } });
    await store.loadWarbandDomains(client, content());
    store.reconcileSquadMutation({
      squad: { id: '40', name: 'First', isActive: true, formation: Array(9).fill(null) },
      activeSquadId: '40', playerRevision: 8,
    }, 'create');
    expect(store.bootstrap?.active_squad?.id).toBe('40');

    store.reconcileSquadDelete({ deletedSquadId: '40', activeSquadId: null, playerRevision: 9 });
    expect(store.warband.squads.data).toEqual([]);
    expect(store.bootstrap?.active_squad).toBeNull();
    expect(store.playerRevision).toBe(9);
  });

  it('rejects revision regression and unreconcilable unit references without changing committed bootstrap', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const client = api();
    await store.loadWarbandDomains(client, content());
    const before = store.bootstrap;
    expect(() => store.reconcileSquadMutation({
      squad: { id: '31', name: 'Regressed', isActive: true, formation: Array(9).fill(null) },
      activeSquadId: '31', playerRevision: 6,
    }, 'update')).toThrow();
    expect(store.bootstrap).toBe(before);
    expect(store.warband.squads).toEqual(jasmine.objectContaining({ status: 'error', error: 'integrity' }));
  });
});
