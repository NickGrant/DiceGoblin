import { ClientContentRegistry } from './client-content-registry';
import { RuntimeApiClient } from './runtime-api-client';
import { canonicalReconstructionPayload, parseReconstructionEnvelope, parseWrongMachineCatalogEnvelope,
  reconstructionPayload, WrongMachineContractError } from './wrong-machine-contracts';

describe('Wrong Machine browser contract', () => {
  const stats = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
  const item = (id: string) => ({ id, display_name: id, description: 'Material.', category: 'material' as const,
    rarity: 'common' as const, icon_key: id, stackable: true });
  const kin = (id: string) => ({ id, display_name: id, description: 'Kin.', art_key: id,
    trait_summary: 'Trait.', stat_modifiers: stats });
  const type = (id: string) => ({ id, display_name: id, description: 'Type.', art_key: id,
    role: 'frontline' as const, tier: 1, base_stats: stats, growth_per_level: stats, ability_ids: ['ability.bash'] });
  const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: {}, kin: { 'kin.pig': kin('kin.pig'), 'kin.lizard_kin': kin('kin.lizard_kin') },
    unit_types: { 'unit_type.bruiser': type('unit_type.bruiser'), 'unit_type.guardian': type('unit_type.guardian') },
    abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash',
      description: 'Hit.', icon_key: 'bash', dice_slot_count: 1 } },
    dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {},
    items: { 'item.pig_ear': item('item.pig_ear'), 'item.mudking_crown_fragment': item('item.mudking_crown_fragment'),
      'item.kobold_scale': item('item.kobold_scale'), 'item.chief_engineer_lens': item('item.chief_engineer_lens') },
    unit_promotions: {}, academy_upgrades: {}, shop_offers: {} } });
  const recipe = (id: string, kinId: string, items: string[], restored = false, owned = 5) => ({
    recipe_id: id, display_name: kinId, description: 'Reconstruct Kin.', kin_id: kinId, kin_restored: restored,
    mode: restored ? 'repeat_reconstruction' : 'first_restoration',
    unit_type_selection: restored ? 'chosen_unlocked' : 'random_unlocked',
    eligible_unit_type_ids: ['unit_type.bruiser', 'unit_type.guardian'],
    prerequisites: [{ unlock_id: 'unlock.capability.wrong_machine_access', owned: true }], prerequisites_met: true,
    price: { currency_id: 'raw_chaos', amount: 5 },
    ingredients: items.map((item_id) => ({ item_id, quantity: 1, owned })), reconstructable: owned >= 1,
  });
  const lizard = recipe('reconstruction_recipe.reconstruct_lizard_kin', 'kin.lizard_kin',
    ['item.kobold_scale', 'item.chief_engineer_lens']);
  const pig = recipe('reconstruction_recipe.reconstruct_pig_kin', 'kin.pig',
    ['item.pig_ear', 'item.mudking_crown_fragment']);
  const read = (rows: unknown[]) => ({ ok: true, data: { raw_chaos: 10, player_revision: 3, recipes: rows } });

  it('projects both authored families through one server-authorized read and builds first request without a choice', () => {
    const catalog = parseWrongMachineCatalogEnvelope(read([lizard, pig]), content);
    expect(catalog.recipes.map((row) => row.kin.id)).toEqual(['kin.lizard_kin', 'kin.pig']);
    const request = reconstructionPayload(catalog.recipes[0], null);
    expect(request.unit_type_id).toBeUndefined();
    expect(request.expected_ingredients.map((row) => row.item_id)).toEqual(['item.chief_engineer_lens', 'item.kobold_scale']);
    expect(request.expected_price.amount).toBe(5);
  });

  it('requires explicit eligible choice for repeat and rejects malformed or invented authority', () => {
    const catalog = parseWrongMachineCatalogEnvelope(read([lizard, { ...pig, kin_restored: true,
      mode: 'repeat_reconstruction', unit_type_selection: 'chosen_unlocked' }]), content);
    expect(() => reconstructionPayload(catalog.recipes[1], null)).toThrowError(WrongMachineContractError);
    expect(() => reconstructionPayload(catalog.recipes[1], 'unit_type.fake')).toThrowError(WrongMachineContractError);
    expect(reconstructionPayload(catalog.recipes[1], 'unit_type.bruiser').unit_type_id).toBe('unit_type.bruiser');
    for (const bad of [[lizard, { ...pig, reconstructable: false }], [lizard, { ...pig, kin_id: 'kin.fake' }],
      [pig, lizard], [lizard, lizard]])
      expect(() => parseWrongMachineCatalogEnvelope(read(bad), content)).toThrowError(WrongMachineContractError);
    expect(() => canonicalReconstructionPayload({ recipe_id: pig.recipe_id, expected_mode: 'first_restoration',
      expected_price: { currency_id: 'raw_chaos', amount: 5 }, expected_ingredients: [], unit_type_id: 'unit_type.bruiser' }))
      .toThrowError(WrongMachineContractError);
  });

  it('accepts an exact finalized receipt and rejects altered spend or resulting type', () => {
    const request = reconstructionPayload(parseWrongMachineCatalogEnvelope(read([lizard, pig]), content).recipes[0], null);
    const data = { recipe_id: request.recipe_id, mode: request.expected_mode,
      unit: { id: '109', display_name: 'Scale', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.lizard_kin',
        level: 1, xp: 0, lifecycle_status: 'active' },
      spend: { currency_id: 'raw_chaos', amount: 5, balance_before: 10, balance_after: 5 },
      consumed_items: request.expected_ingredients.map((row) => ({ ...row, owned_after: 4 })),
      kin_restoration: { kin_id: 'kin.lizard_kin', unlock_id: 'unlock.kin.lizard_kin', outcome: 'granted' }, player_revision: 4 };
    expect(parseReconstructionEnvelope({ ok: true, data }, request, content).unit.id).toBe('109');
    expect(() => parseReconstructionEnvelope({ ok: true, data: { ...data, spend: { ...data.spend, balance_after: 6 } } },
      request, content)).toThrowError(WrongMachineContractError);
  });

  it('uses shared authenticated read and CSRF/idempotent mutation transport', async () => {
    const fetcher = jasmine.createSpy('fetcher').and.resolveTo(new Response(JSON.stringify(read([lizard, pig])), { status: 200 }));
    const client = new RuntimeApiClient(fetcher, '/root');
    const catalog = await client.getWrongMachine(content);
    expect(fetcher.calls.mostRecent().args[0]).toBe('/root/api/v1/wrong-machine');
    const request = reconstructionPayload(catalog.recipes[0], null);
    const data = { recipe_id: request.recipe_id, mode: request.expected_mode,
      unit: { id: '109', display_name: 'Scale', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.lizard_kin',
        level: 1, xp: 0, lifecycle_status: 'active' },
      spend: { currency_id: 'raw_chaos', amount: 5, balance_before: 10, balance_after: 5 },
      consumed_items: request.expected_ingredients.map((row) => ({ ...row, owned_after: 4 })),
      kin_restoration: { kin_id: 'kin.lizard_kin', unlock_id: 'unlock.kin.lizard_kin', outcome: 'granted' }, player_revision: 4 };
    fetcher.and.resolveTo(new Response(JSON.stringify({ ok: true, data }), { status: 200 }));
    await client.reconstructKin(request, 'csrf', 'reconstruction:abc', content);
    const [url, init] = fetcher.calls.mostRecent().args;
    expect(url).toBe('/root/api/v1/wrong-machine/reconstruct');
    expect(init?.headers).toEqual(jasmine.objectContaining({ 'X-CSRF-Token': 'csrf', 'Idempotency-Key': 'reconstruction:abc' }));
    expect(JSON.parse(init?.body as string)).toEqual(request);
    fetcher.and.resolveTo(new Response(JSON.stringify({ ok: false, error: { code: 'reconstruction_changed' } }), { status: 409 }));
    await expectAsync(client.reconstructKin(request, 'csrf', 'reconstruction:next', content))
      .toBeRejectedWith(jasmine.objectContaining({ status: 409, code: 'reconstruction_changed' }));
  });
});
