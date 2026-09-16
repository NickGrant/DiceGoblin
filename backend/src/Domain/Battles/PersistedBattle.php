<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Battles;

use DateTimeImmutable;
use InvalidArgumentException;

final class PersistedBattle
{
  public function __construct(
    public readonly int $id,
    public readonly int $runId,
    public readonly int $runNodeId,
    public readonly FinalizedBattle $battle,
    public readonly DateTimeImmutable $createdAt,
  ) {
    if ($id < 1 || $runId < 1 || $runNodeId < 1) throw new InvalidArgumentException('Persisted battle identities must be positive.');
  }
}
