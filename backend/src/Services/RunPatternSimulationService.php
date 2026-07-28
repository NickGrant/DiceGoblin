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
      'validation_failures' => $validationFailures,
      'node_count' => [
        'min' => min($nodeCounts),
        'max' => max($nodeCounts),
        'avg' => round(array_sum($nodeCounts) / count($nodeCounts), 2),
      ],
      'pattern_frequency' => $patternFrequency,
      'results' => $results,
    ];
  }
}
