<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Domain\Rewards\RewardRollSource;
use RuntimeException;

final class ScriptedRewardRollSource implements RewardRollSource
{
  private int $offset = 0;

  /** @param list<int> $rolls */
  public function __construct(private readonly array $rolls) {}

  public function nextBasisPointRoll(): int
  {
    if (!array_key_exists($this->offset, $this->rolls)) throw new RuntimeException('Scripted reward rolls exhausted.');
    return $this->rolls[$this->offset++];
  }

  public function consumed(): int { return $this->offset; }
}
