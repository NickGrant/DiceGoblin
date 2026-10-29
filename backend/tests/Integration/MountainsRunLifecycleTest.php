<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Application\Rewards\VictoryItemDropPolicy;
use DiceGoblins\Application\Rewards\VictoryItemGrantService;
use DiceGoblins\Application\RunNodes\BossNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\CombatNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\ExitNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\LootNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\RestNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\HazardNodeResolutionHandler;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use PDO;

final class MountainsRunLifecycleTest extends IntegrationTestCase
{

  public function testAuthoredMountainsRunUsesSharedResolutionAndTerminalLifecycle(): void
  {
    [$userId] = $this->fixtureAccount('mountains-lifecycle');
    $services = ControllerServiceFactory::buildContentAware(
      $this->pdo, null, null, $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $now = new DateTimeImmutable('2026-09-20 12:00:00', new DateTimeZone('UTC'));
    $bootstrap = $services['gameBootstrapQuery']->execute($userId, $now);
    $this->assertSame([], $bootstrap['progression']['unlock_ids']);
    $this->assertSame(['region.the_farm'], $bootstrap['progression']['available_region_ids']);
    $this->assertNull($bootstrap['active_run']);

    try {
      $services['startRunCommand']->execute($userId, ['region_id' => 'region.mountains'], 'mountains-locked-start');
      $this->fail('Mountains must remain locked before Farm completion.');
    } catch (RunStartException $e) {
      $this->assertSame(['run_region_locked', 403], [$e->errorCode, $e->httpStatus]);
    }

    $farm = $services['startRunCommand']->execute(
      $userId, ['region_id' => 'region.the_farm'], 'integrated-farm-start');
    $farmRunId = (int)$farm['run']['id'];
    $farmCurrent = $services['currentRunQuery']->execute($userId)['run'];
    $farmNodeIds = array_map('intval', array_column($farmCurrent['nodes'], 'id'));
    $farmCombat = $services['resolveRunNodeCommand']->execute(
      $userId, $farmRunId, $farmNodeIds[0], 'integrated-farm-combat');
    $this->assertBattleResolution($farmCombat, $farmRunId, $farmNodeIds[0], $farmNodeIds[1]);
    $this->assertSame([['item_id' => 'item.pig_ear', 'quantity' => 1, 'owned_after' => 1]], $farmCombat['item_grants']);
    $services['resolveRunNodeCommand']->execute($userId, $farmRunId, $farmNodeIds[1], 'integrated-farm-loot');
    $services['resolveRunNodeCommand']->execute($userId, $farmRunId, $farmNodeIds[2], 'integrated-farm-scout',
      ['encounter_id' => 'encounter.the_farm_scout', 'action_id' => 'hear_paths']);
    $services['resolveRunNodeCommand']->execute($userId, $farmRunId, $farmNodeIds[3], 'integrated-farm-rest',
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
    $farmBoss = $services['resolveRunNodeCommand']->execute(
      $userId, $farmRunId, $farmNodeIds[4], 'integrated-farm-boss');
    $this->assertBattleResolution($farmBoss, $farmRunId, $farmNodeIds[4], $farmNodeIds[5], 'boss');
    $this->assertSame([['outcome' => 'granted', 'unlock_id' => 'unlock.capability.shop_access'],
      ['outcome' => 'granted', 'unlock_id' => 'unlock.region.mountains']],
      $farmBoss['rewards']['unlocks']);
    $this->assertSame([
      ['item_id' => 'item.mudking_crown_fragment', 'quantity' => 1, 'owned_after' => 1],
      ['item_id' => 'item.pig_ear', 'quantity' => 2, 'owned_after' => 3],
    ], $farmBoss['item_grants']);
    $this->assertSame($farmBoss, $services['resolveRunNodeCommand']->execute(
      $userId, $farmRunId, $farmNodeIds[4], 'integrated-farm-boss'));
    $farmExit = $services['resolveRunNodeCommand']->execute(
      $userId, $farmRunId, $farmNodeIds[5], 'integrated-farm-exit');
    $this->assertSame('completed', $farmExit['run']['status']);

    $postFarm = $services['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($postFarm['active_run']);
    $this->assertContains('unlock.region.mountains', $postFarm['progression']['unlock_ids']);
    $this->assertSame(['region.the_farm', 'region.mountains'], $postFarm['progression']['available_region_ids']);

    $mountainsProgressBefore = $this->unitProgress($userId);
    $mountainsTeethBefore = (int)$postFarm['player']['teeth'];
    $mountains = $services['startRunCommand']->execute(
      $userId, ['region_id' => 'region.mountains'], 'mountains-public-start');
    $this->assertSame($mountains, $services['startRunCommand']->execute(
      $userId, ['region_id' => 'region.mountains'], 'mountains-public-start'));
    $runId = (int)$mountains['run']['id'];
    $current = $services['currentRunQuery']->execute($userId)['run'];
    $nodeIds = array_map('intval', array_column($current['nodes'], 'id'));
    $this->assertSame('region.mountains', $current['region_id']);
    $this->assertSame(range(0, 8), array_column($current['nodes'], 'node_index'));
    $this->assertSame([0, 1, 2, 3, 5, 6, 7, 4, 3], array_column(array_column($current['nodes'], 'position'), 'column'));
    $this->assertSame([
      'run_node_type.combat', 'run_node_type.loot', 'run_node_type.combat', 'run_node_type.rest',
      'run_node_type.combat', 'run_node_type.boss', 'run_node_type.exit', 'run_node_type.hazard', 'run_node_type.hazard',
    ], array_column($current['nodes'], 'node_type_id'));
    $this->assertSame([
      'encounter.mountains_kobold_combat_1', null, 'encounter.mountains_kobold_combat_2', 'encounter.mountains_rest',
      'encounter.mountains_kobold_combat_3', 'encounter.mountains_kobold_boss_1', null,
      'encounter.mountains_loose_scree', 'encounter.mountains_rockfall',
    ], array_column($this->rows(
      'SELECT `encounter_id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'encounter_id'));
    $this->assertSame([
      null, 'event.mountains_loot_completed', null, null, null, 'event.mountains_boss_completed', null, null, null,
    ], array_column($this->rows(
      'SELECT `event_id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'event_id'));
    $this->assertCount(8, $current['edges']);
    $this->assertSame(['available', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked'],
      array_column($current['nodes'], 'status'));

    $command = $services['resolveRunNodeCommand'];
    $combatOne = $command->execute($userId, $runId, $nodeIds[0], 'mountains-combat-1');
    $this->assertBattleResolution($combatOne, $runId, $nodeIds[0], $nodeIds[1]);
    $this->assertSame([['item_id' => 'item.kobold_scale', 'quantity' => 1, 'owned_after' => 1]], $combatOne['item_grants']);
    $this->assertStatuses($runId, ['completed', 'available', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked']);

    $loot = $command->execute($userId, $runId, $nodeIds[1], 'mountains-loot');
    $this->assertSame([8, $mountainsTeethBefore + 8, [(string)$nodeIds[2]]], [
      $loot['granted_rewards'][0]['amount'], $loot['wallet']['teeth'], $loot['newly_available_node_ids'],
    ]);
    $lootReplay = $command->execute($userId, $runId, $nodeIds[1], 'mountains-loot');
    $this->assertSame($loot, $lootReplay);
    $this->assertSame($mountainsTeethBefore + 8, (int)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));

    $combatTwo = $command->execute($userId, $runId, $nodeIds[2], 'mountains-combat-2');
    $this->assertSame([(string)$nodeIds[3], (string)$nodeIds[8]], $combatTwo['newly_available_node_ids']);
    $this->assertSame([['item_id' => 'item.kobold_scale', 'quantity' => 1, 'owned_after' => 2]], $combatTwo['item_grants']);
    $frontierBeforeHazard = $services['currentRunQuery']->execute($userId)['run'];
    $this->assertSame([(string)$nodeIds[3], (string)$nodeIds[8]], $frontierBeforeHazard['available_node_ids']);
    $this->assertSame('encounter.mountains_rockfall', $frontierBeforeHazard['nodes'][8]['interaction']['encounter_id']);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `encounter_id` = 'encounter.mountains_rest' WHERE `id` = ?")
      ->execute([$nodeIds[8]]);
    try {
      $services['currentRunQuery']->execute($userId);
      $this->fail('Mismatched authored encounter family was projected.');
    } catch (CurrentRunIntegrityException) { $this->addToAssertionCount(1); }
    $this->pdo?->prepare("UPDATE `run_nodes` SET `encounter_id` = 'encounter.mountains_rockfall' WHERE `id` = ?")
      ->execute([$nodeIds[8]]);
    $rockfall = $command->execute($userId, $runId, $nodeIds[8], 'mountains-rockfall',
      ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'push_through']);
    $this->assertSame(['hazard', [], 'active'],
      [$rockfall['resolution_type'], $rockfall['newly_available_node_ids'], $rockfall['run']['status']]);
    $this->assertCount(5, $rockfall['damage']);
    foreach ($rockfall['damage'] as $transition) {
      $this->assertSame(max(0, $transition['hp_before'] - 2), $transition['hp_after']);
    }
    $this->assertSame($rockfall, $command->execute($userId, $runId, $nodeIds[8], 'mountains-rockfall',
      ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'push_through']));
    $this->assertSame([(string)$nodeIds[3]], $services['currentRunQuery']->execute($userId)['run']['available_node_ids']);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 1 WHERE `run_id` = ?')->execute([$runId]);
    $rest = $command->execute($userId, $runId, $nodeIds[3], 'mountains-rest',
      ['encounter_id' => 'encounter.mountains_rest', 'action_id' => 'rest']);
    $this->assertSame([(string)$nodeIds[7]], $rest['newly_available_node_ids']);
    foreach ($rest['healing'] as $transition) {
      $this->assertSame(1, $transition['hp_before']);
      $this->assertSame($transition['max_hp'], $transition['hp_after']);
    }

    $scree = $command->execute($userId, $runId, $nodeIds[7], 'mountains-scree',
      ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'cross']);
    $this->assertSame([(string)$nodeIds[4]], $scree['newly_available_node_ids']);
    $this->assertSame($scree, $command->execute($userId, $runId, $nodeIds[7], 'mountains-scree',
      ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'cross']));
    $this->assertSame(1, count($scree['damage']));
    $this->assertSame(3, $scree['damage'][0]['hp_before'] - $scree['damage'][0]['hp_after']);
    $afterScree = $services['currentRunQuery']->execute($userId)['run'];
    $this->assertSame([(string)$nodeIds[4]], $afterScree['available_node_ids']);
    $damagedUnit = array_values(array_filter($afterScree['units'],
      static fn(array $unit): bool => $unit['unit_id'] === $scree['damage'][0]['unit_id']));
    $this->assertCount(1, $damagedUnit);
    $this->assertSame($scree['damage'][0]['hp_after'], $damagedUnit[0]['current_hp']);
    $this->assertArrayNotHasKey('interaction', $afterScree['nodes'][7]);
    $this->assertStringNotContainsString('damage_random_participating_unit', json_encode($afterScree, JSON_THROW_ON_ERROR));
    $combatThree = $command->execute($userId, $runId, $nodeIds[4], 'mountains-combat-3');
    $this->assertBattleResolution($combatThree, $runId, $nodeIds[4], $nodeIds[5]);
    $this->assertSame([['item_id' => 'item.kobold_scale', 'quantity' => 1, 'owned_after' => 3]], $combatThree['item_grants']);
    $boss = $command->execute($userId, $runId, $nodeIds[5], 'mountains-boss');
    $this->assertBattleResolution($boss, $runId, $nodeIds[5], $nodeIds[6], 'boss');
    $this->assertSame([['item_id' => 'item.chief_engineer_lens', 'quantity' => 1, 'owned_after' => 1]], $boss['item_grants']);
    $this->assertSame([], $boss['rewards']['unlocks']);
    $participantIds = array_column($this->rows(
      'SELECT `unit_id` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]), 'unit_id');
    $this->assertSame(array_map('strval', $participantIds), array_column($boss['rewards']['unit_xp'], 'unit_id'));
    $this->assertSame(array_fill(0, count($participantIds), 16), array_column($boss['rewards']['unit_xp'], 'amount'));
    $progressAfter = $this->unitProgress($userId);
    $this->assertNotSame($mountainsProgressBefore, $progressAfter);
    $bossReplay = $command->execute($userId, $runId, $nodeIds[5], 'mountains-boss');
    $this->assertSame($boss, $bossReplay);
    $this->assertSame([
      ['item.chief_engineer_lens', 1], ['item.kobold_scale', 3],
      ['item.mudking_crown_fragment', 1], ['item.pig_ear', 3],
    ], array_map(static fn(array $row): array => [$row['item_id'], (int)$row['quantity']],
      $this->rows('SELECT `item_id`, `quantity` FROM `user_items` WHERE `user_id` = ? ORDER BY `item_id`', [$userId])));
    $this->assertSame($progressAfter, $this->unitProgress($userId));

    $revisionBeforeExit = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $exit = $command->execute($userId, $runId, $nodeIds[6], 'mountains-exit');
    $this->assertSame(['exit', [], 'completed', $revisionBeforeExit + 1], [
      $exit['resolution_type'], $exit['newly_available_node_ids'], $exit['run']['status'], $exit['player_revision'],
    ]);
    $exitReplay = $command->execute($userId, $runId, $nodeIds[6], 'mountains-exit');
    $this->assertSame($exit, $exitReplay);
    $this->assertSame('completed', $this->scalar('SELECT `status` FROM `runs` WHERE `id` = ?', [$runId]));
    $this->assertSame('0', (string)$this->scalar(
      "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.swamps'", [$userId]));
    $final = $services['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($final['active_run']);
    $this->assertSame(['region.the_farm', 'region.mountains'], $final['progression']['available_region_ids']);
    $this->assertNotContains('unlock.region.swamps', $final['progression']['unlock_ids']);
    $this->assertSame($mountainsTeethBefore + 8, $final['player']['teeth']);
    $this->assertSame($progressAfter, $this->unitProgress($userId));
  }

  public function testMountainsCombatFailureUsesExistingFailedRunLifecycle(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('mountains-defeat');
    [$runId, $nodeIds] = $this->persistMountainsRun($userId, $fixture);

    $result = $this->command($this->fixedResolver('defeat'))
      ->execute($userId, $runId, $nodeIds[0], 'mountains-defeat-combat');

    $this->assertSame(['combat', 'defeat', 'failed', []], [
      $result['resolution_type'], $result['battle']['outcome'], $result['run']['status'], $result['newly_available_node_ids'],
    ]);
    $this->assertSame(['completed', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked'],
      array_column($this->rows('SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status'));
  }

  public function testHazardRejectsInvalidSelectionsAndPersistsExactlyOnce(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('mountains-hazard-guards');
    [$runId, $nodeIds] = $this->persistMountainsRun($userId, $fixture);
    $selectionCount = 0;
    $command = $this->command($this->fixedResolver('victory'),
      static function (int $count) use (&$selectionCount): int { $selectionCount++; return $count - 1; });
    $request = ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'cross'];
    try {
      $command->execute($userId, $runId, $nodeIds[7], 'hazard-locked', $request);
      $this->fail('Locked Hazard was resolved.');
    } catch (RunNodeResolutionException $e) { $this->assertSame('run_node_unavailable', $e->errorCode); }
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = '2026-09-20 11:00:00'
      WHERE `run_id` = ? AND `node_index` IN (0, 1, 2, 3)")->execute([$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` IN (?, ?)")
      ->execute([$nodeIds[7], $nodeIds[8]]);
    $before = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
    foreach ([
      ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'cross'],
      ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'unknown'],
    ] as $index => $invalid) {
      try {
        $command->execute($userId, $runId, $nodeIds[7], 'hazard-invalid-' . $index, $invalid);
        $this->fail('Invalid Hazard action was resolved.');
      } catch (RunNodeResolutionException $e) { $this->assertSame('invalid_node_request', $e->errorCode); }
    }
    [$otherUser] = $this->fixtureAccount('mountains-hazard-foreign');
    try {
      $command->execute($otherUser, $runId, $nodeIds[7], 'hazard-foreign', $request);
      $this->fail('Foreign player resolved Hazard.');
    } catch (RunNodeResolutionException $e) { $this->assertSame('run_node_not_found', $e->errorCode); }
    $this->assertSame($before, $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]));
    $revisionBefore = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $result = $command->execute($userId, $runId, $nodeIds[7], 'hazard-success', $request);
    $this->assertSame([(string)$nodeIds[4]], $result['newly_available_node_ids']);
    $this->assertSame($revisionBefore + 1, $result['player_revision']);
    $this->assertSame($result, $command->execute($userId, $runId, $nodeIds[7], 'hazard-success', $request));
    $this->assertSame(1, $selectionCount);
    $this->assertSame($revisionBefore + 1,
      (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $after = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
    foreach (array_slice($before, 0, -1) as $index => $unit) $this->assertSame($unit, $after[$index]);
    $last = count($before) - 1;
    $this->assertSame((int)$before[$last]['current_hp'] - 3, (int)$after[$last]['current_hp']);
    $this->assertSame((string)$before[$last]['unit_id'], $result['damage'][0]['unit_id']);
    $this->assertSame(['completed', 'available'], array_column($this->rows(
      'SELECT `status` FROM `run_nodes` WHERE `id` IN (?, ?) ORDER BY `node_index`', [$nodeIds[7], $nodeIds[8]]), 'status'));
    try {
      $command->execute($userId, $runId, $nodeIds[7], 'hazard-completed', $request);
      $this->fail('Completed Hazard was resolved again.');
    } catch (RunNodeResolutionException $e) { $this->assertSame('run_node_already_resolved', $e->errorCode); }
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = '2026-09-20 12:00:00' WHERE `id` = ?")
      ->execute([$runId]);
    try {
      $command->execute($userId, $runId, $nodeIds[8], 'hazard-terminal',
        ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'push_through']);
      $this->fail('Terminal run resolved Hazard.');
    } catch (RunNodeResolutionException $e) { $this->assertSame('run_not_active', $e->errorCode); }
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'active', `ended_at` = NULL WHERE `id` = ?")
      ->execute([$runId]);
    $this->pdo?->prepare('UPDATE `run_nodes` SET `encounter_id` = NULL WHERE `id` = ?')->execute([$nodeIds[8]]);
    try {
      $command->execute($userId, $runId, $nodeIds[8], 'hazard-missing-persisted',
        ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'push_through']);
      $this->fail('Missing persisted encounter was resolved.');
    } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    $this->pdo?->prepare("UPDATE `run_nodes` SET `encounter_id` = 'encounter.the_farm_rest' WHERE `id` = ?")
      ->execute([$nodeIds[8]]);
    try {
      $command->execute($userId, $runId, $nodeIds[8], 'hazard-invalid-persisted',
        ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
      $this->fail('Foreign-region encounter was resolved.');
    } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
  }

  public function testLooseScreeCanSelectFirstOrLastParticipantIncludingOneAtZeroHp(): void
  {
    foreach ([0, 4] as $selectedIndex) {
      [$userId, $fixture] = $this->fixtureAccount('mountains-scree-choice-' . $selectedIndex);
      [$runId, $nodeIds] = $this->persistMountainsRun($userId, $fixture);
      $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = '2026-09-20 11:00:00'
        WHERE `run_id` = ? AND `node_index` IN (0, 1, 2, 3)")->execute([$runId]);
      $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` = ?")
        ->execute([$nodeIds[7]]);
      $before = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
      $this->assertCount(5, $before);
      if ($selectedIndex === 4) {
        $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 0 WHERE `run_id` = ? AND `unit_id` = ?')
          ->execute([$runId, $before[4]['unit_id']]);
        $before[4]['current_hp'] = 0;
      }
      $rolls = 0;
      $command = $this->command($this->fixedResolver('victory'),
        static function (int $count) use ($selectedIndex, &$rolls): int {
          if ($count !== 5) throw new \LogicException('Unexpected participant count.');
          $rolls++;
          return $selectedIndex;
        });
      $request = ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'cross'];
      $revisionBefore = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
      $result = $command->execute($userId, $runId, $nodeIds[7], 'scree-choice-' . $selectedIndex, $request);
      $this->assertCount(1, $result['damage']);
      $this->assertSame((string)$before[$selectedIndex]['unit_id'], $result['damage'][0]['unit_id']);
      $this->assertSame((int)$before[$selectedIndex]['current_hp'], $result['damage'][0]['hp_before']);
      $this->assertSame(max(0, (int)$before[$selectedIndex]['current_hp'] - 3), $result['damage'][0]['hp_after']);
      $after = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
      foreach ($before as $index => $unit) {
        if ($index !== $selectedIndex) $this->assertSame($unit, $after[$index]);
      }
      $this->assertSame($result['damage'][0]['hp_after'], (int)$after[$selectedIndex]['current_hp']);
      $this->assertSame($revisionBefore + 1, $result['player_revision']);
      $this->assertSame($result, $command->execute($userId, $runId, $nodeIds[7], 'scree-choice-' . $selectedIndex, $request));
      $this->assertSame(1, $rolls);
      $this->assertSame($revisionBefore + 1,
        (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    }
  }

  public function testMountainsBossFailureAppliesNoRewardEventOrXp(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('mountains-boss-defeat');
    [$runId, $nodeIds] = $this->persistMountainsRun($userId, $fixture);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = '2026-09-20 11:00:00'
      WHERE `run_id` = ? AND `node_index` < 5")->execute([$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` = ?")->execute([$nodeIds[5]]);
    $before = $this->unitProgress($userId);

    $result = $this->command($this->fixedResolver('defeat'))
      ->execute($userId, $runId, $nodeIds[5], 'mountains-defeat-boss');

    $this->assertSame(['boss', 'defeat', null, 'failed', []], [
      $result['resolution_type'], $result['battle']['outcome'], $result['rewards'], $result['run']['status'],
      $result['newly_available_node_ids'],
    ]);
    $this->assertSame($before, $this->unitProgress($userId));
    $this->assertSame('0', (string)$this->scalar(
      "SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `event_id` = 'event.mountains_boss_completed'", [$userId]));
    $this->assertSame([], $this->rows('SELECT `item_id` FROM `user_items` WHERE `user_id` = ?', [$userId]));
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(string $token): array
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal(
      $token . '-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT), 'Climber');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo),
      $this->content()))->execute($userId);
    return [$userId, $fixture];
  }

  /** @param array<string,mixed> $fixture @return array{0:int,1:array<int,int>} */
  private function persistMountainsRun(int $userId, array $fixture): array
  {
    $repository = new RunPersistenceRepository($this->pdo);
    $graph = (new FixedGraphRunGenerator())->generate($this->content()->runGenerationForRegion('region.mountains'));
    $runId = $repository->createRun($userId, 'region.mountains', (int)$fixture['active_squad_id']);
    $nodeIds = $repository->insertNodes($runId, $graph['nodes']);
    $repository->insertEdges($runId, $graph['edges'], $nodeIds);
    $participants = [];
    foreach ($this->rows('SELECT ui.`id`, ui.`unit_type_id`, ui.`level` FROM `squad_units` su
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE su.`squad_id` = ? AND ui.`user_id` = ? ORDER BY su.`position`',
      [(int)$fixture['active_squad_id'], $userId]) as $unit) {
      $type = $this->content()->unitType((string)$unit['unit_type_id']);
      $maximum = (new BaseLevelStatResolver())->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
      $participants[] = ['unit_id' => (int)$unit['id'], 'current_hp' => $maximum];
    }
    $repository->insertParticipatingUnits($runId, $participants);
    return [$runId, $nodeIds];
  }

  private function command(CombatResolver $resolver, ?\Closure $chooseHazardIndex = null): ResolveRunNodeCommand
  {
    $content = $this->content();
    $state = new PlayerStateRepository($this->pdo);
    $nodes = new RunNodeResolutionRepository($this->pdo);
    $units = new WarbandUnitRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $battles = new BattlePersistenceRepository($this->pdo);
    $combat = new CombatNodeResolutionHandler($nodes, $battles,
      new CombatSnapshotAssembler($content, new SquadRepository($this->pdo), new UnitDetailQuery($units, $content),
        new WarbandDiceRepository($this->pdo)), $resolver, new CombatSeedDeriver(),
      new VictoryItemGrantService(new VictoryItemDropPolicy($content), new UserItemRepository($this->pdo)),
      new \DiceGoblins\Repositories\RunModifierRepository($this->pdo), $content);
    $rewards = new RewardApplicationService($this->pdo, $content,
      new RewardFinalizer(new ScriptedRewardRollSource([1, 1, 1])), new ResolvedEventRepository($this->pdo),
      $state, $units, $unlocks, new \DiceGoblins\Repositories\UserCodexRepository($this->pdo));
    return new ResolveRunNodeCommand($this->pdo, $state, new RunPersistenceRepository($this->pdo), $nodes,
      new IdempotencyRequestRepository($this->pdo), [
        $combat, new BossNodeResolutionHandler($combat, $nodes, $units, $unlocks, $rewards),
        new LootNodeResolutionHandler($nodes, $units, $unlocks, $rewards),
        new RestNodeResolutionHandler($nodes, $units, $content),
        new HazardNodeResolutionHandler($nodes, $units, $content, new BaseLevelStatResolver(), $chooseHazardIndex),
        new ExitNodeResolutionHandler($nodes),
      ], new class implements Clock {
        public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-09-20 12:00:00', new DateTimeZone('UTC')); }
      });
  }

  private function fixedResolver(string $outcome): CombatResolver
  {
    return new class($outcome) implements CombatResolver {
      public function __construct(private readonly string $outcome) {}
      public function resolve(CombatInput $input): CombatResult {
        $terminal = [];
        foreach ($input->combatants as $key => $unit) {
          $hp = ($this->outcome === 'victory' && $unit['side'] === 'enemy')
            || ($this->outcome === 'defeat' && $unit['side'] === 'player') ? 0 : $unit['current_hp'];
          $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $hp, 'max_hp' => $unit['max_hp'],
            'is_defeated' => $hp === 0, 'statuses' => $unit['statuses']];
        }
        $events = new PlaybackRecorder();
        $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($input->combatants)]);
        $events->add('battle_ended', 1, 1, ['outcome' => $this->outcome]);
        return new CombatResult($this->outcome, 1, 1, $terminal, $events->events());
      }
    };
  }

  /** @param array<string,mixed> $result */
  private function assertBattleResolution(array $result, int $runId, int $nodeId, int $nextNodeId,
    string $type = 'combat'): void
  {
    $this->assertSame([$type, 'victory', [(string)$nextNodeId]], [
      $result['resolution_type'], $result['battle']['outcome'], $result['newly_available_node_ids'],
    ]);
    $battle = (new BattlePersistenceRepository($this->pdo))->findForRunNode($runId, $nodeId);
    $this->assertNotNull($battle);
    $this->assertSame((string)$battle->id, $result['battle']['id']);
    $this->assertSame('victory', $battle->battle->result['outcome']);
    $this->assertNotEmpty($battle->battle->result['events']);
  }

  /** @param list<string> $expected */
  private function assertStatuses(int $runId, array $expected): void
  {
    $this->assertSame($expected, array_column($this->rows(
      'SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status'));
  }

  /** @return list<array<string,mixed>> */
  private function unitProgress(int $userId): array
  {
    return $this->rows('SELECT `id`, `level`, `xp` FROM `unit_instances` WHERE `user_id` = ? ORDER BY `id`', [$userId]);
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content'); }
}
