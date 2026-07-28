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
      $placement = $this->placeVariant($segment['phase'], $segment['pattern_key'], $segment['variant'], $cursorX, 0, $tail);
      $graph['nodes'] = [...$graph['nodes'], ...$placement['nodes']];
      $graph['edges'] = [...$graph['edges'], ...$placement['edges']];
      $tail = $placement['tail'];
      $cursorX += (int)$placement['width'];
      $trace->placement($segment['phase'], $segment['pattern_key'], ['node_count' => count($placement['nodes'])]);
    }

    $graph = $this->attachBranches($request, $graph, $rng, $trace);

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
    $requiredTypes = $this->requiredNodeTypes($request);
    while ($currentNodes + $terminalCost < $target) {
      $spine = $this->requiredSpineSegment($request, $segments, $requiredTypes, $currentNodes, $terminalCost, $target, $rng)
        ?? $this->chooseSegment($request, 'spine', $rng);
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
   * @param list<array{phase:string,pattern_key:string,variant:array<string,mixed>}> $segments
   * @param array<string,true> $requiredTypes
   * @return array{phase:string,pattern_key:string,variant:array<string,mixed>}|null
   */
  private function requiredSpineSegment(
    array $request,
    array $segments,
    array $requiredTypes,
    int $currentNodes,
    int $terminalCost,
    int $target,
    DeterministicRandom $rng
  ): ?array {
    foreach (['chaos', 'rest'] as $requiredType) {
      if (!isset($requiredTypes[$requiredType]) || $this->segmentsContainNodeType($segments, $requiredType)) {
        continue;
      }
      if ($currentNodes < 3) {
        continue;
      }

      $segment = $this->chooseSegmentWithNodeType($request, 'spine', $requiredType, $rng);
      if ($segment === null) {
        continue;
      }
      if ($currentNodes + (int)$segment['variant']['node_cost'] + $terminalCost <= $target + 2) {
        return $segment;
      }
    }

    return null;
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
   * @param array<string,mixed> $request
   * @return array{phase:string,pattern_key:string,variant:array<string,mixed>}|null
   */
  private function chooseSegmentWithNodeType(array $request, string $phase, string $nodeType, DeterministicRandom $rng): ?array
  {
    $rules = is_array($request['rules_by_phase'][$phase] ?? null) ? $request['rules_by_phase'][$phase] : [];
    $eligible = [];
    foreach ($rules as $rule) {
      $patternKey = (string)$rule['pattern_slug'] . '@' . (int)$rule['pattern_version'];
      $variants = is_array($request['variants_by_pattern_key'][$patternKey] ?? null) ? $request['variants_by_pattern_key'][$patternKey] : [];
      foreach ($variants as $variant) {
        if ($this->variantHasNodeType($variant, $nodeType)) {
          $eligible[] = ['rule' => $rule, 'pattern_key' => $patternKey, 'variant' => $variant];
        }
      }
    }

    if ($eligible === []) {
      return null;
    }

    $choice = $rng->weightedChoice($eligible, static fn(array $candidate): int => (int)($candidate['rule']['base_weight'] ?? 0));
    return [
      'phase' => $phase,
      'pattern_key' => (string)$choice['pattern_key'],
      'variant' => $choice['variant'],
    ];
  }

  /**
   * @param array<string,mixed> $variant
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>,head:string,tail:string,width:int}
   */
  private function placeVariant(string $phase, string $patternKey, array $variant, int $offsetX, int $offsetY, ?string $previousTail, ?string $branchKey = null): array
  {
    $nodes = [];
    $keyMap = [];
    foreach (array_values(array_filter(is_array($variant['nodes'] ?? null) ? $variant['nodes'] : [], 'is_array')) as $index => $node) {
      $sourceKey = (string)$node['key'];
      $key = "{$patternKey}:{$phase}:{$offsetX}:{$offsetY}:{$sourceKey}:{$index}";
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
        'y' => $offsetY + (int)($node['y'] ?? 0),
        'pattern_key' => $patternKey,
        'path_role' => $phase === 'branch' ? 'branch' : 'spine',
        'depth' => $offsetX + (int)($node['x'] ?? 0),
        'branch_key' => $branchKey,
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
    if (!is_string($head)) {
      throw new RuntimeException("Pattern {$patternKey} did not place a head node.");
    }

    $footprint = is_array($variant['footprint'] ?? null) ? $variant['footprint'] : [];
    return [
      'nodes' => $nodes,
      'edges' => $edges,
      'head' => $head,
      'tail' => $tail,
      'width' => max(1, (int)($footprint['width'] ?? count($nodes))),
    ];
  }

  /**
   * @param array<string,mixed> $request
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  private function attachBranches(array $request, array $graph, DeterministicRandom $rng, RunPatternGenerationTrace $trace): array
  {
    $capRules = is_array($request['rules_by_phase']['cap'] ?? null) ? $request['rules_by_phase']['cap'] : [];
    if ($capRules === []) {
      return $graph;
    }

    $branchBudget = is_array($request['profile']['budgets']['branch_count'] ?? null) ? $request['profile']['budgets']['branch_count'] : [];
    $targetBranches = max(0, (int)($branchBudget['target'] ?? 1));
    $maxByRule = max(1, max(array_map(static fn(array $rule): int => (int)($rule['max_per_run'] ?? 1), $capRules)));
    $desiredBranches = min($targetBranches, $maxByRule);
    if ($desiredBranches <= 0) {
      return $graph;
    }

    $sources = $this->branchSources($graph);
    $branchCount = 0;
    foreach ($sources as $source) {
      if ($branchCount >= $desiredBranches) {
        break;
      }

      $cap = $this->chooseSegment($request, 'cap', $rng->fork('cap-' . $branchCount));
      $branchKey = 'branch-' . ($branchCount + 1);
      $offsetX = (int)$source['x'] + 1;
      $offsetY = 1 + ($branchCount % 3);
      if ($this->wouldOverlap($graph, $cap['variant'], $offsetX, $offsetY)) {
        $trace->candidateRejected('cap', $cap['pattern_key'], 'overlap', ['branch' => $branchKey]);
        continue;
      }

      $capPlacement = $this->placeVariant('cap', $cap['pattern_key'], $cap['variant'], $offsetX, $offsetY, (string)$source['key'], $branchKey);
      $graph['nodes'] = [...$graph['nodes'], ...$capPlacement['nodes']];
      $graph['edges'] = [...$graph['edges'], ...$capPlacement['edges']];
      $trace->placement('cap', $cap['pattern_key'], ['branch_key' => $branchKey]);
      $branchCount++;
    }

    return $graph;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @return list<array<string,mixed>>
   */
  private function branchSources(array $graph): array
  {
    $exitX = 0;
    foreach ($graph['nodes'] as $node) {
      if ((string)($node['type'] ?? '') === 'exit') {
        $exitX = max($exitX, (int)($node['x'] ?? 0));
      }
    }

    $sources = [];
    foreach ($graph['nodes'] as $node) {
      $type = (string)($node['type'] ?? '');
      $pathRole = (string)($node['path_role'] ?? '');
      $x = (int)($node['x'] ?? 0);
      if ($pathRole === 'spine' && !in_array($type, ['start', 'boss', 'exit'], true) && $x <= ($exitX - 2)) {
        $sources[] = $node;
      }
    }

    usort($sources, static fn(array $left, array $right): int => ((int)$left['x']) <=> ((int)$right['x']));
    return $sources;
  }

  /**
   * @param array<string,mixed> $request
   * @return array<string,true>
   */
  private function requiredNodeTypes(array $request): array
  {
    $requirements = is_array($request['profile']['requirements'] ?? null) ? $request['profile']['requirements'] : [];
    $types = [];
    foreach (array_values(array_filter(is_array($requirements['required_node_types'] ?? null) ? $requirements['required_node_types'] : [], 'is_string')) as $type) {
      $types[$type] = true;
    }
    return $types;
  }

  /**
   * @param list<array{phase:string,pattern_key:string,variant:array<string,mixed>}> $segments
   */
  private function segmentsContainNodeType(array $segments, string $nodeType): bool
  {
    foreach ($segments as $segment) {
      if ($this->variantHasNodeType($segment['variant'], $nodeType)) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param array<string,mixed> $variant
   */
  private function variantHasNodeType(array $variant, string $nodeType): bool
  {
    foreach (array_values(array_filter(is_array($variant['nodes'] ?? null) ? $variant['nodes'] : [], 'is_array')) as $node) {
      if ((string)($node['type'] ?? '') === $nodeType) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @param array<string,mixed> $variant
   */
  private function wouldOverlap(array $graph, array $variant, int $offsetX, int $offsetY): bool
  {
    $occupied = [];
    foreach ($graph['nodes'] as $node) {
      $occupied[(int)($node['x'] ?? 0) . ':' . (int)($node['y'] ?? 0)] = true;
    }

    foreach (array_values(array_filter(is_array($variant['nodes'] ?? null) ? $variant['nodes'] : [], 'is_array')) as $node) {
      $key = ($offsetX + (int)($node['x'] ?? 0)) . ':' . ($offsetY + (int)($node['y'] ?? 0));
      if (isset($occupied[$key])) {
        return true;
      }
    }

    return false;
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
