<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;
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
   *   grantService: GrantService,
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
    $grantService = new GrantService();
    $bootstrapper = new PlayerBootstrapper($playerStateRepo, $energyRepo, $grantService);
    $sessionService = new SessionService($userRepo, $csrfService, $bootstrapper);

    return [
      'userRepo' => $userRepo,
      'playerStateRepo' => $playerStateRepo,
      'energyRepo' => $energyRepo,
      'csrfService' => $csrfService,
      'grantService' => $grantService,
      'bootstrapper' => $bootstrapper,
      'sessionService' => $sessionService,
    ];
  }
}
