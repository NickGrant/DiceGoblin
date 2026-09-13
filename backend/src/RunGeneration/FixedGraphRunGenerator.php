<?php
declare(strict_types=1);

namespace DiceGoblins\RunGeneration;

use InvalidArgumentException;

final class FixedGraphRunGenerator
{
  public function __construct(
    private readonly GeneratedRunGraphValidator $validator = new GeneratedRunGraphValidator(),
  ) {}

  /**
   * @param array<string,mixed> $generationDefinition Already validated authored content.
   * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  public function generate(array $generationDefinition): array
  {
    if (($generationDefinition['algorithm'] ?? null) !== 'fixed_graph_v1') {
      throw new InvalidArgumentException('Fixed graph generator requires algorithm fixed_graph_v1.');
    }
    $authoredNodes = $generationDefinition['nodes'] ?? null;
    $authoredEdges = $generationDefinition['edges'] ?? null;
    if (!is_array($authoredNodes) || !array_is_list($authoredNodes) || !is_array($authoredEdges) || !array_is_list($authoredEdges)) {
      throw new InvalidArgumentException('Fixed graph generator requires validated node and edge lists.');
    }

    $indexByKey = [];
    $nodes = [];
    $startKey = (string)($generationDefinition['start_node_key'] ?? '');
    foreach ($authoredNodes as $index => $node) {
      if (!is_array($node) || !is_string($node['key'] ?? null) || isset($indexByKey[$node['key']])) {
        throw new InvalidArgumentException('Fixed graph generator received invalid authored nodes.');
      }
      $indexByKey[$node['key']] = $index;
      $nodes[] = [
        'node_index' => $index,
        'node_type_id' => $node['node_type_id'] ?? null,
        'encounter_id' => $node['encounter_id'] ?? null,
        'status' => $node['key'] === $startKey ? 'available' : 'locked',
        'generated_metadata' => ['position' => $node['position'] ?? null],
      ];
    }

    $edges = [];
    foreach ($authoredEdges as $edge) {
      if (!is_array($edge) || !isset($indexByKey[$edge['from'] ?? null], $indexByKey[$edge['to'] ?? null])) {
        throw new InvalidArgumentException('Fixed graph generator received an edge with an unknown endpoint.');
      }
      $edges[] = [
        'from_node_index' => $indexByKey[$edge['from']],
        'to_node_index' => $indexByKey[$edge['to']],
        'generated_metadata' => null,
      ];
    }

    $graph = ['nodes' => $nodes, 'edges' => $edges];
    $this->validator->assertValid($graph);
    return $graph;
  }
}
