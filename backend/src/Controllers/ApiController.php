<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\EnergyService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\ProfileService;
use DiceGoblins\Services\SessionService;

final class ApiController
{
  /**
   * GET /health
   */
  public function health(): void
  {
    $dbOk = false;

    try {
      $pdo = Db::pdo();
      $pdo->query('SELECT 1')->fetchColumn();
      $dbOk = true;
    } catch (\Throwable $e) {
      $dbOk = false;
    }

    Response::json([
      'ok' => true,
      'service' => 'dice-goblins-backend',
      'env' => Env::get('APP_ENV', 'unknown'),
      'time' => gmdate('c'),
      'db_ok' => $dbOk,
    ]);
  }

  /**
 * GET /api/v1/session
 */
public function session(): void
{
  $services = $this->services();

  try {
    $payload = $services['sessionService']->getSessionPayload();

    Response::json([
      'ok' => true,
      'data' => $payload,
    ]);
  } catch (\Throwable $e) {
    Response::json([
      'ok' => false,
      'error' => [
        'code' => 'server_error',
        'message' => 'Unexpected error.',
      ],
    ], 500);
  }
}


  /**
   * GET /api/v1/profile
   *
   * Hydrates the player state used by the frontend: squads, units, dice, energy, unlocks, active run, etc.
   */
  public function profile(): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (\Throwable $e) {
      // Keep response shape consistent and simple for the client.
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    try {
      $data = $services['profileService']->getProfile($userId);

      Response::json([
        'ok' => true,
        'data' => $data,
      ]);
    } catch (\Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * Simple manual composition (no DI container).
   *
   * @return array{
   *   sessionService: SessionService,
   *   profileService: ProfileService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();

    // Repositories
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);
    $teamRepo = new TeamRepository($pdo);
    $unitRepo = new UnitRepository($pdo);
    $diceRepo = new DiceRepository($pdo);
    $regionRepo = new RegionRepository($pdo);
    $runRepo = new RunRepository($pdo);

    // Services
    $csrfService = new CsrfService();

    $bootstrapper = new PlayerBootstrapper(
      $playerStateRepo,
      $energyRepo
    );

    $energyService = new EnergyService($energyRepo);

    $sessionService = new SessionService(
      $userRepo,
      $csrfService,
      $bootstrapper
    );

    $profileService = new ProfileService(
      $bootstrapper,
      $energyService,
      $playerStateRepo,
      $teamRepo,
      $unitRepo,
      $diceRepo,
      $regionRepo,
      $runRepo,
      $pdo
    );

    return [
      'sessionService' => $sessionService,
      'profileService' => $profileService,
    ];
  }
}
