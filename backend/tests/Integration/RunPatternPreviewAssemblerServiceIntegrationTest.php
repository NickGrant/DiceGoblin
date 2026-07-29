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
    $this->assertGreaterThanOrEqual(3, count(array_unique(array_filter(array_column($result['graph']['nodes'], 'branch_key')))));
    $this->assertLessThanOrEqual(3, $this->maxStraightSpineNodes($result['graph']['nodes']));
    $this->assertSame([1, 2], $this->nonExitSpineRows($result['graph']['nodes']));
    $this->assertContains(0, $this->branchRows($result['graph']['nodes']));
    $this->assertGreaterThanOrEqual(3, max($this->branchRows($result['graph']['nodes'])));
    $this->assertGreaterThanOrEqual(
      $this->nodeX($result['graph']['nodes'], 'boss') - 3,
      $this->maxBranchX($result['graph']['nodes'])
    );
    $this->assertTrue($this->hasBranchRejoin($result['graph']['nodes'], $result['graph']['edges']));
    $this->assertFalse($this->hasCrossingEdges($result['graph']['nodes'], $result['graph']['edges']));
    $this->assertGreaterThanOrEqual(1, $result['trace']['counters']['placements']);
  }

  public function testAssemblesPatternV2PreviewGraphFromSeededDatabaseCatalog(): void
  {
    $this->applyPatternV2Migration();
    $request = (new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo)))->build('mountains', 'preview-v2-seed-1', 'pattern-v2');

    $result = (new RunPatternPreviewAssemblerService())->assemble($request);
    $sameResult = (new RunPatternPreviewAssemblerService())->assemble($request);

    $this->assertTrue($result['validation']['valid'], implode(', ', $result['validation']['errors']));
    $this->assertSame($result['graph'], $sameResult['graph']);
    $this->assertGreaterThanOrEqual(15, count($result['graph']['nodes']));
    $this->assertContains('start', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('rest', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('boss', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('exit', array_column($result['graph']['nodes'], 'type'));
    $this->assertGreaterThanOrEqual(3, count(array_unique(array_column($result['graph']['nodes'], 'y'))));
    $this->assertGreaterThanOrEqual(4, $result['trace']['counters']['placements']);
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @return list<int>
   */
  private function nonExitSpineRows(array $nodes): array
  {
    $rows = [];
    foreach ($nodes as $node) {
      if ((string)($node['path_role'] ?? '') !== 'spine' || (string)($node['type'] ?? '') === 'exit') {
        continue;
      }

      $rows[] = (int)($node['y'] ?? 0);
    }

    $rows = array_values(array_unique($rows));
    sort($rows);
    return $rows;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @return list<int>
   */
  private function branchRows(array $nodes): array
  {
    $rows = [];
    foreach ($nodes as $node) {
      if ((string)($node['path_role'] ?? '') !== 'branch') {
        continue;
      }

      $rows[] = (int)($node['y'] ?? 0);
    }

    return $rows;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function nodeX(array $nodes, string $type): int
  {
    foreach ($nodes as $node) {
      if ((string)($node['type'] ?? '') === $type) {
        return (int)($node['x'] ?? 0);
      }
    }

    $this->fail("Missing {$type} node.");
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function maxBranchX(array $nodes): int
  {
    $max = 0;
    foreach ($nodes as $node) {
      if ((string)($node['path_role'] ?? '') !== 'branch') {
        continue;
      }

      $max = max($max, (int)($node['x'] ?? 0));
    }

    return $max;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function maxStraightSpineNodes(array $nodes): int
  {
    $spine = array_values(array_filter($nodes, static function (array $node): bool {
      return (string)($node['path_role'] ?? '') === 'spine';
    }));
    usort($spine, static function (array $left, array $right): int {
      $leftX = (int)($left['x'] ?? 0);
      $rightX = (int)($right['x'] ?? 0);
      if ($leftX !== $rightX) {
        return $leftX <=> $rightX;
      }
      return ((int)($left['depth'] ?? 0)) <=> ((int)($right['depth'] ?? 0));
    });

    $max = 0;
    $current = 0;
    $previous = null;
    foreach ($spine as $node) {
      $type = (string)($node['type'] ?? '');
      if (in_array($type, ['boss', 'exit'], true)) {
        $previous = null;
        $current = 0;
        continue;
      }

      if ($previous !== null
        && (int)($node['x'] ?? 0) === ((int)($previous['x'] ?? 0)) + 1
        && (int)($node['y'] ?? 0) === (int)($previous['y'] ?? 0)
      ) {
        $current++;
      } else {
        $current = 1;
      }

      $max = max($max, $current);
      $previous = $node;
    }

    return $max;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<array<string,mixed>> $edges
   */
  private function hasBranchRejoin(array $nodes, array $edges): bool
  {
    $nodesByKey = [];
    foreach ($nodes as $node) {
      $nodesByKey[(string)($node['key'] ?? '')] = $node;
    }

    foreach ($edges as $edge) {
      $source = $nodesByKey[(string)($edge['from'] ?? '')] ?? null;
      $target = $nodesByKey[(string)($edge['to'] ?? '')] ?? null;
      if ($source === null || $target === null) {
        continue;
      }

      if ((string)($source['branch_key'] ?? '') !== ''
        && (string)($target['path_role'] ?? '') === 'spine'
      ) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<array<string,mixed>> $edges
   */
  private function hasCrossingEdges(array $nodes, array $edges): bool
  {
    $nodesByKey = [];
    foreach ($nodes as $node) {
      $nodesByKey[(string)($node['key'] ?? '')] = $node;
    }

    $edgeCount = count($edges);
    for ($leftIndex = 0; $leftIndex < $edgeCount; $leftIndex++) {
      $leftEdge = $edges[$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $edgeCount; $rightIndex++) {
        $rightEdge = $edges[$rightIndex];
        $leftFrom = (string)($leftEdge['from'] ?? '');
        $leftTo = (string)($leftEdge['to'] ?? '');
        $rightFrom = (string)($rightEdge['from'] ?? '');
        $rightTo = (string)($rightEdge['to'] ?? '');
        if ($leftFrom === $rightFrom || $leftFrom === $rightTo || $leftTo === $rightFrom || $leftTo === $rightTo) {
          continue;
        }

        $a = $nodesByKey[$leftFrom] ?? null;
        $b = $nodesByKey[$leftTo] ?? null;
        $c = $nodesByKey[$rightFrom] ?? null;
        $d = $nodesByKey[$rightTo] ?? null;
        if ($a === null || $b === null || $c === null || $d === null) {
          continue;
        }

        if ($this->segmentsIntersect(
          (int)($a['x'] ?? 0),
          (int)($a['y'] ?? 0),
          (int)($b['x'] ?? 0),
          (int)($b['y'] ?? 0),
          (int)($c['x'] ?? 0),
          (int)($c['y'] ?? 0),
          (int)($d['x'] ?? 0),
          (int)($d['y'] ?? 0),
        )) {
          return true;
        }
      }
    }

    return false;
  }

  private function segmentsIntersect(int $ax, int $ay, int $bx, int $by, int $cx, int $cy, int $dx, int $dy): bool
  {
    $o1 = $this->orientation($ax, $ay, $bx, $by, $cx, $cy);
    $o2 = $this->orientation($ax, $ay, $bx, $by, $dx, $dy);
    $o3 = $this->orientation($cx, $cy, $dx, $dy, $ax, $ay);
    $o4 = $this->orientation($cx, $cy, $dx, $dy, $bx, $by);

    return $o1 !== $o2 && $o3 !== $o4;
  }

  private function orientation(int $ax, int $ay, int $bx, int $by, int $cx, int $cy): int
  {
    $value = (($by - $ay) * ($cx - $bx)) - (($bx - $ax) * ($cy - $by));
    if ($value === 0) {
      return 0;
    }

    return $value > 0 ? 1 : 2;
  }

  private function applyPatternV2Migration(): void
  {
    $path = dirname(__DIR__, 2) . '/migrations/79_seed_pattern_v2_catalog.sql';
    $sql = file_get_contents($path);
    $this->assertIsString($sql);
    $this->pdo->exec($sql);
  }
}
