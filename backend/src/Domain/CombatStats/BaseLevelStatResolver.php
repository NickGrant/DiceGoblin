<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\CombatStats;

use InvalidArgumentException;

final class BaseLevelStatResolver
{
  private const STATS = ['hp', 'attack', 'defense', 'precision', 'resolve'];

  /** @param array<string,mixed> $baseStats @param array<string,mixed> $growthPerLevel */
  public function resolve(array $baseStats, array $growthPerLevel, int $level): ResolvedCombatStats
  {
    if ($level < 1) throw new InvalidArgumentException('Unit level must be at least 1.');
    $resolved = [];
    foreach (['base_stats' => $baseStats, 'growth_per_level' => $growthPerLevel] as $name => $block) {
      $keys = array_keys($block);
      $expected = self::STATS;
      sort($keys);
      sort($expected);
      if ($keys !== $expected) {
        throw new InvalidArgumentException("{$name} must contain exactly the five combat stats.");
      }
      foreach (self::STATS as $stat) {
        $minimum = $name === 'base_stats' && $stat === 'hp' ? 1 : 0;
        if (!is_int($block[$stat]) || $block[$stat] < $minimum) {
          throw new InvalidArgumentException("{$name}.{$stat} must be an integer of at least {$minimum}.");
        }
      }
    }
    foreach (self::STATS as $stat) {
      $base = $baseStats[$stat];
      $growth = $growthPerLevel[$stat];
      if ($growth > 0 && $level - 1 > intdiv(PHP_INT_MAX - $base, $growth)) {
        throw new InvalidArgumentException("Resolved {$stat} exceeds the integer range.");
      }
      $resolved[$stat] = $base + $growth * ($level - 1);
    }
    return new ResolvedCombatStats($resolved['hp'], $resolved['attack'], $resolved['defense'], $resolved['precision'], $resolved['resolve']);
  }
}
