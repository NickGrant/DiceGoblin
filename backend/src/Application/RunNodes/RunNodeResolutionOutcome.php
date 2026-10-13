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
    public readonly bool $runCompleted = false,
    public readonly bool $nodeCompleted = true,
  ) {
    if (!in_array($resolutionType, ['combat', 'boss', 'loot', 'rest', 'hazard', 'shrine', 'chaos', 'exit', 'interaction_step'], true)) {
      throw new InvalidArgumentException('Run node resolution type is unsupported.');
    }
    if ($runFailed && $runCompleted) throw new InvalidArgumentException('Run cannot fail and complete together.');
    if (!$nodeCompleted && ($resolutionType !== 'interaction_step' || $runFailed || $runCompleted)) {
      throw new InvalidArgumentException('Intermediate interaction outcome is invalid.');
    }
  }
}
