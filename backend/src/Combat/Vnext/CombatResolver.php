<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

interface CombatResolver
{
  public function resolve(CombatInput $input): CombatResult;
}
