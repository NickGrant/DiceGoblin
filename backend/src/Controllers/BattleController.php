<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\BattleLogRepository;
use DiceGoblins\Repositories\BattleRepository;
use DiceGoblins\Repositories\BattleRewardsRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UnitRepository;
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
        $this->respondClaimed($battleIdInt, $battle, null);
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

      $claimSnapshot = $this->applyRewardsAndXp($svc, $userId, $battle);

      $rewards = json_decode($battle['rewards_json'], true);
      if (!is_array($rewards)) {
        $rewards = [];
      }
      $rewards['claim_snapshot'] = $claimSnapshot;
      $svc['battleRewardsRepo']->updateRewardsJson($battleIdInt, $rewards);

      // Transition to claimed. If a race occurred, this returns false but we still respond as claimed.
      $svc['battleRepo']->markClaimedIfCompleted($battleIdInt, $userId);

      $pdo->commit();
      $battle['rewards_json'] = json_encode($rewards, JSON_UNESCAPED_SLASHES);
      $this->respondClaimed($battleIdInt, $battle, $claimSnapshot);
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
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,
   *   xp_total:int,rewards_json:string
   * } $battle
   */
  private function respondClaimed(int $battleIdInt, array $battle, ?array $claimSnapshot): void
  {
    $rewards = json_decode($battle['rewards_json'], true);
    if (!is_array($rewards)) $rewards = [];
    if ($claimSnapshot === null && isset($rewards['claim_snapshot']) && is_array($rewards['claim_snapshot'])) {
      $claimSnapshot = $rewards['claim_snapshot'];
    }
    if ($claimSnapshot === null) {
      $claimSnapshot = [
        'updated_run_unit_state' => [],
        'xp' => [
          'award_per_unit' => (int)$battle['xp_total'],
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => [],
      ];
    }

    unset($rewards['claim_snapshot']);

    Response::json([
      'ok' => true,
      'data' => [
        'battle_id' => (string)$battleIdInt,
        'status' => 'claimed',
        'rewards' => array_merge(['xp_total' => (int)$battle['xp_total']], $rewards),
        'updated_run_unit_state' => $claimSnapshot['updated_run_unit_state'] ?? [],
        'xp' => $claimSnapshot['xp'] ?? [
          'award_per_unit' => (int)$battle['xp_total'],
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => $claimSnapshot['updated_units'] ?? [],
      ],
    ]);
  }

  /**
   * @param array{
   *   battleRepo: BattleRepository,
   *   battleRewardsRepo: BattleRewardsRepository,
   *   runRepo: RunRepository,
   *   teamRepo: TeamRepository,
   *   unitRepo: UnitRepository
   * } $svc
   * @param array{
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,
   *   xp_total:int,rewards_json:string
   * } $battle
   * @return array{
   *   updated_run_unit_state: array<int, array{unit_instance_id:string,hp:int,status_effects:array<int,mixed>}>,
   *   xp: array{
   *     award_per_unit:int,
   *     applied_unit_instance_ids:array<int,string>,
   *     ignored_at_cap_unit_instance_ids:array<int,string>
   *   },
   *   updated_units: array<int, array{id:string,xp:int,level:int}>
   * }
   */
  private function applyRewardsAndXp(array $svc, int $userId, array $battle): array
  {
    $teamId = (int)$battle['team_id'];
    $runId = (int)$battle['run_id'];
    $awardPerUnit = max(0, (int)$battle['xp_total']);

    $teamUnitIds = $this->listTeamUnitIdsForUser($teamId, $userId);
    $runStateByUnitId = [];
    foreach ($svc['runRepo']->getRunUnitState($runId) as $row) {
      $runStateByUnitId[(int)$row['unit_instance_id']] = $row;
    }

    $eligible = [];
    foreach ($teamUnitIds as $unitId) {
      $state = $runStateByUnitId[$unitId] ?? null;
      if (is_array($state) && !empty($state['is_defeated'])) {
        continue;
      }
      $eligible[] = $unitId;
    }

    $applied = [];
    $ignoredAtCap = [];
    $updatedUnits = [];

    foreach ($eligible as $unitId) {
      $unit = $this->lockUnitProgress($userId, $unitId);
      if ($unit === null) {
        continue;
      }

      if ($unit['level'] >= $unit['max_level']) {
        $ignoredAtCap[] = (string)$unitId;
        continue;
      }

      if ($awardPerUnit > 0) {
        $this->incrementUnitXp($userId, $unitId, $awardPerUnit);
        $unit['xp'] += $awardPerUnit;
      }

      $applied[] = (string)$unitId;
      $updatedUnits[] = [
        'id' => (string)$unitId,
        'xp' => (int)$unit['xp'],
        'level' => (int)$unit['level'],
      ];
    }

    $updatedRunState = [];
    foreach ($eligible as $unitId) {
      if (!isset($runStateByUnitId[$unitId]) || !is_array($runStateByUnitId[$unitId])) {
        continue;
      }
      $row = $runStateByUnitId[$unitId];
      $effects = json_decode((string)$row['status_effects_json'], true);
      $updatedRunState[] = [
        'unit_instance_id' => (string)$unitId,
        'hp' => (int)$row['current_hp'],
        'status_effects' => is_array($effects) ? $effects : [],
      ];
    }

    return [
      'updated_run_unit_state' => $updatedRunState,
      'xp' => [
        'award_per_unit' => $awardPerUnit,
        'applied_unit_instance_ids' => $applied,
        'ignored_at_cap_unit_instance_ids' => $ignoredAtCap,
      ],
      'updated_units' => $updatedUnits,
    ];
  }

  /**
   * @return array{
   *   pdo: PDO,
   *   battleRepo: BattleRepository,
   *   battleLogRepo: BattleLogRepository,
   *   battleRewardsRepo: BattleRewardsRepository,
   *   runRepo: RunRepository,
   *   teamRepo: TeamRepository,
   *   unitRepo: UnitRepository,
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
      'battleRewardsRepo' => new BattleRewardsRepository($pdo),
      'runRepo' => new RunRepository($pdo),
      'teamRepo' => new TeamRepository($pdo),
      'unitRepo' => new UnitRepository($pdo),
      'sessionService' => $sessionService,
      'csrfService' => $csrfService,
    ];
  }

  /**
   * @return array<int,int>
   */
  private function listTeamUnitIdsForUser(int $teamId, int $userId): array
  {
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('
      SELECT tu.`unit_instance_id`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      WHERE tu.`team_id` = ? AND ui.`user_id` = ?
      ORDER BY tu.`unit_instance_id` ASC
    ');
    $stmt->execute([$teamId, $userId]);

    return array_map(static fn($id): int => (int)$id, $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  /**
   * @return array{xp:int,level:int,max_level:int}|null
   */
  private function lockUnitProgress(int $userId, int $unitInstanceId): ?array
  {
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('
      SELECT ui.`xp`, ui.`level`, ut.`max_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`id` = ? AND ui.`user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$unitInstanceId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return null;
    }

    return [
      'xp' => (int)$row['xp'],
      'level' => (int)$row['level'],
      'max_level' => (int)$row['max_level'],
    ];
  }

  private function incrementUnitXp(int $userId, int $unitInstanceId, int $deltaXp): void
  {
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('
      UPDATE `unit_instances`
      SET `xp` = `xp` + ?
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([$deltaXp, $unitInstanceId, $userId]);
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
