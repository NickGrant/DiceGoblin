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
    if ((string)($request['generator_version'] ?? '') === 'pattern-v2') {
      return (new RunPatternV2TileComposerService())->assemble($request, $this->validator);
    }

    $trace = new RunPatternGenerationTrace(startedAtMs: $this->nowMs());
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

    $graph = $this->breakLongSpineRows($graph);
    $graph = $this->attachBranches($request, $graph, $rng, $trace);
    $graph = $this->applyStoryPlacementRequests($graph, $request);

    $validation = $this->validator->validate($graph);
    if (!$validation['valid']) {
      $trace->validationFailure($validation['errors']);
    }

    return [
      'graph' => $graph,
      'trace' => $trace->summary($this->nowMs()),
      'validation' => $validation,
    ];
  }

  private function nowMs(): int
  {
    return (int)round(microtime(true) * 1000);
  }

  /**
   * @param array<string,mixed> $request
   * @return list<array{phase:string,pattern_key:string,variant:array<string,mixed>}>
   */
  private function segments(array $request, DeterministicRandom $rng): array
  {
    $segments = [];
    $segments[] = $this->chooseSegment($request, 'start', $rng);

    $target = (int)($request['profile']['budgets']['spine_nodes']['target'] ?? $request['profile']['budgets']['total_nodes']['target'] ?? 10);
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
    $branchRules = is_array($request['rules_by_phase']['branch'] ?? null) ? $request['rules_by_phase']['branch'] : [];
    if ($branchRules === []) {
      return $graph;
    }

    $branchBudget = is_array($request['profile']['budgets']['branch_count'] ?? null) ? $request['profile']['budgets']['branch_count'] : [];
    $targetBranches = max(0, (int)($branchBudget['target'] ?? 1));
    $maxByRule = max(1, max(array_map(static fn(array $rule): int => (int)($rule['max_per_run'] ?? 1), $branchRules)));
    $desiredBranches = min($targetBranches, $maxByRule);
    if ($desiredBranches <= 0) {
      return $graph;
    }

    $branchCount = 0;
    foreach ($this->branchSourceBands($graph, $desiredBranches) as $branchIndex => $sources) {
      $placement = null;
      $target = null;
      $branchKey = 'branch-' . ($branchCount + 1);
      $branches = $this->branchSegmentCandidates($request, $rng->fork('branch-' . $branchIndex));

      foreach ($sources as $source) {
        foreach ($this->branchLaneRows($source, $request, $branchIndex) as $offsetY) {
          foreach ($branches as $branch) {
            $offsetX = (int)$source['x'] + 1;
            if ($this->wouldOverlap($graph, $branch['variant'], $offsetX, $offsetY)) {
              $trace->candidateRejected('branch', $branch['pattern_key'], 'overlap', ['branch_key' => $branchKey, 'row' => $offsetY]);
              continue;
            }
            if ($this->wouldExceedBounds($branch['variant'], $offsetX, $offsetY, $request)) {
              $trace->candidateRejected('branch', $branch['pattern_key'], 'bounds', ['branch_key' => $branchKey, 'row' => $offsetY]);
              continue;
            }

            $candidatePlacement = $this->placeVariant('branch', $branch['pattern_key'], $branch['variant'], $offsetX, $offsetY, (string)$source['key'], $branchKey);
            $candidateTarget = $this->branchRejoinTarget($graph, (string)$source['key'], $candidatePlacement);
            if ($candidateTarget === null) {
              $trace->candidateRejected('branch', $branch['pattern_key'], 'missing_rejoin_target', ['branch_key' => $branchKey, 'row' => $offsetY]);
              continue;
            }

            $candidatePlacement['edges'][] = ['from' => (string)$candidatePlacement['tail'], 'to' => (string)$candidateTarget['key']];
            $candidateGraph = [
              'nodes' => [...$graph['nodes'], ...$candidatePlacement['nodes']],
              'edges' => [...$graph['edges'], ...$candidatePlacement['edges']],
            ];
            if ($this->hasCrossingEdges($candidateGraph)) {
              $trace->candidateRejected('branch', $branch['pattern_key'], 'crossing_edges', ['branch_key' => $branchKey, 'row' => $offsetY]);
              continue;
            }

            $placement = $candidatePlacement;
            $target = $candidateTarget;
            break 3;
          }
        }
      }

      if ($placement === null || $target === null) {
        continue;
      }

      $graph['nodes'] = [...$graph['nodes'], ...$placement['nodes']];
      $graph['edges'] = [...$graph['edges'], ...$placement['edges']];
      $trace->placement('branch', $branch['pattern_key'], [
        'branch_key' => $branchKey,
        'rejoins' => (string)$target['key'],
      ]);
      $branchCount++;
    }

    return $graph;
  }

  /**
   * @param array<string,mixed> $request
   * @return list<array{phase:string,pattern_key:string,variant:array<string,mixed>}>
   */
  private function branchSegmentCandidates(array $request, DeterministicRandom $rng): array
  {
    $rules = is_array($request['rules_by_phase']['branch'] ?? null) ? $request['rules_by_phase']['branch'] : [];
    if ($rules === []) {
      return [];
    }

    $rule = $rng->weightedChoice($rules, static fn(array $rule): int => (int)($rule['base_weight'] ?? 0));
    $patternKey = (string)$rule['pattern_slug'] . '@' . (int)$rule['pattern_version'];
    $variants = is_array($request['variants_by_pattern_key'][$patternKey] ?? null) ? array_values($request['variants_by_pattern_key'][$patternKey]) : [];
    if ($variants === []) {
      throw new RuntimeException("No variants are available for {$patternKey}.");
    }

    $startIndex = $rng->nextInt(0, count($variants) - 1);
    $ordered = [...array_slice($variants, $startIndex), ...array_slice($variants, 0, $startIndex)];

    return array_map(static fn(array $variant): array => [
      'phase' => 'branch',
      'pattern_key' => $patternKey,
      'variant' => $variant,
    ], $ordered);
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @return list<list<array<string,mixed>>>
   */
  private function branchSourceBands(array $graph, int $desiredBranches): array
  {
    $sources = $this->branchSources($graph);
    if ($desiredBranches <= 0 || $sources === []) {
      return [];
    }

    $bands = array_fill(0, $desiredBranches, []);
    $lastIndex = count($sources) - 1;
    foreach ($sources as $sourceIndex => $source) {
      $bandIndex = $lastIndex === 0
        ? 0
        : min($desiredBranches - 1, (int)floor(($sourceIndex / ($lastIndex + 1)) * $desiredBranches));
      $bands[$bandIndex][] = $source;
    }

    $fallback = $sources;
    usort($fallback, static function (array $left, array $right): int {
      $leftX = (int)($left['x'] ?? 0);
      $rightX = (int)($right['x'] ?? 0);
      return $rightX <=> $leftX;
    });

    foreach ($bands as $bandIndex => $bandSources) {
      $keys = [];
      foreach ($bandSources as $source) {
        $keys[(string)($source['key'] ?? '')] = true;
      }

      foreach ($fallback as $source) {
        $key = (string)($source['key'] ?? '');
        if (isset($keys[$key])) {
          continue;
        }

        $bands[$bandIndex][] = $source;
      }
    }

    return $bands;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  private function breakLongSpineRows(array $graph): array
  {
    $spineIndexes = [];
    foreach ($graph['nodes'] as $index => $node) {
      if ((string)($node['path_role'] ?? '') === 'spine') {
        $spineIndexes[] = $index;
      }
    }

    usort($spineIndexes, static function (int $left, int $right) use ($graph): int {
      return ((int)($graph['nodes'][$left]['x'] ?? 0)) <=> ((int)($graph['nodes'][$right]['x'] ?? 0));
    });

    $rowCycle = [1, 1, 2, 2, 1, 2];
    foreach ($spineIndexes as $sequence => $nodeIndex) {
      $type = (string)($graph['nodes'][$nodeIndex]['type'] ?? '');
      if ($type === 'exit') {
        continue;
      }

      $graph['nodes'][$nodeIndex]['y'] = $rowCycle[$sequence % count($rowCycle)];
    }

    return $graph;
  }

  /**
   * @return list<int>
   */
  private function branchLaneRows(array $source, array $request, int $branchIndex): array
  {
    $bounds = is_array($request['profile']['bounds'] ?? null) ? $request['profile']['bounds'] : [];
    $minRow = (int)($bounds['min_row'] ?? 0);
    $maxRow = max(2, (int)($bounds['max_row'] ?? 4));
    $sourceY = (int)($source['y'] ?? 0);

    $upperRows = [];
    for ($row = $sourceY - 1; $row >= $minRow; $row--) {
      $upperRows[] = $row;
    }

    $lowerRows = [];
    for ($row = $sourceY + 1; $row <= $maxRow; $row++) {
      $lowerRows[] = $row;
    }

    $preferred = $branchIndex % 2 === 0
      ? [...$upperRows, ...$lowerRows]
      : [...$lowerRows, ...$upperRows];

    if ($preferred === []) {
      $preferred = range($minRow, $maxRow);
    }

    return array_values(array_filter(
      array_values(array_unique($preferred)),
      static fn(int $row): bool => $row >= $minRow && $row <= $maxRow,
    ));
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   */
  private function branchRejoinTarget(array $graph, string $sourceKey, array $placement): ?array
  {
    $source = $this->nodeByKey($graph, $sourceKey);
    $tail = $this->nodeByKey($placement, (string)($placement['tail'] ?? ''));
    if ($source === null || $tail === null) {
      return null;
    }

    $sourceX = (int)($source['x'] ?? 0);
    $tailX = (int)($tail['x'] ?? 0);
    $candidates = [];
    foreach ($graph['nodes'] as $node) {
      if ((string)($node['path_role'] ?? '') !== 'spine') {
        continue;
      }
      $type = (string)($node['type'] ?? '');
      if (in_array($type, ['start', 'boss', 'exit'], true)) {
        continue;
      }

      $x = (int)($node['x'] ?? 0);
      if ($x <= $tailX || $x < ($sourceX + 3) || $x > ($sourceX + 6)) {
        continue;
      }

      $candidates[] = $node;
    }

    usort($candidates, static function (array $left, array $right): int {
      return ((int)($left['x'] ?? 0)) <=> ((int)($right['x'] ?? 0));
    });

    return $candidates[0] ?? null;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>} $graph
   * @return array<string,mixed>|null
   */
  private function nodeByKey(array $graph, string $key): ?array
  {
    foreach ($graph['nodes'] as $node) {
      if ((string)($node['key'] ?? '') === $key) {
        return $node;
      }
    }

    return null;
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
   * @param array<string,mixed> $request
   */
  private function wouldExceedBounds(array $variant, int $offsetX, int $offsetY, array $request): bool
  {
    $bounds = is_array($request['profile']['bounds'] ?? null) ? $request['profile']['bounds'] : [];
    $minCol = (int)($bounds['min_col'] ?? 0);
    $maxCol = (int)($bounds['max_col'] ?? PHP_INT_MAX);
    $minRow = (int)($bounds['min_row'] ?? 0);
    $maxRow = (int)($bounds['max_row'] ?? PHP_INT_MAX);

    foreach (array_values(array_filter(is_array($variant['nodes'] ?? null) ? $variant['nodes'] : [], 'is_array')) as $node) {
      $x = $offsetX + (int)($node['x'] ?? 0);
      $y = $offsetY + (int)($node['y'] ?? 0);
      if ($x < $minCol || $x > $maxCol || $y < $minRow || $y > $maxRow) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   */
  private function hasCrossingEdges(array $graph): bool
  {
    $nodesByKey = [];
    foreach ($graph['nodes'] as $node) {
      $key = (string)($node['key'] ?? $node['node_key'] ?? '');
      if ($key !== '') {
        $nodesByKey[$key] = $node;
      }
    }

    $edges = $graph['edges'];
    $edgeCount = count($edges);
    for ($leftIndex = 0; $leftIndex < $edgeCount; $leftIndex++) {
      $leftEdge = $edges[$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $edgeCount; $rightIndex++) {
        $rightEdge = $edges[$rightIndex];
        $leftFrom = (string)($leftEdge['from'] ?? $leftEdge['from_node_key'] ?? '');
        $leftTo = (string)($leftEdge['to'] ?? $leftEdge['to_node_key'] ?? '');
        $rightFrom = (string)($rightEdge['from'] ?? $rightEdge['from_node_key'] ?? '');
        $rightTo = (string)($rightEdge['to'] ?? $rightEdge['to_node_key'] ?? '');
        if ($leftFrom === '' || $leftTo === '' || $rightFrom === '' || $rightTo === '') {
          continue;
        }
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

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @param array<string,mixed> $request
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  private function applyStoryPlacementRequests(array $graph, array $request): array
  {
    $storyRequests = array_values(array_filter(
      is_array($request['story_placement_requests'] ?? null) ? $request['story_placement_requests'] : [],
      'is_array',
    ));

    foreach ($storyRequests as $index => $storyRequest) {
      $placement = (string)($storyRequest['placement'] ?? '');
      $graph = match ($placement) {
        'start' => $this->insertStartStoryNode($graph, $storyRequest, $index),
        'before_boss' => $this->insertStoryNodeBeforeType($graph, $storyRequest, $index, 'boss'),
        'before_exit' => $this->insertStoryNodeBeforeType($graph, $storyRequest, $index, 'exit'),
        default => $graph,
      };
    }

    return $graph;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @param array<string,mixed> $storyRequest
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  private function insertStartStoryNode(array $graph, array $storyRequest, int $index): array
  {
    foreach ($graph['nodes'] as $nodeIndex => $node) {
      $graph['nodes'][$nodeIndex]['x'] = (int)($node['x'] ?? 0) + 1;
      $graph['nodes'][$nodeIndex]['depth'] = (int)($node['depth'] ?? 0) + 1;
      if ((string)($node['type'] ?? '') === 'start') {
        $graph['nodes'][$nodeIndex]['type'] = (string)($node['source_type'] ?? 'combat');
      }
    }

    $incoming = [];
    foreach ($graph['edges'] as $edge) {
      $incoming[(string)($edge['to'] ?? '')] = true;
    }

    $roots = [];
    foreach ($graph['nodes'] as $node) {
      $key = (string)($node['key'] ?? '');
      if ($key !== '' && !isset($incoming[$key])) {
        $roots[] = $key;
      }
    }

    if ($roots === []) {
      return $graph;
    }

    $storyNode = $this->storyNode($storyRequest, $index, 'start', 0, (int)($graph['nodes'][0]['y'] ?? 0));
    $graph['nodes'][] = $storyNode;
    foreach ($roots as $rootKey) {
      $graph['edges'][] = ['from' => $storyNode['key'], 'to' => $rootKey];
    }

    return $graph;
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @param array<string,mixed> $storyRequest
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  private function insertStoryNodeBeforeType(array $graph, array $storyRequest, int $index, string $targetType): array
  {
    $target = null;
    foreach ($graph['nodes'] as $node) {
      if ((string)($node['type'] ?? '') === $targetType) {
        $target = $node;
        break;
      }
    }

    if ($target === null) {
      return $graph;
    }

    $targetKey = (string)($target['key'] ?? '');
    $targetX = (int)($target['x'] ?? 0);
    foreach ($graph['nodes'] as $nodeIndex => $node) {
      $x = (int)($node['x'] ?? 0);
      if ($x >= $targetX) {
        $graph['nodes'][$nodeIndex]['x'] = $x + 1;
        $graph['nodes'][$nodeIndex]['depth'] = (int)($node['depth'] ?? $x) + 1;
      }
    }

    $storyNode = $this->storyNode($storyRequest, $index, 'dialogue', $targetX, (int)($target['y'] ?? 0));
    $rewired = [];
    foreach ($graph['edges'] as $edge) {
      if ((string)($edge['to'] ?? '') === $targetKey) {
        $rewired[] = ['from' => (string)$edge['from'], 'to' => $storyNode['key']];
        continue;
      }

      $rewired[] = $edge;
    }
    $rewired[] = ['from' => $storyNode['key'], 'to' => $targetKey];

    $graph['nodes'][] = $storyNode;
    $graph['edges'] = $rewired;

    return $graph;
  }

  /**
   * @param array<string,mixed> $storyRequest
   * @return array<string,mixed>
   */
  private function storyNode(array $storyRequest, int $index, string $type, int $x, int $y): array
  {
    return [
      'key' => 'story:' . $index . ':' . (string)($storyRequest['dialogue_id'] ?? 'dialogue'),
      'type' => $type,
      'source_type' => 'dialogue',
      'x' => $x,
      'y' => $y,
      'pattern_key' => 'story_request',
      'path_role' => 'story',
      'depth' => $x,
      'dialogue_id' => (string)($storyRequest['dialogue_id'] ?? ''),
      'one_time' => (bool)($storyRequest['one_time'] ?? false),
      'placement' => (string)($storyRequest['placement'] ?? ''),
      'tags' => array_values(array_map('strval', is_array($storyRequest['tags'] ?? null) ? $storyRequest['tags'] : [])),
    ];
  }
}
