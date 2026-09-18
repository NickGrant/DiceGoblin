<?php
declare(strict_types=1);

namespace DiceGoblins\RunGeneration;

use InvalidArgumentException;

final class GeneratedRunGraphValidator
{
  /** @param array<string,mixed> $graph */
  public function assertValid(array $graph): void
  {
    $nodes = $graph['nodes'] ?? null;
    $edges = $graph['edges'] ?? null;
    if (!is_array($nodes) || !array_is_list($nodes) || $nodes === []) {
      throw new InvalidArgumentException('Generated run graph must contain nodes.');
    }
    if (!is_array($edges) || !array_is_list($edges)) {
      throw new InvalidArgumentException('Generated run graph edges must be a list.');
    }

    $exitIndexes = [];
    foreach ($nodes as $offset => $node) {
      if (!is_array($node) || array_is_list($node) || ($node['node_index'] ?? null) !== $offset) {
        throw new InvalidArgumentException('Generated run node indexes must be unique, sequential, and ordered from zero.');
      }
      $nodeTypeId = $node['node_type_id'] ?? null;
      if (!is_string($nodeTypeId) || !str_starts_with($nodeTypeId, 'run_node_type.')) {
        throw new InvalidArgumentException("Generated run node {$offset} has an invalid node-type identity.");
      }
      $eventId = $node['event_id'] ?? null;
      if ($eventId !== null && (!is_string($eventId) || preg_match('/^event\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $eventId) !== 1)) {
        throw new InvalidArgumentException("Generated run node {$offset} has an invalid event identity.");
      }
      $expectedStatus = $offset === 0 ? 'available' : 'locked';
      if (($node['status'] ?? null) !== $expectedStatus) {
        throw new InvalidArgumentException('Generated run graph has incoherent initial availability.');
      }
      if ($nodeTypeId === 'run_node_type.exit') $exitIndexes[] = $offset;
    }

    $adjacency = array_fill(0, count($nodes), []);
    $seenEdges = [];
    foreach ($edges as $offset => $edge) {
      if (!is_array($edge) || array_is_list($edge)) {
        throw new InvalidArgumentException("Generated run edge {$offset} must be an object.");
      }
      $from = $edge['from_node_index'] ?? null;
      $to = $edge['to_node_index'] ?? null;
      if (!is_int($from) || !is_int($to) || !isset($nodes[$from]) || !isset($nodes[$to])) {
        throw new InvalidArgumentException("Generated run edge {$offset} references an invalid endpoint.");
      }
      if ($from === $to) {
        throw new InvalidArgumentException("Generated run edge {$offset} must not be a self edge.");
      }
      $edgeKey = $from . "\0" . $to;
      if (isset($seenEdges[$edgeKey])) {
        throw new InvalidArgumentException("Generated run graph contains duplicate edge {$from} -> {$to}.");
      }
      $seenEdges[$edgeKey] = true;
      $adjacency[$from][] = $to;
    }

    if (count($exitIndexes) !== 1) {
      throw new InvalidArgumentException('Generated run graph must contain exactly one exit node.');
    }
    $reachable = [];
    $pending = [0];
    while ($pending !== []) {
      $index = array_shift($pending);
      if (isset($reachable[$index])) continue;
      $reachable[$index] = true;
      foreach ($adjacency[$index] as $next) $pending[] = $next;
    }
    if (!isset($reachable[$exitIndexes[0]])) {
      throw new InvalidArgumentException('Generated run graph exit is unreachable from the start node.');
    }
    if (count($reachable) !== count($nodes)) {
      throw new InvalidArgumentException('Generated run graph contains a node disconnected from the start node.');
    }
  }
}
