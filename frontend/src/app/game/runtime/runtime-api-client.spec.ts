import { RuntimeApiClient, RuntimeApiError, RuntimeFetch } from './runtime-api-client';
import { ClientContentRegistry } from './client-content-registry';
import { parseDiceCollectionEnvelope } from './warband-contracts';

describe('RuntimeApiClient', () => {
  const originalConfig = window.__DICE_GOBLIN_CONFIG__;

  afterEach(() => {
    window.__DICE_GOBLIN_CONFIG__ = originalConfig;
  });

  it('honors configured API base URL and includes browser session credentials', async () => {
    window.__DICE_GOBLIN_CONFIG__ = { apiBaseUrl: 'https://api.example.test/root/' };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify({ ok: true, data: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    const client = new RuntimeApiClient(fetchRequest);

    await client.getBootstrap();

    expect(client.baseUrl).toBe('https://api.example.test/root');
    expect(fetchRequest).toHaveBeenCalledOnceWith(
      'https://api.example.test/root/api/v1/game/bootstrap',
      jasmine.objectContaining({ method: 'GET', credentials: 'include' }),
    );
  });

  it('reports unauthorized responses without exposing their body', async () => {
    const fetchRequest = jasmine
      .createSpy<RuntimeFetch>('fetchRequest')
      .and.resolveTo(new Response('sensitive backend detail', { status: 401 }));

    await expectAsync(new RuntimeApiClient(fetchRequest, '').getBootstrap()).toBeRejectedWith(
      jasmine.objectContaining<RuntimeApiError>({ kind: 'unauthorized', status: 401 }),
    );
  });

  it('calls each lazy Warband query directly with the browser session', async () => {
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.callFake(async () =>
      new Response(JSON.stringify({ ok: true, data: {} }), { status: 200 }),
    );
    const client = new RuntimeApiClient(fetchRequest, '/root');

    await client.getUnits();
    await client.getDice();
    await client.getSquads();
    await client.getUnitDetail('11/unsafe');

    expect(fetchRequest.calls.allArgs().map(([url]) => url)).toEqual([
      '/root/api/v1/units', '/root/api/v1/dice', '/root/api/v1/squads', '/root/api/v1/units/11%2Funsafe',
    ]);
    for (const [, init] of fetchRequest.calls.allArgs()) {
      expect(init).toEqual(jasmine.objectContaining({ method: 'GET', credentials: 'include', headers: { Accept: 'application/json' } }));
    }
  });

  it('gets and strictly parses retained battle playback without CSRF', async () => {
    const payload = { ok: true, data: { battle: { id: '81', run_id: '71', run_node_id: '72', engine_version: 1,
      playback_version: 1, outcome: 'victory', ending_round: 1, ending_tick: 1, participants: [
        { combatant_key: 'p', side: 'player', unit_id: '11', unit_type_id: 'unit_type.bruiser', enemy_unit_type_id: null,
          display_name: 'Bash', art_key: 'unit.bruiser', position: { x: 1, y: 1 }, initial_hp: 5, max_hp: 5,
          terminal_hp: 5, is_defeated: false, terminal_statuses: [] },
        { combatant_key: 'e', side: 'enemy', unit_id: null, unit_type_id: null, enemy_unit_type_id: 'enemy_unit_type.mudwrestler',
          display_name: 'Mudwrestler', art_key: 'enemy.mudwrestler', position: { x: 2, y: 1 }, initial_hp: 1, max_hp: 1,
          terminal_hp: 0, is_defeated: true, terminal_statuses: [] },
      ], events: [
        { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['e', 'p'] } },
        { sequence: 1, type: 'battle_ended', round: 1, tick: 1, facts: { outcome: 'victory' } },
      ] }, player_revision: 4 } };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify(payload), { status: 200 }),
    );
    const result = await new RuntimeApiClient(fetchRequest, '/root').getBattlePlayback('81');
    expect(result.battle.outcome).toBe('victory');
    expect(fetchRequest).toHaveBeenCalledOnceWith('/root/api/v1/battles/81/playback', jasmine.objectContaining({
      method: 'GET', credentials: 'include', headers: { Accept: 'application/json' },
    }));
    expect((fetchRequest.calls.mostRecent().args[1]?.headers as Record<string, string>)['X-CSRF-Token']).toBeUndefined();
    await expectAsync(new RuntimeApiClient(fetchRequest, '').getBattlePlayback('01')).toBeRejectedWith(
      jasmine.objectContaining({ kind: 'malformed-response' }),
    );
  });

  it('resolves a run node with the exact authenticated bodyless mutation contract', async () => {
    const payload = { ok: true, data: {
      resolution_type: 'combat',
      battle: { id: '81', outcome: 'victory', engine_version: 1, playback_version: 1, ending_round: 3, ending_tick: 41 },
      node: { id: '10', status: 'completed', completed_at: '2026-09-16T12:00:00Z' },
      newly_available_node_ids: ['11'], terminal_player_hp: { '21': 12 },
      run: { id: '41', status: 'active', ended_at: null }, player_revision: 8,
    } };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify(payload), { status: 200 }),
    );

    const result = await new RuntimeApiClient(fetchRequest, '/root').resolveRunNode(
      '41', '10', 'csrf-token', 'combat-node:fixed-attempt',
    );

    expect(result.resolutionType).toBe('combat');
    if (result.resolutionType !== 'combat') throw new Error('Expected combat result.');
    expect(result.battle.id).toBe('81');
    expect(fetchRequest).toHaveBeenCalledOnceWith('/root/api/v1/runs/41/nodes/10/resolve', {
      method: 'POST', credentials: 'include', headers: {
        Accept: 'application/json', 'X-CSRF-Token': 'csrf-token', 'Idempotency-Key': 'combat-node:fixed-attempt',
      },
    });
    expect(fetchRequest.calls.mostRecent().args[1]?.body).toBeUndefined();
    await expectAsync(new RuntimeApiClient(fetchRequest, '').resolveRunNode('041', '10', 'csrf', 'attempt'))
      .toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response' }));
  });

  it('sends independent unit rename and complete loadout commands with strict authoritative parsing', async () => {
    const stat = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
    const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {}, kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Goblin.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Bruiser.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.bash'] } },
      abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bash.', icon_key: 'bash', dice_slot_count: 1 } },
      dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } }, dice_aspects: {},
      dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Bone Die', material_id: 'dice_material.bone', rarity: 'common', aspect_ids: [], allowed_sizes: [6] } },
      run_node_types: {},
    } });
    const dice = parseDiceCollectionEnvelope({ ok: true, data: { dice: [
      { id: '21', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.bash', slot_index: 0 }] },
    ] } }, content);
    const unit = { id: '11', display_name: 'New Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active', promotion_history: [], owned_ability_ids: ['ability.bash'], ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }], dice_bindings: [{ ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '21' }] };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.callFake(async () => new Response(JSON.stringify({ ok: true, data: { unit, player_revision: 8 } }), { status: 200 }));
    const client = new RuntimeApiClient(fetchRequest, '/root');
    const loadout = { abilities: [{ ability_id: 'ability.bash', dice_instance_ids: ['21'] }] };

    expect((await client.renameUnit('11', 'New Grub', 'csrf', content, dice)).playerRevision).toBe(8);
    expect((await client.replaceUnitLoadout('11', loadout, 'csrf', content, dice)).unit.displayName).toBe('New Grub');

    const calls = fetchRequest.calls.allArgs();
    expect(calls.map(([url]) => url)).toEqual(['/root/api/v1/units/11/name', '/root/api/v1/units/11/loadout']);
    expect(calls.map(([, init]) => init?.method)).toEqual(['PATCH', 'PUT']);
    expect(calls[0][1]).toEqual(jasmine.objectContaining({ credentials: 'include', body: JSON.stringify({ name: 'New Grub' }) }));
    expect(calls[0][1]?.headers).toEqual(jasmine.objectContaining({ 'X-CSRF-Token': 'csrf', 'Content-Type': 'application/json' }));
    expect(calls[1][1]?.body).toBe(JSON.stringify(loadout));
  });

  it('preserves network, HTTP, and malformed-response distinctions for lazy queries', async () => {
    const network = jasmine.createSpy<RuntimeFetch>('network').and.rejectWith(new Error('offline'));
    await expectAsync(new RuntimeApiClient(network, '').getUnits()).toBeRejectedWith(jasmine.objectContaining({ kind: 'network' }));
    const http = jasmine.createSpy<RuntimeFetch>('http').and.resolveTo(new Response('', { status: 503 }));
    await expectAsync(new RuntimeApiClient(http, '').getDice()).toBeRejectedWith(jasmine.objectContaining({ kind: 'http', status: 503 }));
    const malformed = jasmine.createSpy<RuntimeFetch>('malformed').and.resolveTo(new Response('not json', { status: 200 }));
    await expectAsync(new RuntimeApiClient(malformed, '').getSquads()).toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response' }));
  });

  it('sends direct squad commands with credentials, CSRF, complete JSON, and create-only idempotency', async () => {
    const success = { ok: true, data: {
      squad: { id: '31', name: 'Raiders', is_active: true, formation: Array(9).fill(null) },
      active_squad_id: '31', player_revision: 8,
    } };
    const deleted = { ok: true, data: { deleted_squad_id: '31', active_squad_id: null, player_revision: 9 } };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.callFake(async (_url, init) =>
      new Response(JSON.stringify(init?.method === 'DELETE' ? deleted : success), { status: 200 }),
    );
    const client = new RuntimeApiClient(fetchRequest, '/root');
    const payload = { name: 'Raiders', formation: Array<string | null>(9).fill(null) };

    await client.createSquad(payload, 'csrf-token', 'squad:create:12345678');
    await client.updateSquad('31', payload, 'csrf-token');
    await client.activateSquad('31', 'csrf-token');
    await client.deleteSquad('31', 'csrf-token');

    const calls = fetchRequest.calls.allArgs();
    expect(calls.map(([url]) => url)).toEqual([
      '/root/api/v1/squads', '/root/api/v1/squads/31', '/root/api/v1/squads/31/activate', '/root/api/v1/squads/31',
    ]);
    for (const [, init] of calls) expect(init?.credentials).toBe('include');
    expect(calls[0][1]?.headers).toEqual(jasmine.objectContaining({
      'X-CSRF-Token': 'csrf-token', 'Idempotency-Key': 'squad:create:12345678', 'Content-Type': 'application/json',
    }));
    expect(calls[0][1]?.body).toBe(JSON.stringify(payload));
    expect(calls[1][1]?.headers).not.toEqual(jasmine.objectContaining({ 'Idempotency-Key': jasmine.anything() }));
    expect(calls[2][1]?.body).toBeUndefined();
    expect(calls[3][1]?.body).toBeUndefined();
  });

  it('sends strict run start and current requests with the required security boundaries', async () => {
    const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
      kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {},
    } });
    const energy = { current: 40, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300,
      last_regeneration_at: '2026-09-13T12:00:00Z', next_regeneration_at: null, fully_regenerated_at: null };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.callFake(async (url, init) =>
      new Response(JSON.stringify(String(url).endsWith('/abandon')
        ? { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'abandoned', ended_at: '2026-09-13T12:02:00Z' }, active_run: null, player_revision: 9 } }
        : init?.method === 'POST'
          ? { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'active' }, energy, player_revision: 8 } }
          : { ok: true, data: { run: null, player_revision: 8 } }), { status: 200 }));
    const client = new RuntimeApiClient(fetchRequest, '/root');

    await client.startRun('region.the_farm', 'csrf-token', 'run:start:12345678', content);
    await client.getCurrentRun(content);
    await client.abandonRun('7', 'csrf-token', content);

    const [start, current, abandon] = fetchRequest.calls.allArgs();
    expect(start[0]).toBe('/root/api/v1/runs');
    expect(start[1]).toEqual(jasmine.objectContaining({ method: 'POST', credentials: 'include', body: JSON.stringify({ region_id: 'region.the_farm' }) }));
    expect(start[1]?.headers).toEqual(jasmine.objectContaining({ 'X-CSRF-Token': 'csrf-token', 'Idempotency-Key': 'run:start:12345678' }));
    expect(current[0]).toBe('/root/api/v1/runs/current');
    expect(current[1]).toEqual(jasmine.objectContaining({ method: 'GET', credentials: 'include', headers: { Accept: 'application/json' } }));
    expect(abandon[0]).toBe('/root/api/v1/runs/7/abandon');
    expect(abandon[1]).toEqual(jasmine.objectContaining({ method: 'POST', credentials: 'include' }));
    expect(abandon[1]?.body).toBeUndefined();
    expect(abandon[1]?.headers).toEqual({ Accept: 'application/json', 'X-CSRF-Token': 'csrf-token' });
  });

  it('parses a raw current-run response with completed Combat and Boss battles at the API boundary', async () => {
    const node = (id: string) => ({ id, display_name: id, description: id, icon_key: id });
    const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: {
        'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' },
      }, kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
      run_node_types: {
        'run_node_type.combat': node('run_node_type.combat'), 'run_node_type.loot': node('run_node_type.loot'),
        'run_node_type.rest': node('run_node_type.rest'), 'run_node_type.boss': node('run_node_type.boss'),
        'run_node_type.exit': node('run_node_type.exit'),
      },
    } });
    const raw = { ok: true, data: { run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active',
      created_at: '2026-09-19T12:00:00Z', nodes: [
        { id: '10', node_index: 0, node_type_id: 'run_node_type.combat', status: 'completed', completed_at: '2026-09-19T12:01:00Z', battle_id: '81', position: { column: 0, row: 1 } },
        { id: '11', node_index: 1, node_type_id: 'run_node_type.loot', status: 'completed', completed_at: '2026-09-19T12:02:00Z', battle_id: null, position: { column: 1, row: 1 } },
        { id: '12', node_index: 2, node_type_id: 'run_node_type.rest', status: 'completed', completed_at: '2026-09-19T12:03:00Z', battle_id: null, position: { column: 2, row: 1 } },
        { id: '13', node_index: 3, node_type_id: 'run_node_type.boss', status: 'completed', completed_at: '2026-09-19T12:04:00Z', battle_id: '82', position: { column: 3, row: 1 } },
        { id: '14', node_index: 4, node_type_id: 'run_node_type.exit', status: 'available', completed_at: null, battle_id: null, position: { column: 4, row: 1 } },
      ], edges: [
        { from_node_id: '10', to_node_id: '11' }, { from_node_id: '11', to_node_id: '12' },
        { from_node_id: '12', to_node_id: '13' }, { from_node_id: '13', to_node_id: '14' },
      ], units: [{ unit_id: '21', current_hp: 7 }] }, player_revision: 9 } };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify(raw), { status: 200 }),
    );

    const result = await new RuntimeApiClient(fetchRequest, '/root').getCurrentRun(content);

    expect(result.run?.nodes[3]).toEqual(jasmine.objectContaining({
      nodeTypeId: 'run_node_type.boss', status: 'completed', battleId: '82',
    }));
    expect(result.run?.nodes[4]).toEqual(jasmine.objectContaining({
      nodeTypeId: 'run_node_type.exit', status: 'available', battleId: null,
    }));
  });

  it('rejects a non-canonical abandon run ID before sending a request', async () => {
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest');
    const client = new RuntimeApiClient(fetchRequest, '');
    await expectAsync(client.abandonRun('01', 'csrf', {} as ClientContentRegistry))
      .toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response' }));
    expect(fetchRequest).not.toHaveBeenCalled();
  });

  it('preserves malformed and HTTP 5xx run-start outcomes for ambiguous retry handling', async () => {
    const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
      kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {},
    } });
    const malformed = jasmine.createSpy<RuntimeFetch>('malformed').and.resolveTo(
      new Response(JSON.stringify({ ok: true, data: {} }), { status: 200 }),
    );
    await expectAsync(new RuntimeApiClient(malformed, '').startRun(
      'region.the_farm', 'csrf', 'run:start:12345678', content,
    )).toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response', status: 200 }));

    const unavailable = jasmine.createSpy<RuntimeFetch>('unavailable').and.resolveTo(new Response(JSON.stringify({
      ok: false, error: { code: 'server_error' },
    }), { status: 503 }));
    await expectAsync(new RuntimeApiClient(unavailable, '').startRun(
      'region.the_farm', 'csrf', 'run:start:12345678', content,
    )).toBeRejectedWith(jasmine.objectContaining({ kind: 'http', status: 503, code: 'server_error' }));
  });

  it('turns malformed mutation success into an integrity-safe API failure and retains safe error codes', async () => {
    const malformed = jasmine.createSpy<RuntimeFetch>('malformed').and.resolveTo(new Response(JSON.stringify({ ok: true, data: {} }), { status: 200 }));
    await expectAsync(new RuntimeApiClient(malformed, '').activateSquad('31', 'csrf')).toBeRejectedWith(
      jasmine.objectContaining({ kind: 'malformed-response' }),
    );
    const conflict = jasmine.createSpy<RuntimeFetch>('conflict').and.resolveTo(new Response(JSON.stringify({
      ok: false, error: { code: 'active_squad_delete_forbidden', message: 'server detail' },
    }), { status: 409 }));
    await expectAsync(new RuntimeApiClient(conflict, '').deleteSquad('31', 'csrf')).toBeRejectedWith(
      jasmine.objectContaining({ kind: 'http', status: 409, code: 'active_squad_delete_forbidden' }),
    );
  });
});
