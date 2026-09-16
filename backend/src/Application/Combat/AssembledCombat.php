<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Combat;

use DiceGoblins\Combat\Vnext\CombatInput;

final class AssembledCombat
{
  /** @param list<array<string,mixed>> $manifest */
  public function __construct(
    public readonly CombatInput $input,
    public readonly array $manifest,
  ) {}
}
