<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Battles;

use InvalidArgumentException;

final class CombatSeedDeriver
{
  public const VERSION = 1;

  public function derive(int $runId, int $nodeId, string $encounterId): string
  {
    if ($runId < 1 || $nodeId < 1 || preg_match('/^encounter\.[a-z0-9][a-z0-9_.-]*$/', $encounterId) !== 1) {
      throw new InvalidArgumentException('Combat seed identity is invalid.');
    }
    return 'combat-seed-v' . self::VERSION . ':' . hash('sha256', $runId . ':' . $nodeId . ':' . $encounterId);
  }
}
