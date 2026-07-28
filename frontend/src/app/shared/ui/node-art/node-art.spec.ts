import { CurrentRunNode } from '../../../core/models/api.models';
import { resolveNodeArtUrl, resolveNodeArtVariant, resolveNodeQualityTier } from './node-art';

describe('node-art helpers', () => {
  it('resolves quality tiers from node metadata', () => {
    const node = {
      id: '7',
      run_id: 'run-1',
      node_index: 2,
      node_type: 'loot',
      status: 'available',
      meta: { node_quality_tier: 'great' },
    } as CurrentRunNode;

    expect(resolveNodeQualityTier(node)).toBe('great');
    expect(resolveNodeArtUrl(node, 'loot')).toBe('/assets/ui/node-art/loot/great_a.png');
  });

  it('uses persisted node id parity for stable A/B variants', () => {
    expect(resolveNodeArtVariant({ id: '8', node_index: 1, node_type: 'loot', status: 'locked', run_id: 'run-1' } as CurrentRunNode)).toBe('b');
    expect(resolveNodeArtVariant({ id: '9', node_index: 1, node_type: 'loot', status: 'locked', run_id: 'run-1' } as CurrentRunNode)).toBe('a');
  });

  it('falls back to good art for older nodes without quality metadata', () => {
    const node = {
      id: '12',
      run_id: 'run-1',
      node_index: 3,
      node_type: 'shrine',
      status: 'locked',
    } as CurrentRunNode;

    expect(resolveNodeQualityTier(node)).toBe('good');
    expect(resolveNodeArtUrl(node, 'shrine')).toBe('/assets/ui/node-art/shrines/good_b.png');
  });
});
