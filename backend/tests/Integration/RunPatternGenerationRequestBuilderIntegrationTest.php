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
}
