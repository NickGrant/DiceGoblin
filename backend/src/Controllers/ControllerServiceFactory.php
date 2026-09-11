<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\GameBootstrapQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Energy\EnergyCalculator;
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
   * Content-independent infrastructure shared by API controllers.
   *
   * @return array{
   *   userRepo: UserRepository,
   *   playerStateRepo: PlayerStateRepository,
   *   csrfService: CsrfService,
   *   sessionService: SessionService,
   *   passwordResetService: PasswordResetService
   * }
   */
  public static function buildCore(PDO $pdo): array
  {
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $csrfService = new CsrfService();
    $passwordResetService = new PasswordResetService($pdo, $userRepo);
    $sessionService = new SessionService($userRepo, $csrfService);

    return [
      'userRepo' => $userRepo,
      'playerStateRepo' => $playerStateRepo,
      'csrfService' => $csrfService,
      'sessionService' => $sessionService,
      'passwordResetService' => $passwordResetService,
    ];
  }

  /**
   * Adds the one validated registry shared by content-dependent operations in
   * the current composition graph.
   *
   * @param array<string,mixed>|null $core
   * @return array<string,mixed>
   */
  public static function buildContentAware(PDO $pdo, ?array $core = null, ?ContentRegistry $content = null): array
  {
    $core ??= self::buildCore($pdo);
    $content ??= ContentRegistry::load(dirname(__DIR__, 2) . '/content');

    return array_merge($core, [
      'contentRegistry' => $content,
      'accountCreationService' => new AccountCreationService(
        $pdo,
        $core['userRepo'],
        $core['playerStateRepo'],
        $content->startingEnergy(),
      ),
      'gameBootstrapQuery' => new GameBootstrapQuery(
        $core['userRepo'],
        $core['playerStateRepo'],
        $content,
        $core['csrfService'],
        new EnergyCalculator(),
      ),
    ]);
  }
}
