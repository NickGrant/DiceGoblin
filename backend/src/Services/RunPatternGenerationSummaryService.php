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
}
