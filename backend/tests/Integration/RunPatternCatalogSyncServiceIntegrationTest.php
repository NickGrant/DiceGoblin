<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\RunPatternCatalogSyncService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunPatternCatalogSyncServiceIntegrationTest extends IntegrationTestCase
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
    return 'Set TEST_DB_DSN to run run-pattern catalog sync integration tests.';
  }

  public function testSyncDefaultCatalogMirrorsPatternsRulesAndProfiles(): void
  {
    $service = new RunPatternCatalogSyncService($this->pdo);

    $result = $service->syncDefaultCatalog();

    $this->assertSame(6, $result['patterns']);
    $this->assertSame(12, $result['rules']);
    $this->assertSame(2, $result['profiles']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['catalog_hash']);
    $this->assertSame('6', (string)$this->scalar('SELECT COUNT(*) FROM `run_pattern_definitions`', []));
    $this->assertSame('12', (string)$this->scalar('SELECT COUNT(*) FROM `run_pattern_region_rules`', []));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `run_generation_profiles`', []));

    $mountainsBudgetRaw = (string)$this->scalar(
      "SELECT `budgets_json`
       FROM `run_generation_profiles` rgp
       INNER JOIN `regions` r ON r.`id` = rgp.`region_id`
       WHERE r.`slug` = 'mountains' AND rgp.`generator_version` = 'pattern-v1'",
      []
    );
    $mountainsBudget = json_decode($mountainsBudgetRaw, true);
    $this->assertIsArray($mountainsBudget);
    $this->assertSame(12, (int)($mountainsBudget['total_nodes']['target'] ?? 0));

    $secondResult = $service->syncDefaultCatalog();
    $this->assertSame($result, $secondResult);
    $this->assertSame('6', (string)$this->scalar('SELECT COUNT(*) FROM `run_pattern_definitions`', []));
    $this->assertSame('12', (string)$this->scalar('SELECT COUNT(*) FROM `run_pattern_region_rules`', []));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `run_generation_profiles`', []));
  }
}
