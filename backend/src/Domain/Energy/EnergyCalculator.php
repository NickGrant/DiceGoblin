<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Energy;

use DateTimeImmutable;
use InvalidArgumentException;

/** Deterministically projects persisted Energy without changing durable state. */
final class EnergyCalculator
{
  public function calculate(
    int $persistedCurrent,
    DateTimeImmutable $persistedLastRegenerationAt,
    int $normalMaximum,
    float $regenerationPerHour,
    DateTimeImmutable $now,
  ): EnergyView {
    if ($persistedCurrent < 0) {
      throw new InvalidArgumentException('Persisted Energy cannot be negative.');
    }
    if ($normalMaximum <= 0) {
      throw new InvalidArgumentException('Normal Energy maximum must be positive.');
    }
    if (!is_finite($regenerationPerHour) || $regenerationPerHour <= 0) {
      throw new InvalidArgumentException('Energy regeneration rate must be a positive finite number.');
    }

    $intervalSeconds = 3600.0 / $regenerationPerHour;

    if ($persistedCurrent >= $normalMaximum) {
      return new EnergyView(
        $persistedCurrent,
        $normalMaximum,
        $regenerationPerHour,
        $intervalSeconds,
        $persistedLastRegenerationAt,
        null,
        null,
      );
    }

    $elapsedSeconds = max(0, $now->getTimestamp() - $persistedLastRegenerationAt->getTimestamp());
    $elapsedTicks = (int)floor(($elapsedSeconds * $regenerationPerHour) / 3600.0);
    $effectiveCurrent = min($normalMaximum, $persistedCurrent + $elapsedTicks);

    if ($effectiveCurrent >= $normalMaximum) {
      return new EnergyView(
        $effectiveCurrent,
        $normalMaximum,
        $regenerationPerHour,
        $intervalSeconds,
        $persistedLastRegenerationAt,
        null,
        null,
      );
    }

    $nextRegenerationAt = $persistedLastRegenerationAt->modify(
      '+' . $this->secondsUntilTick($elapsedTicks + 1, $regenerationPerHour) . ' seconds',
    );
    $fullyRegeneratedAt = $persistedLastRegenerationAt->modify(
      '+' . $this->secondsUntilTick($normalMaximum - $persistedCurrent, $regenerationPerHour) . ' seconds',
    );

    return new EnergyView(
      $effectiveCurrent,
      $normalMaximum,
      $regenerationPerHour,
      $intervalSeconds,
      $persistedLastRegenerationAt,
      $nextRegenerationAt,
      $fullyRegeneratedAt,
    );
  }

  private function secondsUntilTick(int $tickNumber, float $regenerationPerHour): int
  {
    return (int)ceil(($tickNumber * 3600.0) / $regenerationPerHour);
  }
}
