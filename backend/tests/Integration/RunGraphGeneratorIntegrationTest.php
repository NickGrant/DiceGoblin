<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\RunGraphGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunGraphGeneratorIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run-graph generation integration tests.';
  }

  public function testFarmGraphRemainsLinearTutorialRun(): void
  {
    $regionId = $this->seededRegionId('the_farm');
    $generator = new RunGraphGenerator($this->pdo);

    $graph = $generator->generate($regionId, 'the_farm', 'farm-seed');

    $this->assertCount(5, $graph['nodes']);
    $this->assertCount(4, $graph['edges']);
    $this->assertSame(
      ['combat', 'loot', 'rest', 'boss', 'exit'],
      array_map(static fn(array $node): string => (string)$node['node_type'], $graph['nodes']),
    );
    $this->assertSame('available', (string)$graph['nodes'][0]['status']);
    $this->assertSame(
      [
        ['from' => 0, 'to' => 1],
        ['from' => 1, 'to' => 2],
        ['from' => 2, 'to' => 3],
        ['from' => 3, 'to' => 4],
      ],
      $graph['edges'],
    );
  }

  /**
   * @dataProvider proceduralRegionProvider
   */
  public function testProceduralRegionsGenerateValidBranchingGraphs(string $regionSlug): void
  {
    $regionId = $this->seededRegionId($regionSlug);
    $generator = new RunGraphGenerator($this->pdo);

    $graph = $generator->generate($regionId, $regionSlug, '424242');
    $analysis = $this->analyzeGraph($graph);

    $availableNodes = array_values(array_filter(
      $graph['nodes'],
      static fn(array $node): bool => (string)$node['status'] === 'available',
    ));
    $bossNodes = array_values(array_filter(
      $graph['nodes'],
      static fn(array $node): bool => (string)$node['node_type'] === 'boss',
    ));
    $exitNodes = array_values(array_filter(
      $graph['nodes'],
      static fn(array $node): bool => (string)$node['node_type'] === 'exit',
    ));

    $this->assertCount(1, $availableNodes);
    $this->assertCount(1, $bossNodes);
    $this->assertCount(1, $exitNodes);
    $this->assertGreaterThanOrEqual(2, count($analysis['start_children']));
    $this->assertTrue(isset($analysis['reachable_from_start'][$analysis['boss_index']]));
    $this->assertTrue(isset($analysis['reachable_from_start'][$analysis['exit_index']]));
    $this->assertTrue(isset($analysis['reachable_from_boss'][$analysis['exit_index']]));
    $this->assertGreaterThanOrEqual(1, $analysis['branch_count']);
    $this->assertGreaterThanOrEqual(1, count($analysis['dead_end_indexes']));
    $this->assertSame([], $analysis['backward_edges']);
    $this->assertSame([], $analysis['duplicate_edges']);
    $this->assertSame([], $analysis['crossing_edges']);
    $this->assertSame($analysis['max_col'] - 1, $analysis['boss_col']);
    $this->assertSame($analysis['max_col'], $analysis['exit_col']);

    foreach ($graph['nodes'] as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $this->assertGreaterThanOrEqual(0, (int)($meta['row'] ?? -1));
      $this->assertLessThanOrEqual(10, (int)($meta['row'] ?? -1));
      $this->assertGreaterThanOrEqual(0, (int)($meta['col'] ?? -1));
    }
  }

  /**
   * @dataProvider proceduralRegionProvider
   */
  public function testProceduralGenerationIsDeterministicAndUsuallyVariesBySeed(string $regionSlug): void
  {
    $regionId = $this->seededRegionId($regionSlug);
    $generator = new RunGraphGenerator($this->pdo);

    $graphA = $generator->generate($regionId, $regionSlug, '555555');
    $graphB = $generator->generate($regionId, $regionSlug, '555555');
    $graphC = $generator->generate($regionId, $regionSlug, '777777');

    $this->assertSame($graphA, $graphB, 'Same seed must produce the same graph.');
    $this->assertNotSame($graphA, $graphC, 'Different seeds should usually produce different graphs.');
  }

  public function testMountainsGenerateMultiLaneHorizontalRoutes(): void
  {
    $regionId = $this->seededRegionId('mountains');
    $generator = new RunGraphGenerator($this->pdo);

    $foundBroadLaneUsage = false;
    for ($seed = 200; $seed < 225; $seed++) {
      $graph = $generator->generate($regionId, 'mountains', (string)$seed);
      $analysis = $this->analyzeGraph($graph);
      if ($analysis['distinct_row_count'] >= 4 && count($analysis['start_children']) >= 2) {
        $foundBroadLaneUsage = true;
        break;
      }
    }

    $this->assertTrue($foundBroadLaneUsage, 'Mountains should regularly generate multi-lane horizontal routes.');
  }

  public function testMountainsCompactRowsAndBreakStraightawaysForReferenceSeed(): void
  {
    $regionId = $this->seededRegionId('mountains');
    $generator = new RunGraphGenerator($this->pdo);

    $graph = $generator->generate($regionId, 'mountains', '7762837825202513111');
    $analysis = $this->analyzeGraph($graph);

    $usedRows = array_values(array_unique(array_map(
      static fn(array $node): int => (int)$node['meta']['row'],
      $graph['nodes'],
    )));
    sort($usedRows);

    $this->assertLessThanOrEqual(5, count($usedRows), 'Reference mountains seed should collapse into a tighter set of rows.');
    $this->assertSame(range(0, count($usedRows) - 1), $usedRows, 'Collapsed rows should be renumbered consecutively.');

    $hasSingleRowShift = false;
    $hasMidRunShortcut = false;
    foreach ($graph['edges'] as $edge) {
      $fromNode = $analysis['node_by_index'][(int)$edge['from']];
      $toNode = $analysis['node_by_index'][(int)$edge['to']];
      $rowDelta = abs((int)$toNode['meta']['row'] - (int)$fromNode['meta']['row']);
      $columnDelta = (int)$toNode['meta']['col'] - (int)$fromNode['meta']['col'];

      if ($rowDelta === 1 && (int)$toNode['meta']['col'] < $analysis['boss_col']) {
        $hasSingleRowShift = true;
      }
      if ($columnDelta > 1 && (int)$fromNode['meta']['col'] > 0 && (int)$toNode['meta']['col'] < $analysis['boss_col']) {
        $hasMidRunShortcut = true;
      }
    }

    $this->assertTrue($hasSingleRowShift, 'Reference mountains seed should include at least one small row shift to break long straight branches.');
    $this->assertTrue($hasMidRunShortcut, 'Reference mountains seed should sometimes add a mid-run shortcut after row collapse.');
  }

  /**
   * @dataProvider proceduralRegionProvider
   */
  public function testProceduralRegionsAvoidRedundantSameRowBypassEdges(string $regionSlug): void
  {
    $regionId = $this->seededRegionId($regionSlug);
    $generator = new RunGraphGenerator($this->pdo);

    for ($seed = 1; $seed <= 80; $seed++) {
      $graph = $generator->generate($regionId, $regionSlug, (string)$seed);
      $analysis = $this->analyzeGraph($graph);

      foreach ($graph['edges'] as $edge) {
        $from = (int)$edge['from'];
        $to = (int)$edge['to'];
        $fromNode = $analysis['node_by_index'][$from];
        $toNode = $analysis['node_by_index'][$to];
        $fromCol = (int)$fromNode['meta']['col'];
        $fromRow = (int)$fromNode['meta']['row'];
        $toCol = (int)$toNode['meta']['col'];

        if (($toCol - $fromCol) <= 1) {
          continue;
        }

        foreach ($analysis['outgoing'][$from] as $childIndex) {
          $childNode = $analysis['node_by_index'][$childIndex];
          $childCol = (int)$childNode['meta']['col'];
          $childRow = (int)$childNode['meta']['row'];
          if ($childCol !== ($fromCol + 1) || $childRow !== $fromRow) {
            continue;
          }

          $this->assertNotContains(
            $to,
            $analysis['outgoing'][$childIndex],
            sprintf(
              'Seed %d for %s should not contain same-row bypass triangle %d->%d->%d alongside %d->%d.',
              $seed,
              $regionSlug,
              $from,
              $childIndex,
              $to,
              $from,
              $to,
            ),
          );
        }
      }
    }
  }

  /**
   * @dataProvider proceduralRegionProvider
   */
  public function testProceduralRegionsAlwaysIncludeAtLeastOneRestNode(string $regionSlug): void
  {
    $regionId = $this->seededRegionId($regionSlug);
    $generator = new RunGraphGenerator($this->pdo);

    for ($seed = 1; $seed <= 60; $seed++) {
      $graph = $generator->generate($regionId, $regionSlug, (string)$seed);
      $restNodes = array_values(array_filter(
        $graph['nodes'],
        static fn(array $node): bool => (string)($node['node_type'] ?? '') === 'rest',
      ));

      $this->assertNotEmpty($restNodes, sprintf(
        'Seed %d for %s should include at least one rest node.',
        $seed,
        $regionSlug,
      ));
    }
  }

  public function testSwampsFavorBroadDeadEndHeavyLayouts(): void
  {
    $regionId = $this->seededRegionId('swamps');
    $generator = new RunGraphGenerator($this->pdo);

    $foundBroadLayout = false;
    for ($seed = 300; $seed < 325; $seed++) {
      $graph = $generator->generate($regionId, 'swamps', (string)$seed);
      $analysis = $this->analyzeGraph($graph);
      if ($analysis['max_children_from_single_node'] >= 3 && count($analysis['dead_end_indexes']) >= 2) {
        $foundBroadLayout = true;
        break;
      }
    }

    $this->assertTrue($foundBroadLayout, 'Swamps should regularly generate broader branch fans with multiple dead ends.');
  }

  /**
   * @dataProvider proceduralRegionProvider
   */
  public function testProceduralDeadEndsRemainOptionalAcrossManySeeds(string $regionSlug): void
  {
    $regionId = $this->seededRegionId($regionSlug);
    $generator = new RunGraphGenerator($this->pdo);

    for ($seed = 100; $seed < 140; $seed++) {
      $graph = $generator->generate($regionId, $regionSlug, (string)$seed);
      $analysis = $this->analyzeGraph($graph);

      foreach ($analysis['dead_end_indexes'] as $deadEndIndex) {
        $nodeType = (string)$analysis['node_by_index'][$deadEndIndex]['node_type'];
        $this->assertNotContains($nodeType, ['boss', 'exit']);

        foreach ($analysis['incoming'][$deadEndIndex] as $parentIndex) {
          $alternateChildren = array_values(array_filter(
            $analysis['outgoing'][$parentIndex],
            static fn(int $childIndex): bool => $childIndex !== $deadEndIndex,
          ));

          $this->assertNotSame([], $alternateChildren, 'Dead-end parent must have an alternate route.');

          $hasBossRoute = false;
          foreach ($alternateChildren as $alternateChild) {
            $reachable = $this->reachableFrom($alternateChild, $analysis['outgoing']);
            if (isset($reachable[$analysis['boss_index']])) {
              $hasBossRoute = true;
              break;
            }
          }

          $this->assertTrue($hasBossRoute, 'Alternate route must still lead to the boss.');
        }
      }
    }
  }

  /**
   * @dataProvider proceduralRegionProvider
   */
  public function testProceduralMapsAvoidCrossingPathsAcrossManySeeds(string $regionSlug): void
  {
    $regionId = $this->seededRegionId($regionSlug);
    $generator = new RunGraphGenerator($this->pdo);

    for ($seed = 150; $seed < 220; $seed++) {
      $graph = $generator->generate($regionId, $regionSlug, (string)$seed);
      $analysis = $this->analyzeGraph($graph);
      $this->assertSame([], $analysis['crossing_edges'], sprintf(
        'Seed %s for %s produced crossing edges: %s',
        (string)$seed,
        $regionSlug,
        implode(', ', $analysis['crossing_edges']),
      ));
    }
  }

  /**
   * @return array<int,array{0:string}>
   */
  public function proceduralRegionProvider(): array
  {
    return [
      ['mountains'],
      ['swamps'],
    ];
  }

  private function seededRegionId(string $slug): int
  {
    $regionId = (int)$this->scalar('SELECT `id` FROM `regions` WHERE `slug` = ? LIMIT 1', [$slug]);
    $this->assertGreaterThan(0, $regionId, sprintf('Seeded region `%s` must exist.', $slug));
    return $regionId;
  }

  /**
   * @param array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>} $graph
   * @return array{
   *   node_by_index: array<int,array<string,mixed>>,
   *   outgoing: array<int,array<int,int>>,
   *   incoming: array<int,array<int,int>>,
   *   reachable_from_start: array<int,bool>,
   *   reachable_from_boss: array<int,bool>,
   *   boss_index: int,
   *   exit_index: int,
   *   branch_count: int,
   *   max_children_from_single_node: int,
   *   dead_end_indexes: array<int,int>,
   *   start_children: array<int,int>,
   *   distinct_row_count: int,
   *   backward_edges: array<int,string>,
   *   duplicate_edges: array<int,string>,
   *   crossing_edges: array<int,string>,
   *   boss_col: int,
   *   exit_col: int,
   *   max_col: int
   * }
   */
  private function analyzeGraph(array $graph): array
  {
    $nodeByIndex = [];
    $outgoing = [];
    $incoming = [];
    $startIndex = -1;
    $bossIndex = -1;
    $exitIndex = -1;

    foreach ($graph['nodes'] as $node) {
      $nodeIndex = (int)$node['node_index'];
      $nodeByIndex[$nodeIndex] = $node;
      $outgoing[$nodeIndex] = [];
      $incoming[$nodeIndex] = [];
      if ((string)$node['status'] === 'available') {
        $startIndex = $nodeIndex;
      }
      if ((string)$node['node_type'] === 'boss') {
        $bossIndex = $nodeIndex;
      }
      if ((string)$node['node_type'] === 'exit') {
        $exitIndex = $nodeIndex;
      }
    }

    $edgeKeys = [];
    $backwardEdges = [];
    $duplicateEdges = [];
    $crossingEdges = [];
    foreach ($graph['edges'] as $edge) {
      $from = (int)$edge['from'];
      $to = (int)$edge['to'];
      $key = $from . '->' . $to;
      if (isset($edgeKeys[$key])) {
        $duplicateEdges[] = $key;
      }
      $edgeKeys[$key] = true;

      if ((int)$nodeByIndex[$to]['meta']['col'] <= (int)$nodeByIndex[$from]['meta']['col']) {
        $backwardEdges[] = $key;
      }

      $outgoing[$from][] = $to;
      $incoming[$to][] = $from;
    }

    $branchCount = count(array_filter(
      $outgoing,
      static fn(array $targets): bool => count($targets) > 1,
    ));
    $maxChildrenFromSingleNode = max(array_map(static fn(array $targets): int => count($targets), $outgoing));

    $deadEndIndexes = array_values(array_filter(
      array_keys($nodeByIndex),
      static fn(int $nodeIndex): bool => count($outgoing[$nodeIndex]) === 0
        && !in_array((string)$nodeByIndex[$nodeIndex]['node_type'], ['boss', 'exit'], true),
    ));

    $startChildren = $outgoing[$startIndex] ?? [];
    $distinctRowCount = count(array_unique(array_map(
      static fn(array $node): int => (int)$node['meta']['row'],
      $graph['nodes'],
    )));

    $edgeCount = count($graph['edges']);
    for ($leftIndex = 0; $leftIndex < $edgeCount; $leftIndex++) {
      $leftEdge = $graph['edges'][$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $edgeCount; $rightIndex++) {
        $rightEdge = $graph['edges'][$rightIndex];
        if (
          $leftEdge['from'] === $rightEdge['from']
          || $leftEdge['from'] === $rightEdge['to']
          || $leftEdge['to'] === $rightEdge['from']
          || $leftEdge['to'] === $rightEdge['to']
        ) {
          continue;
        }

        if ($this->segmentsIntersect(
          (int)$nodeByIndex[(int)$leftEdge['from']]['meta']['col'],
          (int)$nodeByIndex[(int)$leftEdge['from']]['meta']['row'],
          (int)$nodeByIndex[(int)$leftEdge['to']]['meta']['col'],
          (int)$nodeByIndex[(int)$leftEdge['to']]['meta']['row'],
          (int)$nodeByIndex[(int)$rightEdge['from']]['meta']['col'],
          (int)$nodeByIndex[(int)$rightEdge['from']]['meta']['row'],
          (int)$nodeByIndex[(int)$rightEdge['to']]['meta']['col'],
          (int)$nodeByIndex[(int)$rightEdge['to']]['meta']['row'],
        )) {
          $crossingEdges[] = sprintf(
            '%d->%d x %d->%d',
            (int)$leftEdge['from'],
            (int)$leftEdge['to'],
            (int)$rightEdge['from'],
            (int)$rightEdge['to'],
          );
        }
      }
    }

    $bossCol = (int)$nodeByIndex[$bossIndex]['meta']['col'];
    $exitCol = (int)$nodeByIndex[$exitIndex]['meta']['col'];
    $maxCol = max(array_map(
      static fn(array $node): int => (int)$node['meta']['col'],
      $graph['nodes'],
    ));

    return [
      'node_by_index' => $nodeByIndex,
      'outgoing' => $outgoing,
      'incoming' => $incoming,
      'reachable_from_start' => $this->reachableFrom($startIndex, $outgoing),
      'reachable_from_boss' => $this->reachableFrom($bossIndex, $outgoing),
      'boss_index' => $bossIndex,
      'exit_index' => $exitIndex,
      'branch_count' => $branchCount,
      'max_children_from_single_node' => $maxChildrenFromSingleNode,
      'dead_end_indexes' => $deadEndIndexes,
      'start_children' => $startChildren,
      'distinct_row_count' => $distinctRowCount,
      'backward_edges' => $backwardEdges,
      'duplicate_edges' => $duplicateEdges,
      'crossing_edges' => $crossingEdges,
      'boss_col' => $bossCol,
      'exit_col' => $exitCol,
      'max_col' => $maxCol,
    ];
  }

  /**
   * @param array<int,array<int,int>> $outgoing
   * @return array<int,bool>
   */
  private function reachableFrom(int $startIndex, array $outgoing): array
  {
    $visited = [];
    $queue = [$startIndex];

    while ($queue !== []) {
      $current = array_shift($queue);
      if ($current === null || isset($visited[$current])) {
        continue;
      }

      $visited[$current] = true;
      foreach ($outgoing[$current] ?? [] as $next) {
        if (!isset($visited[$next])) {
          $queue[] = $next;
        }
      }
    }

    return $visited;
  }

  private function segmentsIntersect(
    int $ax,
    int $ay,
    int $bx,
    int $by,
    int $cx,
    int $cy,
    int $dx,
    int $dy,
  ): bool {
    $o1 = $this->orientation($ax, $ay, $bx, $by, $cx, $cy);
    $o2 = $this->orientation($ax, $ay, $bx, $by, $dx, $dy);
    $o3 = $this->orientation($cx, $cy, $dx, $dy, $ax, $ay);
    $o4 = $this->orientation($cx, $cy, $dx, $dy, $bx, $by);

    if ($o1 !== $o2 && $o3 !== $o4) {
      return true;
    }

    if ($o1 === 0 && $this->onSegment($ax, $ay, $bx, $by, $cx, $cy)) {
      return true;
    }
    if ($o2 === 0 && $this->onSegment($ax, $ay, $bx, $by, $dx, $dy)) {
      return true;
    }
    if ($o3 === 0 && $this->onSegment($cx, $cy, $dx, $dy, $ax, $ay)) {
      return true;
    }
    if ($o4 === 0 && $this->onSegment($cx, $cy, $dx, $dy, $bx, $by)) {
      return true;
    }

    return false;
  }

  private function orientation(int $ax, int $ay, int $bx, int $by, int $cx, int $cy): int
  {
    $value = (($by - $ay) * ($cx - $bx)) - (($bx - $ax) * ($cy - $by));
    if ($value === 0) {
      return 0;
    }

    return $value > 0 ? 1 : 2;
  }

  private function onSegment(int $ax, int $ay, int $bx, int $by, int $px, int $py): bool
  {
    return $px >= min($ax, $bx)
      && $px <= max($ax, $bx)
      && $py >= min($ay, $by)
      && $py <= max($ay, $by);
  }
}
