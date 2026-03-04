<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Services\PlayerBootstrapper.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use RuntimeException;
use Throwable;

/**
 * PlayerBootstrapper is responsible for ensuring a user has the minimum required
 * DB rows to safely call "read" endpoints like /session and /profile.
 *
 * Keep this class conservative:
 * - It should create "state scaffolding" rows (player_state, energy_state).
 * - It should NOT auto-create gameplay content (teams/units/dice/unlocks) unless explicitly requested,
 *   because those rules tend to evolve and are better handled by a dedicated provisioning action/service.
 */
final class PlayerBootstrapper
{
  public function __construct(
    private readonly PlayerStateRepository $playerStateRepo,
    private readonly EnergyRepository $energyRepo,
    private readonly GrantService $grantService,
  ) {}

  /**
   * Ensures minimal baseline rows exist for the user.
   *
   * Safe to call on every request (idempotent).
   */
  public function ensureBaseline(int $userId): void
  {
    if ($userId <= 0) {
      throw new RuntimeException('Invalid userId.');
    }

    // Both repositories use idempotent creation patterns (INSERT...ON DUPLICATE KEY UPDATE / existence checks).
    $this->playerStateRepo->ensurePlayerState($userId);
    $this->energyRepo->ensureEnergyState($userId);

    // Provision starter gameplay content exactly once (idempotent via user_grants).
    $this->grantService->ensureStarterPackGranted($userId);
  }

  /**
   * Same as ensureBaseline(), but returns which baseline rows were missing before this call.
   *
   * This is useful for "first login" flows if you want to conditionally trigger
   * additional provisioning elsewhere.
   *
   * @return array{created_player_state:bool,created_energy_state:bool}
   */
  public function ensureBaselineWithReport(int $userId): array
  {
    if ($userId <= 0) {
      throw new RuntimeException('Invalid userId.');
    }

    try {
      $hadPlayerState = $this->playerStateRepo->getPlayerState($userId) !== null;
      $hadEnergyState = $this->energyRepo->getEnergyState($userId) !== null;

      $this->ensureBaseline($userId);

      return [
        'created_player_state' => !$hadPlayerState,
        'created_energy_state' => !$hadEnergyState,
      ];
    } catch (Throwable $e) {
      // "ensure" is used during reads; fail loudly so you notice schema mismatches early.
      throw $e;
    }
  }

  /**
   * Optional: create baseline energy state with explicit defaults.
   * Use if you want to configure defaults from Env/config without changing repository defaults.
   */
  public function ensureEnergyStateWithDefaults(
    int $userId,
    int $energyCurrent,
    int $energyMax,
    float $regenRatePerHour,
  ): void {
    if ($userId <= 0) {
      throw new RuntimeException('Invalid userId.');
    }
    if ($energyMax < 0 || $energyCurrent < 0) {
      throw new RuntimeException('Energy values cannot be negative.');
    }
    if ($energyCurrent > $energyMax) {
      $energyCurrent = $energyMax;
    }

    $this->energyRepo->ensureEnergyState($userId, $energyCurrent, $energyMax, $regenRatePerHour);
  }
}
