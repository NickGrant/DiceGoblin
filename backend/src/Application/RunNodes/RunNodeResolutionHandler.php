<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

interface RunNodeResolutionHandler
{
  public function nodeTypeId(): string;

  /** @param array<string,mixed> $playerState @param array<string,mixed> $run @param array<string,mixed> $node */
  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome;
}
