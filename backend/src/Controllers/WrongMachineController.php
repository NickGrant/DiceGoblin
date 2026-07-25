<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\HandlesControllerRequests;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\WrongMachineReconstructionService;
use RuntimeException;
use Throwable;

final class WrongMachineController
{
  use HandlesControllerRequests;
  use RequiresCsrf;

  public function reconstructions(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    Response::json([
      'ok' => true,
      'data' => $svc['wrongMachineReconstructionService']->preview($userId),
    ]);
  }

  public function reconstruct(): void
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

    $lineageSlug = trim((string)($body['lineage_slug'] ?? ''));
    if ($lineageSlug === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'lineage_slug is required.']], 400);
      return;
    }

    try {
      Response::json([
        'ok' => true,
        'data' => $svc['wrongMachineReconstructionService']->reconstruct($userId, $lineageSlug),
      ]);
    } catch (RuntimeException $e) {
      $message = $e->getMessage();
      $status = match ($message) {
        'wrong_machine_locked' => 403,
        'unknown_lineage' => 404,
        'insufficient_items', 'insufficient_raw_chaos' => 409,
        default => 400,
      };
      Response::json(['ok' => false, 'error' => ['code' => $message, 'message' => $this->errorMessage($message)]], $status);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  private function errorMessage(string $code): string
  {
    return match ($code) {
      'wrong_machine_locked' => 'The Wrong Machine has not been recovered yet.',
      'unknown_lineage' => 'Unknown lineage.',
      'insufficient_items' => 'Not enough materials for reconstruction.',
      'insufficient_raw_chaos' => 'Not enough Raw Chaos for reconstruction.',
      default => $code,
    };
  }

  /**
   * @return array{sessionService: SessionService, csrfService: \DiceGoblins\Services\CsrfService, wrongMachineReconstructionService: WrongMachineReconstructionService}
   */
  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'sessionService' => $core['sessionService'],
      'csrfService' => $core['csrfService'],
      'wrongMachineReconstructionService' => new WrongMachineReconstructionService($pdo),
    ];
  }
}
