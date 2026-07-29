<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class RunPatternGenerationSummaryService
{
  /**
   * @param array<string,mixed> $request
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @param array<string,mixed> $traceSummary
   * @return array<string,mixed>
   */
  public function summarize(array $request, array $graph, array $traceSummary): array
  {
    $nodes = array_values(array_filter($graph['nodes'] ?? [], 'is_array'));
    $edges = array_values(array_filter($graph['edges'] ?? [], 'is_array'));
    $nodeTypes = [];
    $patternFrequency = [];

    foreach ($nodes as $node) {
      $type = (string)($node['type'] ?? $node['node_type'] ?? 'unknown');
      $nodeTypes[$type] = ($nodeTypes[$type] ?? 0) + 1;

      $patternKey = (string)($node['pattern_key'] ?? '');
      if ($patternKey !== '') {
        $patternFrequency[$patternKey] = ($patternFrequency[$patternKey] ?? 0) + 1;
      }
    }

    ksort($nodeTypes);
    ksort($patternFrequency);

    return [
      'generator_version' => (string)($request['generator_version'] ?? 'pattern-v1'),
      'profile_version' => (int)($request['profile_version'] ?? 0),
      'catalog_hash' => (string)($request['catalog_hash'] ?? ''),
      'seed' => (string)($request['seed'] ?? ''),
      'region_slug' => (string)($request['region_slug'] ?? ''),
      'node_count' => count($nodes),
      'edge_count' => count($edges),
      'node_types' => $nodeTypes,
      'pattern_frequency' => $patternFrequency,
      'spine_depth' => $this->spineDepth($nodes),
      'branch_count' => $this->branchCount($nodes),
      'occupied_rows' => $this->occupiedCoordinateCount($nodes, 'y'),
      'occupied_columns' => $this->occupiedCoordinateCount($nodes, 'x'),
      'max_straight_spine_nodes' => $this->maxStraightSpineNodes($nodes),
      'boss_path' => $this->bossPathMetrics($nodes, $edges),
      'story_placement_requests' => array_values(array_filter(
        is_array($request['story_placement_requests'] ?? null) ? $request['story_placement_requests'] : [],
        'is_array',
      )),
      'trace' => [
        'counters' => is_array($traceSummary['counters'] ?? null) ? $traceSummary['counters'] : [],
        'event_count' => (int)($traceSummary['event_count'] ?? 0),
        'truncated' => (bool)($traceSummary['truncated'] ?? false),
        'duration_ms' => $traceSummary['duration_ms'] ?? null,
      ],
    ];
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function spineDepth(array $nodes): int
  {
    $max = 0;
    foreach ($nodes as $node) {
      if ((string)($node['path_role'] ?? '') === 'spine') {
        $max = max($max, (int)($node['depth'] ?? 0));
      }
    }
    return $max;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function branchCount(array $nodes): int
  {
    $branches = [];
    foreach ($nodes as $node) {
      $branchKey = (string)($node['branch_key'] ?? '');
      if ($branchKey !== '') {
        $branches[$branchKey] = true;
      }
    }
    return count($branches);
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function occupiedCoordinateCount(array $nodes, string $coordinate): int
  {
    $occupied = [];
    foreach ($nodes as $node) {
      if (array_key_exists($coordinate, $node)) {
        $occupied[(int)$node[$coordinate]] = true;
      }
    }
    return count($occupied);
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function maxStraightSpineNodes(array $nodes): int
  {
    $spine = array_values(array_filter($nodes, static function (array $node): bool {
      return (string)($node['path_role'] ?? '') === 'spine';
    }));
    if ($spine === []) {
      return 0;
    }

    usort($spine, static function (array $left, array $right): int {
      $leftX = (int)($left['x'] ?? 0);
      $rightX = (int)($right['x'] ?? 0);
      if ($leftX !== $rightX) {
        return $leftX <=> $rightX;
      }
      return ((int)($left['depth'] ?? 0)) <=> ((int)($right['depth'] ?? 0));
    });

    $max = 1;
    $current = 1;
    $previous = null;
    foreach ($spine as $node) {
      $type = (string)($node['type'] ?? $node['node_type'] ?? '');
      if (in_array($type, ['boss', 'exit'], true)) {
        $previous = null;
        $current = 1;
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
   * @return array{start_to_boss:int|null,boss_to_exit:int|null}
   */
  private function bossPathMetrics(array $nodes, array $edges): array
  {
    $start = $this->firstNodeKeyByType($nodes, 'start');
    $boss = $this->firstNodeKeyByType($nodes, 'boss');
    $exit = $this->firstNodeKeyByType($nodes, 'exit');

    return [
      'start_to_boss' => $start !== null && $boss !== null
        ? $this->shortestPathLength($start, $boss, $edges)
        : null,
      'boss_to_exit' => $boss !== null && $exit !== null
        ? $this->shortestPathLength($boss, $exit, $edges)
        : null,
    ];
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function firstNodeKeyByType(array $nodes, string $type): ?string
  {
    foreach ($nodes as $node) {
      if ((string)($node['type'] ?? $node['node_type'] ?? '') === $type) {
        $key = (string)($node['key'] ?? $node['node_key'] ?? '');
        return $key === '' ? null : $key;
      }
    }

    return null;
  }

  /**
   * @param list<array<string,mixed>> $edges
   */
  private function shortestPathLength(string $from, string $to, array $edges): ?int
  {
    $adjacency = [];
    foreach ($edges as $edge) {
      $source = (string)($edge['from'] ?? $edge['from_node_key'] ?? '');
      $target = (string)($edge['to'] ?? $edge['to_node_key'] ?? '');
      if ($source !== '' && $target !== '') {
        $adjacency[$source][] = $target;
      }
    }

    $queue = [[$from, 0]];
    $seen = [];
    while ($queue !== []) {
      [$current, $distance] = array_shift($queue);
      if (!is_string($current) || isset($seen[$current])) {
        continue;
      }
      if ($current === $to) {
        return (int)$distance;
      }

      $seen[$current] = true;
      foreach ($adjacency[$current] ?? [] as $next) {
        $queue[] = [$next, ((int)$distance) + 1];
      }
    }

    return null;
  }
}
