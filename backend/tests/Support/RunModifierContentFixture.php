<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class RunModifierContentFixture
{
  public const MODIFIER_ID = 'modifier.test_battle_focus';
  public const ENCOUNTER_ID = 'encounter.test_modifier_shrine';
  public const GENERATION_ID = 'run_generation.test_modifier';

  /** @return list<array<string,mixed>> */
  public static function definitions(bool $nextCombat = false): array
  {
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
    $definitions[] = [
      'id' => self::MODIFIER_ID, 'type' => 'run_modifier', 'kind' => 'next_combat_attack_bonus',
      'display_name' => 'Battle Focus', 'description' => 'Warband Attack rises in the next battle.',
      'attack_bonus' => 2, 'remaining_combats' => 1,
    ];
    return $definitions;
  }

  public static function registry(bool $nextCombat = false): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-run-modifier-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    try {
      file_put_contents($root . '/content.json', json_encode(['definitions' => self::definitions($nextCombat)], JSON_THROW_ON_ERROR));
      return ContentRegistry::load($root);
    } finally {
      if (is_file($root . '/content.json')) unlink($root . '/content.json');
      rmdir($root);
    }
  }
}
