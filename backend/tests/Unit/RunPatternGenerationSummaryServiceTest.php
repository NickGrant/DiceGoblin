<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunPatternGenerationSummaryService;
use PHPUnit\Framework\TestCase;

final class RunPatternGenerationSummaryServiceTest extends TestCase
{
  public function testSummarizesGenerationMetadataAndGraphMetrics(): void
  {
    $summary = (new RunPatternGenerationSummaryService())->summarize(
      [
        'region_slug' => 'mountains',
        'seed' => 'seed-1',
        'generator_version' => 'pattern-v1',
        'profile_version' => 1,
        'catalog_hash' => str_repeat('a', 64),
      ],
      [
        'nodes' => [
          ['key' => 'start', 'type' => 'start', 'pattern_key' => 'shared_start_single@1', 'path_role' => 'spine', 'depth' => 0, 'x' => 0, 'y' => 1],
          ['key' => 'combat', 'type' => 'combat', 'pattern_key' => 'shared_combat_step@1', 'path_role' => 'spine', 'depth' => 1, 'x' => 1, 'y' => 1],
          ['key' => 'boss', 'type' => 'boss', 'pattern_key' => 'shared_boss_exit_terminal@1', 'path_role' => 'spine', 'depth' => 2, 'x' => 2, 'y' => 1],
          ['key' => 'exit', 'type' => 'exit', 'pattern_key' => 'shared_boss_exit_terminal@1', 'path_role' => 'spine', 'depth' => 3, 'x' => 3, 'y' => 1],
          ['key' => 'loot', 'type' => 'loot', 'pattern_key' => 'shared_loot_cap@1', 'branch_key' => 'branch-1', 'x' => 2, 'y' => 2],
        ],
        'edges' => [
          ['from' => 'start', 'to' => 'combat'],
          ['from' => 'combat', 'to' => 'boss'],
          ['from' => 'boss', 'to' => 'exit'],
          ['from' => 'combat', 'to' => 'loot'],
        ],
      ],
      [
        'counters' => ['placements' => 3],
        'event_count' => 3,
        'truncated' => false,
        'duration_ms' => 18,
      ]
    );

    $this->assertSame('pattern-v1', $summary['generator_version']);
    $this->assertSame('mountains', $summary['region_slug']);
    $this->assertSame(5, $summary['node_count']);
    $this->assertSame(4, $summary['edge_count']);
    $this->assertSame(['boss' => 1, 'combat' => 1, 'exit' => 1, 'loot' => 1, 'start' => 1], $summary['node_types']);
    $this->assertSame(3, $summary['spine_depth']);
    $this->assertSame(1, $summary['branch_count']);
    $this->assertSame(2, $summary['occupied_rows']);
    $this->assertSame(4, $summary['occupied_columns']);
    $this->assertSame(2, $summary['max_straight_spine_nodes']);
    $this->assertSame(['start_to_boss' => 2, 'boss_to_exit' => 1], $summary['boss_path']);
    $this->assertSame(3, $summary['trace']['counters']['placements']);
  }

  public function testBossPathMetricsAreNullWhenBossRouteIsIncomplete(): void
  {
    $summary = (new RunPatternGenerationSummaryService())->summarize(
      [
        'region_slug' => 'mountains',
        'seed' => 'seed-2',
      ],
      [
        'nodes' => [
          ['key' => 'start', 'type' => 'start'],
          ['key' => 'boss', 'type' => 'boss'],
          ['key' => 'exit', 'type' => 'exit'],
        ],
        'edges' => [
          ['from' => 'boss', 'to' => 'exit'],
        ],
      ],
      []
    );

    $this->assertSame(['start_to_boss' => null, 'boss_to_exit' => 1], $summary['boss_path']);
  }
}
