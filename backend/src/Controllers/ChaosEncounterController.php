<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\HandlesControllerRequests;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Services\ChaosEncounterService;
use RuntimeException;
use Throwable;

final class ChaosEncounterController
{
  use HandlesControllerRequests;
  use RequiresCsrf;

  public function generate(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    try {
      Response::json(['ok' => true, 'data' => $svc['chaosEncounterService']->generate($userId, $runIdInt, $nodeIdInt)]);
    } catch (RuntimeException $e) {
      $this->handleDomainError($e);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function reroll(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $reelIndex = (int)($body['reel_index'] ?? -1);

    try {
      Response::json(['ok' => true, 'data' => $svc['chaosEncounterService']->rerollOneReel($userId, $runIdInt, $nodeIdInt, $reelIndex)]);
    } catch (RuntimeException $e) {
      $this->handleDomainError($e);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function finalize(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    try {
      Response::json(['ok' => true, 'data' => $svc['chaosEncounterService']->finalize($userId, $runIdInt, $nodeIdInt)]);
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
      'run_node_not_found', 'chaos_result_not_generated' => 404,
      'run_not_active', 'node_not_available', 'invalid_chaos_node', 'chaos_reroll_spent', 'chaos_result_confirmed' => 409,
      default => 400,
    };
    $code = in_array($message, [
      'run_node_not_found',
      'chaos_result_not_generated',
      'run_not_active',
      'node_not_available',
      'invalid_chaos_node',
      'chaos_reroll_spent',
      'chaos_result_confirmed',
      'invalid_reel_index',
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
      'chaosEncounterService' => new ChaosEncounterService($pdo),
    ];
  }
}
