import { ClientContentRegistry } from './client-content-registry';
import { CurrentRun } from './run-contracts';
import { createRunMapLayout, createRunMapPresentation, runNodeColors } from './run-map-model';
import { calculateRuntimeViewport } from './runtime-viewport';

describe('run map model', () => {
  it('uses returned positions and edges for a non-Farm-shaped graph', () => {
    const presentation = createRunMapPresentation(run(), content());
    const snapshot = calculateRuntimeViewport({ cssWidth: 1600, cssHeight: 900,
      safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: false, noHover: false });
    const layout = createRunMapLayout(snapshot, presentation);

    const byId = new Map(layout.nodes.map((node) => [node.id, node]));
    expect(byId.get('91')!.centerX).toBeLessThan(byId.get('77')!.centerX);
    expect(byId.get('92')!.centerY).toBeGreaterThan(byId.get('77')!.centerY);
    expect(layout.edges.map((edge) => [edge.fromNodeId, edge.toNodeId])).toEqual([
      ['91', '77'], ['91', '92'],
    ]);
    expect(layout.nodes.map((node) => node.nodeIndex)).toEqual([0, 1, 2]);
  });

  it('resolves names and descriptions from authored content and keeps statuses distinct', () => {
    const presentation = createRunMapPresentation(run(), content());
    expect(presentation.regionName).toBe('Authored Farm');
    expect(presentation.nodes.map((node) => [node.name, node.description, node.statusLabel])).toEqual([
      ['Authored Fight', 'Authored fight description.', 'Completed'],
      ['Authored Cache', 'Authored cache description.', 'Ready'],
      ['Authored Gate', 'Authored gate description.', 'Locked'],
    ]);
    expect(new Set(Object.values(runNodeColors).map((color) => `${color.fill}:${color.border}`)).size).toBe(3);
  });

  it('fits every returned node and both controls in Compact, Standard, and Wide safe bounds', () => {
    const presentation = createRunMapPresentation(run(), content());
    for (const [width, height, coarsePointer] of [[844, 390, true], [1600, 900, false], [2560, 1080, false]] as const) {
      const snapshot = calculateRuntimeViewport({ cssWidth: width, cssHeight: height,
        safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer, noHover: coarsePointer });
      const layout = createRunMapLayout(snapshot, presentation);
      for (const node of layout.nodes) {
        expect(node.centerX - node.radius).toBeGreaterThanOrEqual(layout.map.x);
        expect(node.centerX + node.radius).toBeLessThanOrEqual(layout.map.right);
        expect(node.centerY - node.radius).toBeGreaterThanOrEqual(layout.map.y);
        expect(node.centerY + node.radius).toBeLessThanOrEqual(layout.map.bottom);
      }
      for (const control of [layout.returnButton, layout.abandonButton]) {
        expect(control.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
        expect(control.right).toBeLessThanOrEqual(snapshot.safeBounds.right);
        expect(control.bottom).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
      }
    }
  });

  it('uses the shared authored labels for the seven-node Mountains sequence', () => {
    const presentation = createRunMapPresentation(mountainsRun(), content());
    expect(presentation.regionName).toBe('Mountains');
    expect(presentation.nodes.map((node) => node.name)).toEqual([
      'Authored Fight', 'Authored Cache', 'Authored Fight', 'Rest', 'Authored Fight', 'Boss', 'Authored Gate',
    ]);
  });
});

function content(): ClientContentRegistry {
  return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
    gameplay: { run_energy_cost: 10 },
    regions: {
      'region.the_farm': { id: 'region.the_farm', display_name: 'Authored Farm', art_key: 'farm' },
      'region.mountains': { id: 'region.mountains', display_name: 'Mountains', art_key: 'mountains' },
    },
    kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
    run_node_types: {
      'run_node_type.combat': { id: 'run_node_type.combat', display_name: 'Authored Fight', description: 'Authored fight description.', icon_key: 'fight' },
      'run_node_type.loot': { id: 'run_node_type.loot', display_name: 'Authored Cache', description: 'Authored cache description.', icon_key: 'cache' },
      'run_node_type.rest': { id: 'run_node_type.rest', display_name: 'Rest', description: 'Recover.', icon_key: 'rest' },
      'run_node_type.boss': { id: 'run_node_type.boss', display_name: 'Boss', description: 'Fight.', icon_key: 'boss' },
      'run_node_type.exit': { id: 'run_node_type.exit', display_name: 'Authored Gate', description: 'Authored gate description.', icon_key: 'gate' },
    },
    items: {},
  } });
}

function run(): CurrentRun {
  return Object.freeze({ id: '41', regionId: 'region.the_farm', squadId: '31', status: 'active',
    createdAt: '2026-09-13T12:00:00Z',
    nodes: Object.freeze([
      Object.freeze({ id: '91', nodeIndex: 0, nodeTypeId: 'run_node_type.combat', status: 'completed', completedAt: '2026-09-13T12:02:00Z', battleId: '501', position: Object.freeze({ column: -4, row: 3 }) }),
      Object.freeze({ id: '77', nodeIndex: 1, nodeTypeId: 'run_node_type.loot', status: 'available', completedAt: null, battleId: null, position: Object.freeze({ column: 8, row: -2 }) }),
      Object.freeze({ id: '92', nodeIndex: 2, nodeTypeId: 'run_node_type.exit', status: 'locked', completedAt: null, battleId: null, position: Object.freeze({ column: 8, row: 9 }) }),
    ]),
    edges: Object.freeze([Object.freeze({ fromNodeId: '91', toNodeId: '77' }), Object.freeze({ fromNodeId: '91', toNodeId: '92' })]),
    units: Object.freeze([Object.freeze({ unitId: '11', currentHp: null })]),
  });
}

function mountainsRun(): CurrentRun {
  const types = ['combat', 'loot', 'combat', 'rest', 'combat', 'boss', 'exit'];
  return Object.freeze({ id: '42', regionId: 'region.mountains', squadId: '31', status: 'active',
    createdAt: '2026-09-20T12:00:00Z',
    nodes: Object.freeze(types.map((type, index) => Object.freeze({ id: String(101 + index), nodeIndex: index,
      nodeTypeId: `run_node_type.${type}`, status: index === 0 ? 'available' as const : 'locked' as const,
      completedAt: null, battleId: null, position: Object.freeze({ column: index, row: 1 }) }))),
    edges: Object.freeze(types.slice(0, -1).map((_, index) => Object.freeze({
      fromNodeId: String(101 + index), toNodeId: String(102 + index),
    }))), units: Object.freeze([Object.freeze({ unitId: '11', currentHp: 20 })]),
  });
}
