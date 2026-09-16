<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\CombatStats;

final class ResolvedCombatStats
{
  public function __construct(
    public readonly int $hp,
    public readonly int $attack,
    public readonly int $defense,
    public readonly int $precision,
    public readonly int $resolve,
  ) {}

  /** @return array{hp:int,attack:int,defense:int,precision:int,resolve:int} */
  public function toArray(): array
  {
    return ['hp' => $this->hp, 'attack' => $this->attack, 'defense' => $this->defense,
      'precision' => $this->precision, 'resolve' => $this->resolve];
  }
}
