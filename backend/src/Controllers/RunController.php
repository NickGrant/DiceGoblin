<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Commands\RunStartIntegrityException;
use DiceGoblins\Application\Commands\RunNotFoundException;
use DiceGoblins\Application\Commands\RunLifecycleConflictException;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\CombatConfigurationException;
use DiceGoblins\Application\Commands\CombatResolutionIntegrityException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class RunController
{
  use RequiresCsrf;

  /** GET /api/v1/runs/current */
  public function current(): void
  {
    $services = $this->authenticatedServices();
    if ($services === null) return;
    try {
      Response::json(['ok' => true, 'data' => $services['currentRunQuery']->execute($services['userId'])]);
    } catch (CurrentRunIntegrityException) {
      $this->error('run_data_integrity_error', 'Run data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  /** POST /api/v1/runs/:runId/abandon */
  public function abandon(?string $runId): void
  {
    $id = $this->runId($runId);
    if ($id === null) return;
    $services = $this->mutationServices();
    if ($services === null) return;
    try {
      Response::json(['ok' => true, 'data' => $services['abandonRunCommand']->execute($services['userId'], $id)]);
    } catch (RunNotFoundException) {
      $this->notFound();
    } catch (RunLifecycleConflictException) {
      $this->error('run_lifecycle_conflict', 'Run cannot be abandoned from its current state.', 409);
    } catch (CurrentRunIntegrityException) {
      $this->error('run_data_integrity_error', 'Run data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

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

  /** POST /api/v1/runs/:runId/nodes/:nodeId/resolve */
  public function resolveNode(?string $runId, ?string $nodeId): void
  {
    $run = $this->positiveId($runId);
    $node = $this->positiveId($nodeId);
    if ($run === null || $node === null) {
      $this->error('run_node_not_found', 'Run node is unavailable.', 404);
      return;
    }
    $services = $this->mutationServices();
    if ($services === null) return;
    if (!JsonRequestBody::isStrictlyEmpty()) {
      $this->error('invalid_node_request', 'Node resolution request must have no body.', 400);
      return;
    }
    try {
      $result = $services['resolveRunNodeCommand']->execute(
        $services['userId'],
        $run,
        $node,
        is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null,
      );
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (RunNodeResolutionException $e) {
      $this->error($e->errorCode, $e->publicMessage, $e->httpStatus);
    } catch (CombatConfigurationException) {
      $this->error('combat_configuration_invalid', 'Participating combat configuration is invalid.', 422);
    } catch (CombatResolutionIntegrityException) {
      $this->error('run_data_integrity_error', 'Run combat data is unavailable.', 500);
    } catch (RunNodeResolutionIntegrityException) {
      $this->error('run_data_integrity_error', 'Run data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  /** @return array<string,mixed>|null */
  private function mutationServices(): ?array
  {
    $services = $this->authenticatedServices();
    if ($services === null || !$this->requireCsrf($services['csrfService'])) return null;
    return $services;
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
      $services = ControllerServiceFactory::buildContentAware($pdo, $core);
      $services['userId'] = $userId;
      return $services;
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
      return null;
    }
  }

  private function runId(?string $value): ?int
  {
    if ($value === null || !preg_match('/^[1-9][0-9]*$/D', $value) || (int)$value <= 0 || (string)(int)$value !== $value) {
      $this->notFound();
      return null;
    }
    return (int)$value;
  }

  private function positiveId(?string $value): ?int
  {
    if ($value === null || !preg_match('/^[1-9][0-9]*$/D', $value) || (int)$value <= 0 || (string)(int)$value !== $value) {
      return null;
    }
    return (int)$value;
  }

  private function notFound(): void
  {
    $this->error('run_not_found', 'Run is unavailable.', 404);
  }

  private function error(string $code, string $message, int $status): void
  {
    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
  }
}
