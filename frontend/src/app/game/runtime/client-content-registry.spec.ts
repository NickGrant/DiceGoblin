import {
  ClientContentError,
  ClientContentLoader,
  ClientContentRegistry,
} from './client-content-registry';
import { RuntimeFetch } from './runtime-api-client';

describe('ClientContentRegistry', () => {
  const revision = 'a'.repeat(64);

  it('loads the generated projection and indexes every public domain by stable ID', async () => {
    const projection = validProjection();
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify(projection), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    const loader = new ClientContentLoader(fetchRequest, 'https://game.test/game-content.json');

    const registry = new ClientContentRegistry(await loader.loadProjection());

    expect(fetchRequest).toHaveBeenCalledOnceWith(
      'https://game.test/game-content.json',
      jasmine.objectContaining({ method: 'GET' }),
    );
    expect(registry.revision).toBe(revision);
    expect(registry.runEnergyCost).toBe(10);
    expect(registry.get('region.the_farm')).toEqual(projection.content.regions['region.the_farm']);
    expect(registry.getKin('kin.goblin')?.display_name).toBe('Basic Goblin');
    expect(registry.getUnitType('unit_type.bruiser')?.base_stats.hp).toBe(22);
    expect(registry.getAbility('ability.basic_attack_melee')?.dice_slot_count).toBe(1);
    expect(registry.getDiceMaterial('dice_material.cardboard')?.allowed_sizes).toEqual([4, 6]);
    expect(registry.getDiceAspect('dice_aspect.striking')?.display_name).toBe('Striking');
    expect(registry.getDiceProfile('dice_profile.cardboard_striking')?.rarity).toBe('common');
    expect(registry.getRunNodeType('run_node_type.combat')?.icon_key).toBe('icon_encounter_combat');
    expect(registry.getItem('item.test.tonic')?.effect).toEqual({ type: 'energy_restore', amount: 5 });
    expect(registry.has('region.missing')).toBeFalse();
  });

  it('rejects missing fields and malformed domain shapes', () => {
    const missingDescription = validProjection();
    delete (
      missingDescription.content.unit_types['unit_type.bruiser'] as Partial<Record<string, unknown>>
    )['description'];
    expect(() => new ClientContentRegistry(missingDescription)).toThrowError(ClientContentError);

    const malformedCatalog = validProjection() as unknown as {
      content: { dice_profiles: unknown };
    };
    malformedCatalog.content.dice_profiles = [];
    expect(() => new ClientContentRegistry(malformedCatalog)).toThrowError(ClientContentError);
  });

  it('rejects malformed identities and dangling projected references', () => {
    const wrongIdentity = validProjection();
    wrongIdentity.content.regions['region.the_farm'].id = 'region.wrong';
    expect(() => new ClientContentRegistry(wrongIdentity)).toThrowError(ClientContentError);

    const missingAbility = validProjection();
    missingAbility.content.unit_types['unit_type.bruiser'].ability_ids = ['ability.missing'];
    expect(() => new ClientContentRegistry(missingAbility)).toThrowError(ClientContentError);

    const missingAspect = validProjection();
    missingAspect.content.dice_profiles['dice_profile.cardboard_striking'].aspect_ids = [
      'dice_aspect.missing',
    ];
    expect(() => new ClientContentRegistry(missingAspect)).toThrowError(ClientContentError);
  });

  it('rejects client-visible profile sizes incompatible with material or aspect restrictions', () => {
    const incompatible = validProjection();
    incompatible.content.dice_profiles['dice_profile.cardboard_striking'].allowed_sizes = [8];
    expect(() => new ClientContentRegistry(incompatible)).toThrowError(ClientContentError);
  });

  it('strictly rejects malformed projected run node types', () => {
    const missingDescription = validProjection();
    delete (
      missingDescription.content.run_node_types['run_node_type.combat'] as Partial<Record<string, unknown>>
    )['description'];
    expect(() => new ClientContentRegistry(missingDescription)).toThrowError(ClientContentError);

    const privateTopology = validProjection();
    (
      privateTopology.content.run_node_types['run_node_type.combat'] as Record<string, unknown>
    )['edges'] = [{ from: 'combat', to: 'loot' }];
    expect(() => new ClientContentRegistry(privateTopology)).toThrowError(ClientContentError);

    const wrongIdentity = validProjection();
    wrongIdentity.content.run_node_types['run_node_type.combat'].id = 'run_node_type.loot';
    expect(() => new ClientContentRegistry(wrongIdentity)).toThrowError(ClientContentError);
  });

  it('strictly rejects unsupported or private item effect fields', () => {
    const unsupported = validProjection();
    unsupported.content.items['item.test.tonic'].effect.type = 'mystery' as 'energy_restore';
    expect(() => new ClientContentRegistry(unsupported)).toThrowError(ClientContentError);

    const privateField = validProjection();
    (privateField.content.items['item.test.tonic'] as unknown as Record<string, unknown>)['grant_config'] = {};
    expect(() => new ClientContentRegistry(privateField)).toThrowError(ClientContentError);
  });

  it('strictly rejects missing, non-positive, or expanded gameplay presentation', () => {
    const invalid = validProjection(); invalid.content.gameplay.run_energy_cost = 0;
    expect(() => new ClientContentRegistry(invalid)).toThrowError(ClientContentError);
    const expanded = validProjection();
    (expanded.content.gameplay as Record<string, unknown>)['starting_energy'] = 50;
    expect(() => new ClientContentRegistry(expanded)).toThrowError(ClientContentError);
  });
});

function validProjection() {
  return {
    revision: 'a'.repeat(64),
    content: {
      gameplay: { run_energy_cost: 10 },
      regions: {
        'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' },
      },
      kin: {
        'kin.goblin': {
          id: 'kin.goblin',
          display_name: 'Basic Goblin',
          description: 'A goblin.',
          art_key: 'goblin_primordial',
          trait_summary: 'No modifier.',
          stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 },
        },
      },
      unit_types: {
        'unit_type.bruiser': {
          id: 'unit_type.bruiser',
          display_name: 'Bruiser',
          description: 'A bruiser.',
          art_key: 'goblin_bruiser',
          role: 'frontline',
          tier: 1,
          base_stats: { hp: 22, attack: 5, defense: 3, precision: 5, resolve: 5 },
          growth_per_level: { hp: 2, attack: 1, defense: 1, precision: 1, resolve: 1 },
          ability_ids: ['ability.basic_attack_melee'],
        },
      },
      abilities: {
        'ability.basic_attack_melee': {
          id: 'ability.basic_attack_melee',
          kind: 'active',
          display_name: 'Basic Attack',
          description: 'Deals damage.',
          icon_key: 'basic_attack',
          dice_slot_count: 1,
        },
      },
      dice_materials: {
        'dice_material.cardboard': {
          id: 'dice_material.cardboard',
          display_name: 'Cardboard',
          description: 'Cardboard.',
          art_key: 'cardboard',
          allowed_sizes: [4, 6],
        },
      },
      dice_aspects: {
        'dice_aspect.striking': {
          id: 'dice_aspect.striking',
          display_name: 'Striking',
          description: 'Deals more damage.',
          allowed_sizes: [4, 6],
        },
      },
      dice_profiles: {
        'dice_profile.cardboard_striking': {
          id: 'dice_profile.cardboard_striking',
          display_name: 'Striking Cardboard',
          material_id: 'dice_material.cardboard',
          rarity: 'common',
          aspect_ids: ['dice_aspect.striking'],
          allowed_sizes: [4, 6],
        },
      },
      run_node_types: {
        'run_node_type.combat': {
          id: 'run_node_type.combat',
          display_name: 'Combat',
          description: 'Fight enemies guarding the path.',
          icon_key: 'icon_encounter_combat',
        },
      },
      items: {
        'item.test.tonic': {
          id: 'item.test.tonic', display_name: 'Tonic', description: 'Restores energy.',
          category: 'consumable', rarity: 'common', icon_key: 'tonic', stackable: true,
          effect: { type: 'energy_restore', amount: 5 },
        },
      },
    },
  };
}
