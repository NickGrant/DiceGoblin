<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Progression;

use InvalidArgumentException;
use OverflowException;

final class UnitXpResolver
{
  /** @return array{level:int,xp:int} */
  public function apply(int $level, int $xp, int $amount): array
  {
    if ($level < 1 || $amount < 1) throw new InvalidArgumentException('XP transition input is invalid.');
    $threshold = $this->threshold($level);
    if ($xp < 0 || $xp >= $threshold) throw new InvalidArgumentException('Current XP must be normalized within the current level.');
    if ($amount > PHP_INT_MAX - $xp) throw new OverflowException('XP addition overflowed.');
    $available = $xp + $amount;

    $low = 0;
    $high = 1;
    $maximumSteps = PHP_INT_MAX - $level;
    while ($high <= $maximumSteps && $this->costWithin($level, $high, $available) !== null) {
      $low = $high;
      if ($high > intdiv($maximumSteps, 2)) {
        $high = $maximumSteps;
        if ($high === $low) break;
      } else {
        $high *= 2;
      }
    }
    $high = min($high, $maximumSteps);
    while ($low < $high) {
      $middle = $low + intdiv($high - $low + 1, 2);
      if ($this->costWithin($level, $middle, $available) !== null) $low = $middle;
      else $high = $middle - 1;
    }

    $cost = $this->costWithin($level, $low, $available);
    if ($cost === null || $level > PHP_INT_MAX - $low) throw new OverflowException('XP level calculation overflowed.');
    $resolvedLevel = $level + $low;
    $resolvedXp = $available - $cost;
    $this->threshold($resolvedLevel);
    return ['level' => $resolvedLevel, 'xp' => $resolvedXp];
  }

  public function threshold(int $level): int
  {
    if ($level < 1) throw new InvalidArgumentException('Unit level must be positive.');
    if ($level > intdiv(PHP_INT_MAX, 100)) throw new OverflowException('XP threshold overflowed.');
    return $level * 100;
  }

  private function costWithin(int $level, int $steps, int $limit): ?int
  {
    if ($steps === 0) return 0;
    if ($steps < 0 || $level > intdiv(PHP_INT_MAX - $steps + 1, 2)) return null;
    $left = $steps;
    $right = (2 * $level) + $steps - 1;
    if (($left & 1) === 0) $left = intdiv($left, 2);
    else $right = intdiv($right, 2);
    if ($right !== 0 && $left > intdiv($limit, $right)) return null;
    $product = $left * $right;
    if ($product > intdiv($limit, 100)) return null;
    return $product * 100;
  }
}
