<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Commands\SquadActiveDeletionException;
use DiceGoblins\Application\Commands\SquadNotFoundException;
use DiceGoblins\Application\Commands\SquadValidationException;
use DiceGoblins\Application\Commands\UnitConfigurationValidationException;
use DiceGoblins\Application\Queries\UnitNotFoundException;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class WarbandController
{
  use RequiresCsrf;

  /** GET /api/v1/units */
  public function units(): void
  {
    $this->collection('units', 'unitCollectionQuery');
  }

  /** GET /api/v1/units/:unitId */
  public function unitDetail(?string $unitId): void
  {
    $services = $this->authenticatedServices();
    if ($services === null) return;

    if ($unitId === null || !ctype_digit($unitId) || (int)$unitId <= 0) {
      $this->unitNotFound();
      return;
    }

    try {
      $unit = $services['unitDetailQuery']->execute($services['userId'], (int)$unitId);
      Response::json(['ok' => true, 'data' => ['unit' => $unit]]);
    } catch (UnitNotFoundException) {
      $this->unitNotFound();
    } catch (WarbandIntegrityException) {
      $this->integrityError();
    } catch (Throwable) {
      $this->serverError();
    }
  }

  /** GET /api/v1/dice */
  public function dice(): void
  {
    $this->collection('dice', 'diceCollectionQuery');
  }

  /** PATCH /api/v1/units/:unitId/name */
  public function renameUnit(?string $unitId): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $id = $this->unitId($unitId);
    if ($id === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->unitConfigurationError();
      return;
    }
    $this->runUnitCommand(fn(): array => $services['renameUnitCommand']->execute($services['userId'], $id, $body));
  }

  /** PUT /api/v1/units/:unitId/loadout */
  public function replaceUnitLoadout(?string $unitId): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $id = $this->unitId($unitId);
    if ($id === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->unitConfigurationError();
      return;
    }
    $this->runUnitCommand(fn(): array => $services['replaceUnitLoadoutCommand']->execute($services['userId'], $id, $body));
  }

  /** GET /api/v1/squads */
  public function squads(): void
  {
    $this->collection('squads', 'squadCollectionQuery');
  }

  /** POST /api/v1/squads */
  public function createSquad(): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->squadError('invalid_squad_configuration', 'Squad configuration is invalid.', 422);
      return;
    }
    $this->runSquadCommand(fn(): array => $services['createSquadCommand']->execute(
      $services['userId'], $body,
      is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null,
    ));
  }

  /** PUT /api/v1/squads/:squadId */
  public function updateSquad(?string $squadId): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $id = $this->squadId($squadId);
    if ($id === null) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->squadError('invalid_squad_configuration', 'Squad configuration is invalid.', 422);
      return;
    }
    $this->runSquadCommand(fn(): array => $services['updateSquadCommand']->execute($services['userId'], $id, $body));
  }

  /** POST /api/v1/squads/:squadId/activate */
  public function activateSquad(?string $squadId): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $id = $this->squadId($squadId);
    if ($id === null) return;
    $this->runSquadCommand(fn(): array => $services['activateSquadCommand']->execute($services['userId'], $id));
  }

  /** DELETE /api/v1/squads/:squadId */
  public function deleteSquad(?string $squadId): void
  {
    $services = $this->mutationServices();
    if ($services === null) return;
    $id = $this->squadId($squadId);
    if ($id === null) return;
    $this->runSquadCommand(fn(): array => $services['deleteSquadCommand']->execute($services['userId'], $id));
  }

  private function collection(string $responseKey, string $queryKey): void
  {
    $services = $this->authenticatedServices();
    if ($services === null) return;

    try {
      Response::json([
        'ok' => true,
        'data' => [$responseKey => $services[$queryKey]->execute($services['userId'])],
      ]);
    } catch (WarbandIntegrityException) {
      $this->integrityError();
    } catch (Throwable) {
      $this->serverError();
    }
  }

  /** @return array<string,mixed>|null */
  private function authenticatedServices(): ?array
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      $this->serverError();
      return null;
    }

    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return null;
    }

    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core);
      $services['userId'] = $userId;
      return $services;
    } catch (Throwable) {
      $this->serverError();
      return null;
    }
  }

  /** @return array<string,mixed>|null */
  private function mutationServices(): ?array
  {
    $services = $this->authenticatedServices();
    if ($services === null || !$this->requireCsrf($services['csrfService'])) return null;
    return $services;
  }

  /** @param callable():array<string,mixed> $command */
  private function runSquadCommand(callable $command): void
  {
    try {
      Response::json(['ok' => true, 'data' => $command()]);
    } catch (SquadValidationException) {
      $this->squadError('invalid_squad_configuration', 'Squad configuration is invalid.', 422);
    } catch (IdempotencyKeyException) {
      $this->squadError('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->squadError('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (SquadNotFoundException) {
      $this->squadError('squad_not_found', 'Squad is unavailable.', 404);
    } catch (SquadActiveDeletionException) {
      $this->squadError('active_squad_delete_forbidden', 'Activate another squad before deleting the active squad.', 409);
    } catch (WarbandIntegrityException) {
      $this->integrityError();
    } catch (Throwable) {
      $this->serverError();
    }
  }

  /** @param callable():array<string,mixed> $command */
  private function runUnitCommand(callable $command): void
  {
    try {
      Response::json(['ok' => true, 'data' => $command()]);
    } catch (UnitConfigurationValidationException) {
      $this->unitConfigurationError();
    } catch (UnitNotFoundException) {
      $this->unitNotFound();
    } catch (WarbandIntegrityException) {
      $this->integrityError();
    } catch (Throwable) {
      $this->serverError();
    }
  }

  private function squadId(?string $value): ?int
  {
    if ($value === null || !preg_match('/^[1-9][0-9]*$/D', $value) || (int)$value <= 0 || (string)(int)$value !== $value) {
      $this->squadError('squad_not_found', 'Squad is unavailable.', 404);
      return null;
    }
    return (int)$value;
  }

  private function unitId(?string $value): ?int
  {
    if ($value === null || !preg_match('/^[1-9][0-9]*$/D', $value) || (int)$value <= 0 || (string)(int)$value !== $value) {
      $this->unitNotFound();
      return null;
    }
    return (int)$value;
  }

  private function unitConfigurationError(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'invalid_unit_configuration', 'message' => 'Unit configuration is invalid.']], 422);
  }

  private function squadError(string $code, string $message, int $status): void
  {
    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
  }

  private function unitNotFound(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'unit_not_found', 'message' => 'Unit is unavailable.']], 404);
  }

  private function integrityError(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'warband_data_integrity_error', 'message' => 'Warband data is unavailable.']], 500);
  }

  private function serverError(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
  }
}
