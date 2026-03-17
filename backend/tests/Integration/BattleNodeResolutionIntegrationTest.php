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
    $this->assertSame(200, $second['status']);

    $battleId = (int)($first['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $this->assertSame($battleId, (int)($second['body']['data']['battle']['battle_id'] ?? 0));

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
    $this->assertSame(200, $second['status']);

    $newBattleId = (int)($first['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $newBattleId);
    $this->assertNotSame($oldBattleId, $newBattleId);
    $this->assertSame($newBattleId, (int)($second['body']['data']['battle']['battle_id'] ?? 0));

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
}