<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class ShrineContentFixture
{
  public const ENCOUNTER_ID = 'encounter.test_shrine';
  public const GENERATION_ID = 'run_generation.test_shrine';

  /** @return list<array<string,mixed>> */
  public static function definitions(): array
  {
    $root = dirname(__DIR__, 2) . '/tests/Fixtures/content';
    $canonical = ContentRegistry::load($root);
    $definitions = [];
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') continue;
      $document = json_decode((string)file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
      array_push($definitions, ...$document['definitions']);
    }
    $definitions[] = [
      'id' => self::ENCOUNTER_ID, 'type' => 'encounter', 'kind' => 'non_combat', 'family' => 'shrine',
      'region_id' => 'region.the_farm', 'display_name' => 'Test Shrine',
      'description' => 'A test-only choice of immediate run HP changes.', 'prompt' => 'Choose one rite.',
      'actions' => [
        ['id' => 'restore', 'label' => 'RESTORE WARBAND',
          'effect' => ['type' => 'restore_participating_units_to_max']],
        ['id' => 'endure', 'label' => 'ENDURE TRIAL',
          'effect' => ['type' => 'damage_all_participating_units', 'amount' => 2]],
      ],
    ];
    $generation = BranchingRunDefinitionFixture::definition($canonical, true);
    $generation['id'] = self::GENERATION_ID;
    $generation['nodes'][0]['node_type_id'] = 'run_node_type.loot';
    $generation['nodes'][0]['encounter_id'] = null;
    $generation['nodes'][0]['event_id'] = 'event.farm_loot_completed';
    $generation['nodes'][3]['node_type_id'] = 'run_node_type.shrine';
    $generation['nodes'][3]['encounter_id'] = self::ENCOUNTER_ID;
    $definitions[] = $generation;
    return $definitions;
  }

  public static function registry(): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-shrine-' . bin2hex(random_bytes(6));
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
