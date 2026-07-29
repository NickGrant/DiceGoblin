<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunGraphValidationService;
use DiceGoblins\Services\RunPatternV2GridCompiler;
use DiceGoblins\Services\RunPatternV2TileComposerService;
use PHPUnit\Framework\TestCase;

final class RunPatternV2TileComposerServiceTest extends TestCase
{
  public function testComposesDeterministicReachableGraphFromGridTiles(): void
  {
    $request = $this->request();
    $service = new RunPatternV2TileComposerService();

    $result = $service->assemble($request, new RunGraphValidationService());
    $sameResult = $service->assemble($request, new RunGraphValidationService());

    $this->assertTrue($result['validation']['valid'], implode(', ', $result['validation']['errors']));
    $this->assertSame($result['graph'], $sameResult['graph']);
    $this->assertContains('start', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('boss', array_column($result['graph']['nodes'], 'type'));
    $this->assertContains('exit', array_column($result['graph']['nodes'], 'type'));
    $this->assertSame(3, $result['trace']['counters']['placements']);
    $this->assertGreaterThanOrEqual(3, count(array_unique(array_column($result['graph']['nodes'], 'y'))));
  }

  /**
   * @return array<string,mixed>
   */
  private function request(): array
  {
    $compiler = new RunPatternV2GridCompiler();
    $tiles = [
      'v2_test_start@1' => $compiler->compile([
        'schema_version' => 'pattern-v2',
        'slug' => 'v2_test_start',
        'version' => 1,
        'width' => 2,
        'height' => 2,
        'cost' => 2,
        'grid' => [
          [['key' => 'start', 'type' => 'combat', 'role' => 'start'], ['key' => 'loot', 'type' => 'loot']],
          [null, ['key' => 'rest', 'type' => 'rest']],
        ],
        'connections' => [
          ['from' => 'start', 'to' => 'loot'],
          ['from' => 'start', 'to' => 'rest'],
        ],
      ]),
      'v2_test_middle@1' => $compiler->compile([
        'schema_version' => 'pattern-v2',
        'slug' => 'v2_test_middle',
        'version' => 1,
        'width' => 3,
        'height' => 3,
        'cost' => 4,
        'grid' => [
          [['key' => 'combat_a', 'type' => 'combat'], null, ['key' => 'combat_b', 'type' => 'combat']],
          [null, ['key' => 'shrine', 'type' => 'shrine'], null],
          [['key' => 'hazard', 'type' => 'hazard'], null, ['key' => 'chaos', 'type' => 'chaos']],
        ],
        'connections' => [
          ['from' => 'combat_a', 'to' => 'combat_b'],
          ['from' => 'hazard', 'to' => 'chaos'],
        ],
      ]),
      'v2_test_terminal@1' => $compiler->compile([
        'schema_version' => 'pattern-v2',
        'slug' => 'v2_test_terminal',
        'version' => 1,
        'width' => 2,
        'height' => 1,
        'cost' => 2,
        'grid' => [
          [['key' => 'boss', 'type' => 'boss'], ['key' => 'exit', 'type' => 'exit']],
        ],
        'connections' => [
          ['from' => 'boss', 'to' => 'exit'],
        ],
      ]),
    ];

    return [
      'seed' => 'v2-composer-test',
      'generator_version' => 'pattern-v2',
      'profile' => [
        'budgets' => [
          'cost' => ['target' => 8],
        ],
      ],
      'rules_by_phase' => [
        'start' => [['pattern_slug' => 'v2_test_start', 'pattern_version' => 1, 'base_weight' => 100]],
        'spine' => [['pattern_slug' => 'v2_test_middle', 'pattern_version' => 1, 'base_weight' => 100, 'max_per_run' => 1]],
        'terminal' => [['pattern_slug' => 'v2_test_terminal', 'pattern_version' => 1, 'base_weight' => 100]],
      ],
      'tiles_by_pattern_key' => $tiles,
    ];
  }
}
