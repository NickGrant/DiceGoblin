import { ClientContentRegistry } from './client-content-registry';
import { RunContractError, parseCurrentRunEnvelope, parseRunStartEnvelope } from './run-contracts';

describe('run contracts', () => {
  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 },
      regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
      kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
      run_node_types: {
        'run_node_type.combat': { id: 'run_node_type.combat', display_name: 'Combat', description: 'Fight.', icon_key: 'combat' },
        'run_node_type.exit': { id: 'run_node_type.exit', display_name: 'Exit', description: 'Leave.', icon_key: 'exit' },
      },
    } });
  }

  function energy() { return { current: 40, normal_max: 50, regeneration_per_hour: 12,
    regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-13T12:00:00Z',
    next_regeneration_at: '2026-09-13T12:05:00Z', fully_regenerated_at: '2026-09-13T12:50:00Z' }; }

  function startEnvelope(): any { return { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'active' },
    energy: energy(), player_revision: 8 } }; }

  function currentEnvelope(): any { return { ok: true, data: { run: { id: '7', region_id: 'region.the_farm', squad_id: '3', status: 'active',
    created_at: '2026-09-13T12:00:00Z', nodes: [
      { id: '10', node_index: 0, node_type_id: 'run_node_type.combat', status: 'completed', completed_at: '2026-09-13T12:02:00Z', position: { column: 0, row: 1 } },
      { id: '11', node_index: 1, node_type_id: 'run_node_type.exit', status: 'available', completed_at: null, position: { column: 1, row: 1 } },
    ], edges: [{ from_node_id: '10', to_node_id: '11' }], units: [{ unit_id: '5', current_hp: 12 }] }, player_revision: 8 } }; }

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
    expect(result.run?.nodes.map((node) => node.status)).toEqual(['completed', 'available']);
    expect(result.run?.regionId).toBe('region.the_farm');
  });

  it('rejects malformed graph, position, completion, unit, and authored state', () => {
    const mutations = [
      (v: any) => v.data.run.nodes[1].node_index = 3,
      (v: any) => v.data.run.nodes[1].id = '10',
      (v: any) => v.data.run.nodes[1].node_type_id = 'run_node_type.missing',
      (v: any) => v.data.run.nodes[0].completed_at = null,
      (v: any) => v.data.run.nodes[0].position.column = 1.5,
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
});
