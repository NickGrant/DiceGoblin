import { ClientContentRegistry } from './client-content-registry';
import { parseDiceCollectionEnvelope, parseUnitCollectionEnvelope } from './warband-contracts';
import { UnitDetailContractError, parseUnitDetailEnvelope, parseUnitMutationEnvelope } from './unit-detail-contracts';

describe('Unit detail contracts', () => {
  function content(): ClientContentRegistry {
    const stat = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {},
      kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Clever.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: {
        'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Strong.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.bash'] },
        'unit_type.veteran': { id: 'unit_type.veteran', display_name: 'Veteran', description: 'Older.', art_key: 'veteran', role: 'frontline', tier: 2, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.volley'] },
      },
      abilities: {
        'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bonk.', icon_key: 'bash', dice_slot_count: 1 },
        'ability.volley': { id: 'ability.volley', kind: 'active', display_name: 'Volley', description: 'Twice.', icon_key: 'volley', dice_slot_count: 2 },
        'ability.thick': { id: 'ability.thick', kind: 'passive', display_name: 'Thick Hide', description: 'Tough.', icon_key: 'thick', dice_slot_count: 0 },
      },
      dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } },
      dice_aspects: {},
      dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Bone Die', material_id: 'dice_material.bone', rarity: 'common', aspect_ids: [], allowed_sizes: [6] } },
      run_node_types: {}, items: {}, shop_offers: {},
    } });
  }

  function rawDetail(): Record<string, unknown> {
    return {
      id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 3, xp: 44, lifecycle_status: 'active',
      promotion_history: [{ from_unit_type_id: 'unit_type.bruiser', to_unit_type_id: 'unit_type.veteran', promoted_at: '2026-01-01T00:00:00Z' }],
      owned_ability_ids: ['ability.bash', 'ability.volley', 'ability.thick'],
      ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }, { ability_id: 'ability.volley', equip_order: 1 }],
      dice_bindings: [
        { ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '21' },
        { ability_id: 'ability.volley', slot_index: 0, dice_instance_id: '22' },
        { ability_id: 'ability.volley', slot_index: 1, dice_instance_id: '23' },
      ],
    };
  }

  function context() {
    const registry = content();
    const units = parseUnitCollectionEnvelope({ ok: true, data: { units: [
      { id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 3, xp: 44, lifecycle_status: 'active' },
    ] } }, registry);
    const dice = parseDiceCollectionEnvelope({ ok: true, data: { dice: [
      { id: '21', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.bash', slot_index: 0 }] },
      { id: '22', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.volley', slot_index: 0 }] },
      { id: '23', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.volley', slot_index: 1 }] },
      { id: '24', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [] },
    ] } }, registry);
    return { registry, summary: units[0], dice };
  }

  function parse(raw = rawDetail()) {
    const { registry, summary, dice } = context();
    return parseUnitDetailEnvelope({ ok: true, data: { unit: raw } }, registry, dice, summary);
  }

  it('strictly resolves durable abilities, ordered actions, promotion history, and exact physical dice', () => {
    const detail = parse();
    expect(detail.ownedAbilities.map((ability) => [ability.display_name, ability.kind])).toEqual([
      ['Bash', 'active'], ['Volley', 'active'], ['Thick Hide', 'passive'],
    ]);
    expect(detail.abilityLoadout.map((entry) => [entry.ability.id, entry.equipOrder])).toEqual([
      ['ability.bash', 0], ['ability.volley', 1],
    ]);
    expect(detail.diceBindings.map((binding) => [binding.ability.id, binding.slotIndex, binding.die.id])).toEqual([
      ['ability.bash', 0, '21'], ['ability.volley', 0, '22'], ['ability.volley', 1, '23'],
    ]);
    expect(detail.promotionHistory[0].toUnitType.display_name).toBe('Veteran');
  });

  it('rejects malformed identity and unknown authored references', () => {
    expect(() => parse({ ...rawDetail(), id: '01' })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...rawDetail(), unit_type_id: 'unit_type.missing' })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...rawDetail(), owned_ability_ids: ['ability.bash', 'ability.missing'] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...rawDetail(), promotion_history: [{ from_unit_type_id: 'unit_type.missing', to_unit_type_id: 'unit_type.veteran', promoted_at_at: '2026-01-01' }] })).toThrowError(UnitDetailContractError);
  });

  it('rejects unowned, passive, duplicate, and non-contiguous loadout entries', () => {
    const base = rawDetail();
    expect(() => parse({ ...base, owned_ability_ids: ['ability.bash', 'ability.thick'] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, ability_loadout: [{ ability_id: 'ability.thick', equip_order: 0 }] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }, { ability_id: 'ability.bash', equip_order: 1 }] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, ability_loadout: [{ ability_id: 'ability.bash', equip_order: 1 }] })).toThrowError(UnitDetailContractError);
  });

  it('rejects incomplete, duplicate-slot, duplicate-die, and dice-summary disagreements', () => {
    const base = rawDetail();
    const bindings = base['dice_bindings'] as Record<string, unknown>[];
    expect(() => parse({ ...base, dice_bindings: bindings.slice(0, 2) })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, dice_bindings: [bindings[0], bindings[1], { ...bindings[2], slot_index: 0 }] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, dice_bindings: [bindings[0], bindings[1], { ...bindings[2], dice_instance_id: '22' }] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, dice_bindings: [bindings[0], bindings[1], { ...bindings[2], dice_instance_id: '24' }] })).toThrowError(UnitDetailContractError);
    expect(() => parse({ ...base, dice_bindings: [bindings[0], bindings[1], { ...bindings[2], dice_instance_id: '999' }] })).toThrowError(UnitDetailContractError);
  });

  it('allows a mutation response to replace this unit bindings but never steal another unit die', () => {
    const { registry, dice } = context();
    const moved = rawDetail();
    moved['dice_bindings'] = [
      { ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '24' },
      { ability_id: 'ability.volley', slot_index: 0, dice_instance_id: '21' },
      { ability_id: 'ability.volley', slot_index: 1, dice_instance_id: '22' },
    ];
    const result = parseUnitMutationEnvelope({ ok: true, data: { unit: moved, player_revision: 8 } }, registry, dice);
    expect(result.playerRevision).toBe(8);

    const conflictingDice = dice.map((die) => die.id === '24' ? { ...die, bindings: [{ unitId: '99', ability: registry.getAbility('ability.bash')!, slotIndex: 0 }] } : die);
    expect(() => parseUnitMutationEnvelope({ ok: true, data: { unit: moved, player_revision: 8 } }, registry, conflictingDice)).toThrowError(UnitDetailContractError);
  });
});
