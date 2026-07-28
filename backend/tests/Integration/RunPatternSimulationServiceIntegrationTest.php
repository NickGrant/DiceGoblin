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
    $this->assertGreaterThanOrEqual(1, $simulation['branch_count']['min']);
    $this->assertSame(0.0, $simulation['fallback_rate']);
    $this->assertArrayHasKey('shared_boss_exit_terminal@1', $simulation['pattern_frequency']);
    $this->assertArrayHasKey('shared_chaos_step@1', $simulation['pattern_frequency']);
    $this->assertCount(5, $simulation['results']);
  }
}
