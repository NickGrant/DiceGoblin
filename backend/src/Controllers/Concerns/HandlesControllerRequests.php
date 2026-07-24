<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers\Concerns;

use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\UnitMutationGuardService;
use Throwable;

trait HandlesControllerRequests
{
  private function requireUserId(SessionService $sessionService): ?int
  {
    try {
      return $sessionService->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return null;
    }
  }

  private function requireMutationUserId(SessionService $sessionService, CsrfService $csrfService): ?int
  {
    $userId = $this->requireUserId($sessionService);
    if ($userId === null) {
      return null;
    }

    if (!$this->requireCsrf($csrfService)) {
      return null;
    }

    return $userId;
  }

  private function requireMutableUnit(
    UnitMutationGuardService $unitMutationGuardService,
    int $userId,
    int $unitId,
    string $code = 'active_run_unit_locked',
    string $message = 'Active run units cannot be changed until the run ends.'
  ): bool {
    if ($unitMutationGuardService->isUnitMutableForUser($userId, $unitId)) {
      return true;
    }

    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], 409);
    return false;
  }

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(string $errorCode = 'validation_error', string $message = 'Invalid JSON body.'): ?array
  {
    $body = JsonRequestBody::decode();
    if ($body !== null) {
      return $body;
    }

    Response::json(['ok' => false, 'error' => ['code' => $errorCode, 'message' => $message]], 400);
    return null;
  }

  private function requirePositiveInt(?string $raw, string $field): ?int
  {
    $value = (int)($raw ?? 0);
    if ($value > 0) {
      return $value;
    }

    Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => "{$field} is required."]], 400);
    return null;
  }

  private function requireNonNegativeInt(?string $raw, string $field): ?int
  {
    if ($raw !== null && $raw !== '' && preg_match('/^\d+$/', $raw)) {
      return (int)$raw;
    }

    Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => "{$field} is required."]], 400);
    return null;
  }
}
