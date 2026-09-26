import { ClientContentRegistry } from '../runtime/client-content-registry';
import { UnitDetail } from '../runtime/unit-detail-contracts';
import { WarbandDieSummary } from '../runtime/warband-contracts';
import { UnitConfigurationDraft } from './unit-configuration-model';

describe('UnitConfigurationDraft', () => {
  function fixture() {
    const stat = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
    const content = new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {}, kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Goblin.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Bruiser.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.inferred_only'] } },
      abilities: {
        'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bash.', icon_key: 'bash', dice_slot_count: 1 },
        'ability.volley': { id: 'ability.volley', kind: 'active', display_name: 'Volley', description: 'Volley.', icon_key: 'volley', dice_slot_count: 2 },
        'ability.passive': { id: 'ability.passive', kind: 'passive', display_name: 'Thick', description: 'Thick.', icon_key: 'thick', dice_slot_count: 0 },
        'ability.inferred_only': { id: 'ability.inferred_only', kind: 'active', display_name: 'Not Owned', description: 'No.', icon_key: 'no', dice_slot_count: 1 },
      },
      dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } }, dice_aspects: {},
      dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Bone Die', material_id: 'dice_material.bone', rarity: 'common', aspect_ids: [], allowed_sizes: [6] } },
      run_node_types: {}, items: {},
    } });
    const bash = content.getAbility('ability.bash')!; const volley = content.getAbility('ability.volley')!; const passive = content.getAbility('ability.passive')!;
    const material = content.getDiceMaterial('dice_material.bone')!; const profile = content.getDiceProfile('dice_profile.bone')!;
    const die = (id: string, unitId?: string): WarbandDieSummary => ({ id, size: 6, profile, material, aspects: [], lifecycleStatus: 'active', bindings: unitId ? [{ unitId, ability: bash, slotIndex: 0 }] : [] });
    const dice = [die('21', '11'), die('22'), die('23'), die('24', '99')];
    const detail: UnitDetail = {
      id: '11', displayName: 'Grub', unitType: content.getUnitType('unit_type.bruiser')!, kin: content.getKin('kin.goblin')!, level: 2, xp: 10, lifecycleStatus: 'active', promotionHistory: [],
      ownedAbilities: [bash, volley, passive], abilityLoadout: [{ ability: bash, equipOrder: 0 }], diceBindings: [{ ability: bash, slotIndex: 0, die: dice[0] }],
    };
    return { content, dice, detail, bash, volley, passive };
  }

  it('keeps rename and loadout changes local and independent', () => {
    const { detail, dice, volley } = fixture();
    const draft = new UnitConfigurationDraft('11', detail.ownedAbilities, detail);
    draft.setName(' New Grub ');
    expect(detail.displayName).toBe('Grub');
    expect(draft.renameDirty).toBeTrue();
    expect(draft.loadoutDirty).toBeFalse();
    expect(draft.renamePayload()).toEqual({ name: 'New Grub' });
    expect(draft.addAbility(volley.id)).toBeTrue();
    expect(draft.loadoutDirty).toBeTrue();
    expect(draft.selectSlot(volley.id, 0)).toBeTrue();
    expect(draft.assignSelectedDie('22', dice)).toBeTrue();
    expect(detail.abilityLoadout.map((entry) => entry.ability.id)).toEqual(['ability.bash']);
  });

  it('uses durable instance ownership, excludes passives, and deterministically adds/removes/reorders', () => {
    const { detail, volley, passive } = fixture();
    const draft = new UnitConfigurationDraft('11', detail.ownedAbilities, detail);
    expect(draft.activeOwnedAbilities.map((ability) => ability.id)).toEqual(['ability.bash', 'ability.volley']);
    expect(draft.passiveOwnedAbilities.map((ability) => ability.id)).toEqual(['ability.passive']);
    expect(draft.addAbility(passive.id)).toBeFalse();
    expect(draft.addAbility('ability.inferred_only')).toBeFalse();
    expect(draft.addAbility(volley.id)).toBeTrue();
    expect(draft.addAbility(volley.id)).toBeFalse();
    expect(draft.moveAbility(volley.id, -1)).toBeTrue();
    expect(draft.loadout.map((entry) => entry.ability.id)).toEqual(['ability.volley', 'ability.bash']);
    expect(draft.removeAbility('ability.bash')).toBeTrue();
    expect(draft.loadout.map((entry) => entry.ability.id)).toEqual(['ability.volley']);
  });

  it('requires a complete non-empty loadout and preserves exact ability/slot order in the body', () => {
    const { detail, dice, volley, bash } = fixture();
    const draft = new UnitConfigurationDraft('11', detail.ownedAbilities, detail);
    draft.addAbility(volley.id);
    expect(draft.loadoutValidationError).toContain('Assign every die slot');
    draft.selectSlot(volley.id, 0); draft.assignSelectedDie('22', dice);
    draft.selectSlot(volley.id, 1); draft.assignSelectedDie('23', dice);
    draft.moveAbility(volley.id, -1);
    expect(draft.loadoutPayload()).toEqual({ abilities: [
      { ability_id: volley.id, dice_instance_ids: ['22', '23'] },
      { ability_id: bash.id, dice_instance_ids: ['21'] },
    ] });
    draft.removeAbility(volley.id); draft.removeAbility(bash.id);
    expect(draft.loadoutValidationError).toContain('at least one');
  });

  it('moves a same-unit die without duplication and rejects dice held by another unit', () => {
    const { detail, dice, volley } = fixture();
    const draft = new UnitConfigurationDraft('11', detail.ownedAbilities, detail);
    draft.addAbility(volley.id);
    draft.selectSlot(volley.id, 0);
    expect(draft.assignSelectedDie('21', dice)).toBeTrue();
    expect(draft.loadout[0].diceInstanceIds).toEqual([null]);
    expect(draft.loadout[1].diceInstanceIds).toEqual(['21', null]);
    draft.selectSlot(volley.id, 1);
    expect(draft.assignSelectedDie('24', dice)).toBeFalse();
    expect(draft.dieAvailability(dice[3])).toBe('other-unit');
  });
});
