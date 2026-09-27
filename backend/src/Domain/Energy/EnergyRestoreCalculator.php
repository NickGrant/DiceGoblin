<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Energy;

use DateTimeImmutable;
use DiceGoblins\Support\ClientSafeInteger;
use InvalidArgumentException;

/** Calculates Energy regeneration materialization and consumable restoration without persistence. */
final class EnergyRestoreCalculator
{
  public function __construct(private readonly EnergyCalculator $energy = new EnergyCalculator()) {}

  public function restore(
    int $persistedCurrent,
    DateTimeImmutable $persistedLastRegenerationAt,
    int $normalMaximum,
    float $regenerationPerHour,
    int $amount,
    DateTimeImmutable $now,
  ): EnergyRestoreResult {
    if ($amount <= 0 || $amount > ClientSafeInteger::MAXIMUM) {
      throw new InvalidArgumentException('Energy restoration amount must be client-safe and positive.');
    }
    if ($persistedCurrent > ClientSafeInteger::MAXIMUM || $normalMaximum > ClientSafeInteger::MAXIMUM) {
      throw new InvalidArgumentException('Energy state exceeds the client-safe range.');
    }
    $effective = $this->energy->calculate(
      $persistedCurrent, $persistedLastRegenerationAt, $normalMaximum, $regenerationPerHour, $now,
    );
    if ($effective->current >= $normalMaximum) {
      throw new EnergyRestoreUnavailableException('Energy is already at or above its normal maximum.');
    }
    if ($amount > ClientSafeInteger::MAXIMUM - $effective->current) {
      throw new InvalidArgumentException('Restored Energy exceeds the client-safe range.');
    }
    $restored = $effective->current + $amount;
    if ($restored >= $normalMaximum) {
      $anchor = $now;
    } else {
      $elapsed = max(0, $now->getTimestamp() - $persistedLastRegenerationAt->getTimestamp());
      $earnedTicks = (int)floor(($elapsed * $regenerationPerHour) / 3600.0);
      $earnedSeconds = (int)ceil(($earnedTicks * 3600.0) / $regenerationPerHour);
      $anchor = $persistedLastRegenerationAt->modify("+{$earnedSeconds} seconds");
    }
    return new EnergyRestoreResult(
      $restored,
      $anchor,
      $this->energy->calculate($restored, $anchor, $normalMaximum, $regenerationPerHour, $now),
    );
  }
}
