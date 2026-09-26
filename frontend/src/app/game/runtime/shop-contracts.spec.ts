import { ClientContentRegistry } from './client-content-registry';
import { ShopContractError, parseShopCatalogEnvelope } from './shop-contracts';

describe('Shop contracts', () => {
  function content(withOffers = true): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: {
      gameplay: { run_energy_cost: 10 }, regions: {}, kin: {}, unit_types: {}, abilities: {},
      dice_materials: { 'dice_material.cardboard': { id: 'dice_material.cardboard', display_name: 'Cardboard', description: 'Card.', art_key: 'card', allowed_sizes: [4, 6, 8] } },
      dice_aspects: {}, dice_profiles: { 'dice_profile.cardboard': { id: 'dice_profile.cardboard', display_name: 'Cardboard', material_id: 'dice_material.cardboard', rarity: 'common', aspect_ids: [], allowed_sizes: [4, 6, 8] } }, run_node_types: {},
      items: { 'item.test.scrap': { id: 'item.test.scrap', display_name: 'Scrap', description: 'Scrap.', category: 'material', rarity: 'common', icon_key: 'scrap', stackable: true } },
      shop_offers: withOffers ? {
        'shop_offer.a1': { id: 'shop_offer.a1', grant: { type: 'item', item_id: 'item.test.scrap', quantity: 2 } },
        'shop_offer.a_': { id: 'shop_offer.a_', grant: { type: 'die', dice_profile_id: 'dice_profile.cardboard', size: 8 } },
      } : {},
    } });
  }
  function envelope() { return { ok: true, data: { teeth: 7, player_revision: 4, offers: [
    { offer_id: 'shop_offer.a1', price: { currency_id: 'teeth', amount: 7 }, available: true, can_afford: true },
    { offer_id: 'shop_offer.a_', price: { currency_id: 'teeth', amount: 10 }, available: true, can_afford: false },
  ] } }; }

  it('resolves authoritative item and die offers in ascii order', () => {
    expect('shop_offer.a1'.localeCompare('shop_offer.a_')).toBeGreaterThan(0);
    const parsed = parseShopCatalogEnvelope(envelope(), content());
    expect(parsed.teeth).toBe(7); expect(parsed.playerRevision).toBe(4);
    expect(parsed.offers.map((offer) => [offer.offer.id, offer.offer.grant.type, offer.canAfford])).toEqual([
      ['shop_offer.a1', 'item', true], ['shop_offer.a_', 'die', false],
    ]);
    expect(parseShopCatalogEnvelope({ ok: true, data: { teeth: 0, player_revision: 1, offers: [] } }, content(false)).offers).toEqual([]);
  });

  it('rejects malformed, expanded, duplicate, unsorted, unknown, and incoherent offers', () => {
    const base = envelope();
    const invalid: unknown[] = [
      { ...base, extra: true },
      { ok: true, data: { ...base.data, teeth: -1 } },
      { ok: true, data: { ...base.data, offers: [...base.data.offers].reverse() } },
      { ok: true, data: { ...base.data, offers: [base.data.offers[0], base.data.offers[0]] } },
      { ok: true, data: { ...base.data, offers: [{ ...base.data.offers[0], offer_id: 'shop_offer.missing' }, base.data.offers[1]] } },
      { ok: true, data: { ...base.data, offers: [{ ...base.data.offers[0], price: { currency_id: 'raw_chaos', amount: 7 } }, base.data.offers[1]] } },
      { ok: true, data: { ...base.data, offers: [{ ...base.data.offers[0], price: { currency_id: 'teeth', amount: 0 } }, base.data.offers[1]] } },
      { ok: true, data: { ...base.data, offers: [{ ...base.data.offers[0], available: 1 }, base.data.offers[1]] } },
      { ok: true, data: { ...base.data, offers: [{ ...base.data.offers[0], can_afford: false }, base.data.offers[1]] } },
      { ok: true, data: { ...base.data, offers: [base.data.offers[0]] } },
    ];
    for (const value of invalid) expect(() => parseShopCatalogEnvelope(value, content())).toThrowError(ShopContractError);
  });
});
