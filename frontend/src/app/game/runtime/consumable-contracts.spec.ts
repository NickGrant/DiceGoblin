import { ClientContentRegistry } from './client-content-registry';
import { ConsumableContractError, parseEnergyRestoreEnvelope, parseRunUnitHealEnvelope } from './consumable-contracts';

describe('Consumable contracts', () => {
  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: {}, kin: {}, unit_types: {}, abilities: {},
      dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {}, academy_upgrades: {}, shop_offers: {},
      items: {
        'item.test.spark': { id: 'item.test.spark', display_name: 'Spark', description: 'Energy.', category: 'consumable', rarity: 'common', icon_key: 'spark', stackable: true, effect: { type: 'energy_restore', amount: 7 } },
        'item.test.heal': { id: 'item.test.heal', display_name: 'Poultice', description: 'Healing.', category: 'consumable', rarity: 'common', icon_key: 'heal', stackable: true, effect: { type: 'unit_heal', amount: 9 } },
      },
    } });
  }
  const energy = { current: 14, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300,
    last_regeneration_at: '2026-09-26T12:10:00Z', next_regeneration_at: '2026-09-26T12:15:00Z', fully_regenerated_at: '2026-09-26T15:25:00Z' };

  it('strictly parses an Energy restore bound to the submitted item', () => {
    const parsed = parseEnergyRestoreEnvelope({ ok: true, data: {
      item_id: 'item.test.spark', quantity_consumed: 1, owned_quantity_after: 2, energy, player_revision: 8,
    } }, { item_id: 'item.test.spark' }, content());
    expect(parsed).toEqual({ itemId: 'item.test.spark', quantityConsumed: 1, ownedQuantityAfter: 2,
      energy: { current: 14, normalMax: 50, regenerationPerHour: 12, regenerationIntervalSeconds: 300,
        lastRegenerationAt: '2026-09-26T12:10:00Z', nextRegenerationAt: '2026-09-26T12:15:00Z', fullyRegeneratedAt: '2026-09-26T15:25:00Z' },
      playerRevision: 8 });
  });

  it('rejects malformed, unsafe, wrong-item, and incoherent Energy restore results', () => {
    const base = { ok: true, data: { item_id: 'item.test.spark', quantity_consumed: 1,
      owned_quantity_after: 2, energy, player_revision: 8 } };
    const invalid = [
      { ...base, extra: true },
      { ok: true, data: { ...base.data, item_id: 'item.test.heal' } },
      { ok: true, data: { ...base.data, quantity_consumed: 2 } },
      { ok: true, data: { ...base.data, owned_quantity_after: Number.MAX_SAFE_INTEGER + 1 } },
      { ok: true, data: { ...base.data, energy: { ...energy, regeneration_interval_seconds: 301 } } },
      { ok: true, data: { ...base.data, energy: { ...energy, current: 50 } } },
      { ok: true, data: { ...base.data, energy: { ...energy, next_regeneration_at: 'not-time' } } },
      { ok: true, data: { ...base.data, extra: true } },
    ];
    for (const value of invalid) expect(() => parseEnergyRestoreEnvelope(value, { item_id: 'item.test.spark' }, content())).toThrowError(ConsumableContractError);
  });

  it('strictly parses healing bound to the submitted run, unit, and item', () => {
    const parsed = parseRunUnitHealEnvelope({ ok: true, data: {
      item_id: 'item.test.heal', quantity_consumed: 1, owned_quantity_after: 0, run_id: '41',
      unit: { unit_id: '21', hp_before: 0, hp_after: 9, max_hp: 22 }, player_revision: 9,
    } }, '41', '21', { item_id: 'item.test.heal' }, content());
    expect(parsed.unit).toEqual({ unitId: '21', hpBefore: 0, hpAfter: 9, maxHp: 22 });
  });

  it('rejects expanded, unsafe, non-healing, and wrong-identity healing receipts', () => {
    const base = { ok: true, data: { item_id: 'item.test.heal', quantity_consumed: 1, owned_quantity_after: 0,
      run_id: '41', unit: { unit_id: '21', hp_before: 1, hp_after: 10, max_hp: 22 }, player_revision: 9 } };
    const invalid = [
      { ok: true, data: { ...base.data, run_id: '42' } },
      { ok: true, data: { ...base.data, unit: { ...base.data.unit, unit_id: '22' } } },
      { ok: true, data: { ...base.data, item_id: 'item.test.spark' } },
      { ok: true, data: { ...base.data, unit: { ...base.data.unit, hp_after: 23 } } },
      { ok: true, data: { ...base.data, unit: { ...base.data.unit, hp_after: 1 } } },
      { ok: true, data: { ...base.data, player_revision: Number.MAX_SAFE_INTEGER + 1 } },
      { ok: true, data: { ...base.data, unit: { ...base.data.unit, extra: true } } },
    ];
    for (const value of invalid) expect(() => parseRunUnitHealEnvelope(value, '41', '21', { item_id: 'item.test.heal' }, content())).toThrowError(ConsumableContractError);
    expect(() => parseRunUnitHealEnvelope(base, '041', '21', { item_id: 'item.test.heal' }, content())).toThrowError(ConsumableContractError);
  });
});
