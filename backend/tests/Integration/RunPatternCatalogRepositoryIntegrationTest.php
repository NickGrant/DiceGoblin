<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternCatalogSyncService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunPatternCatalogRepositoryIntegrationTest extends IntegrationTestCase
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
    return 'Set TEST_DB_DSN to run run-pattern catalog repository integration tests.';
  }

  public function testRepositoryLoadsSyncedPatternCatalogForRegion(): void
  {
    (new RunPatternCatalogSyncService($this->pdo))->syncDefaultCatalog();
    $repository = new RunPatternCatalogRepository($this->pdo);

    $profile = $repository->findEnabledProfile('mountains', 'pattern-v1');
    $this->assertIsArray($profile);
    $this->assertSame('mountains', $profile['region_slug']);
    $this->assertSame(1, $profile['profile_version']);
    $this->assertSame(12, (int)$profile['budgets']['total_nodes']['target']);

    $spineRules = $repository->listEnabledRules('mountains', 'pattern-v1', 'spine');
    $this->assertCount(2, $spineRules);
    $this->assertSame(['shared_combat_step', 'shared_hazard_rest'], array_column($spineRules, 'pattern_slug'));

    $patterns = $repository->listEnabledPatternDefinitions();
    $this->assertCount(6, $patterns);
    $this->assertSame('shared_boss_exit_terminal', $patterns[0]['slug']);
    $this->assertSame('shared_boss_exit_terminal', $patterns[0]['definition']['slug']);
  }
}
