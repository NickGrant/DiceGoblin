<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class RunModifierContentFixture
{
  public const MODIFIER_ID = 'modifier.test_battle_focus';
  public const ENCOUNTER_ID = 'encounter.test_modifier_shrine';
  public const GENERATION_ID = 'run_generation.test_modifier';
  public const SIDE_HAZARD_ID = 'encounter.test_modifier_hazard';
  public const SIDE_SHRINE_ID = 'encounter.test_modifier_side_shrine';

  /** @return list<array<string,mixed>> */
  public static function definitions(bool $nextCombat = false, ?string $sideFamily = null): array
  {
    if ($sideFamily !== null && !in_array($sideFamily, ['rest', 'hazard', 'shrine', 'chaos'], true)) {
      throw new \InvalidArgumentException('Unsupported test-only side encounter family.');
    }
    $definitions = ShrineContentFixture::definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] === ShrineContentFixture::ENCOUNTER_ID) {
        $definition = [
          'id' => self::ENCOUNTER_ID, 'type' => 'encounter', 'kind' => 'non_combat', 'family' => 'shrine',
          'region_id' => 'region.the_farm', 'display_name' => 'Test Focus Shrine',
          'description' => 'A test-only source of the next battle focus.', 'prompt' => 'Prepare for battle?',
          'actions' => [['id' => 'focus', 'label' => 'TAKE FOCUS',
            'effect' => ['type' => 'create_run_modifier', 'modifier_id' => self::MODIFIER_ID]]],
        ];
      }
      if ($definition['id'] === ShrineContentFixture::GENERATION_ID) {
        $definition['id'] = self::GENERATION_ID;
        $definition['nodes'][3]['encounter_id'] = self::ENCOUNTER_ID;
        if ($sideFamily !== null) {
          $definition['nodes'][2]['node_type_id'] = 'run_node_type.' . $sideFamily;
          $definition['nodes'][2]['encounter_id'] = match ($sideFamily) {
            'rest' => 'encounter.the_farm_rest',
            'hazard' => self::SIDE_HAZARD_ID,
            'shrine' => self::SIDE_SHRINE_ID,
            'chaos' => ChaosContentFixture::ENCOUNTER_ID,
          };
          $definition['nodes'][2]['event_id'] = null;
        }
        if ($nextCombat) {
          $combat = $definition['nodes'][0];
          $combat['key'] = 'combat_after_shrine';
          $combat['node_type_id'] = 'run_node_type.combat';
          $combat['encounter_id'] = 'encounter.the_farm_mud_combat_1';
          $combat['event_id'] = null;
          $combat['position'] = ['column' => 3, 'row' => 2];
          array_splice($definition['nodes'], 4, 0, [$combat]);
          $definition['edges'] = array_map(static fn(array $edge): array => $edge['from'] === 'rest'
            ? ['from' => 'rest', 'to' => 'combat_after_shrine'] : $edge, $definition['edges']);
          $definition['edges'][] = ['from' => 'combat_after_shrine', 'to' => 'boss'];
        }
      }
    }
    unset($definition);
    if ($sideFamily === 'hazard') $definitions[] = [
      'id' => self::SIDE_HAZARD_ID, 'type' => 'encounter', 'kind' => 'non_combat', 'family' => 'hazard',
      'region_id' => 'region.the_farm', 'display_name' => 'Test Side Hazard',
      'description' => 'A test-only hazard beside the focus shrine.', 'prompt' => 'Cross?',
      'actions' => [['id' => 'cross', 'label' => 'CROSS',
        'effect' => ['type' => 'damage_all_participating_units', 'amount' => 1]]],
    ];
    if ($sideFamily === 'shrine') $definitions[] = [
      'id' => self::SIDE_SHRINE_ID, 'type' => 'encounter', 'kind' => 'non_combat', 'family' => 'shrine',
      'region_id' => 'region.the_farm', 'display_name' => 'Test Side Shrine',
      'description' => 'A test-only healing shrine beside the focus shrine.', 'prompt' => 'Rest?',
      'actions' => [['id' => 'restore', 'label' => 'RESTORE',
        'effect' => ['type' => 'restore_participating_units_to_max']]],
    ];
    if ($sideFamily === 'chaos') {
      foreach (ChaosContentFixture::definitions() as $candidate) {
        if ($candidate['id'] === ChaosContentFixture::ENCOUNTER_ID) {
          $definitions[] = $candidate;
          break;
        }
      }
    }
    $definitions[] = [
      'id' => self::MODIFIER_ID, 'type' => 'run_modifier', 'kind' => 'next_combat_attack_bonus',
      'display_name' => 'Battle Focus', 'description' => 'Warband Attack rises in the next battle.',
      'attack_bonus' => 2, 'remaining_combats' => 1,
    ];
    return $definitions;
  }

  public static function registry(bool $nextCombat = false, ?string $sideFamily = null): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-run-modifier-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    try {
      file_put_contents($root . '/content.json', json_encode(['definitions' => self::definitions($nextCombat, $sideFamily)], JSON_THROW_ON_ERROR));
      return ContentRegistry::load($root);
    } finally {
      if (is_file($root . '/content.json')) unlink($root . '/content.json');
      rmdir($root);
    }
  }
}
