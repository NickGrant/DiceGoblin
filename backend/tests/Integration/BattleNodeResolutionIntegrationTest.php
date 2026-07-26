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

    $hazardNodeId = $this->insertRunNode($runId, 'hazard', 'available');
    $hazardRes = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$hazardNodeId));
    $this->assertSame(200, $hazardRes['status']);
    $hazardBattleId = (int)($hazardRes['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $hazardBattleId);
    $hazardPreview = $hazardRes['body']['data']['battle']['reward_preview'] ?? null;
    $this->assertIsArray($hazardPreview);
    $this->assertSame('hazard', (string)($hazardPreview['node_type'] ?? ''));
    $this->assertSame('hazard_avoided', (string)($hazardRes['body']['data']['battle']['log']['events'][0]['message'] ?? ''));
    [$hazardXp, $hazardSoft] = $this->battleRewardTuple($hazardBattleId);
    $this->assertSame(0, $hazardXp);
    $this->assertSame(0, $hazardSoft);

    $shrineNodeId = $this->insertRunNode($runId, 'shrine', 'available');
    $shrineRes = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$shrineNodeId));
    $this->assertSame(200, $shrineRes['status']);
    $shrineBattleId = (int)($shrineRes['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $shrineBattleId);
    $shrinePreview = $shrineRes['body']['data']['battle']['reward_preview'] ?? null;
    $this->assertIsArray($shrinePreview);
    $this->assertSame('shrine', (string)($shrinePreview['node_type'] ?? ''));
    $this->assertSame('shrine_favor_granted', (string)($shrineRes['body']['data']['battle']['log']['events'][0]['message'] ?? ''));
    $shrineResult = $shrineRes['body']['data']['battle']['log']['events'][0]['shrine_result'] ?? null;
    $this->assertIsArray($shrineResult);
    $this->assertContains((string)($shrineResult['favor'] ?? ''), ['bone_whisper', 'rust_blessing', 'bog_luck']);
    [$shrineXp, $shrineSoft] = $this->battleRewardTuple($shrineBattleId);
    $this->assertSame(0, $shrineXp);
    $this->assertGreaterThanOrEqual(4, $shrineSoft);
    $this->assertLessThanOrEqual(8, $shrineSoft);

    $shrineRewardsRaw = (string)$this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$shrineBattleId]);
    $shrineRewards = json_decode($shrineRewardsRaw, true);
    $this->assertIsArray($shrineRewards);
    $this->assertSame('shrine', (string)($shrineRewards['encounter_result']['family'] ?? ''));

    $lootNodeId = $this->insertRunNode($runId, 'loot', 'available');
    $lootRes = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$lootNodeId));
    $this->assertSame(200, $lootRes['status']);
    $lootBattleId = (int)($lootRes['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $lootBattleId);
    $lootPreview = $lootRes['body']['data']['battle']['reward_preview'] ?? null;
    $this->assertIsArray($lootPreview);
    $this->assertSame('loot', (string)($lootPreview['node_type'] ?? ''));
    $this->assertSame(0, (int)($lootPreview['xp_total'] ?? -1));
    $this->assertSame(8, (int)($lootPreview['currency_soft'] ?? -1));
    $this->assertIsArray($lootPreview['units'] ?? null);
    $this->assertIsArray($lootPreview['dice'] ?? null);
    $this->assertNotEmpty($lootPreview['dice']);
    $firstLootDie = $lootPreview['dice'][0];
    $this->assertIsArray($firstLootDie);
    $this->assertArrayHasKey('label', $firstLootDie);
    $this->assertArrayHasKey('material', $firstLootDie);
    $this->assertArrayHasKey('sides', $firstLootDie);
    $this->assertIsArray($firstLootDie['affixes'] ?? null);
    [$lootXp, $lootSoft] = $this->battleRewardTuple($lootBattleId);
    $this->assertSame(0, $lootXp);
    $this->assertSame(8, $lootSoft);

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
      $this->assertGreaterThanOrEqual(5, $combatSoft);
      $this->assertLessThanOrEqual(10, $combatSoft);
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

  public function testFarmBossVictoryGrantsPigKinProgressionItems(): void
  {
    $userId = $this->insertUser();
    $farmRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1", []);
    $this->assertGreaterThan(0, $farmRegionId);
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $farmRegionId, 73747576);
    $nodeId = $this->insertRunNode($runId, 'boss', 'available');

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    for ($i = 0; $i < 3; $i++) {
      $unitId = $this->insertUnit($userId, $unitTypeId, 10, 0);
      $this->insertTeamUnit($teamId, $unitId);
      $this->insertRunUnitState($runId, $unitId, 40, false);
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $response = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $response['status'], json_encode($response['body']));

    $battle = is_array($response['body']['data']['battle'] ?? null) ? $response['body']['data']['battle'] : [];
    $this->assertSame('victory', (string)($battle['outcome'] ?? ''));
    $preview = is_array($battle['reward_preview'] ?? null) ? $battle['reward_preview'] : [];
    $previewItems = is_array($preview['items'] ?? null) ? $preview['items'] : [];
    $this->assertNotEmpty($previewItems);

    $itemSlugs = array_map(static fn(array $item): string => (string)($item['item_slug'] ?? ''), $previewItems);
    $this->assertContains('pig_ear', $itemSlugs);
    $this->assertContains('mudking_crown_fragment', $itemSlugs);

    $battleId = (int)($battle['battle_id'] ?? 0);
    $rewards = json_decode((string)$this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$battleId]), true);
    $this->assertIsArray($rewards);
    $this->assertIsArray($rewards['item_grants'] ?? null);
    $this->assertIsArray($rewards['granted_items'] ?? null);

    $this->assertSame(2, $this->ownedItemQuantity($userId, 'pig_ear'));
    $this->assertSame(1, $this->ownedItemQuantity($userId, 'mudking_crown_fragment'));
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

    $equippedUnitIds = [];
    foreach ($unitTypeRows as $row) {
      $unitId = $this->insertUnit($userId, (int)$row['id'], 1, 0);
      $this->insertTeamUnit($teamId, $unitId);
      $this->insertRunUnitState($runId, $unitId, 20, false);
      $equippedUnitIds[] = $unitId;
    }

    $equipStmt = $this->pdo->prepare('
      INSERT INTO `unit_instance_equipped_abilities` (`unit_instance_id`, `ability_id`, `equip_order`, `speed_cost`)
      VALUES (?, ?, ?, ?)
    ');
    $equipStmt->execute([$equippedUnitIds[0], 'basic_attack_melee', 0, 4]);
    $equipStmt->execute([$equippedUnitIds[0], 'heavy_strike', 1, 8]);
    $equipStmt->execute([$equippedUnitIds[1], 'heavy_strike', 0, 8]);
    $equipStmt->execute([$equippedUnitIds[1], 'basic_attack_melee', 1, 4]);

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

      $this->assertIsInt($event['ability_instance_index'] ?? null, 'Action events must include equipped ability instance order.');
      $this->assertIsString($event['loadout_source'] ?? null, 'Action events must include loadout source metadata.');
      $this->assertIsArray($event['dice_used'] ?? null, 'Action events must include dice_used metadata.');
      $this->assertIsArray($event['dice_rolls'] ?? null, 'Action events must include dice_rolls metadata.');
      $this->assertIsArray($event['slot_traces'] ?? null, 'Action events must include per-slot trace metadata.');
      $this->assertIsString($event['slot_trace_summary'] ?? null, 'Action events must include slot trace summary.');
      $this->assertIsString($event['dice_outcome'] ?? null, 'Action events must include dice_outcome summary.');
      $this->assertIsString($event['ability_outcome'] ?? null, 'Action events must include ability_outcome summary.');
      $this->assertIsInt($event['actor_hp_after'] ?? null, 'Action events must include actor_hp_after snapshot.');
      $this->assertIsInt($event['actor_max_hp'] ?? null, 'Action events must include actor_max_hp snapshot.');
      $this->assertIsInt($event['target_hp_after'] ?? null, 'Action events must include target_hp_after snapshot.');
      $this->assertIsInt($event['target_max_hp'] ?? null, 'Action events must include target_max_hp snapshot.');
    }

    $this->assertGreaterThan(2, count($roundOneTickSet), 'Round one should contain action ticks beyond the previous fixed two-tick cadence.');
  }

  public function testResolveNodeUsesCumulativeEquippedAbilityTicks(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 81818181);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $stmt = $this->pdo->prepare("SELECT `id` FROM `unit_types` WHERE `slug` = 'frontline_bruiser_t1' LIMIT 1");
    $stmt->execute();
    $unitTypeId = (int)$stmt->fetchColumn();
    $this->assertGreaterThan(0, $unitTypeId);

    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 20, false);

    $equipStmt = $this->pdo->prepare('
      INSERT INTO `unit_instance_equipped_abilities` (`unit_instance_id`, `ability_id`, `equip_order`, `speed_cost`)
      VALUES (?, ?, ?, ?)
    ');
    $equipStmt->execute([$unitId, 'basic_attack_melee', 0, 4]);
    $equipStmt->execute([$unitId, 'heavy_strike', 1, 8]);

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

    $playerRoundOneTicks = [];
    foreach ($events as $event) {
      if (
        is_array($event)
        && (string)($event['type'] ?? '') === 'action'
        && (string)($event['side'] ?? '') === 'player'
        && (string)($event['actor_unit_instance_id'] ?? '') === (string)$unitId
        && (int)($event['round'] ?? 0) === 1
      ) {
        $playerRoundOneTicks[] = (int)($event['tick'] ?? 0);
      }
    }

    $this->assertContains(4, $playerRoundOneTicks);
    $this->assertContains(12, $playerRoundOneTicks);
    $this->assertContains(16, $playerRoundOneTicks);
    $this->assertNotContains(8, $playerRoundOneTicks, 'The second equipped ability should fire at cumulative tick 12, not at its raw speed tick.');
    $this->assertGreaterThanOrEqual(3, count($playerRoundOneTicks), 'Remaining round budget should allow a repeated attack action when it fits.');
  }

  public function testResolveNodeUsesD1FallbackForPlayerEmptyDiceSlots(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 45454545);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $stmt = $this->pdo->prepare("SELECT `id` FROM `unit_types` WHERE `slug` = 'frontline_bruiser_t1' LIMIT 1");
    $stmt->execute();
    $unitTypeId = (int)$stmt->fetchColumn();
    $this->assertGreaterThan(0, $unitTypeId);

    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 20, false);

    $equipStmt = $this->pdo->prepare('
      INSERT INTO `unit_instance_equipped_abilities` (`unit_instance_id`, `ability_id`, `equip_order`, `speed_cost`)
      VALUES (?, ?, ?, ?)
    ');
    $equipStmt->execute([$unitId, 'heavy_strike', 0, 8]);

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
    $slotTraces = is_array($playerAction['slot_traces'] ?? null) ? $playerAction['slot_traces'] : [];
    $this->assertCount(1, $slotTraces);
    $this->assertSame('empty_slot', (string)($diceUsed[0]['kind'] ?? ''));
    $this->assertSame(1, (int)($diceUsed[0]['sides'] ?? 0));
    $this->assertSame(1, (int)($diceRolls[0]['sides'] ?? 0));
    $this->assertSame(1, (int)($diceRolls[0]['roll'] ?? 0));
    $this->assertSame(0, (int)($slotTraces[0]['slot_index'] ?? -1));
    $this->assertSame(true, (bool)($slotTraces[0]['empty_slot'] ?? false));
    $this->assertStringContainsString('slot1=empty_slot(d1) => 1 (mod +0)', (string)($playerAction['slot_trace_summary'] ?? ''));
  }

  public function testResolveNodeUsesBoundAbilityDiceInsteadOfLegacyUnitPool(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 56565656);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $stmt = $this->pdo->prepare("SELECT `id` FROM `unit_types` WHERE `slug` = 'frontline_bruiser_t1' LIMIT 1");
    $stmt->execute();
    $unitTypeId = (int)$stmt->fetchColumn();
    $this->assertGreaterThan(0, $unitTypeId);

    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 20, false);

    $equipStmt = $this->pdo->prepare('
      INSERT INTO `unit_instance_equipped_abilities` (`unit_instance_id`, `ability_id`, `equip_order`, `speed_cost`)
      VALUES (?, ?, ?, ?)
    ');
    $equipStmt->execute([$unitId, 'heavy_strike', 0, 8]);

    $diceDefStmt = $this->pdo->prepare("SELECT `id` FROM `dice_definitions` WHERE `rarity` = 'common' AND `sides` = 4 LIMIT 1");
    $diceDefStmt->execute();
    $d4DefinitionId = (int)$diceDefStmt->fetchColumn();
    $this->assertGreaterThan(0, $d4DefinitionId);

    $insertDice = $this->pdo->prepare('INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`) VALUES (?, ?, NULL)');
    $insertDice->execute([$userId, $d4DefinitionId]);
    $boundDiceId = (int)$this->pdo->lastInsertId();

    $insertBinding = $this->pdo->prepare('
      INSERT INTO `unit_ability_dice` (`unit_instance_id`, `ability_id`, `slot_index`, `dice_instance_id`)
      VALUES (?, ?, ?, ?)
    ');
    $insertBinding->execute([$unitId, 'heavy_strike', 0, $boundDiceId]);

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

    $heavyStrikeAction = null;
    foreach ($events as $event) {
      if (
        is_array($event)
        && (string)($event['type'] ?? '') === 'action'
        && (string)($event['side'] ?? '') === 'player'
        && (string)($event['actor_unit_instance_id'] ?? '') === (string)$unitId
        && (string)($event['ability_id'] ?? '') === 'heavy_strike'
      ) {
        $heavyStrikeAction = $event;
        break;
      }
    }

    $this->assertIsArray($heavyStrikeAction, 'Expected a heavy_strike action event.');
    $diceUsed = is_array($heavyStrikeAction['dice_used'] ?? null) ? $heavyStrikeAction['dice_used'] : [];
    $slotTraces = is_array($heavyStrikeAction['slot_traces'] ?? null) ? $heavyStrikeAction['slot_traces'] : [];
    $this->assertCount(1, $diceUsed);
    $this->assertCount(1, $slotTraces);
    $this->assertSame((string)$boundDiceId, (string)($diceUsed[0]['dice_instance_id'] ?? ''));
    $this->assertSame(4, (int)($diceUsed[0]['sides'] ?? 0));
    $this->assertSame((string)$boundDiceId, (string)($slotTraces[0]['dice_instance_id'] ?? ''));
    $this->assertSame(false, (bool)($slotTraces[0]['empty_slot'] ?? true));
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

  private function ownedItemQuantity(int $userId, string $itemSlug): int
  {
    return (int)$this->scalar(
      'SELECT COALESCE(ui.`quantity`, 0)
       FROM `items` i
       LEFT JOIN `user_items` ui ON ui.`item_id` = i.`id` AND ui.`user_id` = ?
       WHERE i.`slug` = ?
       LIMIT 1',
      [$userId, $itemSlug]
    );
  }
}
