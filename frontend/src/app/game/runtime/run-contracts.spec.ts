import { ClientContentRegistry } from './client-content-registry';
import { RunContractError, parseCurrentRunEnvelope, parseRunAbandonEnvelope, parseRunStartEnvelope } from './run-contracts';

describe('run contracts', () => {
  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 },
      regions: {
        'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' },
        'region.mountains': { id: 'region.mountains', display_name: 'Mountains', art_key: 'mountains' },
      },
      kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
      run_node_types: {
        'run_node_type.combat': { id: 'run_node_type.combat', display_name: 'Combat', description: 'Fight.', icon_key: 'combat' },
        'run_node_type.loot': { id: 'run_node_type.loot', display_name: 'Loot', description: 'Collect.', icon_key: 'loot' },
        'run_node_type.rest': { id: 'run_node_type.rest', display_name: 'Rest', description: 'Recover.', icon_key: 'rest' },
        'run_node_type.boss': { id: 'run_node_type.boss', display_name: 'Boss', description: 'Fight.', icon_key: 'boss' },
        'run_node_type.exit': { id: 'run_node_type.exit', display_name: 'Exit', description: 'Leave.', icon_key: 'exit' },
      },
      items: {},
      shop_offers: {},
    } });
  }

  function energy() { return { current: 40, normal_max: 50, regeneration_per_hour: 12,
    regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-13T12:00:00Z',
    next_regeneration_at: '2026-09-13T12:05:00Z', fully_regenerated_at: '2026-09-13T12:50:00Z' }; }

  function startEnvelope(): any { return { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'active' },
    energy: energy(), player_revision: 8 } }; }

  function currentEnvelope(): any { return { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'active',
    created_at: '2026-09-13T12:00:00Z', nodes: [
      { id: '10', node_index: 0, node_type_id: 'run_node_type.combat', status: 'completed', completed_at: '2026-09-13T12:02:00Z', battle_id: '31', position: { column: 0, row: 1 } },
      { id: '11', node_index: 1, node_type_id: 'run_node_type.loot', status: 'completed', completed_at: '2026-09-13T12:03:00Z', battle_id: null, position: { column: 1, row: 1 } },
      { id: '12', node_index: 2, node_type_id: 'run_node_type.rest', status: 'completed', completed_at: '2026-09-13T12:04:00Z', battle_id: null, position: { column: 2, row: 1 } },
      { id: '13', node_index: 3, node_type_id: 'run_node_type.boss', status: 'completed', completed_at: '2026-09-13T12:05:00Z', battle_id: '32', position: { column: 3, row: 1 } },
      { id: '14', node_index: 4, node_type_id: 'run_node_type.exit', status: 'available', completed_at: null, battle_id: null, position: { column: 4, row: 1 } },
    ], edges: [
      { from_node_id: '10', to_node_id: '11' }, { from_node_id: '11', to_node_id: '12' },
      { from_node_id: '12', to_node_id: '13' }, { from_node_id: '13', to_node_id: '14' },
    ], units: [{ unit_id: '5', current_hp: 12 }] }, player_revision: 8 } }; }

  it('strictly parses authoritative start state without private topology', () => {
    const result = parseRunStartEnvelope(startEnvelope(), content());
    expect(result.run).toEqual({ id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'active' });
    expect(result.energy.current).toBe(40);
    for (const mutate of [
      (value: any) => value.data.run.nodes = [],
      (value: any) => value.data.run.region_id = 'region.missing',
      (value: any) => value.data.energy.current = -1,
      (value: any) => value.data.extra = true,
    ]) { const value = startEnvelope(); mutate(value); expect(() => parseRunStartEnvelope(value, content())).toThrowError(RunContractError); }
  });

  it('accepts legitimate progressed node state and resolves authored references', () => {
    const result = parseCurrentRunEnvelope(currentEnvelope(), content());
    expect(result.run?.nodes.map((node) => node.status)).toEqual([
      'completed', 'completed', 'completed', 'completed', 'available',
    ]);
    expect(result.run?.nodes.map((node) => node.battleId)).toEqual(['31', null, null, '32', null]);
    expect(result.run?.nodes[3].battleId).toBe('32');
    expect(result.run?.regionId).toBe('region.the_farm');
  });

  it('accepts the authored seven-node Mountains graph without region-specific parsing', () => {
    const value: any = currentEnvelope();
    value.data.run.region_id = 'region.mountains';
    const types = ['combat', 'loot', 'combat', 'rest', 'combat', 'boss', 'exit'];
    value.data.run.nodes = types.map((type, index) => ({
      id: String(20 + index), node_index: index, node_type_id: `run_node_type.${type}`,
      status: index === 0 ? 'available' : 'locked', completed_at: null, battle_id: null,
      position: { column: index, row: 1 },
    }));
    value.data.run.edges = types.slice(0, -1).map((_, index) => ({
      from_node_id: String(20 + index), to_node_id: String(21 + index),
    }));

    const parsed = parseCurrentRunEnvelope(value, content());

    expect(parsed.run?.regionId).toBe('region.mountains');
    expect(parsed.run?.nodes.map((node) => node.nodeTypeId)).toEqual(types.map((type) => `run_node_type.${type}`));
    expect(parsed.run?.edges.length).toBe(6);
  });

  it('rejects every invalid battle-bearing and non-battle-bearing node correspondence', () => {
    const mutations = [
      (v: any) => v.data.run.nodes[0].battle_id = null,
      (v: any) => { v.data.run.nodes[0].status = 'available'; v.data.run.nodes[0].completed_at = null; },
      (v: any) => v.data.run.nodes[3].battle_id = null,
      (v: any) => { v.data.run.nodes[3].status = 'available'; v.data.run.nodes[3].completed_at = null; },
      (v: any) => v.data.run.nodes[1].battle_id = '33',
      (v: any) => v.data.run.nodes[2].battle_id = '33',
      (v: any) => v.data.run.nodes[4].battle_id = '33',
    ];
    for (const mutate of mutations) {
      const value = currentEnvelope(); mutate(value);
      expect(() => parseCurrentRunEnvelope(value, content())).toThrowError(RunContractError);
    }
  });

  it('rejects malformed graph, position, completion, unit, and authored state', () => {
    const mutations = [
      (v: any) => v.data.run.nodes[1].node_index = 3,
      (v: any) => v.data.run.nodes[1].id = '10',
      (v: any) => v.data.run.nodes[1].node_type_id = 'run_node_type.missing',
      (v: any) => v.data.run.nodes[0].completed_at = null,
      (v: any) => v.data.run.nodes[0].position.column = 1.5,
      (v: any) => v.data.run.nodes[0].battle_id = '031',
      (v: any) => v.data.run.edges = [],
      (v: any) => v.data.run.edges[0].to_node_id = '10',
      (v: any) => v.data.run.units.push({ unit_id: '5', current_hp: null }),
      (v: any) => v.data.run.units[0].current_hp = -1,
    ];
    for (const mutate of mutations) { const value = currentEnvelope(); mutate(value); expect(() => parseCurrentRunEnvelope(value, content())).toThrowError(RunContractError); }
  });

  it('strictly accepts the no-current-run response', () => {
    expect(parseCurrentRunEnvelope({ ok: true, data: { run: null, player_revision: 9 } }, content()))
      .toEqual({ run: null, playerRevision: 9 });
  });

  it('strictly parses authoritative abandon without accepting Energy or extra state', () => {
    const valid: any = { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3',
      status: 'abandoned', ended_at: '2026-09-13T12:04:00Z' }, active_run: null, player_revision: 9 } };
    expect(parseRunAbandonEnvelope(valid, content())).toEqual({ run: { id: '7', regionId: 'region.the_farm',
      squadId: '3', status: 'abandoned', endedAt: '2026-09-13T12:04:00Z' }, activeRun: null, playerRevision: 9 });
    for (const mutate of [
      (value: any) => value.data.energy = energy(),
      (value: any) => value.data.run.extra = true,
      (value: any) => value.data.run.status = 'active',
      (value: any) => value.data.run.ended_at = 'not-a-time',
      (value: any) => value.data.active_run = {},
      (value: any) => value.data.run.region_id = 'region.missing',
    ]) {
      const candidate = structuredClone(valid); mutate(candidate);
      expect(() => parseRunAbandonEnvelope(candidate, content())).toThrowError(RunContractError);
    }
  });
});
