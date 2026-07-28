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
          ['key' => 'start', 'type' => 'start', 'pattern_key' => 'shared_start_single@1', 'path_role' => 'spine', 'depth' => 0],
          ['key' => 'combat', 'type' => 'combat', 'pattern_key' => 'shared_combat_step@1', 'path_role' => 'spine', 'depth' => 1],
          ['key' => 'loot', 'type' => 'loot', 'pattern_key' => 'shared_loot_cap@1', 'branch_key' => 'branch-1'],
        ],
        'edges' => [
          ['from' => 'start', 'to' => 'combat'],
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
    $this->assertSame(3, $summary['node_count']);
    $this->assertSame(2, $summary['edge_count']);
    $this->assertSame(['combat' => 1, 'loot' => 1, 'start' => 1], $summary['node_types']);
    $this->assertSame(1, $summary['spine_depth']);
    $this->assertSame(1, $summary['branch_count']);
    $this->assertSame(3, $summary['trace']['counters']['placements']);
  }
}
