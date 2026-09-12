<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\UnitNotFoundException;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use Throwable;

final class WarbandController
{
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

  /** GET /api/v1/squads */
  public function squads(): void
  {
    $this->collection('squads', 'squadCollectionQuery');
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
