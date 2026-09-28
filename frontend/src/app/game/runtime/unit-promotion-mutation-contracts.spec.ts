import { ClientContentRegistry } from './client-content-registry';
import { RuntimeApiClient, RuntimeApiError, RuntimeFetch } from './runtime-api-client';
import { UnitPromotionMutationContractError, canonicalUnitPromotionPayload,
  parseUnitPromotionMutationEnvelope } from './unit-promotion-mutation-contracts';

describe('Unit promotion mutation contracts', () => {
  const request = { promotion_id: 'unit_promotion.bruiser.enforcer', expected_price: { currency_id: 'raw_chaos' as const, amount: 5 } };
  function content(): ClientContentRegistry {
    const stat = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
    const base = { display_name: 'Bruiser', description: 'Strong.', art_key: 'bruiser', role: 'frontline',
      base_stats: stat, growth_per_level: stat, ability_ids: ['ability.bash'] };
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: {}, kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin',
        description: 'Clever.', art_key: 'goblin', trait_summary: 'Quick.',
        stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: { 'unit_type.bruiser': { ...base, id: 'unit_type.bruiser', tier: 1 },
        'unit_type.enforcer': { ...base, id: 'unit_type.enforcer', tier: 2, ability_ids: ['ability.bash', 'ability.smash'] } },
      abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bash.', icon_key: 'bash', dice_slot_count: 1 },
        'ability.smash': { id: 'ability.smash', kind: 'active', display_name: 'Smash', description: 'Smash.', icon_key: 'smash', dice_slot_count: 1 } },
      dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {}, items: {}, shop_offers: {}, academy_upgrades: {},
      unit_promotions: { 'unit_promotion.bruiser.enforcer': { id: 'unit_promotion.bruiser.enforcer',
        from_unit_type_id: 'unit_type.bruiser', to_unit_type_id: 'unit_type.enforcer' } },
    } });
  }
  function response(grants: readonly string[] = ['ability.smash']) {
    return { ok: true, data: {
      promotion: { promotion_id: request.promotion_id, from_unit_type_id: 'unit_type.bruiser',
        to_unit_type_id: 'unit_type.enforcer', granted_ability_ids: grants },
      spend: { currency_id: 'raw_chaos', amount: 5, balance_before: 10, balance_after: 5 },
      unit: { id: '11', display_name: 'Grub', unit_type_id: 'unit_type.enforcer', kin_id: 'kin.goblin',
        level: 3, xp: 44, xp_to_next_level: 300, lifecycle_status: 'active',
        promotion_history: [{ from_unit_type_id: 'unit_type.bruiser', to_unit_type_id: 'unit_type.enforcer', promoted_at: '2026-09-28 12:00:00' }],
        owned_ability_ids: ['ability.bash', 'ability.smash'], ability_loadout: [], dice_bindings: [] },
      player_revision: 2,
    } };
  }

  it('parses nonzero and zero grants through the strict Unit Detail contract', () => {
    const registry = content();
    const result = parseUnitPromotionMutationEnvelope(response(), '11', request, registry, []);
    expect(result.grantedAbilities.map((ability) => ability.id)).toEqual(['ability.smash']);
    expect(result.unit.xpToNextLevel).toBe(300);
    expect(result.unit.promotionHistory.at(-1)?.toUnitType.id).toBe('unit_type.enforcer');
    expect(parseUnitPromotionMutationEnvelope(response([]), '11', request, registry, []).grantedAbilities).toEqual([]);
  });

  it('rejects malformed request, spend, history, target, abilities, and Unit Detail', () => {
    const registry = content();
    expect(() => canonicalUnitPromotionPayload({ ...request, extra: true })).toThrowError(UnitPromotionMutationContractError);
    expect(() => canonicalUnitPromotionPayload({ ...request, expected_price: { currency_id: 'teeth', amount: 5 } }))
      .toThrowError(UnitPromotionMutationContractError);
    const valid = response();
    const parse = (value: unknown) => parseUnitPromotionMutationEnvelope(value, '11', request, registry, []);
    for (const invalid of [
      { ...valid, data: { ...valid.data, spend: { ...valid.data.spend, balance_after: 6 } } },
      { ...valid, data: { ...valid.data, promotion: { ...valid.data.promotion, to_unit_type_id: 'unit_type.bruiser' } } },
      { ...valid, data: { ...valid.data, promotion: { ...valid.data.promotion, granted_ability_ids: ['ability.missing'] } } },
      { ...valid, data: { ...valid.data, promotion: { ...valid.data.promotion, granted_ability_ids: ['ability.smash', 'ability.smash'] } } },
      { ...valid, data: { ...valid.data, promotion: { ...valid.data.promotion, granted_ability_ids: ['ability.smash', 'ability.bash'] } } },
      { ...valid, data: { ...valid.data, unit: { ...valid.data.unit, id: '12' } } },
      { ...valid, data: { ...valid.data, unit: { ...valid.data.unit, xp: 300 } } },
      { ...valid, data: { ...valid.data, unit: { ...valid.data.unit, promotion_history: [] } } },
      { ...valid, data: { ...valid.data, unit: { ...valid.data.unit, owned_ability_ids: ['ability.bash'] } } },
    ]) expect(() => parse(invalid)).toThrowError(UnitPromotionMutationContractError);
  });

  it('sends the exact POST body, CSRF, and idempotency key through RuntimeApiClient', async () => {
    const registry = content();
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify(response()), { status: 200 }));
    const client = new RuntimeApiClient(fetchRequest, '/root');
    expect((await client.promoteUnit('11', request, 'csrf', 'promotion-key-1', registry, [])).unit.unitType.id)
      .toBe('unit_type.enforcer');
    expect(fetchRequest).toHaveBeenCalledOnceWith('/root/api/v1/units/11/promote', jasmine.objectContaining({
      method: 'POST', credentials: 'include', body: JSON.stringify(request),
      headers: jasmine.objectContaining({ 'X-CSRF-Token': 'csrf', 'Idempotency-Key': 'promotion-key-1' }),
    }));
    await expectAsync(client.promoteUnit('011', request, 'csrf', 'promotion-key-2', registry, []))
      .toBeRejectedWith(jasmine.objectContaining<RuntimeApiError>({ kind: 'malformed-response' }));
    expect(fetchRequest).toHaveBeenCalledTimes(1);
    const malformedFetch = jasmine.createSpy<RuntimeFetch>('malformedFetch').and.resolveTo(
      new Response(JSON.stringify({ ...response(), data: { ...response().data, spend: { currency_id: 'teeth' } } }), { status: 200 }));
    await expectAsync(new RuntimeApiClient(malformedFetch, '/root').promoteUnit('11', request, 'csrf', 'promotion-key-3', registry, []))
      .toBeRejectedWith(jasmine.objectContaining<RuntimeApiError>({ kind: 'malformed-response', status: 200 }));
  });
});
