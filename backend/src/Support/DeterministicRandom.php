<?php
declare(strict_types=1);

namespace DiceGoblins\Support;

use InvalidArgumentException;

final class DeterministicRandom
{
  private int $cursor = 0;

  public function __construct(private readonly string $seed)
  {
  }

  public function nextInt(int $min, int $max): int
  {
    if ($max < $min) {
      throw new InvalidArgumentException('The maximum random bound must be greater than or equal to the minimum bound.');
    }

    $range = ($max - $min) + 1;
    return $min + ($this->nextUInt32() % $range);
  }

  public function chance(int $numerator, int $denominator): bool
  {
    if ($denominator <= 0 || $numerator < 0 || $numerator > $denominator) {
      throw new InvalidArgumentException('Chance requires 0 <= numerator <= denominator and a positive denominator.');
    }

    return $this->nextInt(1, $denominator) <= $numerator;
  }

  /**
   * @template T
   * @param list<T> $items
   * @param callable(T):int $weight
   * @return T
   */
  public function weightedChoice(array $items, callable $weight): mixed
  {
    if ($items === []) {
      throw new InvalidArgumentException('Cannot choose from an empty weighted list.');
    }

    $total = 0;
    $weights = [];
    foreach ($items as $index => $item) {
      $itemWeight = max(0, $weight($item));
      $weights[$index] = $itemWeight;
      $total += $itemWeight;
    }

    if ($total <= 0) {
      throw new InvalidArgumentException('Weighted choice requires at least one positive weight.');
    }

    $roll = $this->nextInt(1, $total);
    foreach ($items as $index => $item) {
      $roll -= $weights[$index];
      if ($roll <= 0) {
        return $item;
      }
    }

    return $items[array_key_last($items)];
  }

  public function fork(string $salt): self
  {
    return new self($this->seed . ':' . $salt);
  }

  private function nextUInt32(): int
  {
    $hash = hash('sha256', $this->seed . ':' . $this->cursor++);
    return (int)hexdec(substr($hash, 0, 8));
  }
}
