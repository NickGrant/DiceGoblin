<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Queries\GameBootstrapIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use Throwable;

final class GameBootstrapController
{
  /** GET /api/v1/game/bootstrap */
  public function bootstrap(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
      return;
    }

    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core);
      $data = $services['gameBootstrapQuery']->execute(
        $userId,
        new DateTimeImmutable('now', new DateTimeZone('UTC')),
      );

      Response::json([
        'ok' => true,
        'data' => $data,
      ]);
    } catch (GameBootstrapIntegrityException|CurrentRunIntegrityException|WarbandIntegrityException) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'player_state_integrity_error',
          'message' => 'Required player state is unavailable.',
        ],
      ], 500);
    } catch (Throwable) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }
}
