<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\StarterPackProvisioningService;
use PDO;

final class ControllerServiceFactory
{
  /**
   * Shared auth/bootstrap graph used by API mutation/read controllers.
   *
   * @return array{
   *   userRepo: UserRepository,
   *   playerStateRepo: PlayerStateRepository,
   *   energyRepo: EnergyRepository,
   *   csrfService: CsrfService,
   *   starterPackProvisioningService: StarterPackProvisioningService,
   *   bootstrapper: PlayerBootstrapper,
   *   sessionService: SessionService
   * }
   */
  public static function buildCore(PDO $pdo): array
  {
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);
    $csrfService = new CsrfService();
    $starterPackProvisioningService = new StarterPackProvisioningService();
    $bootstrapper = new PlayerBootstrapper($playerStateRepo, $energyRepo, $starterPackProvisioningService);
    $sessionService = new SessionService($userRepo, $csrfService, $bootstrapper);

    return [
      'userRepo' => $userRepo,
      'playerStateRepo' => $playerStateRepo,
      'energyRepo' => $energyRepo,
      'csrfService' => $csrfService,
      'starterPackProvisioningService' => $starterPackProvisioningService,
      'bootstrapper' => $bootstrapper,
      'sessionService' => $sessionService,
    ];
  }
}
