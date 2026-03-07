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
use DiceGoblins\Repositories\BattleRepository;
use DiceGoblins\Repositories\BattleRewardsRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;

use PDO;
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
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,seed:string,
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
        'run_resolution' => null,
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
        'run_resolution' => $claimSnapshot['run_resolution'] ?? null,
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
   *   runRepo: RunRepository
   * } $svc
   * @param array{
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,seed:string,
   *   xp_total:int,rewards_json:string
   * } $battle
   * @return array{
   *   updated_run_unit_state: array<int, array{unit_instance_id:string,hp:int,is_defeated:bool,status_effects:array<int,mixed>}>,
   *   run_resolution: array{run_id:string,status:string}|null,
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
    $runId = (int)$battle['run_id'];
    $battleId = (int)$battle['id'];
    $battleSeed = (string)$battle['seed'];
    $awardPerUnit = max(0, (int)$battle['xp_total']);

    $runStateRows = $svc['runRepo']->getRunUnitStateForUpdate($runId);
    $runStateByUnitId = [];
    foreach ($runStateRows as $row) {
      $runStateByUnitId[(int)$row['unit_instance_id']] = $row;
    }
    if (count($runStateByUnitId) === 0) {
      return [
        'updated_run_unit_state' => [],
        'run_resolution' => null,
        'xp' => [
          'award_per_unit' => $awardPerUnit,
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => [],
      ];
    }

    $unitMaxHp = $this->getUnitMaxHpByIdsForUser($userId, array_keys($runStateByUnitId));

    $eligible = [];
    foreach ($runStateByUnitId as $unitId => $state) {
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

      // Attrition state mutation is deterministic from battle seed + unit id.
      $maxHp = max(1, (int)($unitMaxHp[$unitId] ?? 1));
      $currentHp = (int)$runStateByUnitId[$unitId]['current_hp'];
      $lossPct = $this->deterministicLossPercent($battleSeed, $battleId, $unitId, (string)$battle['outcome']);
      $hpLoss = max(1, (int)floor($maxHp * $lossPct));
      $newHp = max(0, $currentHp - $hpLoss);

      $runStateByUnitId[$unitId]['current_hp'] = $newHp;
      $runStateByUnitId[$unitId]['is_defeated'] = $newHp <= 0;

      $svc['runRepo']->upsertRunUnitState(
        $runId,
        $unitId,
        $newHp,
        $newHp <= 0,
        (string)$runStateByUnitId[$unitId]['cooldowns_json'],
        (string)$runStateByUnitId[$unitId]['status_effects_json']
      );

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
    foreach ($runStateByUnitId as $unitId => $row) {
      $effects = json_decode((string)$row['status_effects_json'], true);
      $updatedRunState[] = [
        'unit_instance_id' => (string)$unitId,
        'hp' => (int)$row['current_hp'],
        'is_defeated' => !empty($row['is_defeated']),
        'status_effects' => is_array($effects) ? $effects : [],
      ];
    }

    $runResolution = null;
    if ((string)$battle['outcome'] === 'defeat') {
      $remaining = array_filter(
        $runStateByUnitId,
        static fn(array $row): bool => !empty($row['current_hp']) && empty($row['is_defeated'])
      );

      if (count($remaining) === 0) {
        $svc['runRepo']->applyRunEndCleanup($runId, $userId, true);
        $svc['runRepo']->endRun($userId, $runId, 'failed');
        $runResolution = [
          'run_id' => (string)$runId,
          'status' => 'failed',
        ];

        $updatedRunState = array_map(static fn(array $row): array => [
          'unit_instance_id' => (string)$row['unit_instance_id'],
          'hp' => (int)$row['current_hp'],
          'is_defeated' => (bool)$row['is_defeated'],
          'status_effects' => json_decode((string)$row['status_effects_json'], true) ?: [],
        ], $svc['runRepo']->getRunUnitState($runId));
      }
    }

    return [
      'updated_run_unit_state' => $updatedRunState,
      'run_resolution' => $runResolution,
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
      'battleRepo' => new BattleRepository($pdo),
      'battleLogRepo' => new BattleLogRepository($pdo),
      'battleRewardsRepo' => new BattleRewardsRepository($pdo),
      'runRepo' => new RunRepository($pdo),
      'sessionService' => $core['sessionService'],
      'csrfService' => $core['csrfService'],
    ];
  }

  /**
   * @param array<int,int> $unitIds
   * @return array<int,int>
   */
  private function getUnitMaxHpByIdsForUser(int $userId, array $unitIds): array
  {
    if (count($unitIds) === 0) {
      return [];
    }

    $pdo = Db::pdo();
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], array_values($unitIds));

    $stmt = $pdo->prepare("
      SELECT
        ui.`id` AS `unit_instance_id`,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`max_hp_per_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
    ");
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $baseStats = json_decode((string)$row['base_stats_json'], true);
      if (!is_array($baseStats)) {
        $baseStats = [];
      }
      $level = max(1, (int)$row['level']);
      $baseMaxHp = max(1, (int)($baseStats['max_hp'] ?? 1));
      $maxHpPerLevel = max(0, (int)$row['max_hp_per_level']);
      $out[(int)$row['unit_instance_id']] = $baseMaxHp + (($level - 1) * $maxHpPerLevel);
    }

    return $out;
  }

  private function deterministicLossPercent(string $seed, int $battleId, int $unitId, string $outcome): float
  {
    $hash = hash('sha256', $seed . '|' . $battleId . '|' . $unitId);
    $slice = substr($hash, 0, 4);
    $roll = ((int)base_convert($slice, 16, 10)) % 100;

    if ($outcome === 'defeat') {
      // 45% - 95%
      return (45 + ($roll % 51)) / 100.0;
    }

    // Victory attrition: 10% - 35%
    return (10 + ($roll % 26)) / 100.0;
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
