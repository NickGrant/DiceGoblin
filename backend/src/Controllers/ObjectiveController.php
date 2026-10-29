<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Repositories\UserObjectiveRepository;
use Throwable;

final class ObjectiveController
{
  public function __construct(private readonly ?ContentRegistry $content = null) {}

  /** GET /api/v1/objectives */
  public function catalog(): void
  {
    try { $pdo = Db::pdo(); $core = ControllerServiceFactory::buildCore($pdo); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return; }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) { $this->error('unauthorized', 'No active session.', 401); return; }
    try {
      $content = $this->content ?? ContentRegistry::loadConfigured();
      $result = (new ObjectiveQuery(new UserObjectiveRepository($pdo), $content))->execute($userId);
      Response::json(['ok' => true, 'data' => $result]);
    } catch (Throwable) {
      $this->error('objective_data_integrity_error', 'Objectives are unavailable.', 500);
    }
  }

  private function error(string $code, string $message, int $status): void
  { Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status); }
}
