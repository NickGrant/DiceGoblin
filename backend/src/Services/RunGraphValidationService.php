<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class RunGraphValidationService
{
  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
   * @return array{valid:bool,errors:list<string>}
   */
  public function validate(array $graph): array
  {
    $nodes = array_values(array_filter($graph['nodes'] ?? [], 'is_array'));
    $edges = array_values(array_filter($graph['edges'] ?? [], 'is_array'));
    $errors = [];

    $nodeKeys = $this->nodeKeys($nodes, $errors);
    $adjacency = $this->adjacency($edges, $nodeKeys, $errors);

    $start = $this->firstNodeKeyByType($nodes, 'start');
    $boss = $this->firstNodeKeyByType($nodes, 'boss');
    $exit = $this->firstNodeKeyByType($nodes, 'exit');
    if ($start === null) {
      $errors[] = 'missing_start_node';
    }
    if ($boss === null) {
      $errors[] = 'missing_boss_node';
    }
    if ($exit === null) {
      $errors[] = 'missing_exit_node';
    }

    $errors = array_merge($errors, $this->overlapErrors($nodes));
    $errors = array_merge($errors, $this->openSocketErrors($nodes));
    $errors = array_merge($errors, $this->nonForwardEdgeErrors($nodes, $edges));
    $errors = array_merge($errors, $this->crossingEdgeErrors($nodes, $edges));

    if ($start !== null) {
      $reachable = $this->reachableFrom($start, $adjacency);
      foreach (array_keys($nodeKeys) as $key) {
        if (!isset($reachable[$key])) {
          $errors[] = "unreachable_node:{$key}";
        }
      }

      if ($boss !== null && !isset($reachable[$boss])) {
        $errors[] = 'boss_not_reachable';
      }
      if ($exit !== null && !isset($reachable[$exit])) {
        $errors[] = 'exit_not_reachable';
      }
      if ($boss !== null && $exit !== null && $this->canReachWithoutNode($start, $exit, $boss, $adjacency)) {
        $errors[] = 'exit_bypasses_boss';
      }
    }

    return [
      'valid' => $errors === [],
      'errors' => array_values(array_unique($errors)),
    ];
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<string> $errors
   * @return array<string,true>
   */
  private function nodeKeys(array $nodes, array &$errors): array
  {
    $keys = [];
    foreach ($nodes as $index => $node) {
      $key = (string)($node['key'] ?? $node['node_key'] ?? '');
      if ($key === '') {
        $errors[] = "node_missing_key:{$index}";
        continue;
      }
      if (isset($keys[$key])) {
        $errors[] = "duplicate_node_key:{$key}";
      }
      $keys[$key] = true;
    }
    return $keys;
  }

  /**
   * @param list<array<string,mixed>> $edges
   * @param array<string,true> $nodeKeys
   * @param list<string> $errors
   * @return array<string,list<string>>
   */
  private function adjacency(array $edges, array $nodeKeys, array &$errors): array
  {
    $adjacency = [];
    foreach (array_keys($nodeKeys) as $key) {
      $adjacency[$key] = [];
    }

    foreach ($edges as $index => $edge) {
      $from = (string)($edge['from'] ?? $edge['from_node_key'] ?? '');
      $to = (string)($edge['to'] ?? $edge['to_node_key'] ?? '');
      if ($from === '' || !isset($nodeKeys[$from])) {
        $errors[] = "edge_missing_from:{$index}";
        continue;
      }
      if ($to === '' || !isset($nodeKeys[$to])) {
        $errors[] = "edge_missing_to:{$index}";
        continue;
      }
      $adjacency[$from][] = $to;
    }

    return $adjacency;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   */
  private function firstNodeKeyByType(array $nodes, string $type): ?string
  {
    foreach ($nodes as $node) {
      if ((string)($node['type'] ?? $node['node_type'] ?? '') === $type) {
        return (string)($node['key'] ?? $node['node_key'] ?? '');
      }
    }
    return null;
  }

  /**
   * @param array<string,list<string>> $adjacency
   * @return array<string,true>
   */
  private function reachableFrom(string $start, array $adjacency): array
  {
    $reachable = [];
    $queue = [$start];
    while ($queue !== []) {
      $current = array_shift($queue);
      if (!is_string($current) || isset($reachable[$current])) {
        continue;
      }
      $reachable[$current] = true;
      foreach ($adjacency[$current] ?? [] as $next) {
        $queue[] = $next;
      }
    }
    return $reachable;
  }

  /**
   * @param array<string,list<string>> $adjacency
   */
  private function canReachWithoutNode(string $start, string $target, string $blocked, array $adjacency): bool
  {
    if ($start === $blocked || $target === $blocked) {
      return false;
    }

    $reachable = [];
    $queue = [$start];
    while ($queue !== []) {
      $current = array_shift($queue);
      if (!is_string($current) || $current === $blocked || isset($reachable[$current])) {
        continue;
      }
      if ($current === $target) {
        return true;
      }
      $reachable[$current] = true;
      foreach ($adjacency[$current] ?? [] as $next) {
        $queue[] = $next;
      }
    }

    return false;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @return list<string>
   */
  private function overlapErrors(array $nodes): array
  {
    $errors = [];
    $occupied = [];
    foreach ($nodes as $node) {
      if (!array_key_exists('x', $node) || !array_key_exists('y', $node)) {
        continue;
      }
      $position = (int)$node['x'] . ',' . (int)$node['y'];
      $key = (string)($node['key'] ?? $node['node_key'] ?? '');
      if (isset($occupied[$position])) {
        $errors[] = "node_overlap:{$occupied[$position]}:{$key}";
      }
      $occupied[$position] = $key;
    }
    return $errors;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @return list<string>
   */
  private function openSocketErrors(array $nodes): array
  {
    $errors = [];
    foreach ($nodes as $node) {
      $key = (string)($node['key'] ?? $node['node_key'] ?? '');
      foreach (array_values(array_filter(is_array($node['open_sockets'] ?? null) ? $node['open_sockets'] : [], 'is_array')) as $socket) {
        if ((bool)($socket['visible'] ?? true)) {
          $errors[] = 'open_visible_socket:' . $key . ':' . (string)($socket['id'] ?? 'unknown');
        }
      }
    }
    return $errors;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<array<string,mixed>> $edges
   * @return list<string>
   */
  private function crossingEdgeErrors(array $nodes, array $edges): array
  {
    $nodesByKey = [];
    foreach ($nodes as $node) {
      $key = (string)($node['key'] ?? $node['node_key'] ?? '');
      if ($key !== '') {
        $nodesByKey[$key] = $node;
      }
    }

    $errors = [];
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
          $errors[] = "edge_crossing:{$leftFrom}:{$leftTo}:{$rightFrom}:{$rightTo}";
        }
      }
    }

    return $errors;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<array<string,mixed>> $edges
   * @return list<string>
   */
  private function nonForwardEdgeErrors(array $nodes, array $edges): array
  {
    $nodesByKey = [];
    foreach ($nodes as $node) {
      $key = (string)($node['key'] ?? $node['node_key'] ?? '');
      if ($key !== '') {
        $nodesByKey[$key] = $node;
      }
    }

    $errors = [];
    foreach ($edges as $index => $edge) {
      $from = (string)($edge['from'] ?? $edge['from_node_key'] ?? '');
      $to = (string)($edge['to'] ?? $edge['to_node_key'] ?? '');
      $fromNode = $nodesByKey[$from] ?? null;
      $toNode = $nodesByKey[$to] ?? null;
      if ($fromNode === null || $toNode === null) {
        continue;
      }

      if ((int)($toNode['x'] ?? 0) <= (int)($fromNode['x'] ?? 0)) {
        $errors[] = "non_forward_edge:{$index}:{$from}:{$to}";
      }
    }

    return $errors;
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
}
