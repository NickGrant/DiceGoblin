<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Response;
use Throwable;

final class ApiController
{
  public function health(): void
  {
    $dbOk = false;
    try {
      Db::pdo()->query('SELECT 1')->fetchColumn();
      $dbOk = true;
    } catch (Throwable) {
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

  public function session(): void
  {
    $core = ControllerServiceFactory::buildCore(Db::pdo());
    try {
      Response::json(['ok' => true, 'data' => $core['sessionService']->getSessionPayload()]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => [
        'code' => 'server_error', 'message' => 'Unexpected error.',
      ]], 500);
    }
  }
}
