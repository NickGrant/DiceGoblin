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
    int $regenerationPerHour,
    DateTimeImmutable $now,
  ): EnergyView {
    if ($persistedCurrent < 0) {
      throw new InvalidArgumentException('Persisted Energy cannot be negative.');
    }
    if ($normalMaximum <= 0) {
      throw new InvalidArgumentException('Normal Energy maximum must be positive.');
    }
    if ($regenerationPerHour <= 0 || 3600 % $regenerationPerHour !== 0) {
      throw new InvalidArgumentException('Energy regeneration rate must be positive and divide evenly into one hour.');
    }

    $intervalSeconds = intdiv(3600, $regenerationPerHour);

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
    $elapsedTicks = intdiv($elapsedSeconds, $intervalSeconds);
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

    $effectiveTickAnchor = $persistedLastRegenerationAt->modify('+' . ($elapsedTicks * $intervalSeconds) . ' seconds');
    $nextRegenerationAt = $effectiveTickAnchor->modify("+{$intervalSeconds} seconds");
    $missingEnergy = $normalMaximum - $effectiveCurrent;
    $fullyRegeneratedAt = $nextRegenerationAt->modify('+' . (($missingEnergy - 1) * $intervalSeconds) . ' seconds');

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
}
