<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Energy;

use DateTimeImmutable;
use InvalidArgumentException;

/** Calculates a successful Energy materialization and spend without persistence. */
final class EnergySpendCalculator
{
  public function __construct(private readonly EnergyCalculator $energy = new EnergyCalculator()) {}

  public function spend(
    int $persistedCurrent,
    DateTimeImmutable $persistedLastRegenerationAt,
    int $normalMaximum,
    float $regenerationPerHour,
    int $cost,
    DateTimeImmutable $now,
  ): EnergySpendResult {
    if ($cost <= 0) throw new InvalidArgumentException('Energy cost must be positive.');

    $effective = $this->energy->calculate(
      $persistedCurrent,
      $persistedLastRegenerationAt,
      $normalMaximum,
      $regenerationPerHour,
      $now,
    );
    if ($effective->current < $cost) {
      throw new InsufficientEnergyException('Effective Energy is insufficient.');
    }

    $postSpend = $effective->current - $cost;
    $reachedCap = $persistedCurrent >= $normalMaximum || $effective->current >= $normalMaximum;
    if ($reachedCap || $postSpend >= $normalMaximum) {
      $anchor = $now;
    } else {
      $elapsed = max(0, $now->getTimestamp() - $persistedLastRegenerationAt->getTimestamp());
      $earnedTicks = (int)floor(($elapsed * $regenerationPerHour) / 3600.0);
      $earnedSeconds = (int)ceil(($earnedTicks * 3600.0) / $regenerationPerHour);
      $anchor = $persistedLastRegenerationAt->modify("+{$earnedSeconds} seconds");
    }

    $view = $this->energy->calculate(
      $postSpend,
      $anchor,
      $normalMaximum,
      $regenerationPerHour,
      $now,
    );
    return new EnergySpendResult($postSpend, $anchor, $view);
  }
}
