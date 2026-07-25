<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Controllers\GameplayController;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class RunStateCleanupIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run-state cleanup integration tests.';
  }

  public function testAbandonRunEndpointAppliesCleanupAndMarksRunAbandoned(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId, 99887766);

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 15);
    $this->insertRunUnitState($runId, $unitId, 2, true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->abandonRun((string)$runId));
    $this->assertSame(200, $res['status']);
    $this->assertSame('abandoned', (string)($res['body']['data']['status'] ?? ''));

    $runStatus = (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $this->assertSame('abandoned', $runStatus);
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));

    $state = $this->rows(
      'SELECT `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?',
      [$runId, $unitId]
    );
    $this->assertCount(1, $state);
    $this->assertGreaterThan(0, (int)$state[0]['current_hp']);
    $this->assertSame('0', (string)$state[0]['is_defeated']);
    $this->assertSame('{}', (string)$state[0]['cooldowns_json']);
    $this->assertSame('[]', (string)$state[0]['status_effects_json']);
  }

  public function testAbandonRunSummaryKeepsPreCleanupProgressionSnapshot(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 12344321);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 3, 20);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 0, true);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'victory', 999111, 20, 2);
    $this->insertBattleRewards($battleId, 10, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
      'claim_snapshot' => [
        'xp' => [
          'award_per_unit' => 20,
          'applied_unit_instance_ids' => [(string)$unitId],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => [
          ['id' => (string)$unitId, 'xp' => 20, 'level' => 3, 'name' => 'Progress Test Unit'],
        ],
      ],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->abandonRun((string)$runId));
    $this->assertSame(200, $res['status']);

    $summary = is_array($res['body']['data']['run_summary'] ?? null) ? $res['body']['data']['run_summary'] : [];
    $progressionDetail = array_values(array_filter($summary['progression_detail'] ?? [], 'is_array'));
    $this->assertCount(1, $progressionDetail);
    $this->assertSame((string)$unitId, (string)($progressionDetail[0]['unit_instance_id'] ?? ''));
    $this->assertSame(20, (int)($progressionDetail[0]['xp_gained'] ?? 0));
    $this->assertSame(0, (int)($progressionDetail[0]['level_gain_count'] ?? -1));
    $this->assertSame(20, (int)($progressionDetail[0]['final_xp'] ?? -1));

    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  public function testAbandonRunSummaryIncludesZeroXpDefeatedUnitsInProgressionDetail(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 98761234);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $xpUnitId = $this->insertUnit($userId, $unitTypeId, 2, 25);
    $defeatedUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);

    $this->insertTeamUnit($teamId, $xpUnitId);
    $this->insertTeamUnit($teamId, $defeatedUnitId);
    $this->insertRunUnitState($runId, $xpUnitId, 6, false);
    $this->insertRunUnitState($runId, $defeatedUnitId, 0, true);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'victory', 444999, 20, 2);
    $this->insertBattleRewards($battleId, 10, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
      'claim_snapshot' => [
        'xp' => [
          'award_per_unit' => 20,
          'applied_unit_instance_ids' => [(string)$xpUnitId],
          'ignored_at_cap_unit_instance_ids' => [],
        ],
        'updated_units' => [
          ['id' => (string)$xpUnitId, 'xp' => 25, 'level' => 2, 'name' => 'XP Unit'],
          ['id' => (string)$defeatedUnitId, 'xp' => 0, 'level' => 1, 'name' => 'Defeated Unit'],
        ],
      ],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->abandonRun((string)$runId));
    $this->assertSame(200, $res['status']);

    $summary = is_array($res['body']['data']['run_summary'] ?? null) ? $res['body']['data']['run_summary'] : [];
    $progressionDetail = array_values(array_filter($summary['progression_detail'] ?? [], 'is_array'));
    $this->assertCount(2, $progressionDetail);

    $detailById = [];
    foreach ($progressionDetail as $entry) {
      $detailById[(string)($entry['unit_instance_id'] ?? '')] = $entry;
    }

    $this->assertArrayHasKey((string)$xpUnitId, $detailById);
    $this->assertArrayHasKey((string)$defeatedUnitId, $detailById);
    $this->assertSame(20, (int)($detailById[(string)$xpUnitId]['xp_gained'] ?? -1));
    $this->assertSame(0, (int)($detailById[(string)$defeatedUnitId]['xp_gained'] ?? -1));
    $this->assertTrue((bool)($detailById[(string)$defeatedUnitId]['is_defeated'] ?? false));
  }

  public function testExitRunEndpointCompletesRunAndPreservesUnitXp(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId, 44332211);

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 25);
    $this->insertRunUnitState($runId, $unitId, 2, true);
    $exitNodeId = $this->insertRunNode($runId, 'exit', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->exitRun((string)$runId));
    $this->assertSame(200, $res['status']);
    $this->assertSame('completed', (string)($res['body']['data']['status'] ?? ''));
    $this->assertSame((string)$exitNodeId, (string)($res['body']['data']['exit_node_id'] ?? ''));

    $runStatus = (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $this->assertSame('completed', $runStatus);

    $exitNodeStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$exitNodeId]);
    $this->assertSame('cleared', $exitNodeStatus);

    $this->assertSame('25', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));

    $state = $this->rows(
      'SELECT `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?',
      [$runId, $unitId]
    );
    $this->assertCount(1, $state);
    $this->assertGreaterThan(0, (int)$state[0]['current_hp']);
    $this->assertSame('0', (string)$state[0]['is_defeated']);
    $this->assertSame('{}', (string)$state[0]['cooldowns_json']);
    $this->assertSame('[]', (string)$state[0]['status_effects_json']);
  }

  public function testExitRunEndpointReturnsConflictWhenExitNodeIsNotAvailable(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId, 88990011);
    $this->insertRunNode($runId, 'exit', 'locked');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->exitRun((string)$runId));
    $this->assertSame(409, $res['status']);
    $this->assertSame('run_exit_unavailable', (string)($res['body']['error']['code'] ?? ''));

    $runStatus = (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $this->assertSame('active', $runStatus);
  }

  public function testRestWorkflowHealsFinalizeAndAutoLevel(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 515151);
    $restNodeId = $this->insertRunNode($runId, 'rest', 'available');
    $nextNodeId = $this->insertRunNode($runId, 'combat', 'locked');
    $this->insertRunEdge($runId, $restNodeId, $nextNodeId);

    [$unitTypeId, $maxLevel] = $this->pickUnitTypeForProgressTest();
    $levelForPromo = min($maxLevel, 1);
    $u1 = $this->insertUnit($userId, $unitTypeId, $levelForPromo, 120);
    $u2 = $this->insertUnit($userId, $unitTypeId, $levelForPromo, 0);
    $this->insertTeamUnit($teamId, $u1);
    $this->insertTeamUnit($teamId, $u2);
    $this->insertRunUnitState($runId, $u1, 8, false);
    $this->insertRunUnitState($runId, $u2, 1, true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([]);

    $gameplay = new GameplayController();
    $openRes = $this->invoke(fn() => $gameplay->openRest((string)$runId, (string)$restNodeId));
    $this->assertSame(200, $openRes['status']);
    $this->assertArrayNotHasKey('formation', $openRes['body']['data']);
    $this->assertArrayNotHasKey('unit_ids', $openRes['body']['data']);

    $this->setJsonBody([]);
    $finalizeRes = $this->invoke(fn() => $gameplay->finalizeRest((string)$runId, (string)$restNodeId));
    $this->assertSame(200, $finalizeRes['status'], json_encode($finalizeRes['body']));

    $restStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$restNodeId]);
    $this->assertSame('cleared', $restStatus);
    $nextStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nextNodeId]);
    $this->assertSame('available', $nextStatus);

    $runUnits = $this->rows(
      'SELECT `unit_instance_id`, `current_hp`, `is_defeated` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_instance_id` ASC',
      [$runId]
    );
    $this->assertSame([(string)$u1, (string)$u2], array_map(static fn(array $r): string => (string)$r['unit_instance_id'], $runUnits));
    $this->assertGreaterThan(8, (int)$runUnits[0]['current_hp']);
    $this->assertGreaterThan(1, (int)$runUnits[1]['current_hp']);
    $this->assertSame(0, (int)$runUnits[0]['is_defeated']);
    $this->assertSame(0, (int)$runUnits[1]['is_defeated']);

    $u1Level = (int)$this->scalar('SELECT `level` FROM `unit_instances` WHERE `id` = ?', [$u1]);
    $u1Xp = (int)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$u1]);
    $this->assertGreaterThanOrEqual(1, $u1Level);
    $this->assertLessThan(120, $u1Xp);
  }

  public function testFinalizeRestUnlocksConvergingChildWhenAnyParentClears(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 919191);

    $restNodeId = $this->insertRunNode($runId, 'rest', 'available');
    $otherParentNodeId = $this->insertRunNode($runId, 'combat', 'locked');
    $sharedNextNodeId = $this->insertRunNode($runId, 'combat', 'locked');

    $this->insertRunEdge($runId, $restNodeId, $sharedNextNodeId);
    $this->insertRunEdge($runId, $otherParentNodeId, $sharedNextNodeId);

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 8, false);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([]);

    $gameplay = new GameplayController();
    $finalizeRes = $this->invoke(fn() => $gameplay->finalizeRest((string)$runId, (string)$restNodeId));

    $this->assertSame(200, $finalizeRes['status'], json_encode($finalizeRes['body']));
    $unlockedNodeIds = $finalizeRes['body']['data']['next']['unlocked_node_ids'] ?? [];
    $this->assertContains((string)$sharedNextNodeId, is_array($unlockedNodeIds) ? $unlockedNodeIds : []);

    $sharedStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$sharedNextNodeId]);
    $this->assertSame('available', $sharedStatus);
  }

  public function testPromotionAndDiceEndpointsRejectActiveRunUnitsEvenWithRestContext(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 616161);
    $restNodeId = $this->insertRunNode($runId, 'rest', 'available');

    [$unitTypeId, $maxLevel] = $this->pickUnitTypeForProgressTest();
    $primary = $this->insertUnit($userId, $unitTypeId, $maxLevel, 0);
    $secondaryA = $this->insertUnit($userId, $unitTypeId, $maxLevel, 0);
    $secondaryB = $this->insertUnit($userId, $unitTypeId, $maxLevel, 0);
    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($primary, $unitTypeId);
    $this->insertTeamUnit($teamId, $primary);
    $this->insertRunUnitState($runId, $primary, 10, false);
    $this->insertRunUnitState($runId, $secondaryA, 10, false);

    $diceDefId = $this->pickAnyDiceDefinitionId();
    $diceId = $this->insertDiceInstance($userId, $diceDefId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $gameplay = new GameplayController();

    $this->setJsonBody(['dice_instance_id' => (string)$diceId]);
    $equipBlocked = $this->invoke(fn() => $gameplay->assignAbilitySlotDie((string)$primary, 'heavy_strike', '0'));
    $this->assertSame(409, $equipBlocked['status']);
    $this->assertSame('active_run_unit_locked', (string)($equipBlocked['body']['error']['code'] ?? ''));

    $this->setJsonBody([
      'dice_instance_id' => (string)$diceId,
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ]);
    $equipStillBlocked = $this->invoke(fn() => $gameplay->assignAbilitySlotDie((string)$primary, 'heavy_strike', '0'));
    $this->assertSame(409, $equipStillBlocked['status']);
    $this->assertSame('active_run_unit_locked', (string)($equipStillBlocked['body']['error']['code'] ?? ''));

    $this->setJsonBody([
      'primary_unit_instance_id' => (string)$primary,
      'secondary_unit_instance_ids' => [(string)$secondaryA, (string)$secondaryB],
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ]);
    $promoteBlocked = $this->invoke(fn() => $gameplay->promoteUnit((string)$primary));
    $this->assertSame(409, $promoteBlocked['status']);
    $this->assertSame('unit_in_active_run', (string)($promoteBlocked['body']['error']['code'] ?? ''));

    $this->setJsonBody([
      'primary_unit_instance_id' => (string)$primary,
      'secondary_unit_instance_ids' => [(string)$secondaryA, (string)$secondaryB],
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ]);
    $promoteStillBlocked = $this->invoke(fn() => $gameplay->promoteUnit((string)$primary));
    $this->assertSame(409, $promoteStillBlocked['status']);
    $this->assertSame('unit_in_active_run', (string)($promoteStillBlocked['body']['error']['code'] ?? ''));
  }
}
