<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\AccountCreationService;
use DiceGoblins\Services\PasswordResetService;
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
   *   csrfService: CsrfService,
   *   sessionService: SessionService,
   *   accountCreationService: AccountCreationService,
   *   passwordResetService: PasswordResetService
   * }
   */
  public static function buildCore(PDO $pdo): array
  {
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $csrfService = new CsrfService();
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $accountCreationService = new AccountCreationService($pdo, $userRepo, $playerStateRepo, $content->startingEnergy());
    $passwordResetService = new PasswordResetService($pdo, $userRepo);
    $sessionService = new SessionService($userRepo, $csrfService);

    return [
      'userRepo' => $userRepo,
      'playerStateRepo' => $playerStateRepo,
      'csrfService' => $csrfService,
      'sessionService' => $sessionService,
      'accountCreationService' => $accountCreationService,
      'passwordResetService' => $passwordResetService,
    ];
  }
}
