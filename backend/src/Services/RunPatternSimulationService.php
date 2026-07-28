<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class RunPatternSimulationService
{
  public function __construct(
    private readonly RunPatternGenerationRequestBuilder $requestBuilder,
    private readonly RunPatternPreviewAssemblerService $assembler = new RunPatternPreviewAssemblerService()
  ) {
  }

  /**
   * @return array<string,mixed>
   */
  public function simulate(string $regionSlug, int $runs, string $seedPrefix = 'sim', string $generatorVersion = 'pattern-v1'): array
  {
    $runs = max(1, $runs);
    $results = [];
    $successes = 0;
    $validationFailures = [];
    $nodeCounts = [];
    $branchCounts = [];
    $backtracks = [];
    $patternFrequency = [];

    for ($i = 1; $i <= $runs; $i++) {
      $seed = "{$seedPrefix}-{$i}";
      $request = $this->requestBuilder->build($regionSlug, $seed, $generatorVersion);
      $assembly = $this->assembler->assemble($request);
      $valid = (bool)$assembly['validation']['valid'];
      if ($valid) {
        $successes++;
      }

      foreach ($assembly['validation']['errors'] as $error) {
        $validationFailures[$error] = ($validationFailures[$error] ?? 0) + 1;
      }

      $nodeCount = count($assembly['graph']['nodes']);
      $nodeCounts[] = $nodeCount;
      $branchCount = $this->branchCount($assembly['graph']['nodes']);
      $branchCounts[] = $branchCount;
      $traceCounters = is_array($assembly['trace']['counters'] ?? null) ? $assembly['trace']['counters'] : [];
      $backtracks[] = (int)($traceCounters['backtracks'] ?? 0);
      foreach ($assembly['graph']['nodes'] as $node) {
        $patternKey = (string)($node['pattern_key'] ?? '');
        if ($patternKey !== '') {
          $patternFrequency[$patternKey] = ($patternFrequency[$patternKey] ?? 0) + 1;
        }
      }

      $results[] = [
        'seed' => $seed,
        'valid' => $valid,
        'node_count' => $nodeCount,
        'edge_count' => count($assembly['graph']['edges']),
        'branch_count' => $branchCount,
        'backtracks' => (int)($traceCounters['backtracks'] ?? 0),
        'errors' => $assembly['validation']['errors'],
      ];
    }

    ksort($validationFailures);
    ksort($patternFrequency);

    return [
      'region_slug' => $regionSlug,
      'generator_version' => $generatorVersion,
      'runs' => $runs,
      'successes' => $successes,
      'success_rate' => round($successes / $runs, 4),
      'fallback_rate' => 0.0,
      'validation_failures' => $validationFailures,
      'node_count' => [
        'min' => min($nodeCounts),
        'max' => max($nodeCounts),
        'avg' => round(array_sum($nodeCounts) / count($nodeCounts), 2),
      ],
      'branch_count' => [
        'min' => min($branchCounts),
        'max' => max($branchCounts),
        'avg' => round(array_sum($branchCounts) / count($branchCounts), 2),
      ],
      'backtracks' => [
        'min' => min($backtracks),
        'max' => max($backtracks),
        'avg' => round(array_sum($backtracks) / count($backtracks), 2),
      ],
      'pattern_frequency' => $patternFrequency,
      'results' => $results,
    ];
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
