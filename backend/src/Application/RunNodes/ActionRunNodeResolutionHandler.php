<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

interface ActionRunNodeResolutionHandler extends RunNodeResolutionHandler
{
  /** @param array<string,mixed> $playerState @param array<string,mixed> $run @param array<string,mixed> $node */
  public function resolveAction(int $userId, array $playerState, array $run, array $node,
    string $encounterId, string $actionId): RunNodeResolutionOutcome;
}
