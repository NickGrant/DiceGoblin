<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use InvalidArgumentException;

final class RunNodeResolutionOutcome
{
  /** @param array<string,mixed> $facts */
  public function __construct(
    public readonly string $resolutionType,
    public readonly array $facts,
    public readonly bool $runFailed = false,
  ) {
    if (!in_array($resolutionType, ['combat', 'boss', 'loot', 'rest'], true)) {
      throw new InvalidArgumentException('Run node resolution type is unsupported.');
    }
  }
}
