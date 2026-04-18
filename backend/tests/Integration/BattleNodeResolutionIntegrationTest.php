<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class BattleNodeResolutionIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run battle resolution integration tests.';
  }

  public function testResolveNodeUsesDeterministicEngineAndPersistsCanonicalLog(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 11223344);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $first = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $second = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $first['status']);

    $battleId = (int)($first['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $firstOutcome = (string)($first['body']['data']['battle']['outcome'] ?? '');
    if ($firstOutcome === 'defeat') {
      $this->assertSame(409, $second['status']);
      $this->assertSame('run_not_active', (string)($second['body']['error']['code'] ?? ''));
    } else {
      $this->assertSame(200, $second['status']);
      $this->assertSame($battleId, (int)($second['body']['data']['battle']['battle_id'] ?? 0));
    }

    $logRaw = $this->scalar('SELECT `log_json` FROM `battle_logs` WHERE `battle_id` = ?', [$battleId]);
    $log = json_decode((string)$logRaw, true);
    $this->assertIsArray($log);

    $meta = is_array($log['meta'] ?? null) ? $log['meta'] : [];
    $events = is_array($log['events'] ?? null) ? $log['events'] : [];

    $this->assertSame('deterministic_v1', (string)($meta['engine'] ?? ''));
    $this->assertGreaterThan(0, (int)($meta['rng']['seed'] ?? 0));
    $this->assertSame((string)$runId, (string)($meta['run_id'] ?? ''));
    $this->assertSame((string)$nodeId, (string)($meta['node_id'] ?? ''));

    $eventTypes = array_map(
      static fn($event): string => is_array($event) ? (string)($event['type'] ?? '') : '',
      $events
    );
    $this->assertContains('battle_start', $eventTypes);
    $this->assertContains('battle_end', $eventTypes);
    $this->assertNotContains('note', $eventTypes, 'Placeholder note event should not be present.');
  }

  public function testResolveNodeAllowsRetryAfterClaimedDefeat(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 33322211);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 10, false);

    $oldBattleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'defeat', 111111, 60, 3);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $first = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $second = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $first['status']);

    $newBattleId = (int)($first['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $newBattleId);
    $this->assertNotSame($oldBattleId, $newBattleId);
    $newOutcome = (string)($first['body']['data']['battle']['outcome'] ?? '');
    if ($newOutcome === 'defeat') {
      $this->assertSame(409, $second['status']);
      $this->assertSame('run_not_active', (string)($second['body']['error']['code'] ?? ''));
    } else {
      $this->assertSame(200, $second['status']);
      $this->assertSame($newBattleId, (int)($second['body']['data']['battle']['battle_id'] ?? 0));
    }

    $this->assertSame(
      '0',
      (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `id` = ?', [$oldBattleId])
    );
    $this->assertSame(
      '1',
      (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `node_id` = ?', [$runId, $nodeId])
    );
  }

  public function testResolveNodeRewardEconomyFixturesStayWithinExpectedBounds(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 20260304);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();

    $restNodeId = $this->insertRunNode($runId, 'rest', 'available');
    $restRes = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$restNodeId));
    $this->assertSame(200, $restRes['status']);
    $restBattleId = (int)($restRes['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $restBattleId);
    [$restXp, $restSoft] = $this->battleRewardTuple($restBattleId);
    $this->assertSame(0, $restXp);
    $this->assertSame(0, $restSoft);

    $lootNodeId = $this->insertRunNode($runId, 'loot', 'available');
    $lootRes = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$lootNodeId));
    $this->assertSame(200, $lootRes['status']);
    $lootBattleId = (int)($lootRes['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $lootBattleId);
    [$lootXp, $lootSoft] = $this->battleRewardTuple($lootBattleId);
    $this->assertSame(0, $lootXp);
    $this->assertSame(5, $lootSoft);

    $combatNodeId = $this->insertRunNode($runId, 'combat', 'available');
    $combatRes = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$combatNodeId));
    $this->assertSame(200, $combatRes['status']);
    $combatBattleId = (int)($combatRes['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $combatBattleId);
    [$combatXp, $combatSoft] = $this->battleRewardTuple($combatBattleId);
    $outcome = (string)($combatRes['body']['data']['battle']['outcome'] ?? '');
    $this->assertContains($outcome, ['victory', 'defeat']);

    if ($outcome === 'victory') {
      $this->assertGreaterThan(0, $combatXp);
      $this->assertGreaterThanOrEqual(3, $combatSoft);
      $this->assertLessThanOrEqual(7, $combatSoft);
    } else {
      $this->assertGreaterThanOrEqual(0, $combatXp);
      $this->assertSame(0, $combatSoft);
    }

    foreach ([$restBattleId, $lootBattleId, $combatBattleId] as $battleId) {
      $rewardsRaw = (string)$this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$battleId]);
      $rewards = json_decode($rewardsRaw, true);
      $this->assertIsArray($rewards);
      $this->assertArrayHasKey('new_dice_instance_ids', $rewards);
      $this->assertArrayHasKey('region_items', $rewards);
      $this->assertIsArray($rewards['new_dice_instance_ids']);
      $this->assertIsArray($rewards['region_items']);
    }
  }

  public function testResolveNodeSchedulesMultipleRoundActionsAndExcludesPassives(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 90909090);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $unitTypeRows = $this->rows('SELECT `id` FROM `unit_types` ORDER BY `id` ASC LIMIT 2', []);
    $this->assertCount(2, $unitTypeRows);

    foreach ($unitTypeRows as $row) {
      $unitId = $this->insertUnit($userId, (int)$row['id'], 1, 0);
      $this->insertTeamUnit($teamId, $unitId);
      $this->insertRunUnitState($runId, $unitId, 20, false);
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $response = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $response['status']);

    $battleId = (int)($response['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);

    $logRaw = $this->scalar('SELECT `log_json` FROM `battle_logs` WHERE `battle_id` = ?', [$battleId]);
    $log = json_decode((string)$logRaw, true);
    $this->assertIsArray($log);

    $events = is_array($log['events'] ?? null) ? $log['events'] : [];

    $roundOneActions = array_values(array_filter(
      $events,
      static fn($event): bool => is_array($event)
        && (string)($event['type'] ?? '') === 'action'
        && (int)($event['round'] ?? 0) === 1
    ));

    $this->assertGreaterThan(
      2,
      count($roundOneActions),
      'Round one should schedule more than one action per side when multiple units and active abilities are present.'
    );

    $roundOneTickSet = [];
    foreach ($roundOneActions as $event) {
      $tick = (int)($event['tick'] ?? 0);
      $roundOneTickSet[$tick] = true;

      $abilityId = (string)($event['ability_id'] ?? '');
      $this->assertNotContains(
        $abilityId,
        ['thick_hide', 'sharpshooter', 'toxic_training'],
        'Passive abilities must never be emitted as action events.'
      );

      $this->assertIsArray($event['dice_used'] ?? null, 'Action events must include dice_used metadata.');
      $this->assertIsArray($event['dice_rolls'] ?? null, 'Action events must include dice_rolls metadata.');
      $this->assertIsString($event['dice_outcome'] ?? null, 'Action events must include dice_outcome summary.');
      $this->assertIsString($event['ability_outcome'] ?? null, 'Action events must include ability_outcome summary.');
    }

    $this->assertGreaterThan(2, count($roundOneTickSet), 'Round one should contain action ticks beyond the previous fixed two-tick cadence.');
  }

  public function testResolveNodeUsesD1FallbackForPlayerEmptyDiceSlots(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 45454545);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 20, false);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $response = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $response['status']);

    $battleId = (int)($response['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);

    $logRaw = $this->scalar('SELECT `log_json` FROM `battle_logs` WHERE `battle_id` = ?', [$battleId]);
    $log = json_decode((string)$logRaw, true);
    $this->assertIsArray($log);
    $events = is_array($log['events'] ?? null) ? $log['events'] : [];

    $playerAction = null;
    foreach ($events as $event) {
      if (is_array($event) && (string)($event['type'] ?? '') === 'action' && (string)($event['side'] ?? '') === 'player') {
        $playerAction = $event;
        break;
      }
    }

    $this->assertIsArray($playerAction, 'Expected at least one player action event.');
    $diceUsed = is_array($playerAction['dice_used'] ?? null) ? $playerAction['dice_used'] : [];
    $diceRolls = is_array($playerAction['dice_rolls'] ?? null) ? $playerAction['dice_rolls'] : [];
    $this->assertNotSame([], $diceUsed);
    $this->assertNotSame([], $diceRolls);
    $this->assertSame('empty_slot', (string)($diceUsed[0]['kind'] ?? ''));
    $this->assertSame(1, (int)($diceUsed[0]['sides'] ?? 0));
    $this->assertSame(1, (int)($diceRolls[0]['sides'] ?? 0));
    $this->assertSame(1, (int)($diceRolls[0]['roll'] ?? 0));
  }

  public function testResolveNodeDefeatEndsRunImmediately(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 12121212);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $response = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $response['status']);

    $battle = $response['body']['data']['battle'] ?? [];
    $outcome = (string)($battle['outcome'] ?? '');
    $this->assertSame('defeat', $outcome);

    $this->assertSame('failed', (string)($response['body']['data']['node']['status'] ?? ''));

    $runRow = $this->rows('SELECT `status`, `ended_at` FROM `region_runs` WHERE `id` = ? LIMIT 1', [$runId]);
    $this->assertCount(1, $runRow);
    $this->assertSame('failed', (string)$runRow[0]['status']);
    $this->assertNotNull($runRow[0]['ended_at']);
  }
}
