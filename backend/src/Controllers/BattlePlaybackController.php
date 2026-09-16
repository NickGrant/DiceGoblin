<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\BattlePlaybackIntegrityException;
use DiceGoblins\Application\Queries\BattlePlaybackNotFoundException;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use Throwable;

final class BattlePlaybackController
{
  /** GET /api/v1/battles/:battleId/playback */
  public function playback(?string $battleId): void
  {
    $services = $this->authenticatedServices();
    if ($services === null) return;
    $id = $this->positiveId($battleId);
    if ($id === null) {
      $this->notFound();
      return;
    }
    try {
      Response::json(['ok' => true, 'data' => $services['battlePlaybackQuery']->execute($services['userId'], $id)]);
    } catch (BattlePlaybackNotFoundException) {
      $this->notFound();
    } catch (BattlePlaybackIntegrityException) {
      $this->error('battle_data_integrity_error', 'Battle playback is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  /** @return array<string,mixed>|null */
  private function authenticatedServices(): ?array
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
      return null;
    }
    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      $this->error('unauthorized', 'No active session.', 401);
      return null;
    }
    try {
      $services = ControllerServiceFactory::buildBattleRead($pdo, $core);
      $services['userId'] = $userId;
      return $services;
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
      return null;
    }
  }

  private function positiveId(?string $value): ?int
  {
    if ($value === null || preg_match('/^[1-9][0-9]*$/D', $value) !== 1
      || (int)$value <= 0 || (string)(int)$value !== $value) return null;
    return (int)$value;
  }

  private function notFound(): void
  {
    $this->error('battle_not_found', 'Battle is unavailable.', 404);
  }

  private function error(string $code, string $message, int $status): void
  {
    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
  }
}
