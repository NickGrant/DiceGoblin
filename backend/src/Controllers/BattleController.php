<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\BattleLogRepository;
use DiceGoblins\Repositories\BattleRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;

use PDO;
use Throwable;

final class BattleController
{
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
      $pdo->beginTransaction();

      $battle = $svc['battleRepo']->getForClaimForUpdate($battleIdInt, $userId);
      if ($battle === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'forbidden',
            'message' => 'Battle not found or not owned by user.',
          ],
        ], 403);
        return;
      }

      if (!in_array($battle['outcome'], ['victory', 'defeat'], true)) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'server_error',
            'message' => 'Invalid battle outcome state.',
          ],
        ], 500);
        return;
      }

      // Idempotent: already claimed
      if ($battle['status'] === 'claimed') {
        $pdo->commit();
        $this->respondClaimed($battleIdInt, $battle);
        return;
      }

      if ($battle['status'] !== 'completed') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'battle_not_completed',
            'message' => 'Battle is not in a claimable state.',
          ],
        ], 409);
        return;
      }

      // Transition to claimed. If a race occurred, this returns false but we still respond claimed.
      $svc['battleRepo']->markClaimedIfCompleted($battleIdInt, $userId);

      $pdo->commit();
      $this->respondClaimed($battleIdInt, $battle);
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
   *   id:string,status:string,outcome:string,rules_version:string,
   *   xp_total:int,rewards_json:string
   * } $battle
   */
  private function respondClaimed(int $battleIdInt, array $battle): void
  {
    $rewards = json_decode($battle['rewards_json'], true);
    if (!is_array($rewards)) $rewards = [];

    Response::json([
      'ok' => true,
      'data' => [
        'battle_id' => (string)$battleIdInt,
        'status' => 'claimed',
        'rewards' => array_merge(['xp_total' => (int)$battle['xp_total']], $rewards),

        // Placeholders until you wire run_unit_state + XP application in Milestone 2
        'updated_run_unit_state' => [],
        'xp' => [
          'award_per_unit' => (int)$battle['xp_total'],
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => [],
      ],
    ]);
  }

  /**
   * @return array{
   *   pdo: PDO,
   *   battleRepo: BattleRepository,
   *   battleLogRepo: BattleLogRepository,
   *   sessionService: SessionService,
   *   csrfService: CsrfService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();

    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);

    $csrfService = new CsrfService();
    $grantService = new GrantService();
    $bootstrapper = new PlayerBootstrapper($playerStateRepo, $energyRepo, $grantService);
    $sessionService = new SessionService($userRepo, $csrfService, $bootstrapper);

    return [
      'pdo' => $pdo,
      'battleRepo' => new BattleRepository($pdo),
      'battleLogRepo' => new BattleLogRepository($pdo),
      'sessionService' => $sessionService,
      'csrfService' => $csrfService,
    ];
  }

  private function requireCsrf(CsrfService $csrfService): bool
  {
    $provided = $csrfService->extractProvidedToken();

    if (!$csrfService->validateToken($provided)) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'csrf_invalid',
          'message' => 'Invalid CSRF token.',
        ],
      ], 403);
      return false;
    }

    return true;
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
}
