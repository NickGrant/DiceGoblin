<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\ConsumableUseException;
use DiceGoblins\Application\Commands\ConsumableUseIntegrityException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class ConsumableController
{
  use RequiresCsrf;

  public function __construct(private readonly ?ContentRegistry $content = null) {}

  /** POST /api/v1/energy/restore */
  public function restoreEnergy(): void
  {
    $context = $this->mutationContext();
    if ($context === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) { $this->error('invalid_consumable_request', 'Consumable request is invalid.', 422); return; }
    $this->execute(fn() => $context['services']['restoreEnergyCommand']->execute(
      $context['userId'], $body, $this->idempotencyKey(),
    ));
  }

  /** POST /api/v1/runs/:runId/units/:unitId/heal */
  public function healRunUnit(?string $runId, ?string $unitId): void
  {
    $run = $this->positiveId($runId); $unit = $this->positiveId($unitId);
    if ($run === null || $unit === null) { $this->error('run_unit_not_found', 'Run unit is unavailable.', 404); return; }
    $context = $this->mutationContext();
    if ($context === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) { $this->error('invalid_consumable_request', 'Consumable request is invalid.', 422); return; }
    $this->execute(fn() => $context['services']['healRunUnitCommand']->execute(
      $context['userId'], $run, $unit, $body, $this->idempotencyKey(),
    ));
  }

  /** @param callable():array<string,mixed> $operation */
  private function execute(callable $operation): void
  {
    try { Response::json(['ok' => true, 'data' => $operation()]); }
    catch (IdempotencyKeyException) { $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400); }
    catch (IdempotencyConflictException) { $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409); }
    catch (ConsumableUseException $e) { $this->error($e->errorCode, $e->publicMessage, $e->httpStatus); }
    catch (ConsumableUseIntegrityException) { $this->error('consumable_data_integrity_error', 'Consumable data is unavailable.', 500); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); }
  }

  /** @return array{userId:int,services:array<string,mixed>}|null */
  private function mutationContext(): ?array
  {
    try { $pdo = Db::pdo(); $core = ControllerServiceFactory::buildCore($pdo); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return null; }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) { $this->error('unauthorized', 'No active session.', 401); return null; }
    if (!$this->requireCsrf($core['csrfService'])) return null;
    try { return ['userId' => $userId, 'services' => ControllerServiceFactory::buildContentAware($pdo, $core, $this->content)]; }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return null; }
  }

  private function idempotencyKey(): ?string
  { return is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null; }

  private function positiveId(?string $value): ?int
  {
    if ($value === null || !preg_match('/^[1-9][0-9]*$/D', $value) || (int)$value <= 0 || (string)(int)$value !== $value) return null;
    return (int)$value;
  }

  private function error(string $code, string $message, int $status): void
  { Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status); }
}
