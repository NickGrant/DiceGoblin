<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunPatternV2GridCompiler;
use PHPUnit\Framework\TestCase;

final class RunPatternV2GridCompilerTest extends TestCase
{
  public function testCompilesGridNodesAndKeepsConnectorsOutOfRuntimeNodes(): void
  {
    $tile = (new RunPatternV2GridCompiler())->compile([
      'schema_version' => 'pattern-v2',
      'slug' => 'v2_test_tile',
      'version' => 1,
      'width' => 3,
      'height' => 2,
      'cost' => 3,
      'tags' => ['test'],
      'grid' => [
        [
          ['key' => 'combat_a', 'type' => 'combat'],
          ['type' => 'connector'],
          ['key' => 'combat_b', 'type' => 'combat'],
        ],
        [
          null,
          ['key' => 'loot', 'type' => 'loot'],
          null,
        ],
      ],
      'connections' => [
        ['from' => 'combat_a', 'to' => 'combat_b', 'through' => [['row' => 0, 'col' => 1]]],
      ],
      'exits' => [
        ['row' => 0, 'col' => 2, 'direction' => 'right'],
      ],
    ]);

    $this->assertSame('v2_test_tile@1', $tile['tile_key']);
    $this->assertSame(3, $tile['width']);
    $this->assertSame(2, $tile['height']);
    $this->assertSame(['combat_a', 'combat_b', 'loot'], array_column($tile['nodes'], 'key'));
    $this->assertSame([['row' => 0, 'col' => 1]], $tile['connectors']);
    $this->assertSame('combat_a', $tile['edges'][0]['from']);
    $this->assertSame('combat_b', $tile['edges'][0]['to']);
    $this->assertSame([['row' => 0, 'col' => 1]], $tile['edges'][0]['through']);
  }
}
