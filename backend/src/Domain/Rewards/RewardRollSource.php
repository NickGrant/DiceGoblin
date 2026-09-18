<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Rewards;

interface RewardRollSource
{
  public function nextBasisPointRoll(): int;
}
