import { ClientContentRegistry } from './client-content-registry';
import { InventoryContractError, parseItemCollectionEnvelope } from './inventory-contracts';

describe('inventory contracts', () => {
  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: {}, kin: {}, unit_types: {}, abilities: {},
      dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {},
      items: {
        'item.test.a1': { id: 'item.test.a1', display_name: 'A1', description: 'ASCII digit fixture.', category: 'material', rarity: 'common', icon_key: 'a1', stackable: true },
        'item.test.a_': { id: 'item.test.a_', display_name: 'A underscore', description: 'ASCII underscore fixture.', category: 'material', rarity: 'common', icon_key: 'a_underscore', stackable: true },
        'item.test.dust': { id: 'item.test.dust', display_name: 'Dust', description: 'Useful dust.', category: 'material', rarity: 'common', icon_key: 'dust', stackable: true },
        'item.test.tonic': { id: 'item.test.tonic', display_name: 'Tonic', description: 'Restores energy.', category: 'consumable', rarity: 'uncommon', icon_key: 'tonic', stackable: true, effect: { type: 'energy_restore', amount: 5 } },
      },
      shop_offers: {},
    } });
  }

  it('resolves a deterministic positive inventory against authored content', () => {
    const result = parseItemCollectionEnvelope({ ok: true, data: { items: [
      { item_id: 'item.test.dust', quantity: 3 },
      { item_id: 'item.test.tonic', quantity: 1 },
    ] } }, content());
    expect(result.map((stack) => [stack.item.id, stack.quantity])).toEqual([
      ['item.test.dust', 3], ['item.test.tonic', 1],
    ]);
    expect(parseItemCollectionEnvelope({ ok: true, data: { items: [] } }, content())).toEqual([]);
  });

  it('accepts ascii_bin order when locale collation would reverse legal item IDs', () => {
    const digitId = 'item.test.a1';
    const underscoreId = 'item.test.a_';
    expect(digitId < underscoreId).toBeTrue();
    expect(digitId.localeCompare(underscoreId)).toBeGreaterThan(0);

    const result = parseItemCollectionEnvelope({ ok: true, data: { items: [
      { item_id: digitId, quantity: 1 },
      { item_id: underscoreId, quantity: 2 },
    ] } }, content());
    expect(result.map((stack) => stack.item.id)).toEqual([digitId, underscoreId]);
    expect(() => parseItemCollectionEnvelope({ ok: true, data: { items: [
      { item_id: underscoreId, quantity: 2 },
      { item_id: digitId, quantity: 1 },
    ] } }, content())).toThrowError(InventoryContractError);
  });

  it('rejects malformed, duplicate, unsorted, non-positive, and stale stacks', () => {
    const invalid = [
      { ok: true, data: { items: [{ item_id: 'item.test.dust', quantity: 0 }] } },
      { ok: true, data: { items: [{ item_id: 'item.test.tonic', quantity: 1 }, { item_id: 'item.test.dust', quantity: 1 }] } },
      { ok: true, data: { items: [{ item_id: 'item.test.dust', quantity: 1 }, { item_id: 'item.test.dust', quantity: 2 }] } },
      { ok: true, data: { items: [{ item_id: 'item.missing', quantity: 1 }] } },
      { ok: true, data: { items: [{ item_id: 'item.test.dust', quantity: 1, private_effect: true }] } },
      { ok: true, data: { items: [] }, extra: true },
    ];
    for (const value of invalid) {
      expect(() => parseItemCollectionEnvelope(value, content())).toThrowError(InventoryContractError);
    }
  });
});
