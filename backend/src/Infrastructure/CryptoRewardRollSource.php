<?php
declare(strict_types=1);

namespace DiceGoblins\Infrastructure;

use DiceGoblins\Domain\Rewards\RewardRollSource;

final class CryptoRewardRollSource implements RewardRollSource
{
  public function nextBasisPointRoll(): int
  {
    return random_int(1, 10000);
  }
}
