<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Battles;

use InvalidArgumentException;

final class CombatFormationPosition
{
  /** @return array{x:int,y:int} */
  public static function fromSquadPosition(int $position): array
  {
    if ($position < 0 || $position > 8) throw new InvalidArgumentException('Squad position must be from 0 to 8.');
    return ['x' => $position % 3, 'y' => intdiv($position, 3)];
  }
}
