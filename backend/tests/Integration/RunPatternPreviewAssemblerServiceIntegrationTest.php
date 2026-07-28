<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternCatalogSyncService;
use DiceGoblins\Services\RunPatternGenerationRequestBuilder;
use DiceGoblins\Services\RunPatternPreviewAssemblerService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunPatternPreviewAssemblerServiceIntegrationTest extends IntegrationTestCase
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
    return 'Set TEST_DB_DSN to run run-pattern preview assembler integration tests.';
  }

  public function testAssemblesValidDeterministicPreviewGraphFromCatalogRequest(): void
  {
    (new RunPatternCatalogSyncService($this->pdo))->syncDefaultCatalog();
    $request = (new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo)))->build('mountains', 'preview-seed-1');

    $result = (new RunPatternPreviewAssemblerService())->assemble($request);
    $sameResult = (new RunPatternPreviewAssemblerService())->assemble($request);

    $this->assertTrue($result['validation']['valid'], implode(', ', $result['validation']['errors']));
    $this->assertSame($result['graph'], $sameResult['graph']);
    $this->assertGreaterThanOrEqual(10, count($result['graph']['nodes']));
    $this->assertContains('start', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('chaos', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('rest', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('boss', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('exit', array_column($result['graph']['nodes'], 'type'));
    $this->assertGreaterThanOrEqual(1, count(array_unique(array_filter(array_column($result['graph']['nodes'], 'branch_key')))));
    $this->assertGreaterThanOrEqual(1, $result['trace']['counters']['placements']);
  }
}
