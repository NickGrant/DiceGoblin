import { ClientContentRegistry } from './client-content-registry';
import {
  WarbandContractError,
  parseDiceCollectionEnvelope,
  parseSquadCollectionEnvelope,
  parseSquadDeleteEnvelope,
  parseSquadMutationEnvelope,
  parseUnitCollectionEnvelope,
  requireActiveSquadAgreement,
} from './warband-contracts';

describe('Warband collection contracts', () => {
  function registry(): ClientContentRegistry {
    return new ClientContentRegistry({
      revision: 'a'.repeat(64),
      content: {
        gameplay: { run_energy_cost: 10 },
        regions: {},
        kin: {
          'kin.goblin': { id: 'kin.goblin', display_name: 'Cave Goblin', description: 'Scrappy.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } },
        },
        unit_types: {
          'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Hits hard.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: { hp: 10, attack: 4, defense: 2, precision: 1, resolve: 1 }, growth_per_level: { hp: 2, attack: 1, defense: 1, precision: 0, resolve: 0 }, ability_ids: ['ability.bash'] },
        },
        abilities: {
          'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bonk.', icon_key: 'bash', dice_slot_count: 1 },
        },
        dice_materials: {
          'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Carved bone.', art_key: 'bone', allowed_sizes: [6] },
        },
        dice_aspects: {
          'dice_aspect.heavy': { id: 'dice_aspect.heavy', display_name: 'Heavy', description: 'Weighty.', allowed_sizes: [6] },
        },
        dice_profiles: {
          'dice_profile.bone_heavy': { id: 'dice_profile.bone_heavy', display_name: 'Knucklebone', material_id: 'dice_material.bone', rarity: 'uncommon', aspect_ids: ['dice_aspect.heavy'], allowed_sizes: [6] },
        },
        run_node_types: {},
      },
    });
  }

  it('strictly parses and resolves unit, die, binding, and squad presentation', () => {
    const content = registry();
    const units = parseUnitCollectionEnvelope({ ok: true, data: { units: [
      { id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 3, xp: 44, lifecycle_status: 'active' },
    ] } }, content);
    const dice = parseDiceCollectionEnvelope({ ok: true, data: { dice: [
      { id: '21', size: 6, profile_id: 'dice_profile.bone_heavy', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.bash', slot_index: 0 }] },
    ] } }, content);
    const squads = parseSquadCollectionEnvelope({ ok: true, data: { squads: [
      { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] },
    ] } });

    expect(units[0].unitType.display_name).toBe('Bruiser');
    expect(units[0].kin.display_name).toBe('Cave Goblin');
    expect(dice[0].profile.rarity).toBe('uncommon');
    expect(dice[0].material.display_name).toBe('Bone');
    expect(dice[0].aspects[0].display_name).toBe('Heavy');
    expect(dice[0].bindings[0].ability.display_name).toBe('Bash');
    expect(squads[0].formation.length).toBe(9);
    requireActiveSquadAgreement(squads, '31');
  });

  it('rejects malformed envelopes, unexpected fields, and missing authored references', () => {
    const content = registry();
    expect(() => parseUnitCollectionEnvelope({ ok: true, data: { units: [], extra: true } }, content)).toThrowError(WarbandContractError);
    expect(() => parseUnitCollectionEnvelope({ ok: true, data: { units: [
      { id: '1', display_name: 'Lost', unit_type_id: 'unit_type.missing', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' },
    ] } }, content)).toThrowError(WarbandContractError);
    expect(() => parseDiceCollectionEnvelope({ ok: true, data: { dice: [
      { id: '2', size: 8, profile_id: 'dice_profile.bone_heavy', lifecycle_status: 'active', bindings: [] },
    ] } }, content)).toThrowError(WarbandContractError);
  });

  it('rejects invalid formations, duplicate active squads, and bootstrap disagreement', () => {
    expect(() => parseSquadCollectionEnvelope({ ok: true, data: { squads: [
      { id: '1', name: 'Copies', is_active: true, formation: ['4', '4', null, null, null, null, null, null, null] },
    ] } })).toThrowError(WarbandContractError);
    expect(() => parseSquadCollectionEnvelope({ ok: true, data: { squads: [
      { id: '1', name: 'One', is_active: true, formation: Array(9).fill(null) },
      { id: '2', name: 'Two', is_active: true, formation: Array(9).fill(null) },
    ] } })).toThrowError(WarbandContractError);
    const squads = parseSquadCollectionEnvelope({ ok: true, data: { squads: [
      { id: '1', name: 'One', is_active: true, formation: Array(9).fill(null) },
    ] } });
    expect(() => requireActiveSquadAgreement(squads, '2')).toThrowError(WarbandContractError);
  });

  it('strictly parses squad mutation and delete responses', () => {
    const mutation = parseSquadMutationEnvelope({ ok: true, data: {
      squad: { id: '31', name: ' Raiders ', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] },
      active_squad_id: '31', player_revision: 8,
    } });
    expect(mutation.squad.name).toBe('Raiders');
    expect(mutation.playerRevision).toBe(8);
    expect(parseSquadDeleteEnvelope({ ok: true, data: {
      deleted_squad_id: '31', active_squad_id: null, player_revision: 9,
    } }).deletedSquadId).toBe('31');
  });

  it('rejects inconsistent or structurally malformed mutation success responses', () => {
    expect(() => parseSquadMutationEnvelope({ ok: true, data: {
      squad: { id: '31', name: 'Raiders', is_active: false, formation: Array(9).fill(null) },
      active_squad_id: '31', player_revision: 8,
    } })).toThrowError(WarbandContractError);
    expect(() => parseSquadMutationEnvelope({ ok: true, data: {
      squad: { id: '31', name: 'Raiders', is_active: true, formation: Array(8).fill(null) },
      active_squad_id: '31', player_revision: 8,
    } })).toThrowError(WarbandContractError);
    expect(() => parseSquadDeleteEnvelope({ ok: true, data: {
      deleted_squad_id: '31', active_squad_id: '31', player_revision: 9,
    } })).toThrowError(WarbandContractError);
  });
});
