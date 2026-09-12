<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class WarbandFixtureEnvironment
{
  public static function isEnabled(?string $environment, ?string $explicitOptIn): bool
  {
    return $explicitOptIn === '1' && in_array($environment, ['dev', 'test', 'uat'], true);
  }
}
