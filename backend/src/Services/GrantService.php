<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

/**
 * Backward-compatible wrapper around starter-pack provisioning.
 * New code should prefer StarterPackProvisioningService directly.
 */
final class GrantService
{
  private StarterPackProvisioningService $starterPackProvisioningService;

  public function __construct(?StarterPackProvisioningService $starterPackProvisioningService = null)
  {
    $this->starterPackProvisioningService = $starterPackProvisioningService ?? new StarterPackProvisioningService();
  }

  public function ensureStarterPackGranted(int $userId): void
  {
    $this->starterPackProvisioningService->ensureStarterPackGranted($userId);
  }
}
