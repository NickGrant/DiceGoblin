<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\HandlesControllerRequests;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Services\AcademyService;
use RuntimeException;
use Throwable;

final class AcademyController
{
  use HandlesControllerRequests;
  use RequiresCsrf;

  public function catalog(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      Response::json([
        'ok' => true,
        'data' => $svc['academyService']->buildCatalog($userId),
      ]);
    } catch (RuntimeException $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'feature_locked', 'message' => $e->getMessage()]], 403);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function unlockUnitType(): void
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

    $unitTypeSlug = trim((string)($body['unit_type_slug'] ?? ''));
    if ($unitTypeSlug === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'unit_type_slug is required.']], 400);
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      $result = $svc['academyService']->unlockUnitType($userId, $unitTypeSlug);
      Response::json([
        'ok' => true,
        'data' => $result,
      ]);
    } catch (RuntimeException $e) {
      if ($e->getMessage() === 'Not enough soft currency.') {
        Response::json(['ok' => false, 'error' => ['code' => 'insufficient_currency', 'message' => $e->getMessage()]], 409);
        return;
      }
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'pdo' => $pdo,
      'csrfService' => $core['csrfService'],
      'sessionService' => $core['sessionService'],
      'bootstrapper' => $core['bootstrapper'],
      'academyService' => new AcademyService($pdo, $core['playerStateRepo']),
    ];
  }
}
