<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class UserDataSyncService
{
  public function __construct(
    private readonly PlayerBootstrapper $bootstrapper,
    private readonly UnitLoadoutService $unitLoadoutService,
    private readonly DiceAffixService $diceAffixService,
  ) {}

  public function syncForUser(int $userId): void
  {
    $this->bootstrapper->ensureBaseline($userId);
    $this->unitLoadoutService->ensureStateForUser($userId);
    $this->diceAffixService->ensureAffixesForUser($userId);
  }
}
