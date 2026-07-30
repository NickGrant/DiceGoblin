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
  public function claimBattle(int $userId, int $battleId, string $claimAction = 'accept'): array
  {
    $claimAction = strtolower(trim($claimAction));
    if (!in_array($claimAction, ['accept', 'decline'], true)) {
      throw new RuntimeException('invalid_claim_action');
    }

    return $this->withinTransaction(function () use ($userId, $battleId, $claimAction): array {
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

      $rewards = json_decode($battle['rewards_json'], true);
      if (!is_array($rewards)) {
        $rewards = [];
      }

      if ($claimAction === 'decline') {
        if ((string)($battle['node_type'] ?? '') !== 'shrine' || !$this->shrineCanBeDeclined($rewards)) {
          throw new RuntimeException('shrine_not_declineable');
        }
        $claimSnapshot = $this->buildDeclinedShrineClaimSnapshot((int)$battle['run_id']);
      } else {
        $claimSnapshot = $this->applyBattleRewardsAndXp($userId, $battle);
      }

      $rewards['claim_snapshot'] = $claimSnapshot;
      $this->battleRewardsRepository()->updateRewardsJsonAndCurrencySoft(
        $battleId,
        $rewards,
        (int)($claimSnapshot['currency']['soft_awarded'] ?? $battle['currency_soft'] ?? 0)
      );
      $this->battleRepository()->markClaimedIfCompleted($battleId, $userId);

      $battle['status'] = 'claimed';
      $battle['currency_soft'] = (int)($claimSnapshot['currency']['soft_awarded'] ?? $battle['currency_soft'] ?? 0);
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
    $shrineCurrencyBonus = $nodeType === 'shrine'
      ? $this->shrineCurrencyBonus($runId, (int)$battle['node_id'], $rewards)
      : 0;
    if ($shrineCurrencyBonus > 0) {
      $softCurrencyAward += $shrineCurrencyBonus;
    }
    $run = $this->runRepository->getRunForUser($userId, $runId);
    $runAlreadyEnded = is_array($run) && (string)($run['status'] ?? '') !== 'active';
    $newFeatureUnlocks = $this->unlockBattleClaimRewards($userId, $battle, $run);

    $runStateRows = $this->runRepository->getRunUnitStateForUpdate($runId);
    $runStateByUnitId = [];
    foreach ($runStateRows as $row) {
      $runStateByUnitId[(int)$row['unit_instance_id']] = $row;
    }
    $shrineEffects = $nodeType === 'shrine'
      ? $this->applyShrineClaimEffects($userId, $runId, (int)$battle['node_id'], $rewards, $runStateByUnitId)
      : [];
    $hazardEffects = $nodeType === 'hazard'
      ? $this->applyHazardClaimEffects($userId, $runId, (int)$battle['node_id'], $rewards, $runStateByUnitId)
      : [];
    if ($shrineCurrencyBonus > 0) {
      array_unshift($shrineEffects, [
        'type' => 'double_run_teeth',
        'soft_awarded' => $shrineCurrencyBonus,
      ]);
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
        'shrine_effects' => $shrineEffects,
        'hazard_effects' => $hazardEffects,
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
        'shrine_effects' => $shrineEffects,
        'hazard_effects' => $hazardEffects,
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
        'shrine_effects' => $shrineEffects,
        'hazard_effects' => $hazardEffects,
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
          'shrine_effects' => $shrineEffects,
          'hazard_effects' => $hazardEffects,
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
      'shrine_effects' => $shrineEffects,
      'hazard_effects' => $hazardEffects,
      'new_feature_unlocks' => $newFeatureUnlocks,
      'updated_units' => $updatedUnits,
    ];
  }

  /**
   * @param array<string,mixed> $rewards
   */
  private function shrineCurrencyBonus(int $runId, int $nodeId, array $rewards): int
  {
    $effect = $this->shrineEffect($rewards);
    if ((string)($effect['type'] ?? '') !== 'double_run_teeth') {
      return 0;
    }

    $stmt = $this->pdo->prepare('
      SELECT COALESCE(SUM(br.`currency_soft`), 0)
      FROM `battle_rewards` br
      JOIN `battles` b ON b.`id` = br.`battle_id`
      JOIN `run_nodes` current_node ON current_node.`id` = ?
      JOIN `run_nodes` earned_node
        ON earned_node.`id` = b.`node_id`
       AND earned_node.`run_id` = b.`run_id`
      WHERE b.`run_id` = ?
        AND b.`status` = \'claimed\'
        AND earned_node.`node_index` < current_node.`node_index`
    ');
    $stmt->execute([$nodeId, $runId]);
    return max(0, (int)$stmt->fetchColumn());
  }

  /**
   * @param array<string,mixed> $rewards
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyShrineClaimEffects(int $userId, int $runId, int $nodeId, array $rewards, array &$runStateByUnitId): array
  {
    $effect = $this->shrineEffect($rewards);
    $type = (string)($effect['type'] ?? '');
    if ($type === '') {
      return [];
    }

    return match ($type) {
      'heal_random_unit' => $this->applyShrineRandomHeal($userId, $runId, $nodeId, $effect, $runStateByUnitId),
      'drain_highest_life_heal_rest' => $this->applyShrineDrainAndHeal($userId, $runId, $effect, $runStateByUnitId),
      'squad_damage_next_combat' => $this->applyShrineSquadDamageEffect($runId, $effect, $runStateByUnitId),
      'run_stat_modifier_next_combat', 'stat_modifier_next_combat', 'squad_stat_modifier_next_combat' => $this->applyShrineRunStatModifierEffect($runId, $effect, $runStateByUnitId),
      'clear_random_combat_node' => $this->applyShrineClearCombatNode($runId, $nodeId),
      'upgrade_run_unit_tier' => $this->applyShrineUnitTierUpgrade($userId, $runId, $nodeId, $effect),
      default => [],
    };
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<string,mixed>
   */
  private function shrineEffect(array $rewards): array
  {
    $encounter = is_array($rewards['encounter_result'] ?? null) ? $rewards['encounter_result'] : [];
    $result = is_array($encounter['result'] ?? null) ? $encounter['result'] : [];
    return is_array($result['effect'] ?? null) ? $result['effect'] : [];
  }

  /**
   * @param array<string,mixed> $rewards
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyHazardClaimEffects(int $userId, int $runId, int $nodeId, array $rewards, array &$runStateByUnitId): array
  {
    $effect = $this->hazardEffect($rewards);
    $type = (string)($effect['type'] ?? '');
    if ($type === '') {
      return [];
    }

    return match ($type) {
      'damage_random_unit' => $this->applyHazardRandomDamage($runId, $nodeId, $effect, $runStateByUnitId),
      'damage_squad' => $this->applyHazardSquadDamage($runId, $effect, $runStateByUnitId),
      'lose_teeth' => $this->applyHazardTeethLoss($userId, $effect),
      'run_stat_modifier_next_combat', 'stat_modifier_next_combat', 'squad_stat_modifier_next_combat' => $this->applyHazardRunStatModifierEffect($runId, $effect, $runStateByUnitId),
      'route_pressure' => [[
        'type' => 'route_pressure',
        'pressure' => (string)($effect['pressure'] ?? 'route'),
      ]],
      default => [],
    };
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<string,mixed>
   */
  private function hazardEffect(array $rewards): array
  {
    $encounter = is_array($rewards['encounter_result'] ?? null) ? $rewards['encounter_result'] : [];
    $result = is_array($encounter['result'] ?? null) ? $encounter['result'] : [];
    return is_array($result['effect'] ?? null) ? $result['effect'] : [];
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyHazardRandomDamage(int $runId, int $nodeId, array $effect, array &$runStateByUnitId): array
  {
    $candidates = array_values(array_filter(array_keys($runStateByUnitId), fn(int $unitId): bool =>
      empty($runStateByUnitId[$unitId]['is_defeated']) && (int)$runStateByUnitId[$unitId]['current_hp'] > 1
    ));
    if ($candidates === []) {
      return [];
    }

    $pick = $candidates[$this->deterministicIndex("hazard-damage|{$runId}|{$nodeId}", count($candidates))];
    $damage = max(1, (int)($effect['damage'] ?? 1));
    $before = max(0, (int)$runStateByUnitId[$pick]['current_hp']);
    $after = max(1, $before - $damage);
    $runStateByUnitId[$pick]['current_hp'] = $after;
    $runStateByUnitId[$pick]['is_defeated'] = false;
    $this->runRepository->upsertRunUnitState($runId, $pick, $after, false, (string)$runStateByUnitId[$pick]['cooldowns_json'], (string)$runStateByUnitId[$pick]['status_effects_json']);

    return [[
      'type' => 'damage_random_unit',
      'unit_instance_id' => (string)$pick,
      'damage' => $before - $after,
      'hp_before' => $before,
      'hp_after' => $after,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyHazardSquadDamage(int $runId, array $effect, array &$runStateByUnitId): array
  {
    $damage = max(1, (int)($effect['damage'] ?? 1));
    $changes = [];
    foreach ($runStateByUnitId as $unitId => &$state) {
      if (!empty($state['is_defeated']) || (int)$state['current_hp'] <= 1) {
        continue;
      }
      $before = max(0, (int)$state['current_hp']);
      $after = max(1, $before - $damage);
      $state['current_hp'] = $after;
      $state['is_defeated'] = false;
      $this->runRepository->upsertRunUnitState($runId, $unitId, $after, false, (string)$state['cooldowns_json'], (string)$state['status_effects_json']);
      $changes[] = [
        'unit_instance_id' => (string)$unitId,
        'damage' => $before - $after,
        'hp_before' => $before,
        'hp_after' => $after,
      ];
    }
    unset($state);

    return $changes === [] ? [] : [[
      'type' => 'damage_squad',
      'damage' => $damage,
      'changes' => $changes,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @return list<array<string,mixed>>
   */
  private function applyHazardTeethLoss(int $userId, array $effect): array
  {
    $amount = max(1, (int)($effect['amount'] ?? 1));
    $playerStateRepository = $this->playerStateRepository();
    $playerStateRepository->ensurePlayerState($userId);
    $playerState = $playerStateRepository->getPlayerStateForUpdate($userId);
    if (!is_array($playerState)) {
      return [];
    }

    $before = max(0, (int)$playerState['currency_soft']);
    $lost = min($before, $amount);
    $playerStateRepository->setCurrency($userId, $before - $lost, max(0, (int)$playerState['currency_hard']));

    return [[
      'type' => 'lose_teeth',
      'requested_amount' => $amount,
      'soft_lost' => $lost,
      'soft_before' => $before,
      'soft_after' => $before - $lost,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyHazardRunStatModifierEffect(int $runId, array $effect, array &$runStateByUnitId): array
  {
    $statMultipliers = $this->normalizeShrineFloatMap($effect['stat_multipliers'] ?? []);
    $statAdders = $this->normalizeShrineIntMap($effect['stat_adders'] ?? []);
    $allowedMultipliers = array_flip(['attack', 'defense', 'precision', 'resolve', 'damage']);
    $allowedAdders = array_flip(['attack', 'defense', 'precision', 'resolve']);
    $statMultipliers = array_intersect_key($statMultipliers, $allowedMultipliers);
    $statAdders = array_intersect_key($statAdders, $allowedAdders);
    if ($statMultipliers === [] && $statAdders === []) {
      return [];
    }

    $effectRow = [
      'type' => 'run_stat_modifier_next_combat',
      'stat_multipliers' => $statMultipliers,
      'stat_adders' => $statAdders,
      'remaining_combats' => 1,
      'source' => 'hazard',
    ];
    $applied = [];
    foreach ($runStateByUnitId as $unitId => &$state) {
      if (!empty($state['is_defeated'])) {
        continue;
      }
      $effects = json_decode((string)$state['status_effects_json'], true);
      $effects = is_array($effects) ? $effects : [];
      $effects[] = $effectRow;
      $state['status_effects_json'] = json_encode($effects, JSON_UNESCAPED_SLASHES);
      $this->runRepository->upsertRunUnitState($runId, $unitId, (int)$state['current_hp'], !empty($state['is_defeated']), (string)$state['cooldowns_json'], (string)$state['status_effects_json']);
      $applied[] = (string)$unitId;
    }
    unset($state);

    return $applied === [] ? [] : [[
      'type' => 'run_stat_modifier_next_combat',
      'stat_multipliers' => $statMultipliers,
      'stat_adders' => $statAdders,
      'applied_unit_instance_ids' => $applied,
    ]];
  }

  /**
   * @param array<string,mixed> $rewards
   */
  private function shrineCanBeDeclined(array $rewards): bool
  {
    $encounter = is_array($rewards['encounter_result'] ?? null) ? $rewards['encounter_result'] : [];
    $result = is_array($encounter['result'] ?? null) ? $encounter['result'] : [];
    $cost = is_array($result['cost'] ?? null) ? $result['cost'] : [];
    return !empty($result['declineable']) || !empty($cost['declineable']);
  }

  /**
   * @return array<string,mixed>
   */
  private function buildDeclinedShrineClaimSnapshot(int $runId): array
  {
    return [
      'updated_run_unit_state' => $this->formatRunUnitStateSnapshot($this->runStateByUnitId($runId)),
      'run_resolution' => null,
      'xp' => [
        'award_per_unit' => 0,
        'applied_unit_instance_ids' => [],
        'ignored_at_cap_unit_instance_ids' => [],
      ],
      'currency' => [
        'soft_awarded' => 0,
        'raw_chaos_awarded' => 0,
      ],
      'shrine_effects' => [],
      'shrine_decision' => 'decline',
      'new_feature_unlocks' => [],
      'updated_units' => [],
    ];
  }

  /**
   * @return array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}>
   */
  private function runStateByUnitId(int $runId): array
  {
    $rows = $this->runRepository->getRunUnitStateForUpdate($runId);
    $byUnitId = [];
    foreach ($rows as $row) {
      $byUnitId[(int)$row['unit_instance_id']] = $row;
    }
    return $byUnitId;
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyShrineRandomHeal(int $userId, int $runId, int $nodeId, array $effect, array &$runStateByUnitId): array
  {
    $maxHpByUnit = $this->getUnitMaxHpByIdsForUser($userId, array_keys($runStateByUnitId));
    $candidates = [];
    foreach ($runStateByUnitId as $unitId => $state) {
      $maxHp = max(1, (int)($maxHpByUnit[$unitId] ?? 1));
      $currentHp = max(0, (int)$state['current_hp']);
      if ($currentHp < $maxHp) {
        $candidates[] = $unitId;
      }
    }
    if ($candidates === []) {
      return [];
    }

    $pick = $candidates[$this->deterministicIndex("shrine-heal|{$runId}|{$nodeId}", count($candidates))];
    $maxHp = max(1, (int)($maxHpByUnit[$pick] ?? 1));
    $before = max(0, (int)$runStateByUnitId[$pick]['current_hp']);
    $amountPct = max(1, (int)($effect['amount_pct'] ?? 35));
    $amount = max(1, (int)ceil($maxHp * ($amountPct / 100)));
    $after = min($maxHp, $before + $amount);
    $runStateByUnitId[$pick]['current_hp'] = $after;
    $runStateByUnitId[$pick]['is_defeated'] = $after <= 0;
    $this->runRepository->upsertRunUnitState($runId, $pick, $after, $after <= 0, (string)$runStateByUnitId[$pick]['cooldowns_json'], (string)$runStateByUnitId[$pick]['status_effects_json']);

    return [[
      'type' => 'heal_random_unit',
      'unit_instance_id' => (string)$pick,
      'hp_before' => $before,
      'hp_after' => $after,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyShrineDrainAndHeal(int $userId, int $runId, array $effect, array &$runStateByUnitId): array
  {
    $maxHpByUnit = $this->getUnitMaxHpByIdsForUser($userId, array_keys($runStateByUnitId));
    $donorId = null;
    $donorHp = -1;
    foreach ($runStateByUnitId as $unitId => $state) {
      $currentHp = max(0, (int)$state['current_hp']);
      if ($currentHp > $donorHp) {
        $donorId = $unitId;
        $donorHp = $currentHp;
      }
    }
    if ($donorId === null || count($runStateByUnitId) < 2) {
      return [];
    }

    $drainPct = max(1, min(95, (int)($effect['drain_pct'] ?? 50)));
    $drain = max(1, (int)floor($donorHp * ($drainPct / 100)));
    $donorAfter = max(1, $donorHp - $drain);
    $changes = [[
      'unit_instance_id' => (string)$donorId,
      'hp_before' => $donorHp,
      'hp_after' => $donorAfter,
      'role' => 'donor',
    ]];
    $runStateByUnitId[$donorId]['current_hp'] = $donorAfter;
    $runStateByUnitId[$donorId]['is_defeated'] = false;
    $this->runRepository->upsertRunUnitState($runId, $donorId, $donorAfter, false, (string)$runStateByUnitId[$donorId]['cooldowns_json'], (string)$runStateByUnitId[$donorId]['status_effects_json']);

    foreach ($runStateByUnitId as $unitId => &$state) {
      if ($unitId === $donorId) {
        continue;
      }
      $before = max(0, (int)$state['current_hp']);
      $after = max(1, (int)($maxHpByUnit[$unitId] ?? $before));
      $state['current_hp'] = $after;
      $state['is_defeated'] = false;
      $this->runRepository->upsertRunUnitState($runId, $unitId, $after, false, (string)$state['cooldowns_json'], (string)$state['status_effects_json']);
      $changes[] = [
        'unit_instance_id' => (string)$unitId,
        'hp_before' => $before,
        'hp_after' => $after,
        'role' => 'healed',
      ];
    }
    unset($state);

    return [[
      'type' => 'drain_highest_life_heal_rest',
      'changes' => $changes,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyShrineSquadDamageEffect(int $runId, array $effect, array &$runStateByUnitId): array
  {
    $multiplier = (float)($effect['damage_multiplier'] ?? 1.10);
    $effectRow = [
      'type' => 'squad_damage_next_combat',
      'damage_multiplier' => $multiplier,
      'remaining_combats' => 1,
      'source' => 'shrine',
    ];
    $applied = [];
    foreach ($runStateByUnitId as $unitId => &$state) {
      if (!empty($state['is_defeated'])) {
        continue;
      }
      $effects = json_decode((string)$state['status_effects_json'], true);
      $effects = is_array($effects) ? $effects : [];
      $effects[] = $effectRow;
      $state['status_effects_json'] = json_encode($effects, JSON_UNESCAPED_SLASHES);
      $this->runRepository->upsertRunUnitState($runId, $unitId, (int)$state['current_hp'], !empty($state['is_defeated']), (string)$state['cooldowns_json'], (string)$state['status_effects_json']);
      $applied[] = (string)$unitId;
    }
    unset($state);

    return $applied === [] ? [] : [[
      'type' => 'squad_damage_next_combat',
      'damage_multiplier' => $multiplier,
      'applied_unit_instance_ids' => $applied,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @param array<int,array{unit_instance_id:string,current_hp:int,is_defeated:bool,cooldowns_json:string,status_effects_json:string}> $runStateByUnitId
   * @return list<array<string,mixed>>
   */
  private function applyShrineRunStatModifierEffect(int $runId, array $effect, array &$runStateByUnitId): array
  {
    $statMultipliers = $this->normalizeShrineFloatMap($effect['stat_multipliers'] ?? []);
    $statAdders = $this->normalizeShrineIntMap($effect['stat_adders'] ?? []);
    $allowedMultipliers = array_flip(['attack', 'defense', 'precision', 'resolve', 'damage']);
    $allowedAdders = array_flip(['attack', 'defense', 'precision', 'resolve']);
    $statMultipliers = array_intersect_key($statMultipliers, $allowedMultipliers);
    $statAdders = array_intersect_key($statAdders, $allowedAdders);
    if ($statMultipliers === [] && $statAdders === []) {
      return [];
    }

    $effectType = in_array((string)($effect['type'] ?? ''), ['stat_modifier_next_combat', 'squad_stat_modifier_next_combat'], true)
      ? (string)$effect['type']
      : 'run_stat_modifier_next_combat';
    $effectRow = [
      'type' => $effectType,
      'stat_multipliers' => $statMultipliers,
      'stat_adders' => $statAdders,
      'remaining_combats' => 1,
      'source' => 'shrine',
    ];
    $applied = [];
    foreach ($runStateByUnitId as $unitId => &$state) {
      if (!empty($state['is_defeated'])) {
        continue;
      }
      $effects = json_decode((string)$state['status_effects_json'], true);
      $effects = is_array($effects) ? $effects : [];
      $effects[] = $effectRow;
      $state['status_effects_json'] = json_encode($effects, JSON_UNESCAPED_SLASHES);
      $this->runRepository->upsertRunUnitState($runId, $unitId, (int)$state['current_hp'], !empty($state['is_defeated']), (string)$state['cooldowns_json'], (string)$state['status_effects_json']);
      $applied[] = (string)$unitId;
    }
    unset($state);

    return $applied === [] ? [] : [[
      'type' => $effectType,
      'stat_multipliers' => $statMultipliers,
      'stat_adders' => $statAdders,
      'applied_unit_instance_ids' => $applied,
    ]];
  }

  /**
   * @return array<string,float>
   */
  private function normalizeShrineFloatMap(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $key => $raw) {
      if (is_numeric($raw)) {
        $out[(string)$key] = (float)$raw;
      }
    }

    return $out;
  }

  /**
   * @return array<string,int>
   */
  private function normalizeShrineIntMap(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $key => $raw) {
      if (is_numeric($raw)) {
        $out[(string)$key] = (int)$raw;
      }
    }

    return $out;
  }

  /**
   * @return list<array<string,mixed>>
   */
  private function applyShrineClearCombatNode(int $runId, int $nodeId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `run_nodes`
      WHERE `run_id` = ?
        AND `id` != ?
        AND `node_type` = \'combat\'
        AND `status` = \'available\'
      ORDER BY `node_index` ASC
      FOR UPDATE
    ');
    $stmt->execute([$runId, $nodeId]);
    $nodeIds = array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    if ($nodeIds === []) {
      return [];
    }

    $clearedNodeId = $nodeIds[$this->deterministicIndex("shrine-clear-combat|{$runId}|{$nodeId}", count($nodeIds))];
    $this->runNodeRepository->markCleared($runId, $clearedNodeId);
    $unlocked = $this->runNodeRepository->syncAvailableNodesFromClearedParents($runId);

    return [[
      'type' => 'clear_random_combat_node',
      'cleared_node_id' => (string)$clearedNodeId,
      'unlocked_node_ids' => $unlocked,
    ]];
  }

  /**
   * @param array<string,mixed> $effect
   * @return list<array<string,mixed>>
   */
  private function applyShrineUnitTierUpgrade(int $userId, int $runId, int $nodeId, array $effect): array
  {
    $eligibleUnitIds = $this->eligibleRunRewardUnitIdsBeforeNode($runId, $nodeId);
    if ($eligibleUnitIds === []) {
      return [];
    }

    $unitsById = $this->loadUpgradeableRewardUnitsForUpdate($userId, $eligibleUnitIds);
    $eligible = [];
    $tierIncrease = max(1, (int)($effect['tier_increase'] ?? 1));
    $maxTier = max(1, (int)($effect['max_tier'] ?? 3));
    foreach ($eligibleUnitIds as $unitId) {
      $unit = $unitsById[$unitId] ?? null;
      if (!is_array($unit)) {
        continue;
      }
      $target = $this->upgradeTargetUnitType((string)$unit['unit_type_slug'], (int)$unit['tier'], $tierIncrease, $maxTier);
      if (is_array($target)) {
        $unit['target'] = $target;
        $eligible[] = $unit;
      }
    }

    if ($eligible === []) {
      return [];
    }

    $pick = $eligible[$this->deterministicIndex("shrine-unit-upgrade|{$runId}|{$nodeId}", count($eligible))];
    $target = is_array($pick['target'] ?? null) ? $pick['target'] : [];
    $targetTier = max(1, (int)($target['tier'] ?? ((int)$pick['tier'] + $tierIncrease)));
    $unitId = (int)$pick['id'];

    $stmt = $this->pdo->prepare('
      UPDATE `unit_instances`
      SET `unit_type_id` = ?, `tier` = ?, `level` = 1, `xp` = 0
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([(int)$target['id'], $targetTier, $unitId, $userId]);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, (int)$target['id']);

    return [[
      'type' => 'upgrade_run_unit_tier',
      'unit_instance_id' => (string)$unitId,
      'unit_type_slug_before' => (string)$pick['unit_type_slug'],
      'unit_type_name_before' => (string)$pick['unit_type_name'],
      'tier_before' => (int)$pick['tier'],
      'unit_type_slug_after' => (string)$target['slug'],
      'unit_type_name_after' => (string)$target['name'],
      'tier_after' => $targetTier,
    ]];
  }

  /**
   * @return list<int>
   */
  private function eligibleRunRewardUnitIdsBeforeNode(int $runId, int $nodeId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT br.`rewards_json`
      FROM `battle_rewards` br
      JOIN `battles` b ON b.`id` = br.`battle_id`
      JOIN `run_nodes` current_node ON current_node.`id` = ?
      JOIN `run_nodes` earned_node
        ON earned_node.`id` = b.`node_id`
       AND earned_node.`run_id` = b.`run_id`
      WHERE b.`run_id` = ?
        AND earned_node.`node_index` < current_node.`node_index`
      ORDER BY earned_node.`node_index` ASC, b.`id` ASC
    ');
    $stmt->execute([$nodeId, $runId]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $rewards = json_decode((string)($row['rewards_json'] ?? ''), true);
      $unitIds = is_array($rewards) && is_array($rewards['new_unit_instance_ids'] ?? null)
        ? $rewards['new_unit_instance_ids']
        : [];
      foreach ($unitIds as $unitId) {
        $id = (int)$unitId;
        if ($id > 0 && !in_array($id, $ids, true)) {
          $ids[] = $id;
        }
      }
    }

    return $ids;
  }

  /**
   * @param list<int> $unitIds
   * @return array<int,array{id:int,unit_type_id:int,unit_type_slug:string,unit_type_name:string,tier:int}>
   */
  private function loadUpgradeableRewardUnitsForUpdate(int $userId, array $unitIds): array
  {
    $unitIds = array_values(array_unique(array_filter($unitIds, static fn(int $id): bool => $id > 0)));
    if ($unitIds === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT ui.`id`, ui.`unit_type_id`, ui.`tier`, ut.`slug` AS `unit_type_slug`, ut.`name` AS `unit_type_name`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
      FOR UPDATE
    ");
    $stmt->execute(array_merge([$userId], $unitIds));

    $units = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $units[(int)$row['id']] = [
        'id' => (int)$row['id'],
        'unit_type_id' => (int)$row['unit_type_id'],
        'unit_type_slug' => (string)$row['unit_type_slug'],
        'unit_type_name' => (string)$row['unit_type_name'],
        'tier' => max(1, (int)$row['tier']),
      ];
    }

    return $units;
  }

  /**
   * @return array{id:int,slug:string,name:string,tier:int}|null
   */
  private function upgradeTargetUnitType(string $currentSlug, int $currentTier, int $tierIncrease, int $maxTier): ?array
  {
    $stem = preg_replace('/_t\d+$/', '', $currentSlug);
    if (!is_string($stem) || $stem === '') {
      return null;
    }

    $targetTier = min($maxTier, max(1, $currentTier) + $tierIncrease);
    if ($targetTier <= $currentTier) {
      return null;
    }

    $targetSlug = "{$stem}_t{$targetTier}";
    $stmt = $this->pdo->prepare('SELECT `id`, `slug`, `name` FROM `unit_types` WHERE `slug` = ? LIMIT 1');
    $stmt->execute([$targetSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return null;
    }

    return [
      'id' => (int)$row['id'],
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'tier' => $targetTier,
    ];
  }

  private function deterministicIndex(string $seed, int $count): int
  {
    if ($count <= 1) {
      return 0;
    }

    return ((int)base_convert(substr(hash('sha256', $seed), 0, 8), 16, 10)) % $count;
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
