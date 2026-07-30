<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Controllers\BattleController.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\BattleLogRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\RunLifecycleService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Support\RunSummaryBuilder;

use PDO;
use RuntimeException;
use Throwable;

final class BattleController
{
  use RequiresCsrf;

  /**
   * GET /api/v1/battles/:battleId/log
   */
  public function getBattleLog(?string $battleId = null): void
  {
    $svc = $this->services();

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    $battleIdInt = $this->requirePositiveInt($battleId, 'battleId');
    if ($battleIdInt === null) return;

    try {
      $row = $svc['battleLogRepo']->getForUser($battleIdInt, $userId);
      if ($row === null) {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'forbidden',
            'message' => 'Battle not found or not owned by user.',
          ],
        ], 403);
        return;
      }

      $log = json_decode($row['log_json'], true);
      if (!is_array($log)) {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'server_error',
            'message' => 'Stored battle log is invalid.',
          ],
        ], 500);
        return;
      }

      Response::json([
        'ok' => true,
        'data' => [
          'battle_id' => $row['battle_id'],
          'rules_version' => $row['rules_version'],
          'log' => $log,
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * POST /api/v1/battles/:battleId/claim
   *
   * Idempotent: if already claimed, returns claimed payload again.
   */
  public function claimBattle(?string $battleId = null): void
  {
    $svc = $this->services();

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $battleIdInt = $this->requirePositiveInt($battleId, 'battleId');
    if ($battleIdInt === null) return;

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];

    try {
      $body = $this->readJsonBody();
      if ($body === null) {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'validation_error',
            'message' => 'Invalid JSON body.',
          ],
        ], 400);
        return;
      }

      $claimAction = isset($body['action']) ? (string)$body['action'] : 'accept';
      $claimResult = $svc['runLifecycleService']->claimBattle($userId, $battleIdInt, $claimAction);
      $this->respondClaimed(
        $battleIdInt,
        $claimResult['battle'],
        $claimResult['claim_snapshot'],
        $pdo,
        $userId
      );
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      if ($e->getMessage() === 'battle_not_found') {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'forbidden',
            'message' => 'Battle not found or not owned by user.',
          ],
        ], 403);
        return;
      }

      if ($e->getMessage() === 'battle_not_completed') {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'battle_not_completed',
            'message' => 'Battle is not in a claimable state.',
          ],
        ], 409);
        return;
      }

      if ($e->getMessage() === 'invalid_battle_outcome') {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'server_error',
            'message' => 'Invalid battle outcome state.',
          ],
        ], 500);
        return;
      }

      if ($e->getMessage() === 'invalid_claim_action' || $e->getMessage() === 'shrine_not_declineable') {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => $e->getMessage(),
            'message' => $e->getMessage() === 'shrine_not_declineable'
              ? 'This shrine cannot be declined.'
              : 'Invalid claim action.',
          ],
        ], 409);
        return;
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * @param array{
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,node_id:string,node_type:string,seed:string,
    *   xp_total:int,currency_soft:int,rewards_json:string
   * } $battle
   */
  private function respondClaimed(int $battleIdInt, array $battle, ?array $claimSnapshot, PDO $pdo, int $userId): void
  {
    $rewards = json_decode($battle['rewards_json'], true);
    if (!is_array($rewards)) $rewards = [];
    if ($claimSnapshot === null && isset($rewards['claim_snapshot']) && is_array($rewards['claim_snapshot'])) {
      $claimSnapshot = $rewards['claim_snapshot'];
    }
    if ($claimSnapshot === null) {
      $claimSnapshot = [
        'updated_run_unit_state' => [],
        'run_resolution' => null,
        'xp' => [
          'award_per_unit' => (int)$battle['xp_total'],
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'currency' => [
          'soft_awarded' => max(0, (int)($battle['currency_soft'] ?? 0)),
        ],
        'updated_units' => [],
      ];
    }
    $claimSnapshot['updated_run_unit_state'] = $this->normalizeUpdatedRunUnitState($claimSnapshot['updated_run_unit_state'] ?? []);
    $summaryBuilder = new RunSummaryBuilder($pdo);
    $rewardLabels = $summaryBuilder->buildBattleRewardLabels($userId, $rewards);
    $runSummary = $summaryBuilder->buildRunSummary(
      $userId,
      (int)$battle['run_id'],
      is_array($claimSnapshot['terminal_run_unit_state'] ?? null)
        ? $claimSnapshot['terminal_run_unit_state']
        : ($claimSnapshot['updated_run_unit_state'] ?? null)
    );

    unset($rewards['claim_snapshot']);
    $rewards = array_merge($rewards, $rewardLabels);

    $updatedUnits = $this->normalizeUpdatedUnits($claimSnapshot['updated_units'] ?? []);
    $softAwarded = max(
      0,
      (int)($claimSnapshot['currency']['soft_awarded'] ?? $battle['currency_soft'] ?? 0)
    );

    Response::json([
      'ok' => true,
      'data' => [
        'battle_id' => (string)$battleIdInt,
        'status' => 'claimed',
        'rewards' => array_merge([
          'xp_total' => (int)$battle['xp_total'],
          'currency_soft' => $softAwarded,
        ], $rewards),
        'updated_run_unit_state' => $claimSnapshot['updated_run_unit_state'] ?? [],
        'run_resolution' => $claimSnapshot['run_resolution'] ?? null,
        'xp' => $claimSnapshot['xp'] ?? [
          'award_per_unit' => (int)$battle['xp_total'],
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'shrine_effects' => is_array($claimSnapshot['shrine_effects'] ?? null) ? $claimSnapshot['shrine_effects'] : [],
        'hazard_effects' => is_array($claimSnapshot['hazard_effects'] ?? null) ? $claimSnapshot['hazard_effects'] : [],
        'shrine_decision' => isset($claimSnapshot['shrine_decision']) ? (string)$claimSnapshot['shrine_decision'] : null,
        'updated_units' => $updatedUnits,
        'run_summary' => $runSummary,
      ],
    ]);
  }

  /**
   * @param mixed $rows
   * @return array<int,array{unit_instance_id:string,hp:int,is_defeated:bool,status_effects:array<int,mixed>}>
   */
  private function normalizeUpdatedRunUnitState(mixed $rows): array
  {
    if (!is_array($rows)) {
      return [];
    }

    $normalized = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $effects = $row['status_effects'] ?? [];
      $normalized[] = [
        'unit_instance_id' => (string)($row['unit_instance_id'] ?? ''),
        'hp' => (int)($row['hp'] ?? 0),
        'is_defeated' => !empty($row['is_defeated']),
        'status_effects' => is_array($effects) ? $effects : [],
      ];
    }

    return $normalized;
  }

  /**
   * @param mixed $rows
   * @return array<int,array{id:string,xp:int,level:int,name:string}>
   */
  private function normalizeUpdatedUnits(mixed $rows): array
  {
    if (!is_array($rows)) {
      return [];
    }

    $normalized = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $normalized[] = [
        'id' => (string)($row['id'] ?? ''),
        'xp' => (int)($row['xp'] ?? 0),
        'level' => (int)($row['level'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
      ];
    }

    return $normalized;
  }

  /**
   * @return array{
   *   pdo: PDO,
   *   battleLogRepo: BattleLogRepository,
   *   runRepo: RunRepository,
   *   runLifecycleService: RunLifecycleService,
   *   sessionService: SessionService,
   *   csrfService: CsrfService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'pdo' => $pdo,
      'battleLogRepo' => new BattleLogRepository($pdo),
      'runRepo' => new RunRepository($pdo),
      'runLifecycleService' => new RunLifecycleService(
        $pdo,
        new RunRepository($pdo),
        new RegionRepository($pdo),
        new RunNodeRepository($pdo),
      ),
      'sessionService' => $core['sessionService'],
      'csrfService' => $core['csrfService'],
    ];
  }

  private function requirePositiveInt(?string $raw, string $field): ?int
  {
    $v = (int)($raw ?? 0);
    if ($v <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => "{$field} is required.",
        ],
      ], 400);
      return null;
    }
    return $v;
  }

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(): ?array
  {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
      return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
  }
}
