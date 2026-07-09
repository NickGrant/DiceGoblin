<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers\Concerns;

use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Services\SessionService;
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
