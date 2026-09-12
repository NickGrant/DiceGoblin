<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\WarbandFixtureEnvironment;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Response;
use Throwable;

final class WarbandFixtureController
{
  use RequiresCsrf;

  /** POST /api/v1/debug/fixtures/warband */
  public function replace(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      $this->serverError();
      return;
    }

    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }

    if (!WarbandFixtureEnvironment::isEnabled(Env::get('APP_ENV'), Env::get('ENABLE_WARBAND_FIXTURES'))) {
      Response::json(['ok' => false, 'error' => ['code' => 'warband_fixture_disabled', 'message' => 'Warband fixtures are unavailable.']], 404);
      return;
    }

    if (!$this->requireCsrf($core['csrfService'])) return;

    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core);
      $fixture = $services['provisionWarbandFixtureCommand']->execute($userId);
      Response::json(['ok' => true, 'data' => ['fixture' => $fixture]], 200);
    } catch (WarbandIntegrityException) {
      Response::json(['ok' => false, 'error' => ['code' => 'warband_data_integrity_error', 'message' => 'Warband fixture content is invalid.']], 500);
    } catch (Throwable) {
      $this->serverError();
    }
  }

  private function serverError(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
  }
}
