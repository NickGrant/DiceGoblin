<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\CodexQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Repositories\UserCodexRepository;
use Throwable;

final class CodexController
{
  public function __construct(private readonly ?ContentRegistry $content = null) {}

  /** GET /api/v1/codex */
  public function catalog(): void
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
      $content = $this->content ?? ContentRegistry::load(dirname(__DIR__, 2) . '/content');
      $query = new CodexQuery(new UserCodexRepository($pdo), $content);
      Response::json(['ok' => true, 'data' => $query->execute($userId)]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'codex_data_integrity_error', 'message' => 'Codex data is unavailable.']], 500);
    }
  }
}
