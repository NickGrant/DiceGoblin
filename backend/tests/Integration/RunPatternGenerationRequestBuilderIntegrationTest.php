<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternCatalogSyncService;
use DiceGoblins\Services\RunPatternGenerationRequestBuilder;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunPatternGenerationRequestBuilderIntegrationTest extends IntegrationTestCase
{
  protected function tearDown(): void
  {
    if ($this->pdo !== null) {
      $this->pdo->exec('DELETE FROM `run_pattern_region_rules`');
      $this->pdo->exec('DELETE FROM `run_generation_profiles`');
      $this->pdo->exec('DELETE FROM `run_pattern_definitions`');
    }

    parent::tearDown();
  }

  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run-pattern generation request builder integration tests.';
  }

  public function testBuildsStableGenerationRequestForEnabledRegionProfile(): void
  {
    (new RunPatternCatalogSyncService($this->pdo))->syncDefaultCatalog();

    $builder = new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo));
    $request = $builder->build('mountains', 'qa-seed-12');
    $sameRequest = $builder->build('mountains', 'qa-seed-12');

    $this->assertSame('mountains', $request['region_slug']);
    $this->assertSame('qa-seed-12', $request['seed']);
    $this->assertSame('pattern-v1', $request['generator_version']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $request['catalog_hash']);
    $this->assertSame($request['catalog_hash'], $sameRequest['catalog_hash']);

    $this->assertArrayHasKey('start', $request['rules_by_phase']);
    $this->assertArrayHasKey('spine', $request['rules_by_phase']);
    $this->assertArrayHasKey('terminal', $request['rules_by_phase']);
    $this->assertArrayHasKey('shared_start_single@1', $request['patterns_by_key']);

    $variants = $request['variants_by_pattern_key']['shared_start_single@1'];
    $this->assertCount(1, $variants);
    $this->assertSame('shared_start_single@1:identity', $variants[0]['variant_key']);
  }

  public function testBuildsPatternV2GenerationRequestWithGridTiles(): void
  {
    $this->seedPatternV2Fixture();

    $builder = new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo));
    $request = $builder->build('mountains', 'v2-seed-1', 'pattern-v2');

    $this->assertSame('pattern-v2', $request['generator_version']);
    $this->assertSame([], $request['variants_by_pattern_key']);
    $this->assertArrayHasKey('v2_test_start@1', $request['tiles_by_pattern_key']);
    $this->assertSame(3, $request['tiles_by_pattern_key']['v2_test_start@1']['width']);
    $this->assertSame(2, $request['tiles_by_pattern_key']['v2_test_start@1']['height']);
    $this->assertSame(['start_node', 'combat_node'], array_column($request['tiles_by_pattern_key']['v2_test_start@1']['nodes'], 'key'));
    $this->assertSame([['row' => 0, 'col' => 1]], $request['tiles_by_pattern_key']['v2_test_start@1']['connectors']);
  }

  public function testBuildsPatternV2GenerationRequestFromMigrationSeededCatalog(): void
  {
    $this->applyPatternV2Migration();

    $builder = new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo));
    $request = $builder->build('mountains', 'v2-seeded-catalog', 'pattern-v2');

    $this->assertSame('mountains', $request['region_slug']);
    $this->assertSame('pattern-v2', $request['generator_version']);
    $this->assertSame(3, $request['profile_version']);
    $this->assertSame([], $request['variants_by_pattern_key']);
    $this->assertSame(['spine', 'start', 'terminal'], array_keys($request['rules_by_phase']));
    $this->assertCount(5, $request['patterns_by_key']);
    $this->assertCount(5, $request['tiles_by_pattern_key']);
    $this->assertArrayHasKey('v2_mountain_start_cluster@1', $request['tiles_by_pattern_key']);
    $this->assertArrayHasKey('v2_mountain_braided_combat@1', $request['tiles_by_pattern_key']);
    $this->assertArrayHasKey('v2_mountain_dense_braid@1', $request['tiles_by_pattern_key']);
    $this->assertArrayHasKey('v2_general_loot_connector@1', $request['tiles_by_pattern_key']);
    $this->assertArrayHasKey('v2_mountain_boss_exit@1', $request['tiles_by_pattern_key']);
    $this->assertSame(3, $request['tiles_by_pattern_key']['v2_mountain_start_cluster@1']['height']);
    $this->assertSame(5, $request['tiles_by_pattern_key']['v2_mountain_braided_combat@1']['width']);
    $this->assertSame(5, $request['tiles_by_pattern_key']['v2_mountain_dense_braid@1']['height']);
    $this->assertSame(['start', 'mountain'], $request['tiles_by_pattern_key']['v2_mountain_start_cluster@1']['tags']);
    $this->assertContains('boss', array_column($request['tiles_by_pattern_key']['v2_mountain_boss_exit@1']['nodes'], 'type'));
  }

  private function seedPatternV2Fixture(): void
  {
    $regionId = (int)$this->scalar('SELECT `id` FROM `regions` WHERE `slug` = ?', ['mountains']);
    $definition = [
      'schema_version' => 'pattern-v2',
      'slug' => 'v2_test_start',
      'version' => 1,
      'status' => 'enabled',
      'width' => 3,
      'height' => 2,
      'cost' => 2,
      'tags' => ['start', 'mountain'],
      'grid' => [
        [
          ['key' => 'start_node', 'type' => 'combat', 'role' => 'start'],
          ['type' => 'connector'],
          ['key' => 'combat_node', 'type' => 'combat', 'difficulty' => 'easy'],
        ],
        [null, null, null],
      ],
      'connections' => [
        ['from' => 'start_node', 'to' => 'combat_node', 'through' => [['row' => 0, 'col' => 1]]],
      ],
      'exits' => [
        ['row' => 0, 'col' => 2, 'direction' => 'right'],
      ],
    ];
    $definitionJson = json_encode($definition, JSON_UNESCAPED_SLASHES);
    $this->assertIsString($definitionJson);

    $stmt = $this->pdo->prepare('
      INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
      VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute(['v2_test_start', 1, 'enabled', $definitionJson, hash('sha256', $definitionJson)]);
    $patternId = (int)$this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare('
      INSERT INTO `run_pattern_region_rules` (
        `pattern_definition_id`, `region_id`, `generator_version`, `base_weight`, `allowed_phase`,
        `min_depth`, `max_depth`, `max_per_run`, `cooldown_patterns`, `enabled`, `weight_modifiers_json`
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$patternId, $regionId, 'pattern-v2', 100, 'start', 0, 0, 1, 0, 1, '{}']);

    $profile = [
      'region_slug' => 'mountains',
      'generator_version' => 'pattern-v2',
      'profile_version' => 1,
      'enabled' => true,
      'bounds' => ['min_col' => 0, 'max_col' => 8, 'min_row' => 0, 'max_row' => 5],
      'budgets' => ['cost' => ['min' => 2, 'target' => 2, 'max' => 2, 'hard' => true]],
      'requirements' => ['required_tags' => ['start']],
      'retry_policy' => ['generation_attempts' => 1],
      'weight_policy' => [],
    ];
    $profileJson = json_encode($profile, JSON_UNESCAPED_SLASHES);
    $this->assertIsString($profileJson);

    $stmt = $this->pdo->prepare('
      INSERT INTO `run_generation_profiles` (
        `region_id`, `generator_version`, `profile_version`, `enabled`,
        `bounds_json`, `budgets_json`, `requirements_json`, `retry_policy_json`, `weight_policy_json`, `content_hash`
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
      $regionId,
      'pattern-v2',
      1,
      1,
      json_encode($profile['bounds'], JSON_UNESCAPED_SLASHES),
      json_encode($profile['budgets'], JSON_UNESCAPED_SLASHES),
      json_encode($profile['requirements'], JSON_UNESCAPED_SLASHES),
      json_encode($profile['retry_policy'], JSON_UNESCAPED_SLASHES),
      json_encode($profile['weight_policy'], JSON_UNESCAPED_SLASHES),
      hash('sha256', $profileJson),
    ]);
  }

  private function applyPatternV2Migration(): void
  {
    foreach ([
      '79_seed_pattern_v2_catalog.sql',
      '80_fix_pattern_v2_perimeter_exits.sql',
      '81_seed_pattern_v2_dense_mountain_tiles.sql',
      '82_compact_mountains_pattern_v2_profile.sql',
    ] as $filename) {
      $path = dirname(__DIR__, 2) . '/migrations/' . $filename;
      $sql = file_get_contents($path);
      $this->assertIsString($sql);
      $this->pdo->exec($sql);
    }
  }
}
