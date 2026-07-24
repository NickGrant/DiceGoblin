<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\HandlesControllerRequests;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Services\BountyBoardService;
use RuntimeException;
use Throwable;

final class BountyBoardController
{
  use HandlesControllerRequests;
  use RequiresCsrf;

  public function board(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      Response::json(['ok' => true, 'data' => $svc['bountyBoardService']->board($userId)]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function accept(): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $slug = trim((string)($body['slug'] ?? ''));
    if ($slug === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'slug is required.']], 400);
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      Response::json(['ok' => true, 'data' => $svc['bountyBoardService']->accept($userId, $slug)]);
    } catch (RuntimeException $e) {
      $this->handleDomainError($e);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function sync(): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      Response::json(['ok' => true, 'data' => $svc['bountyBoardService']->syncProgress($userId)]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function claim(?string $userBountyId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $bountyId = $this->requirePositiveInt($userBountyId, 'bounty_id');
    if ($bountyId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      Response::json(['ok' => true, 'data' => $svc['bountyBoardService']->claim($userId, $bountyId)]);
    } catch (RuntimeException $e) {
      $this->handleDomainError($e);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  private function handleDomainError(RuntimeException $e): void
  {
    $message = $e->getMessage();
    $status = match ($message) {
      'bounty_not_found' => 404,
      'bounty_active_limit', 'bounty_already_accepted', 'bounty_not_completed' => 409,
      default => 400,
    };
    $code = in_array($message, [
      'bounty_not_found',
      'bounty_active_limit',
      'bounty_already_accepted',
      'bounty_not_completed',
    ], true) ? $message : 'validation_error';

    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
  }

  /**
   * @return array<string,mixed>
   */
  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'csrfService' => $core['csrfService'],
      'sessionService' => $core['sessionService'],
      'bootstrapper' => $core['bootstrapper'],
      'bountyBoardService' => new BountyBoardService($pdo, $core['playerStateRepo']),
    ];
  }
}
