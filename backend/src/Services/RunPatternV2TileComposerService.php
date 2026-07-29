<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Support\DeterministicRandom;
use DiceGoblins\Support\RunPatternGenerationTrace;
use RuntimeException;

final class RunPatternV2TileComposerService
{
  /**
   * @param array<string,mixed> $request
   * @return array{graph:array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>},trace:array<string,mixed>,validation:array{valid:bool,errors:list<string>}}
   */
  public function assemble(array $request, RunGraphValidationService $validator): array
  {
    $trace = new RunPatternGenerationTrace(startedAtMs: $this->nowMs());
    $rng = new DeterministicRandom((string)($request['seed'] ?? 'pattern-v2-preview'));

    $placements = $this->placements($request, $rng);
    $graph = $this->composePlacements($placements, $trace);
    $validation = $validator->validate($graph);
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
   * @return list<array{phase:string,pattern_key:string,tile:array<string,mixed>}>
   */
  private function placements(array $request, DeterministicRandom $rng): array
  {
    $placements = [];
    $placements[] = $this->chooseTile($request, 'start', $rng);

    $terminal = $this->chooseTile($request, 'terminal', $rng);
    $budget = is_array($request['profile']['budgets']['cost'] ?? null) ? $request['profile']['budgets']['cost'] : [];
    $targetCost = max(1, (int)($budget['target'] ?? 18));
    $currentCost = (int)$placements[0]['tile']['cost'] + (int)$terminal['tile']['cost'];

    $spineRules = is_array($request['rules_by_phase']['spine'] ?? null) ? $request['rules_by_phase']['spine'] : [];
    $maxSpineTiles = max(1, max(array_map(static fn(array $rule): int => (int)($rule['max_per_run'] ?? 1), $spineRules ?: [['max_per_run' => 1]])));
    while ($currentCost < $targetCost && count($placements) <= $maxSpineTiles) {
      $tile = $this->chooseTile($request, 'spine', $rng);
      $placements[] = $tile;
      $currentCost += (int)$tile['tile']['cost'];
    }

    $placements[] = $terminal;
    return $placements;
  }

  /**
   * @param array<string,mixed> $request
   * @return array{phase:string,pattern_key:string,tile:array<string,mixed>}
   */
  private function chooseTile(array $request, string $phase, DeterministicRandom $rng): array
  {
    $rules = is_array($request['rules_by_phase'][$phase] ?? null) ? $request['rules_by_phase'][$phase] : [];
    if ($rules === []) {
      throw new RuntimeException("No {$phase} rules are available for pattern-v2 composition.");
    }

    $rule = $rng->weightedChoice($rules, static fn(array $rule): int => (int)($rule['base_weight'] ?? 0));
    $patternKey = (string)$rule['pattern_slug'] . '@' . (int)$rule['pattern_version'];
    $tile = is_array($request['tiles_by_pattern_key'][$patternKey] ?? null) ? $request['tiles_by_pattern_key'][$patternKey] : null;
    if ($tile === null) {
      throw new RuntimeException("No pattern-v2 tile is available for {$patternKey}.");
    }

    return [
      'phase' => $phase,
      'pattern_key' => $patternKey,
      'tile' => $tile,
    ];
  }

  /**
   * @param list<array{phase:string,pattern_key:string,tile:array<string,mixed>}> $placements
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  private function composePlacements(array $placements, RunPatternGenerationTrace $trace): array
  {
    $graph = ['nodes' => [], 'edges' => []];
    $previousSinks = [];
    $cursorX = 0;
    foreach ($placements as $index => $placement) {
      $offsetY = (int)($placement['tile']['height'] ?? 1) <= 1 ? $index % 3 : 0;
      $placed = $this->placeTile($placement['phase'], $placement['pattern_key'], $placement['tile'], $cursorX, $offsetY, $index);
      $graph['edges'] = [...$graph['edges'], ...$this->bridgeEdges($graph['nodes'], $previousSinks, $placed['nodes'], $placed['roots'])];

      $graph['nodes'] = [...$graph['nodes'], ...$placed['nodes']];
      $graph['edges'] = [...$graph['edges'], ...$placed['edges']];
      $previousSinks = $placed['sinks'];
      $cursorX = $placed['max_x'] + 1;
      $trace->placement($placement['phase'], $placement['pattern_key'], [
        'node_count' => count($placed['nodes']),
        'roots' => count($placed['roots']),
        'sinks' => count($placed['sinks']),
      ]);
    }

    return $graph;
  }

  /**
   * @param list<array<string,mixed>> $sourceNodes
   * @param list<string> $sourceKeys
   * @param list<array<string,mixed>> $targetNodes
   * @param list<string> $targetKeys
   * @return list<array{from:string,to:string}>
   */
  private function bridgeEdges(array $sourceNodes, array $sourceKeys, array $targetNodes, array $targetKeys): array
  {
    if ($sourceKeys === [] || $targetKeys === []) {
      return [];
    }

    $sources = $this->nodesByKey($sourceNodes, $sourceKeys);
    $targets = $this->nodesByKey($targetNodes, $targetKeys);
    usort($sources, static fn(array $left, array $right): int => ((int)($left['y'] ?? 0)) <=> ((int)($right['y'] ?? 0)));
    usort($targets, static fn(array $left, array $right): int => ((int)($left['y'] ?? 0)) <=> ((int)($right['y'] ?? 0)));

    $edges = [];
    foreach ($targets as $targetIndex => $target) {
      $sourceIndex = min(count($sources) - 1, (int)floor(($targetIndex / max(1, count($targets) - 1)) * max(0, count($sources) - 1)));
      $edges[(string)$sources[$sourceIndex]['key'] . '>' . (string)$target['key']] = [
        'from' => (string)$sources[$sourceIndex]['key'],
        'to' => (string)$target['key'],
      ];
    }

    foreach ($sources as $sourceIndex => $source) {
      $targetIndex = min(count($targets) - 1, (int)floor(($sourceIndex / max(1, count($sources) - 1)) * max(0, count($targets) - 1)));
      $edges[(string)$source['key'] . '>' . (string)$targets[$targetIndex]['key']] = [
        'from' => (string)$source['key'],
        'to' => (string)$targets[$targetIndex]['key'],
      ];
    }

    return array_values($edges);
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<string> $keys
   * @return list<array<string,mixed>>
   */
  private function nodesByKey(array $nodes, array $keys): array
  {
    $wanted = array_fill_keys($keys, true);
    return array_values(array_filter($nodes, static fn(array $node): bool => isset($wanted[(string)($node['key'] ?? '')])));
  }

  /**
   * @param array<string,mixed> $tile
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>,roots:list<string>,sinks:list<string>,max_x:int}
   */
  private function placeTile(string $phase, string $patternKey, array $tile, int $offsetX, int $offsetY, int $instance): array
  {
    $nodes = [];
    $tileEdges = $this->tileEdges($tile, $phase);
    $localPositions = $this->forwardPositions($tile, $tileEdges);
    $branchKeys = $this->localBranchKeys($tile, $tileEdges, $patternKey, $instance);
    $keyMap = [];
    foreach (array_values(array_filter(is_array($tile['nodes'] ?? null) ? $tile['nodes'] : [], 'is_array')) as $node) {
      $sourceKey = (string)($node['key'] ?? '');
      if ($sourceKey === '') {
        continue;
      }

      $globalKey = "{$patternKey}:{$phase}:{$instance}:{$sourceKey}";
      $keyMap[$sourceKey] = $globalKey;
      $type = (string)($node['type'] ?? 'combat');
      if ((string)($node['role'] ?? '') === 'start') {
        $type = 'start';
      }

      $nodes[] = [
        ...$node,
        'key' => $globalKey,
        'type' => $type,
        'source_type' => (string)($node['type'] ?? $type),
        'x' => $offsetX + (int)($localPositions[$sourceKey]['x'] ?? $node['x'] ?? 0),
        'y' => $offsetY + (int)($localPositions[$sourceKey]['y'] ?? $node['y'] ?? 0),
        'pattern_key' => $patternKey,
        'path_role' => $phase === 'terminal' ? 'terminal' : 'spine',
        'depth' => $offsetX + (int)($localPositions[$sourceKey]['x'] ?? $node['x'] ?? 0),
        'branch_key' => $branchKeys[$sourceKey] ?? null,
      ];
    }

    $edges = [];
    $incoming = [];
    $outgoing = [];
    foreach ($tileEdges as $edge) {
      $from = $keyMap[(string)($edge['from'] ?? '')] ?? null;
      $to = $keyMap[(string)($edge['to'] ?? '')] ?? null;
      if ($from === null || $to === null) {
        continue;
      }
      $edges[] = ['from' => $from, 'to' => $to];
      $incoming[$to] = true;
      $outgoing[$from] = true;
    }

    $roots = [];
    $sinks = [];
    $maxX = $offsetX;
    foreach ($nodes as $node) {
      $key = (string)$node['key'];
      $maxX = max($maxX, (int)$node['x']);
      if (!isset($incoming[$key])) {
        $roots[] = $key;
      }
      if (!isset($outgoing[$key])) {
        $sinks[] = $key;
      }
    }

    return [
      'nodes' => $nodes,
      'edges' => $edges,
      'roots' => $roots,
      'sinks' => $sinks,
      'max_x' => $maxX,
    ];
  }

  /**
   * @param array<string,mixed> $tile
   * @return list<array<string,mixed>>
   */
  private function tileEdges(array $tile, string $phase): array
  {
    $edges = array_values(array_filter(is_array($tile['edges'] ?? null) ? $tile['edges'] : [], 'is_array'));
    if ($phase !== 'terminal') {
      return $edges;
    }

    $nodeTypes = [];
    foreach (array_values(array_filter(is_array($tile['nodes'] ?? null) ? $tile['nodes'] : [], 'is_array')) as $node) {
      $key = (string)($node['key'] ?? '');
      if ($key !== '') {
        $nodeTypes[$key] = (string)($node['type'] ?? '');
      }
    }

    $exitKey = array_search('exit', $nodeTypes, true);
    if (!is_string($exitKey)) {
      return $edges;
    }

    $outgoing = [];
    foreach ($edges as $edge) {
      $from = (string)($edge['from'] ?? '');
      if ($from !== '') {
        $outgoing[$from] = true;
      }
    }

    foreach ($nodeTypes as $nodeKey => $nodeType) {
      if ($nodeKey === $exitKey || $nodeType === 'exit' || isset($outgoing[$nodeKey])) {
        continue;
      }

      $edges[] = ['from' => $nodeKey, 'to' => $exitKey];
    }

    return $edges;
  }

  /**
   * @param array<string,mixed> $tile
   * @return array<string,string>
   */
  private function localBranchKeys(array $tile, array $tileEdges, string $patternKey, int $instance): array
  {
    $nodeKeys = [];
    foreach (array_values(array_filter(is_array($tile['nodes'] ?? null) ? $tile['nodes'] : [], 'is_array')) as $node) {
      $key = (string)($node['key'] ?? '');
      if ($key !== '') {
        $nodeKeys[$key] = true;
      }
    }

    $incoming = [];
    $adjacency = [];
    foreach (array_keys($nodeKeys) as $key) {
      $adjacency[$key] = [];
    }
    foreach ($tileEdges as $edge) {
      $from = (string)($edge['from'] ?? '');
      $to = (string)($edge['to'] ?? '');
      if (!isset($nodeKeys[$from], $nodeKeys[$to])) {
        continue;
      }

      $adjacency[$from][] = $to;
      $incoming[$to] = true;
    }

    $roots = [];
    foreach (array_keys($nodeKeys) as $key) {
      if (!isset($incoming[$key])) {
        $roots[] = $key;
      }
    }

    $branchKeys = [];
    foreach (array_slice($roots, 1) as $rootIndex => $root) {
      $branchKey = "v2-branch:{$patternKey}:{$instance}:" . ($rootIndex + 1);
      $queue = [$root];
      while ($queue !== []) {
        $current = array_shift($queue);
        if (!is_string($current) || isset($branchKeys[$current])) {
          continue;
        }

        $branchKeys[$current] = $branchKey;
        foreach ($adjacency[$current] ?? [] as $next) {
          $queue[] = $next;
        }
      }
    }

    return $branchKeys;
  }

  /**
   * @param array<string,mixed> $tile
   * @return array<string,array{x:int,y:int}>
   */
  private function forwardPositions(array $tile, array $tileEdges): array
  {
    $positions = [];
    foreach (array_values(array_filter(is_array($tile['nodes'] ?? null) ? $tile['nodes'] : [], 'is_array')) as $node) {
      $key = (string)($node['key'] ?? '');
      if ($key !== '') {
        $positions[$key] = ['x' => (int)($node['x'] ?? 0), 'y' => (int)($node['y'] ?? 0)];
      }
    }

    for ($pass = 0; $pass < count($positions) + 1; $pass++) {
      $changed = false;
      foreach ($tileEdges as $edge) {
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        if (!isset($positions[$from], $positions[$to])) {
          continue;
        }

        $minimumX = $positions[$from]['x'] + 1;
        if ($positions[$to]['x'] < $minimumX) {
          $positions[$to]['x'] = $minimumX;
          $changed = true;
        }
      }
      if (!$changed) {
        break;
      }
    }

    return $positions;
  }
}
