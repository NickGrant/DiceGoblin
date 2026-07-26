<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Services\RunLifecycleService;
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class RunLifecycleServiceIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run lifecycle service integration tests.';
  }

  public function testFailRunResetsDefeatedXpAndRestoresRunUnitState(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId, 10111213);

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $survivorId = $this->insertUnit($userId, $unitTypeId, 1, 14);
    $defeatedId = $this->insertUnit($userId, $unitTypeId, 1, 27);
    $this->insertRunUnitState($runId, $survivorId, 5, false);
    $this->insertRunUnitState($runId, $defeatedId, 0, true);

    $result = $this->service()->failRun($userId, $runId);

    $this->assertSame(['run_id' => (string)$runId, 'status' => 'failed'], $result);
    $this->assertSame('failed', (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]));
    $this->assertSame('14', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$survivorId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$defeatedId]));

    $stateRows = $this->rows(
      'SELECT `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`
       FROM `run_unit_state`
       WHERE `run_id` = ?
       ORDER BY `unit_instance_id` ASC',
      [$runId]
    );
    $this->assertCount(2, $stateRows);
    foreach ($stateRows as $row) {
      $this->assertGreaterThan(0, (int)$row['current_hp']);
      $this->assertSame('0', (string)$row['is_defeated']);
      $this->assertSame('{}', (string)$row['cooldowns_json']);
      $this->assertSame('[]', (string)$row['status_effects_json']);
    }
  }

  public function testAbandonRunReturnsPreCleanupSummarySnapshot(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 20212223);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 2, 19);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 0, true);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'victory', 30313233, 20, 2);
    $this->insertBattleRewards($battleId, 9, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
      'claim_snapshot' => [
        'xp' => [
          'award_per_unit' => 9,
          'applied_unit_instance_ids' => [(string)$unitId],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => [
          ['id' => (string)$unitId, 'xp' => 19, 'level' => 2, 'name' => 'Progress Test Unit'],
        ],
      ],
    ]);

    $result = $this->service()->abandonRun($userId, $runId);

    $this->assertSame('abandoned', $result['status']);
    $summary = is_array($result['run_summary'] ?? null) ? $result['run_summary'] : [];
    $progressionDetail = array_values(array_filter($summary['progression_detail'] ?? [], 'is_array'));
    $this->assertCount(1, $progressionDetail);
    $this->assertSame((string)$unitId, (string)($progressionDetail[0]['unit_instance_id'] ?? ''));
    $this->assertSame(19, (int)($progressionDetail[0]['final_xp'] ?? -1));
    $this->assertTrue((bool)($progressionDetail[0]['is_defeated'] ?? false));

    $this->assertSame('abandoned', (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  public function testCompleteRunClearsExitNodeAndGrantsCompletionUnlocks(): void
  {
    $userId = $this->insertUser();
    $farmRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1", []);
    $mountainsRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1", []);
    $this->assertGreaterThan(0, $farmRegionId);
    $this->assertGreaterThan(0, $mountainsRegionId);

    $runId = $this->insertRun($userId, $farmRegionId, 40414243);
    $exitNodeId = $this->insertRunNode($runId, 'exit', 'available');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 33);
    $this->insertRunUnitState($runId, $unitId, 1, true);

    $result = $this->service()->completeRun($userId, $runId, $farmRegionId, $exitNodeId);

    $this->assertSame('completed', $result['status']);
    $this->assertSame((string)$exitNodeId, $result['exit_node_id']);
    $this->assertSame('completed', (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]));
    $this->assertSame('cleared', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$exitNodeId]));
    $this->assertSame('33', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));

    $summary = is_array($result['run_summary'] ?? null) ? $result['run_summary'] : [];
    $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
    $this->assertContains('shop', $meta['new_feature_unlocks'] ?? []);
    $this->assertContains('mountains', $meta['new_region_unlocks'] ?? []);

    $unlockService = new UserUnlockService($this->pdo);
    $this->assertTrue($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP));
    $this->assertSame(
      '1',
      (string)$this->scalar(
        'SELECT COUNT(*) FROM `region_unlocks` WHERE `user_id` = ? AND `region_id` = ?',
        [$userId, $mountainsRegionId]
      )
    );
  }

  public function testClaimingFarmBossVictoryUnlocksShopBeforeRunCompletion(): void
  {
    $userId = $this->insertUser();
    $farmRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1", []);
    $mountainsRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1", []);
    $this->assertGreaterThan(0, $farmRegionId);
    $this->assertGreaterThan(0, $mountainsRegionId);

    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $farmRegionId, 41424344);
    $bossNodeId = $this->insertRunNode($runId, 'boss', 'cleared');
    $exitNodeId = $this->insertRunNode($runId, 'exit', 'available');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 12, false);

    $battleId = $this->insertBattle($userId, $runId, $bossNodeId, $teamId, 'completed', 'victory', 91929394, 20, 2);
    $this->insertBattleRewards($battleId, 8, 5, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $claim = $this->service()->claimBattle($userId, $battleId);
    $snapshot = is_array($claim['claim_snapshot'] ?? null) ? $claim['claim_snapshot'] : [];

    $this->assertTrue($claim['newly_claimed']);
    $this->assertContains(UserUnlockService::FEATURE_SHOP, $snapshot['new_feature_unlocks'] ?? []);

    $unlockService = new UserUnlockService($this->pdo);
    $this->assertTrue($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP));
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = ?",
        [$userId, UserUnlockService::FEATURE_SHOP]
      )
    );

    $complete = $this->service()->completeRun($userId, $runId, $farmRegionId, $exitNodeId);
    $summary = is_array($complete['run_summary'] ?? null) ? $complete['run_summary'] : [];
    $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];

    $this->assertNotContains(UserUnlockService::FEATURE_SHOP, $meta['new_feature_unlocks'] ?? []);
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = ?",
        [$userId, UserUnlockService::FEATURE_SHOP]
      )
    );
  }

  public function testCompleteSwampsRunGrantsWrongMachineFeatureOnce(): void
  {
    $userId = $this->insertUser();
    $swampsRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1", []);
    $this->assertGreaterThan(0, $swampsRegionId);

    $firstRunId = $this->insertRun($userId, $swampsRegionId, 42434445);
    $firstExitNodeId = $this->insertRunNode($firstRunId, 'exit', 'available');

    $first = $this->service()->completeRun($userId, $firstRunId, $swampsRegionId, $firstExitNodeId);
    $firstSummary = is_array($first['run_summary'] ?? null) ? $first['run_summary'] : [];
    $firstMeta = is_array($firstSummary['meta'] ?? null) ? $firstSummary['meta'] : [];

    $this->assertSame('completed', $first['status']);
    $this->assertContains(UserUnlockService::FEATURE_WRONG_MACHINE, $firstMeta['new_feature_unlocks'] ?? []);

    $unlockService = new UserUnlockService($this->pdo);
    $this->assertTrue($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE));

    $secondRunId = $this->insertRun($userId, $swampsRegionId, 52535455);
    $secondExitNodeId = $this->insertRunNode($secondRunId, 'exit', 'available');

    $second = $this->service()->completeRun($userId, $secondRunId, $swampsRegionId, $secondExitNodeId);
    $secondSummary = is_array($second['run_summary'] ?? null) ? $second['run_summary'] : [];
    $secondMeta = is_array($secondSummary['meta'] ?? null) ? $secondSummary['meta'] : [];

    $this->assertSame('completed', $second['status']);
    $this->assertNotContains(UserUnlockService::FEATURE_WRONG_MACHINE, $secondMeta['new_feature_unlocks'] ?? []);
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = ?",
        [$userId, UserUnlockService::FEATURE_WRONG_MACHINE]
      )
    );
  }

  public function testClaimBattleAppliesRewardsAndStoresIdempotentClaimSnapshot(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 51525354);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 12, false);
    $this->setSoftCurrency($userId, 4);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'victory', 61626364, 60, 3);
    $this->insertBattleRewards($battleId, 8, 5, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $first = $this->service()->claimBattle($userId, $battleId);
    $second = $this->service()->claimBattle($userId, $battleId);

    $this->assertTrue($first['newly_claimed']);
    $this->assertFalse($second['newly_claimed']);
    $this->assertSame('claimed', (string)$first['battle']['status']);
    $this->assertSame('claimed', (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$battleId]));
    $this->assertSame('8', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
    $this->assertSame('9', (string)$this->scalar('SELECT `currency_soft` FROM `player_state` WHERE `user_id` = ?', [$userId]));

    $firstSnapshot = is_array($first['claim_snapshot'] ?? null) ? $first['claim_snapshot'] : [];
    $secondSnapshot = is_array($second['claim_snapshot'] ?? null) ? $second['claim_snapshot'] : [];
    $this->assertEquals($firstSnapshot, $secondSnapshot);
    $this->assertSame([(string)$unitId], $firstSnapshot['xp']['applied_unit_instance_ids'] ?? null);

    $storedRewards = json_decode((string)$this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$battleId]), true);
    $this->assertIsArray($storedRewards);
    $this->assertIsArray($storedRewards['claim_snapshot'] ?? null);
  }

  private function service(): RunLifecycleService
  {
    $pdo = $this->pdo;
    \assert($pdo instanceof \PDO);

    return new RunLifecycleService(
      $pdo,
      new RunRepository($pdo),
      new RegionRepository($pdo),
      new RunNodeRepository($pdo),
    );
  }
}
