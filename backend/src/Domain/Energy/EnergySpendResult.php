<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Energy;

use DateTimeImmutable;

final class EnergySpendResult
{
  public function __construct(
    public readonly int $persistedCurrent,
    public readonly DateTimeImmutable $persistedLastRegenerationAt,
    public readonly EnergyView $view,
  ) {}
}
