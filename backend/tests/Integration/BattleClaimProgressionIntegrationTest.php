<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class BattleClaimProgressionIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run battle claim integration tests.';
  }

  public function testClaimBattleAppliesXpOnceAndReturnsIdempotentSnapshot(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 55667788);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, $maxLevel] = $this->pickUnitTypeForProgressTest();

    $eligibleUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $maxedUnitId = $this->insertUnit($userId, $unitTypeId, $maxLevel, 5);
    $defeatedUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);

    $this->insertTeamUnit($teamId, $eligibleUnitId);
    $this->insertTeamUnit($teamId, $maxedUnitId);
    $this->insertTeamUnit($teamId, $defeatedUnitId);

    $this->insertRunUnitState($runId, $eligibleUnitId, 12, false);
    $this->insertRunUnitState($runId, $maxedUnitId, 14, false);
    $this->insertRunUnitState($runId, $defeatedUnitId, 0, true);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'victory', 99112233, 60, 3);
    $this->insertBattleRewards($battleId, 20, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $first = $this->invoke(fn() => $controller->claimBattle((string)$battleId));
    $second = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(200, $first['status']);
    $this->assertSame(200, $second['status']);

    $firstData = is_array($first['body']['data'] ?? null) ? $first['body']['data'] : [];
    $secondData = is_array($second['body']['data'] ?? null) ? $second['body']['data'] : [];

    $this->assertSame('claimed', (string)($firstData['status'] ?? ''));
    $this->assertSame($firstData, $secondData, 'Claim response should be idempotent across repeated calls.');

    $xp = is_array($firstData['xp'] ?? null) ? $firstData['xp'] : [];
    $applied = is_array($xp['applied_unit_instance_ids'] ?? null) ? $xp['applied_unit_instance_ids'] : [];
    $ignored = is_array($xp['ignored_at_cap_unit_instance_ids'] ?? null) ? $xp['ignored_at_cap_unit_instance_ids'] : [];

    $this->assertContains((string)$eligibleUnitId, $applied);
    $this->assertContains((string)$maxedUnitId, $ignored);
    $this->assertNotContains((string)$defeatedUnitId, $applied);
    $this->assertNotContains((string)$defeatedUnitId, $ignored);

    $this->assertSame('20', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$eligibleUnitId]));
    $this->assertSame('5', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$maxedUnitId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$defeatedUnitId]));

    $updatedRunState = is_array($firstData['updated_run_unit_state'] ?? null) ? $firstData['updated_run_unit_state'] : [];
    $stateByUnit = [];
    foreach ($updatedRunState as $row) {
      if (!is_array($row)) {
        continue;
      }
      $stateByUnit[(string)($row['unit_instance_id'] ?? '')] = $row;
    }
    $this->assertArrayHasKey((string)$eligibleUnitId, $stateByUnit);
    $this->assertArrayHasKey((string)$maxedUnitId, $stateByUnit);
    $this->assertArrayHasKey((string)$defeatedUnitId, $stateByUnit);
    $this->assertArrayHasKey('is_defeated', $stateByUnit[(string)$eligibleUnitId]);

    $eligibleHp = (int)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?', [$runId, $eligibleUnitId]);
    $maxedHp = (int)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?', [$runId, $maxedUnitId]);
    $defeatedHp = (int)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?', [$runId, $defeatedUnitId]);
    $this->assertLessThan(12, $eligibleHp);
    $this->assertLessThan(14, $maxedHp);
    $this->assertSame(0, $defeatedHp);

    $rewardsRaw = $this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$battleId]);
    $rewards = json_decode((string)$rewardsRaw, true);
    $this->assertIsArray($rewards);
    $this->assertArrayHasKey('claim_snapshot', $rewards);
    $this->assertIsArray($rewards['claim_snapshot']);
  }

  public function testClaimBattleMaintainsProgressionInvariantsAcrossRepeatedClaims(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 77889911);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitA = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $unitB = $this->insertUnit($userId, $unitTypeId, 1, 3);

    $this->insertTeamUnit($teamId, $unitA);
    $this->insertTeamUnit($teamId, $unitB);
    $this->insertRunUnitState($runId, $unitA, 10, false);
    $this->insertRunUnitState($runId, $unitB, 8, false);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'victory', 10293847, 60, 3);
    $this->insertBattleRewards($battleId, 11, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $first = $this->invoke(fn() => $controller->claimBattle((string)$battleId));
    $second = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(200, $first['status']);
    $this->assertSame(200, $second['status']);

    $this->assertSame('11', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitA]));
    $this->assertSame('14', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitB]));

    $stateRows = $this->rows(
      'SELECT rus.`unit_instance_id`, rus.`current_hp`, rus.`is_defeated`, ui.`level`, ut.`base_stats_json`, ut.`max_hp_per_level`
       FROM `run_unit_state` rus
       JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
       JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
       WHERE rus.`run_id` = ?
       ORDER BY rus.`unit_instance_id` ASC',
      [$runId]
    );

    $this->assertCount(2, $stateRows);
    foreach ($stateRows as $row) {
      $baseStats = json_decode((string)$row['base_stats_json'], true);
      $this->assertIsArray($baseStats);
      $level = max(1, (int)$row['level']);
      $baseMaxHp = max(1, (int)($baseStats['max_hp'] ?? 1));
      $maxHpPerLevel = max(0, (int)$row['max_hp_per_level']);
      $maxHp = $baseMaxHp + (($level - 1) * $maxHpPerLevel);

      $hp = (int)$row['current_hp'];
      $defeated = (int)$row['is_defeated'] === 1;

      $this->assertGreaterThanOrEqual(0, $hp);
      $this->assertLessThanOrEqual($maxHp, $hp);
      $this->assertSame($hp === 0, $defeated);
    }

    $firstData = is_array($first['body']['data'] ?? null) ? $first['body']['data'] : [];
    $secondData = is_array($second['body']['data'] ?? null) ? $second['body']['data'] : [];
    $this->assertSame($firstData['xp'] ?? null, $secondData['xp'] ?? null);
    $this->assertSame($firstData['updated_run_unit_state'] ?? null, $secondData['updated_run_unit_state'] ?? null);
  }

  public function testClaimBattleReturnsTypedRewardLabelsAndRunWideSummary(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 99118822);
    $firstNodeId = $this->insertRunNode($runId, 'combat', 'cleared');
    $secondNodeId = $this->insertRunNode($runId, 'boss', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 12, false);

    $unitTypeRow = $this->rows('SELECT `slug`, `name` FROM `unit_types` WHERE `id` = ? LIMIT 1', [$unitTypeId]);
    $this->assertCount(1, $unitTypeRow);
    $rewardedUnitSlug = (string)$unitTypeRow[0]['slug'];
    $rewardedUnitName = (string)$unitTypeRow[0]['name'];

    $diceDefinitionRow = $this->rows(
      "SELECT `id`, `rarity`, `sides` FROM `dice_definitions` WHERE `rarity` = 'rare' AND `sides` = 6 ORDER BY `id` ASC LIMIT 1",
      []
    );
    $this->assertCount(1, $diceDefinitionRow);
    $rewardedDiceDefinitionId = (int)$diceDefinitionRow[0]['id'];
    $rewardedDiceId = $this->insertDiceInstance($userId, $rewardedDiceDefinitionId);

    $claimedBattleId = $this->insertBattle($userId, $runId, $firstNodeId, $teamId, 'claimed', 'victory', 10101010, 40, 2);
    $this->insertBattleRewards($claimedBattleId, 10, 7, [
      'unit_grants' => [
        ['unit_type_slug' => $rewardedUnitSlug, 'tier' => 1, 'level' => 1],
      ],
      'new_unit_instance_ids' => [],
      'new_dice_instance_ids' => [],
      'region_items' => [],
      'claim_snapshot' => [
        'updated_run_unit_state' => [
          ['unit_instance_id' => (string)$unitId, 'hp' => 10, 'is_defeated' => false, 'status_effects' => []],
        ],
        'run_resolution' => null,
        'xp' => [
          'award_per_unit' => 10,
          'applied_unit_instance_ids' => [(string)$unitId],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'currency' => ['soft_awarded' => 7],
        'updated_units' => [
          ['id' => (string)$unitId, 'xp' => 10, 'level' => 1, 'name' => $rewardedUnitName],
        ],
      ],
    ]);

    $currentBattleId = $this->insertBattle($userId, $runId, $secondNodeId, $teamId, 'completed', 'victory', 20202020, 60, 3);
    $this->insertBattleRewards($currentBattleId, 12, 5, [
      'new_unit_instance_ids' => [],
      'new_dice_instance_ids' => [(string)$rewardedDiceId],
      'dice_grants' => [
        ['rarity' => 'rare', 'sides' => 6],
      ],
      'region_items' => [],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$currentBattleId));
    $this->assertSame(200, $res['status']);

    $data = is_array($res['body']['data'] ?? null) ? $res['body']['data'] : [];
    $rewards = is_array($data['rewards'] ?? null) ? $data['rewards'] : [];
    $runSummary = is_array($data['run_summary'] ?? null) ? $data['run_summary'] : [];

    $this->assertSame(['bone d6'], $rewards['new_dice_labels'] ?? null);
    $this->assertIsArray($runSummary['rewards'] ?? null);
    $this->assertContains('Teeth +12', $runSummary['rewards'] ?? []);
    $this->assertContains(sprintf('New Units: %s', $rewardedUnitName), $runSummary['rewards'] ?? []);
    $this->assertContains('New Dice: bone d6', $runSummary['rewards'] ?? []);
    $this->assertContains(sprintf('%s +22 XP', $rewardedUnitName), $runSummary['progression'] ?? []);
  }

  public function testClaimDefeatWithNoRemainingUnitsFailsRunAndResetsDefeatedXp(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 44556677);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitA = $this->insertUnit($userId, $unitTypeId, 1, 30);
    $unitB = $this->insertUnit($userId, $unitTypeId, 1, 40);

    $this->insertTeamUnit($teamId, $unitA);
    $this->insertTeamUnit($teamId, $unitB);
    $this->insertRunUnitState($runId, $unitA, 1, false);
    $this->insertRunUnitState($runId, $unitB, 1, false);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'defeat', 1234567, 60, 3);
    $this->insertBattleRewards($battleId, 10, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$battleId));
    $this->assertSame(200, $res['status']);

    $data = is_array($res['body']['data'] ?? null) ? $res['body']['data'] : [];
    $runResolution = is_array($data['run_resolution'] ?? null) ? $data['run_resolution'] : [];
    $this->assertSame('failed', (string)($runResolution['status'] ?? ''));
    $runSummary = is_array($data['run_summary'] ?? null) ? $data['run_summary'] : [];
    $this->assertSame([], $runSummary['survivors'] ?? null);
    $this->assertCount(2, $runSummary['defeated'] ?? []);

    $runStatus = (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $this->assertSame('failed', $runStatus);
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitA]));
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitB]));

    $stateRows = $this->rows(
      'SELECT `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_instance_id` ASC',
      [$runId]
    );
    $this->assertCount(2, $stateRows);
    foreach ($stateRows as $row) {
      $this->assertSame('0', (string)$row['is_defeated']);
      $this->assertSame('{}', (string)$row['cooldowns_json']);
      $this->assertSame('[]', (string)$row['status_effects_json']);
      $this->assertGreaterThan(0, (int)$row['current_hp']);
    }
  }

  public function testClaimAlreadyFailedDefeatBattleReturnsStablePayload(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 88990011, 'failed');
    $nodeId = $this->insertRunNode($runId, 'boss', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitA = $this->insertUnit($userId, $unitTypeId, 1, 12);
    $unitB = $this->insertUnit($userId, $unitTypeId, 1, 9);

    $this->insertTeamUnit($teamId, $unitA);
    $this->insertTeamUnit($teamId, $unitB);
    $this->insertRunUnitState($runId, $unitA, 15, false);
    $this->insertRunUnitState($runId, $unitB, 13, false);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'defeat', 445566, 20, 1);
    $this->insertBattleRewards($battleId, 10, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $first = $this->invoke(fn() => $controller->claimBattle((string)$battleId));
    $second = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(200, $first['status']);
    $this->assertSame(200, $second['status']);

    $firstData = is_array($first['body']['data'] ?? null) ? $first['body']['data'] : [];
    $secondData = is_array($second['body']['data'] ?? null) ? $second['body']['data'] : [];
    $this->assertSame($firstData, $secondData);
    $this->assertSame('claimed', (string)($firstData['status'] ?? ''));

    $runResolution = is_array($firstData['run_resolution'] ?? null) ? $firstData['run_resolution'] : [];
    $this->assertSame('failed', (string)($runResolution['status'] ?? ''));

    $this->assertSame('failed', (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]));
    $this->assertSame('claimed', (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$battleId]));
    $this->assertSame('12', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitA]));
    $this->assertSame('9', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitB]));
  }
}
