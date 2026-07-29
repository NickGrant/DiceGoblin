<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternCatalogSyncService;
use DiceGoblins\Services\RunPatternGenerationRequestBuilder;
use DiceGoblins\Services\RunPatternSimulationService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunPatternSimulationServiceIntegrationTest extends IntegrationTestCase
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
    return 'Set TEST_DB_DSN to run run-pattern simulation integration tests.';
  }

  public function testSimulatesDeterministicSeedBatch(): void
  {
    (new RunPatternCatalogSyncService($this->pdo))->syncDefaultCatalog();

    $simulation = (new RunPatternSimulationService(
      new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo))
    ))->simulate('mountains', 5, 'qa-pattern');

    $this->assertSame('mountains', $simulation['region_slug']);
    $this->assertSame(5, $simulation['runs']);
    $this->assertSame(5, $simulation['successes']);
    $this->assertSame(1.0, $simulation['success_rate']);
    $this->assertSame([], $simulation['validation_failures']);
    $this->assertGreaterThanOrEqual(10, $simulation['node_count']['min']);
    $this->assertGreaterThanOrEqual(9, $simulation['spine_depth']['min']);
    $this->assertGreaterThanOrEqual(1, $simulation['branch_count']['min']);
    $this->assertLessThanOrEqual(3, $simulation['max_straight_spine_nodes']['max']);
    $this->assertGreaterThanOrEqual(3, $simulation['occupied_rows']['min']);
    $this->assertGreaterThanOrEqual(10, $simulation['occupied_columns']['min']);
    $this->assertGreaterThanOrEqual(1, $simulation['edge_count']['min']);
    $this->assertArrayHasKey('combat', $simulation['node_type_frequency']);
    $this->assertSame(0.0, $simulation['fallback_rate']);
    $this->assertSame(0.0, $simulation['backtracks']['avg']);
    $this->assertNotNull($simulation['duration_ms']['avg']);
    $this->assertGreaterThanOrEqual(1, $simulation['boss_path']['start_to_boss']['min']);
    $this->assertSame(1.0, $simulation['boss_path']['boss_to_exit']['min']);
    $this->assertArrayHasKey('shared_boss_exit_terminal@1', $simulation['pattern_frequency']);
    $this->assertArrayHasKey('shared_chaos_step@1', $simulation['pattern_frequency']);
    $this->assertCount(5, $simulation['results']);

    $gate = (new RunPatternSimulationService(
      new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo))
    ))->evaluateGate($simulation, ['min_occupied_rows' => 3, 'min_occupied_columns' => 10]);

    $this->assertTrue($gate['passed'], json_encode($gate['checks'], JSON_UNESCAPED_SLASHES));
    $this->assertSame(
      [
        'success_rate',
        'fallback_rate',
        'validation_failures',
        'branch_count_min',
        'backtracks_avg',
        'max_straight_spine_nodes',
        'occupied_rows_min',
        'occupied_columns_min',
      ],
      array_column($gate['checks'], 'name'),
    );
  }
}
