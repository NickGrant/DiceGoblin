<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Commands\RunStartIntegrityException;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class RunController
{
  use RequiresCsrf;

  /** POST /api/v1/runs */
  public function start(): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->error('invalid_run_request', 'Run request is invalid.', 422);
      return;
    }

    try {
      $result = $services['startRunCommand']->execute(
        $services['userId'],
        $body,
        is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null,
      );
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (RunStartException $e) {
      $this->error($e->errorCode, $e->publicMessage, $e->httpStatus);
    } catch (RunStartIntegrityException) {
      $this->error('run_data_integrity_error', 'Run configuration data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  /** @return array<string,mixed>|null */
  private function mutationServices(): ?array
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
    if (!$this->requireCsrf($core['csrfService'])) return null;

    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core);
      $services['userId'] = $userId;
      return $services;
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
      return null;
    }
  }

  private function error(string $code, string $message, int $status): void
  {
    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
  }
}
