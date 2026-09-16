<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BaseLevelStatResolverTest extends TestCase
{
  public function testAuthoredBruiserAtLevelOneAndHigherLevels(): void
  {
    $type = ContentRegistry::load(dirname(__DIR__, 2) . '/content')->unitType('unit_type.bruiser');
    $resolver = new BaseLevelStatResolver();
    $this->assertSame(['hp' => 22, 'attack' => 5, 'defense' => 3, 'precision' => 5, 'resolve' => 5],
      $resolver->resolve($type['base_stats'], $type['growth_per_level'], 1)->toArray());
    $this->assertSame(['hp' => 26, 'attack' => 7, 'defense' => 5, 'precision' => 7, 'resolve' => 7],
      $resolver->resolve($type['base_stats'], $type['growth_per_level'], 3)->toArray());
    $this->assertSame(['hp' => 30, 'attack' => 9, 'defense' => 7, 'precision' => 9, 'resolve' => 9],
      $resolver->resolve($type['base_stats'], $type['growth_per_level'], 5)->toArray());
    $this->assertNotContains('speed', array_keys($resolver->resolve($type['base_stats'], $type['growth_per_level'], 1)->toArray()));
  }

  /** @dataProvider invalidInputProvider */
  public function testInvalidStatAndLevelInputIsRejected(array $base, array $growth, int $level): void
  {
    $this->expectException(InvalidArgumentException::class);
    (new BaseLevelStatResolver())->resolve($base, $growth, $level);
  }

  public function invalidInputProvider(): array
  {
    $base = ['hp' => 10, 'attack' => 0, 'defense' => 1, 'precision' => 2, 'resolve' => 3];
    $growth = ['hp' => 2, 'attack' => 1, 'defense' => 0, 'precision' => 1, 'resolve' => 0];
    return [
      'zero level' => [$base, $growth, 0],
      'negative level' => [$base, $growth, -1],
      'missing base' => [array_diff_key($base, ['resolve' => true]), $growth, 1],
      'missing growth' => [$base, array_diff_key($growth, ['attack' => true]), 2],
      'zero base hp' => [array_replace($base, ['hp' => 0]), $growth, 1],
      'negative base' => [array_replace($base, ['defense' => -1]), $growth, 1],
      'negative growth' => [$base, array_replace($growth, ['resolve' => -1]), 2],
      'noninteger base' => [array_replace($base, ['precision' => '2']), $growth, 1],
      'noninteger growth' => [$base, array_replace($growth, ['hp' => 1.5]), 2],
      'speed base' => [array_replace($base, ['speed' => 1]), $growth, 1],
      'speed growth' => [$base, array_replace($growth, ['speed' => 1]), 1],
      'overflow' => [$base, array_replace($growth, ['hp' => PHP_INT_MAX]), 3],
    ];
  }
}
