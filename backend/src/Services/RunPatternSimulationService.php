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
    $edgeCounts = [];
    $spineDepths = [];
    $durations = [];
    $nodeTypeFrequency = [];
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
      $edgeCount = count($assembly['graph']['edges']);
      $edgeCounts[] = $edgeCount;
      $branchCount = $this->branchCount($assembly['graph']['nodes']);
      $branchCounts[] = $branchCount;
      $spineDepth = $this->spineDepth($assembly['graph']['nodes']);
      $spineDepths[] = $spineDepth;
      $traceCounters = is_array($assembly['trace']['counters'] ?? null) ? $assembly['trace']['counters'] : [];
      $backtracks[] = (int)($traceCounters['backtracks'] ?? 0);
      $duration = $assembly['trace']['duration_ms'] ?? null;
      if (is_numeric($duration)) {
        $durations[] = (float)$duration;
      }

      foreach ($assembly['graph']['nodes'] as $node) {
        $nodeType = (string)($node['type'] ?? $node['node_type'] ?? 'unknown');
        $nodeTypeFrequency[$nodeType] = ($nodeTypeFrequency[$nodeType] ?? 0) + 1;

        $patternKey = (string)($node['pattern_key'] ?? '');
        if ($patternKey !== '') {
          $patternFrequency[$patternKey] = ($patternFrequency[$patternKey] ?? 0) + 1;
        }
      }

      $results[] = [
        'seed' => $seed,
        'valid' => $valid,
        'node_count' => $nodeCount,
        'edge_count' => $edgeCount,
        'branch_count' => $branchCount,
        'spine_depth' => $spineDepth,
        'backtracks' => (int)($traceCounters['backtracks'] ?? 0),
        'duration_ms' => $duration,
        'errors' => $assembly['validation']['errors'],
      ];
    }

    ksort($validationFailures);
    ksort($nodeTypeFrequency);
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
      'edge_count' => [
        'min' => min($edgeCounts),
        'max' => max($edgeCounts),
        'avg' => round(array_sum($edgeCounts) / count($edgeCounts), 2),
      ],
      'branch_count' => [
        'min' => min($branchCounts),
        'max' => max($branchCounts),
        'avg' => round(array_sum($branchCounts) / count($branchCounts), 2),
      ],
      'spine_depth' => [
        'min' => min($spineDepths),
        'max' => max($spineDepths),
        'avg' => round(array_sum($spineDepths) / count($spineDepths), 2),
      ],
      'backtracks' => [
        'min' => min($backtracks),
        'max' => max($backtracks),
        'avg' => round(array_sum($backtracks) / count($backtracks), 2),
      ],
      'duration_ms' => $this->distribution($durations),
      'node_type_frequency' => $nodeTypeFrequency,
      'pattern_frequency' => $patternFrequency,
      'results' => $results,
    ];
  }

  /**
   * @param array<string,mixed> $simulation
   * @param array<string,mixed> $options
   * @return array{passed:bool,checks:list<array{name:string,passed:bool,expected:mixed,actual:mixed,message:string}>}
   */
  public function evaluateGate(array $simulation, array $options = []): array
  {
    $checks = [];
    $minSuccessRate = (float)($options['min_success_rate'] ?? 1.0);
    $maxFallbackRate = (float)($options['max_fallback_rate'] ?? 0.0);
    $maxBacktracksAvg = (float)($options['max_backtracks_avg'] ?? 0.0);
    $minBranchCount = (int)($options['min_branch_count'] ?? 1);

    $checks[] = $this->check(
      'success_rate',
      (float)($simulation['success_rate'] ?? 0.0) >= $minSuccessRate,
      $minSuccessRate,
      (float)($simulation['success_rate'] ?? 0.0),
      'Simulation success rate must meet or exceed the configured minimum.'
    );
    $checks[] = $this->check(
      'fallback_rate',
      (float)($simulation['fallback_rate'] ?? 1.0) <= $maxFallbackRate,
      $maxFallbackRate,
      (float)($simulation['fallback_rate'] ?? 1.0),
      'Fallback rate must not exceed the configured maximum.'
    );
    $checks[] = $this->check(
      'validation_failures',
      count(is_array($simulation['validation_failures'] ?? null) ? $simulation['validation_failures'] : []) === 0,
      [],
      $simulation['validation_failures'] ?? null,
      'Simulation must not produce validation failures.'
    );
    $checks[] = $this->check(
      'branch_count_min',
      (int)($simulation['branch_count']['min'] ?? 0) >= $minBranchCount,
      $minBranchCount,
      (int)($simulation['branch_count']['min'] ?? 0),
      'Every generated graph must meet the configured minimum branch count.'
    );
    $checks[] = $this->check(
      'backtracks_avg',
      (float)($simulation['backtracks']['avg'] ?? 0.0) <= $maxBacktracksAvg,
      $maxBacktracksAvg,
      (float)($simulation['backtracks']['avg'] ?? 0.0),
      'Average backtracks must not exceed the configured maximum.'
    );

    $passed = true;
    foreach ($checks as $check) {
      if (!$check['passed']) {
        $passed = false;
      }
    }

    return [
      'passed' => $passed,
      'checks' => $checks,
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
   * @param list<float> $values
   * @return array{min:float|null,max:float|null,avg:float|null}
   */
  private function distribution(array $values): array
  {
    if ($values === []) {
      return ['min' => null, 'max' => null, 'avg' => null];
    }

    return [
      'min' => round(min($values), 2),
      'max' => round(max($values), 2),
      'avg' => round(array_sum($values) / count($values), 2),
    ];
  }

  /**
   * @return array{name:string,passed:bool,expected:mixed,actual:mixed,message:string}
   */
  private function check(string $name, bool $passed, mixed $expected, mixed $actual, string $message): array
  {
    return [
      'name' => $name,
      'passed' => $passed,
      'expected' => $expected,
      'actual' => $actual,
      'message' => $message,
    ];
  }
}
