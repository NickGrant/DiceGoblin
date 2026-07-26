<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\BattleLogRepository;
use DiceGoblins\Repositories\BattleRepository;
use DiceGoblins\Repositories\BattleRewardsRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Support\RunSummaryBuilder;
use PDO;
use RuntimeException;
use Throwable;

final class RunLifecycleService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly RunRepository $runRepository,
    private readonly RegionRepository $regionRepository,
    private readonly RunNodeRepository $runNodeRepository,
    private ?BattleRepository $battleRepository = null,
    private ?BattleLogRepository $battleLogRepository = null,
    private ?BattleRewardsRepository $battleRewardsRepository = null,
    private ?PlayerStateRepository $playerStateRepository = null,
    private ?UnitProgressionService $unitProgressionService = null,
  ) {}

  /**
   * @return array{run_id:string,status:string}
   */
  public function failRun(int $userId, int $runId): array
  {
    return $this->withinTransaction(function () use ($userId, $runId): array {
      $this->runRepository->applyRunEndCleanup($runId, $userId, true);
      $this->runRepository->endRun($userId, $runId, 'failed');

      return [
        'run_id' => (string)$runId,
        'status' => 'failed',
      ];
    });
  }

  /**
   * @return array{run_id:string,status:string,run_summary:array<string,mixed>}
   */
  public function abandonRun(int $userId, int $runId): array
  {
    return $this->withinTransaction(function () use ($userId, $runId): array {
      $runSummary = $this->buildRunSummary($userId, $runId);
      $this->runRepository->applyRunEndCleanup($runId, $userId, true);
      $this->runRepository->endRun($userId, $runId, 'abandoned');

      return [
        'run_id' => (string)$runId,
        'status' => 'abandoned',
        'run_summary' => $runSummary,
      ];
    });
  }

  /**
   * @return array{
   *   run_id:string,
   *   status:string,
   *   exit_node_id:?string,
   *   run_summary:array<string,mixed>
   * }
   */
  public function completeRun(int $userId, int $runId, int $regionId, ?int $exitNodeId = null): array
  {
    return $this->withinTransaction(function () use ($userId, $runId, $regionId, $exitNodeId): array {
      if ($exitNodeId !== null && $exitNodeId > 0) {
        $this->runNodeRepository->markCleared($runId, $exitNodeId);
      }

      $runSummary = $this->buildRunSummary($userId, $runId);
      $this->runRepository->applyRunEndCleanup($runId, $userId, false);
      $this->runRepository->endRun($userId, $runId, 'completed');
      $runSummary['meta'] = $this->unlockSuccessfulCompletionRewards($userId, $regionId);

      return [
        'run_id' => (string)$runId,
        'status' => 'completed',
        'exit_node_id' => $exitNodeId !== null && $exitNodeId > 0 ? (string)$exitNodeId : null,
        'run_summary' => $runSummary,
      ];
    });
  }

  /**
   * @return array{
   *   battle: array{
   *     id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,node_id:string,node_type:string,seed:string,
   *     xp_total:int,currency_soft:int,rewards_json:string
   *   },
   *   claim_snapshot: array<string,mixed>|null,
   *   newly_claimed: bool
   * }
   */
  public function claimBattle(int $userId, int $battleId): array
  {
    return $this->withinTransaction(function () use ($userId, $battleId): array {
      $battle = $this->battleRepository()->getForClaimForUpdate($battleId, $userId);
      if ($battle === null) {
        throw new RuntimeException('battle_not_found');
      }

      if (!in_array($battle['outcome'], ['victory', 'defeat'], true)) {
        throw new RuntimeException('invalid_battle_outcome');
      }

      if ($battle['status'] === 'claimed') {
        return [
          'battle' => $battle,
          'claim_snapshot' => $this->extractClaimSnapshot($battle),
          'newly_claimed' => false,
        ];
      }

      if ($battle['status'] !== 'completed') {
        throw new RuntimeException('battle_not_completed');
      }

      $claimSnapshot = $this->applyBattleRewardsAndXp($userId, $battle);

      $rewards = json_decode($battle['rewards_json'], true);
      if (!is_array($rewards)) {
        $rewards = [];
      }
      $rewards['claim_snapshot'] = $claimSnapshot;
      $this->battleRewardsRepository()->updateRewardsJson($battleId, $rewards);
      $this->battleRepository()->markClaimedIfCompleted($battleId, $userId);

      $battle['status'] = 'claimed';
      $battle['rewards_json'] = json_encode($rewards, JSON_UNESCAPED_SLASHES);

      return [
        'battle' => $battle,
        'claim_snapshot' => $claimSnapshot,
        'newly_claimed' => true,
      ];
    });
  }

  /**
   * @param array<string,mixed> $battle
   * @return array<string,mixed>|null
   */
  private function extractClaimSnapshot(array $battle): ?array
  {
    $rewards = json_decode((string)($battle['rewards_json'] ?? ''), true);
    if (!is_array($rewards)) {
      return null;
    }

    return isset($rewards['claim_snapshot']) && is_array($rewards['claim_snapshot'])
      ? $rewards['claim_snapshot']
      : null;
  }

  /**
   * @param array{
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,node_id:string,node_type:string,seed:string,
   *   xp_total:int,currency_soft:int,rewards_json:string
   * } $battle
   * @return array{
   *   updated_run_unit_state: array<int, array{unit_instance_id:string,hp:int,is_defeated:bool,status_effects:array<int,mixed>}>,
   *   terminal_run_unit_state?: array<int, array{unit_instance_id:string,hp:int,is_defeated:bool,status_effects:array<int,mixed>}>,
   *   run_resolution: array{run_id:string,status:string}|null,
   *   xp: array{
   *     award_per_unit:int,
   *     applied_unit_instance_ids:array<int,string>,
   *     ignored_at_cap_unit_instance_ids:array<int,string>
   *   },
   *   currency: array{soft_awarded:int},
   *   updated_units: array<int, array{id:string,xp:int,level:int,name:string}>
   * }
   */
  private function applyBattleRewardsAndXp(int $userId, array $battle): array
  {
    $runId = (int)$battle['run_id'];
    $battleId = (int)$battle['id'];
    $nodeType = (string)($battle['node_type'] ?? 'combat');
    $awardPerUnit = max(0, (int)$battle['xp_total']);
    $softCurrencyAward = max(0, (int)($battle['currency_soft'] ?? 0));
    $rewards = json_decode((string)($battle['rewards_json'] ?? ''), true);
    if (!is_array($rewards)) {
      $rewards = [];
    }
    $chaosBonus = is_array($rewards['chaos_bonus'] ?? null) ? $rewards['chaos_bonus'] : [];
    $chaosCurrency = is_array($chaosBonus['currency'] ?? null) ? $chaosBonus['currency'] : [];
    $rawChaosAward = max(0, (int)($chaosCurrency['raw_chaos'] ?? 0));
    if ($rawChaosAward > 0 && !(new UserUnlockService($this->pdo))->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE)) {
      $rawChaosAward = 0;
    }
    $run = $this->runRepository->getRunForUser($userId, $runId);
    $runAlreadyEnded = is_array($run) && (string)($run['status'] ?? '') !== 'active';
    $newFeatureUnlocks = $this->unlockBattleClaimRewards($userId, $battle, $run);

    $runStateRows = $this->runRepository->getRunUnitStateForUpdate($runId);
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
        'currency' => [
          'soft_awarded' => $softCurrencyAward,
          'raw_chaos_awarded' => $rawChaosAward,
        ],
        'new_feature_unlocks' => $newFeatureUnlocks,
        'updated_units' => [],
      ];
    }

    if ($softCurrencyAward > 0 || $rawChaosAward > 0) {
      $playerStateRepository = $this->playerStateRepository();
      $playerStateRepository->ensurePlayerState($userId);
      $playerState = $playerStateRepository->getPlayerStateForUpdate($userId);
      if (is_array($playerState)) {
        $nextSoft = max(0, (int)$playerState['currency_soft'] + $softCurrencyAward);
        $nextHard = max(0, (int)$playerState['currency_hard']);
        $playerStateRepository->setCurrency($userId, $nextSoft, $nextHard);
        if ($rawChaosAward > 0) {
          $playerStateRepository->setRawChaos($userId, max(0, (int)$playerState['currency_raw_chaos'] + $rawChaosAward));
        }
      }
    }

    if ($runAlreadyEnded && (string)$battle['outcome'] === 'defeat') {
      return [
        'updated_run_unit_state' => $this->formatRunUnitStateSnapshot($runStateByUnitId),
        'run_resolution' => [
          'run_id' => (string)$runId,
          'status' => (string)($run['status'] ?? 'failed'),
        ],
        'xp' => [
          'award_per_unit' => $awardPerUnit,
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'currency' => [
          'soft_awarded' => $softCurrencyAward,
          'raw_chaos_awarded' => $rawChaosAward,
        ],
        'new_feature_unlocks' => $newFeatureUnlocks,
        'updated_units' => [],
      ];
    }

    $isCombatLikeNode = in_array($nodeType, ['combat', 'boss', 'chaos'], true);
    if (!$isCombatLikeNode) {
      return [
        'updated_run_unit_state' => $this->formatRunUnitStateSnapshot($runStateByUnitId),
        'run_resolution' => null,
        'xp' => [
          'award_per_unit' => $awardPerUnit,
          'applied_unit_instance_ids' => [],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'currency' => [
          'soft_awarded' => $softCurrencyAward,
          'raw_chaos_awarded' => $rawChaosAward,
        ],
        'new_feature_unlocks' => $newFeatureUnlocks,
        'updated_units' => [],
      ];
    }

    $hpAfterBattle = [];
    foreach ($runStateByUnitId as $unitId => $row) {
      $hpAfterBattle[$unitId] = max(0, (int)$row['current_hp']);
    }
    $usedBattleLog = false;

    $logRow = $this->battleLogRepository()->getForUser($battleId, $userId);
    $log = is_array($logRow) ? json_decode((string)($logRow['log_json'] ?? ''), true) : null;
    if (is_array($log)) {
      $players = $log['meta']['participants']['player'] ?? null;
      if (is_array($players)) {
        foreach ($players as $participant) {
          if (!is_array($participant)) {
            continue;
          }
          $unitId = (int)($participant['unit_instance_id'] ?? 0);
          if ($unitId <= 0 || !array_key_exists($unitId, $hpAfterBattle)) {
            continue;
          }
          $startHp = isset($participant['current_hp'])
            ? (int)$participant['current_hp']
            : (int)($participant['max_hp'] ?? $hpAfterBattle[$unitId]);
          $hpAfterBattle[$unitId] = max(0, $startHp);
        }
      }

      $events = $log['events'] ?? null;
      if (is_array($events)) {
        foreach ($events as $event) {
          if (!is_array($event) || (string)($event['type'] ?? '') !== 'action') {
            continue;
          }
          $targetUnitId = (int)($event['target_unit_instance_id'] ?? 0);
          if ($targetUnitId <= 0 || !array_key_exists($targetUnitId, $hpAfterBattle)) {
            continue;
          }
          if (!isset($event['target_hp_after']) || !is_numeric($event['target_hp_after'])) {
            continue;
          }
          $hpAfterBattle[$targetUnitId] = max(0, (int)$event['target_hp_after']);
          $usedBattleLog = true;
        }
      }
    }

    if (!$usedBattleLog) {
      $unitMaxHp = $this->getUnitMaxHpByIdsForUser($userId, array_keys($runStateByUnitId));
      $battleSeed = (string)$battle['seed'];
      foreach ($runStateByUnitId as $unitId => $row) {
        $maxHp = max(1, (int)($unitMaxHp[$unitId] ?? 1));
        $currentHp = max(0, (int)$row['current_hp']);
        $lossPct = $this->deterministicLossPercent($battleSeed, $battleId, $unitId, (string)$battle['outcome']);
        $hpLoss = max(1, (int)floor($maxHp * $lossPct));
        $hpAfterBattle[$unitId] = max(0, $currentHp - $hpLoss);
      }
    }

    foreach ($runStateByUnitId as $unitId => $state) {
      $currentHp = isset($hpAfterBattle[$unitId])
        ? max(0, (int)$hpAfterBattle[$unitId])
        : max(0, (int)$state['current_hp']);
      $isDefeated = $currentHp <= 0;

      $runStateByUnitId[$unitId]['current_hp'] = $currentHp;
      $runStateByUnitId[$unitId]['is_defeated'] = $isDefeated;
      $runStateByUnitId[$unitId]['cooldowns_json'] = '{}';
      $runStateByUnitId[$unitId]['status_effects_json'] = '[]';

      $this->runRepository->upsertRunUnitState($runId, $unitId, $currentHp, $isDefeated, '{}', '[]');
    }

    $eligible = [];
    foreach ($runStateByUnitId as $unitId => $state) {
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
        'name' => (string)$unit['name'],
      ];
    }

    $updatedRunState = $this->formatRunUnitStateSnapshot($runStateByUnitId);
    $runResolution = null;

    if ((string)$battle['outcome'] === 'defeat') {
      $remaining = array_filter(
        $runStateByUnitId,
        static fn(array $row): bool => !empty($row['current_hp']) && empty($row['is_defeated'])
      );

      if (count($remaining) === 0) {
        $terminalRunState = $this->formatRunUnitStateSnapshot($runStateByUnitId);
        $runResolution = $this->failRun($userId, $runId);

        $updatedRunState = array_map(static fn(array $row): array => [
          'unit_instance_id' => (string)$row['unit_instance_id'],
          'hp' => (int)$row['current_hp'],
          'is_defeated' => (bool)$row['is_defeated'],
          'status_effects' => json_decode((string)$row['status_effects_json'], true) ?: [],
        ], $this->runRepository->getRunUnitState($runId));

        return [
          'updated_run_unit_state' => $updatedRunState,
          'terminal_run_unit_state' => $terminalRunState,
          'run_resolution' => $runResolution,
          'xp' => [
            'award_per_unit' => $awardPerUnit,
            'applied_unit_instance_ids' => $applied,
            'ignored_at_cap_unit_instance_ids' => $ignoredAtCap,
          ],
          'currency' => [
            'soft_awarded' => $softCurrencyAward,
            'raw_chaos_awarded' => $rawChaosAward,
          ],
          'new_feature_unlocks' => $newFeatureUnlocks,
          'updated_units' => $updatedUnits,
        ];
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
      'currency' => [
        'soft_awarded' => $softCurrencyAward,
        'raw_chaos_awarded' => $rawChaosAward,
      ],
      'new_feature_unlocks' => $newFeatureUnlocks,
      'updated_units' => $updatedUnits,
    ];
  }

  /**
   * @param array<string,mixed> $battle
   * @param array<string,mixed>|null $run
   * @return list<string>
   */
  private function unlockBattleClaimRewards(int $userId, array $battle, ?array $run): array
  {
    if ((string)($battle['outcome'] ?? '') !== 'victory' || (string)($battle['node_type'] ?? '') !== 'boss') {
      return [];
    }
    if (!is_array($run)) {
      return [];
    }

    $regionId = (int)($run['region_id'] ?? 0);
    if ($regionId <= 0) {
      return [];
    }

    $region = $this->regionRepository->getRegionById($regionId);
    if ($region === null || (string)($region['slug'] ?? '') !== 'the_farm') {
      return [];
    }

    $unlockService = new UserUnlockService($this->pdo);
    if ($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP)) {
      return [];
    }

    $unlockService->grant($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP);
    return [UserUnlockService::FEATURE_SHOP];
  }

  /**
   * @param array<int,array{
   *   unit_instance_id:string,
   *   current_hp:int,
   *   is_defeated:bool,
   *   status_effects_json:string
   * }> $runStateByUnitId
   * @return array<int,array{unit_instance_id:string,hp:int,is_defeated:bool,status_effects:array<int,mixed>}>
   */
  private function formatRunUnitStateSnapshot(array $runStateByUnitId): array
  {
    $snapshot = [];
    foreach ($runStateByUnitId as $unitId => $row) {
      $effects = json_decode((string)$row['status_effects_json'], true);
      $snapshot[] = [
        'unit_instance_id' => (string)$unitId,
        'hp' => (int)$row['current_hp'],
        'is_defeated' => !empty($row['is_defeated']),
        'status_effects' => is_array($effects) ? $effects : [],
      ];
    }

    return $snapshot;
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

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], array_values($unitIds));

    $stmt = $this->pdo->prepare("
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
      $out[(int)$row['unit_instance_id']] = $this->unitProgressionService()->maxHpForLevel(
        $row['base_stats_json'],
        (int)$row['level'],
        (int)$row['max_hp_per_level']
      );
    }

    return $out;
  }

  private function deterministicLossPercent(string $seed, int $battleId, int $unitId, string $outcome): float
  {
    $hash = hash('sha256', $seed . '|' . $battleId . '|' . $unitId);
    $slice = substr($hash, 0, 4);
    $roll = ((int)base_convert($slice, 16, 10)) % 100;

    if ($outcome === 'defeat') {
      return (45 + ($roll % 51)) / 100.0;
    }

    return (10 + ($roll % 26)) / 100.0;
  }

  /**
   * @return array{xp:int,level:int,max_level:int,name:string}|null
   */
  private function lockUnitProgress(int $userId, int $unitInstanceId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT ui.`xp`, ui.`level`, ui.`display_name`, ut.`name` AS `unit_type_name`, ut.`max_level`
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
      'name' => trim((string)($row['display_name'] ?? '')) !== ''
        ? (string)$row['display_name']
        : (string)($row['unit_type_name'] ?? ('Unit ' . $unitInstanceId)),
    ];
  }

  private function incrementUnitXp(int $userId, int $unitInstanceId, int $deltaXp): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `unit_instances`
      SET `xp` = `xp` + ?
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([$deltaXp, $unitInstanceId, $userId]);
  }

  private function battleRepository(): BattleRepository
  {
    $this->battleRepository ??= new BattleRepository($this->pdo);
    return $this->battleRepository;
  }

  private function battleLogRepository(): BattleLogRepository
  {
    $this->battleLogRepository ??= new BattleLogRepository($this->pdo);
    return $this->battleLogRepository;
  }

  private function battleRewardsRepository(): BattleRewardsRepository
  {
    $this->battleRewardsRepository ??= new BattleRewardsRepository($this->pdo);
    return $this->battleRewardsRepository;
  }

  private function playerStateRepository(): PlayerStateRepository
  {
    $this->playerStateRepository ??= new PlayerStateRepository($this->pdo);
    return $this->playerStateRepository;
  }

  private function unitProgressionService(): UnitProgressionService
  {
    $this->unitProgressionService ??= new UnitProgressionService();
    return $this->unitProgressionService;
  }

  /**
   * @template T
   * @param callable():T $callback
   * @return T
   */
  private function withinTransaction(callable $callback): mixed
  {
    $ownsTransaction = false;

    try {
      if (!$this->pdo->inTransaction()) {
        $this->pdo->beginTransaction();
        $ownsTransaction = true;
      }

      $result = $callback();

      if ($ownsTransaction) {
        $this->pdo->commit();
      }

      return $result;
    } catch (Throwable $throwable) {
      if ($ownsTransaction && $this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }

      throw $throwable;
    }
  }

  /**
   * @return array<string,mixed>
   */
  private function buildRunSummary(int $userId, int $runId): array
  {
    return (new RunSummaryBuilder($this->pdo))->buildRunSummary($userId, $runId);
  }

  /**
   * @return array{
   *   completed_region_slug:?string,
   *   completed_region_name:?string,
   *   new_feature_unlocks:array<int,string>,
   *   new_region_unlocks:array<int,string>
   * }
   */
  private function unlockSuccessfulCompletionRewards(int $userId, int $completedRegionId): array
  {
    if ($completedRegionId <= 0) {
      return [
        'completed_region_slug' => null,
        'completed_region_name' => null,
        'new_feature_unlocks' => [],
        'new_region_unlocks' => [],
      ];
    }

    $completedRegion = $this->regionRepository->getRegionById($completedRegionId);
    if ($completedRegion === null) {
      return [
        'completed_region_slug' => null,
        'completed_region_name' => null,
        'new_feature_unlocks' => [],
        'new_region_unlocks' => [],
      ];
    }

    $newFeatureUnlocks = [];
    $newRegionUnlocks = [];
    $completedRegionSlug = (string)$completedRegion['slug'];
    $unlockService = new UserUnlockService($this->pdo);

    if (
      $completedRegionSlug === 'the_farm'
      && !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP)
    ) {
      $unlockService->grant($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP);
      $newFeatureUnlocks[] = UserUnlockService::FEATURE_SHOP;
    }

    if (
      $completedRegionSlug === 'swamps'
      && !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE)
    ) {
      $unlockService->grant($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE);
      $newFeatureUnlocks[] = UserUnlockService::FEATURE_WRONG_MACHINE;
    }

    $nextSlug = $this->regionRepository->getNextRegionSlug($completedRegionSlug);
    if ($nextSlug === null) {
      return [
        'completed_region_slug' => $completedRegionSlug,
        'completed_region_name' => (string)($completedRegion['name'] ?? ''),
        'new_feature_unlocks' => $newFeatureUnlocks,
        'new_region_unlocks' => $newRegionUnlocks,
      ];
    }

    $nextRegion = $this->regionRepository->getRegionBySlug($nextSlug);
    if ($nextRegion === null || !$nextRegion['is_enabled']) {
      return [
        'completed_region_slug' => $completedRegionSlug,
        'completed_region_name' => (string)($completedRegion['name'] ?? ''),
        'new_feature_unlocks' => $newFeatureUnlocks,
        'new_region_unlocks' => $newRegionUnlocks,
      ];
    }

    if (!$this->regionRepository->isRegionUnlocked($userId, (int)$nextRegion['id'])) {
      $newRegionUnlocks[] = (string)$nextRegion['slug'];
    }
    $this->regionRepository->unlockRegion($userId, (int)$nextRegion['id']);

    return [
      'completed_region_slug' => $completedRegionSlug,
      'completed_region_name' => (string)($completedRegion['name'] ?? ''),
      'new_feature_unlocks' => $newFeatureUnlocks,
      'new_region_unlocks' => $newRegionUnlocks,
    ];
  }
}
