<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class BranchingRunDefinitionFixture
{
  /** @return array<string,mixed> */
  public static function definition(ContentRegistry $content, bool $deadEnd = false): array
  {
    $farm = $content->runGenerationForRegion('region.the_farm');
    $alternateLoot = $farm['nodes'][1];
    $alternateLoot['key'] = 'loot_alternate';
    $alternateLoot['position'] = ['column' => 1, 'row' => 2];
    return [
      'id' => 'run_generation.test_branching', 'type' => 'run_generation',
      'algorithm' => 'fixed_graph_v1', 'start_node_key' => 'combat',
      'nodes' => [$farm['nodes'][0], $farm['nodes'][1], $alternateLoot,
        $farm['nodes'][2], $farm['nodes'][3], $farm['nodes'][4]],
      'edges' => [
        ['from' => 'combat', 'to' => 'loot'],
        ['from' => 'combat', 'to' => 'loot_alternate'],
        ['from' => 'loot', 'to' => 'rest'],
        ...($deadEnd ? [] : [['from' => 'loot_alternate', 'to' => 'rest']]),
        ['from' => 'rest', 'to' => 'boss'],
        ['from' => 'boss', 'to' => 'exit'],
      ],
    ];
  }
}
