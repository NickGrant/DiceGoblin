<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class ChaosContentFixture
{
  public const ENCOUNTER_ID = 'encounter.test_chaos';
  public const GENERATION_ID = 'run_generation.test_chaos';

  /** @return list<array<string,mixed>> */
  public static function definitions(): array
  {
    $definitions = ShrineContentFixture::definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] === ShrineContentFixture::ENCOUNTER_ID) {
        $definition = [
          'id' => self::ENCOUNTER_ID, 'type' => 'encounter', 'kind' => 'non_combat', 'family' => 'chaos',
          'region_id' => 'region.the_farm', 'display_name' => 'Test Chaos',
          'description' => 'A test-only volatile run encounter.', 'prompt' => 'Accept the surge?',
          'actions' => [[
            'id' => 'accept', 'label' => 'ACCEPT SURGE',
            'effect' => ['type' => 'random_participating_hp', 'outcomes' => [
              ['id' => 'mend', 'weight' => 1, 'effect' => ['type' => 'restore_participating_units_to_max']],
              ['id' => 'wound', 'weight' => 1, 'effect' => ['type' => 'damage_all_participating_units', 'amount' => 2]],
            ]],
          ]],
        ];
      }
      if ($definition['id'] === ShrineContentFixture::GENERATION_ID) {
        $definition['id'] = self::GENERATION_ID;
        $definition['nodes'][3]['node_type_id'] = 'run_node_type.chaos';
        $definition['nodes'][3]['encounter_id'] = self::ENCOUNTER_ID;
      }
    }
    unset($definition);
    return $definitions;
  }

  public static function registry(): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-chaos-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    try {
      file_put_contents($root . '/content.json', json_encode(['definitions' => self::definitions()], JSON_THROW_ON_ERROR));
      return ContentRegistry::load($root);
    } finally {
      if (is_file($root . '/content.json')) unlink($root . '/content.json');
      rmdir($root);
    }
  }
}
