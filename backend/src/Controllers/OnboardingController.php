<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use Throwable;

final class OnboardingController
{
  public function __construct(private readonly ?ContentRegistry $content = null) {}

  public function status(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
      return;
    }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }
    try {
      $content = $this->content ?? ContentRegistry::loadConfigured();
      $query = new OnboardingQuery(new WarbandUnitRepository($pdo), new UserCodexRepository($pdo),
        new PlayerStateRepository($pdo), $content);
      Response::json(['ok' => true, 'data' => $query->execute($userId)]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'onboarding_state_integrity_error',
        'message' => 'Onboarding state is unavailable.']], 500);
    }
  }
}
