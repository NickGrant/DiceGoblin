<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Support\DeterministicRandom;
use DiceGoblins\Support\RunPatternGenerationTrace;
use RuntimeException;

final class RunPatternPreviewAssemblerService
{
  public function __construct(
    private readonly RunGraphValidationService $validator = new RunGraphValidationService()
  ) {
  }

  /**
   * @param array<string,mixed> $request
   * @return array{graph:array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>},trace:array<string,mixed>,validation:array{valid:bool,errors:list<string>}}
   */
  public function assemble(array $request): array
  {
    $trace = new RunPatternGenerationTrace();
    $rng = new DeterministicRandom((string)($request['seed'] ?? 'pattern-preview'));
    $graph = ['nodes' => [], 'edges' => []];
    $cursorX = 0;
    $tail = null;

    foreach ($this->segments($request, $rng) as $segment) {
      $placement = $this->placeVariant($segment['phase'], $segment['pattern_key'], $segment['variant'], $cursorX, $tail);
      $graph['nodes'] = [...$graph['nodes'], ...$placement['nodes']];
      $graph['edges'] = [...$graph['edges'], ...$placement['edges']];
      $tail = $placement['tail'];
      $cursorX += (int)$placement['width'];
      $trace->placement($segment['phase'], $segment['pattern_key'], ['node_count' => count($placement['nodes'])]);
    }

    $validation = $this->validator->validate($graph);
    if (!$validation['valid']) {
      $trace->validationFailure($validation['errors']);
    }

    return [
      'graph' => $graph,
      'trace' => $trace->summary(),
      'validation' => $validation,
    ];
  }

  /**
   * @param array<string,mixed> $request
   * @return list<array{phase:string,pattern_key:string,variant:array<string,mixed>}>
   */
  private function segments(array $request, DeterministicRandom $rng): array
  {
    $segments = [];
    $segments[] = $this->chooseSegment($request, 'start', $rng);

    $target = (int)($request['profile']['budgets']['total_nodes']['target'] ?? 10);
    $currentNodes = (int)$segments[0]['variant']['node_cost'];
    $terminal = $this->chooseSegment($request, 'terminal', $rng);
    $terminalCost = (int)$terminal['variant']['node_cost'];
    while ($currentNodes + $terminalCost < $target) {
      $spine = $this->chooseSegment($request, 'spine', $rng);
      $segments[] = $spine;
      $currentNodes += (int)$spine['variant']['node_cost'];
      if (count($segments) > 20) {
        throw new RuntimeException('Pattern preview assembly exceeded the segment limit.');
      }
    }

    $segments[] = $terminal;
    return $segments;
  }

  /**
   * @param array<string,mixed> $request
   * @return array{phase:string,pattern_key:string,variant:array<string,mixed>}
   */
  private function chooseSegment(array $request, string $phase, DeterministicRandom $rng): array
  {
    $rules = is_array($request['rules_by_phase'][$phase] ?? null) ? $request['rules_by_phase'][$phase] : [];
    if ($rules === []) {
      throw new RuntimeException("No {$phase} rules are available for pattern preview assembly.");
    }

    $rule = $rng->weightedChoice($rules, static fn(array $rule): int => (int)($rule['base_weight'] ?? 0));
    $patternKey = (string)$rule['pattern_slug'] . '@' . (int)$rule['pattern_version'];
    $variants = is_array($request['variants_by_pattern_key'][$patternKey] ?? null) ? $request['variants_by_pattern_key'][$patternKey] : [];
    if ($variants === []) {
      throw new RuntimeException("No variants are available for {$patternKey}.");
    }

    return [
      'phase' => $phase,
      'pattern_key' => $patternKey,
      'variant' => $variants[$rng->nextInt(0, count($variants) - 1)],
    ];
  }

  /**
   * @param array<string,mixed> $variant
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>,tail:string,width:int}
   */
  private function placeVariant(string $phase, string $patternKey, array $variant, int $offsetX, ?string $previousTail): array
  {
    $nodes = [];
    $keyMap = [];
    foreach (array_values(array_filter(is_array($variant['nodes'] ?? null) ? $variant['nodes'] : [], 'is_array')) as $index => $node) {
      $sourceKey = (string)$node['key'];
      $key = "{$patternKey}:{$phase}:{$offsetX}:{$sourceKey}:{$index}";
      $keyMap[$sourceKey] = $key;
      $type = (string)($node['type'] ?? 'combat');
      if ((string)($node['role'] ?? '') === 'start') {
        $type = 'start';
      }

      $nodes[] = [
        ...$node,
        'key' => $key,
        'type' => $type,
        'source_type' => (string)($node['type'] ?? $type),
        'x' => $offsetX + (int)($node['x'] ?? 0),
        'y' => (int)($node['y'] ?? 0),
        'pattern_key' => $patternKey,
        'path_role' => $phase === 'branch' ? 'branch' : 'spine',
        'depth' => $offsetX + (int)($node['x'] ?? 0),
      ];
    }

    $edges = [];
    foreach (array_values(array_filter(is_array($variant['edges'] ?? null) ? $variant['edges'] : [], 'is_array')) as $edge) {
      $from = $keyMap[(string)$edge['from']] ?? null;
      $to = $keyMap[(string)$edge['to']] ?? null;
      if ($from !== null && $to !== null) {
        $edges[] = ['from' => $from, 'to' => $to];
      }
    }

    $head = $this->socketNode($variant, $keyMap, 'entry') ?? ($nodes[0]['key'] ?? null);
    if ($previousTail !== null && is_string($head)) {
      $edges[] = ['from' => $previousTail, 'to' => $head];
    }

    $tail = $this->socketNode($variant, $keyMap, 'exit') ?? ($nodes[array_key_last($nodes)]['key'] ?? null);
    if (!is_string($tail)) {
      throw new RuntimeException("Pattern {$patternKey} did not place any nodes.");
    }

    $footprint = is_array($variant['footprint'] ?? null) ? $variant['footprint'] : [];
    return [
      'nodes' => $nodes,
      'edges' => $edges,
      'tail' => $tail,
      'width' => max(1, (int)($footprint['width'] ?? count($nodes))),
    ];
  }

  /**
   * @param array<string,mixed> $variant
   * @param array<string,string> $keyMap
   */
  private function socketNode(array $variant, array $keyMap, string $kind): ?string
  {
    foreach (array_values(array_filter(is_array($variant['sockets'] ?? null) ? $variant['sockets'] : [], 'is_array')) as $socket) {
      if ((string)($socket['kind'] ?? '') === $kind) {
        return $keyMap[(string)($socket['node'] ?? '')] ?? null;
      }
    }
    return null;
  }
}
