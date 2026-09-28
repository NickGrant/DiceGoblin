<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\AcademyQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use Throwable;

final class AcademyReadController
{
  public function __construct(private readonly ?ContentRegistry $content = null) {}

  public function catalog(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
      return;
    }
    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }
    try {
      $content = $this->content ?? ContentRegistry::load(dirname(__DIR__, 2) . '/content');
      $query = new AcademyQuery(new PlayerStateRepository($pdo), new UserUnlockRepository($pdo), $content);
      Response::json(['ok' => true, 'data' => $query->execute($userId)]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'academy_data_integrity_error', 'message' => 'Academy data is unavailable.']], 500);
    }
  }
}
