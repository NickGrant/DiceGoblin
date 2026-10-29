<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ShrineContentFixture;
use PDO;

final class ShrineRunLifecycleTest extends IntegrationTestCase
{

  public function testBothAuthoredChoicesFinalizeOnlySelectedHpEffectAndRetainFrontier(): void
  {
    foreach (['restore', 'endure'] as $actionId) {
      [$userId, $runId, $ids, $content] = $this->readyShrine($actionId);
      $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
      $query = $services['currentRunQuery'];
      $command = $services['resolveRunNodeCommand'];
      $read = $query->execute($userId)['run'];
      $interaction = $read['nodes'][3]['interaction'];
      $this->assertSame(['encounter_id', 'title', 'body', 'prompt', 'actions'], array_keys($interaction));
      $this->assertSame(ShrineContentFixture::ENCOUNTER_ID, $interaction['encounter_id']);
      $this->assertSame([
        ['id' => 'restore', 'label' => 'RESTORE WARBAND'],
        ['id' => 'endure', 'label' => 'ENDURE TRIAL'],
      ], $interaction['actions']);
      $this->assertStringNotContainsString('restore_participating_units_to_max', json_encode($read, JSON_THROW_ON_ERROR));
      $this->assertStringNotContainsString('damage_all_participating_units', json_encode($read, JSON_THROW_ON_ERROR));
      $this->assertSame([(string)$ids[2], (string)$ids[3]], $read['available_node_ids']);
      $before = (new PlayerStateRepository($this->pdo))->getPlayerState($userId);
      $hpBefore = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
      $this->assertCount(5, $hpBefore);
      $request = ['encounter_id' => ShrineContentFixture::ENCOUNTER_ID, 'action_id' => $actionId];
      $result = $command->execute($userId, $runId, $ids[3], 'shrine-' . $actionId, $request);
      $this->assertSame(['shrine', $actionId, [(string)$ids[4]], 'active', $before['player_revision'] + 1], [
        $result['resolution_type'], $result['action_id'], $result['newly_available_node_ids'],
        $result['run']['status'], $result['player_revision'],
      ]);
      $this->assertArrayNotHasKey('wallet', $result);
      $this->assertArrayNotHasKey('granted_rewards', $result);
      $this->assertCount(5, $result['hp_changes']);
      $hpAfter = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
      foreach ($result['hp_changes'] as $index => $change) {
        $expectedHp = $actionId === 'restore' ? $change['max_hp'] : 3;
        $this->assertSame((string)$hpBefore[$index]['unit_id'], $change['unit_id']);
        $this->assertSame(5, $change['hp_before']);
        $this->assertSame($expectedHp, $change['hp_after']);
        $this->assertSame($expectedHp, (int)$hpAfter[$index]['current_hp']);
      }
      $after = (new PlayerStateRepository($this->pdo))->getPlayerState($userId);
      $this->assertSame($before['teeth'], $after['teeth']);
      $this->assertSame($before['raw_chaos'], $after['raw_chaos']);
      $this->assertSame($before['player_revision'] + 1, $after['player_revision']);
      $this->assertSame($result, $command->execute($userId, $runId, $ids[3], 'shrine-' . $actionId, $request));
      $this->assertErrorCode('run_node_already_resolved',
        fn() => $command->execute($userId, $runId, $ids[3], 'completed-' . $actionId, $request));
      $this->assertSame($after, (new PlayerStateRepository($this->pdo))->getPlayerState($userId));
      $this->assertSame($hpAfter, $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]));
      $final = $query->execute($userId)['run'];
      $this->assertSame([(string)$ids[2], (string)$ids[4]], $final['available_node_ids']);
      $this->assertArrayNotHasKey('interaction', $final['nodes'][3]);
      $this->assertSame(array_map(static fn(array $row): int => (int)$row['current_hp'], $hpAfter),
        array_column($final['units'], 'current_hp'));
      $reloaded = new PDO((string)getenv('TEST_DB_DSN'), (string)getenv('TEST_DB_USER'),
        (string)getenv('TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      $this->assertSame($final, ControllerServiceFactory::buildContentAware($reloaded, null, $content)
        ['currentRunQuery']->execute($userId)['run']);
    }
  }

  public function testInvalidUnavailableForeignAndCorruptShrineAttemptsDoNotMutate(): void
  {
    [$userId, $runId, $ids, $content] = $this->readyShrine('guards');
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $command = $services['resolveRunNodeCommand'];
    $request = ['encounter_id' => ShrineContentFixture::ENCOUNTER_ID, 'action_id' => 'restore'];
    $before = (new PlayerStateRepository($this->pdo))->getPlayerState($userId);
    $hpBefore = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'locked' WHERE `id` = ?")->execute([$ids[3]]);
    $this->assertErrorCode('run_node_unavailable', fn() => $command->execute($userId, $runId, $ids[3], 'shrine-locked', $request));
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` = ?")->execute([$ids[3]]);
    foreach ([
      ['encounter_id' => ShrineContentFixture::ENCOUNTER_ID, 'action_id' => 'missing'],
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest'],
    ] as $index => $invalid) {
      $this->assertErrorCode('invalid_node_request',
        fn() => $command->execute($userId, $runId, $ids[3], 'invalid-' . $index, $invalid));
    }
    $other = $services['accountCreationService']->createLocal('shrine-foreign-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Visitor');
    $this->trackUserId($other);
    $this->assertErrorCode('run_node_not_found', fn() => $command->execute($other, $runId, $ids[3], 'shrine-foreign', $request));
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = '2026-09-20 12:00:00' WHERE `id` = ?")
      ->execute([$runId]);
    $this->assertErrorCode('run_not_active', fn() => $command->execute($userId, $runId, $ids[3], 'terminal', $request));
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'active', `ended_at` = NULL WHERE `id` = ?")->execute([$runId]);
    foreach ([null, 'encounter.missing', 'encounter.the_farm_rest'] as $index => $encounterId) {
      $this->pdo?->prepare('UPDATE `run_nodes` SET `encounter_id` = ? WHERE `id` = ?')
        ->execute([$encounterId, $ids[3]]);
      try {
        $services['currentRunQuery']->execute($userId);
        $this->fail('Invalid persisted Shrine association was projected.');
      } catch (CurrentRunIntegrityException) { $this->addToAssertionCount(1); }
      try {
        $command->execute($userId, $runId, $ids[3], 'corrupt-' . $index,
          ['encounter_id' => $encounterId ?? ShrineContentFixture::ENCOUNTER_ID, 'action_id' => 'restore']);
        $this->fail('Invalid persisted Shrine association was resolved.');
      } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    }
    $this->pdo?->prepare('UPDATE `run_nodes` SET `encounter_id` = ? WHERE `id` = ?')
      ->execute([ShrineContentFixture::ENCOUNTER_ID, $ids[3]]);
    $this->pdo?->prepare('UPDATE `runs` SET `region_id` = ? WHERE `id` = ?')
      ->execute(['region.mountains', $runId]);
    try {
      $services['currentRunQuery']->execute($userId);
      $this->fail('Foreign-region Shrine was projected.');
    } catch (CurrentRunIntegrityException) { $this->addToAssertionCount(1); }
    try {
      $command->execute($userId, $runId, $ids[3], 'shrine-wrong-region', $request);
      $this->fail('Foreign-region Shrine was resolved.');
    } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame($before, (new PlayerStateRepository($this->pdo))->getPlayerState($userId));
    $this->assertSame($hpBefore, $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]));
  }

  /** @return array{0:int,1:int,2:array<int,int>,3:ContentRegistry} */
  private function readyShrine(string $token): array
  {
    $content = ShrineContentFixture::registry();
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $userId = $services['accountCreationService']->createLocal('shrine-' . $token . '-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Pilgrim');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))
      ->execute($userId);
    $graph = (new FixedGraphRunGenerator())->generate($content->runGeneration(ShrineContentFixture::GENERATION_ID));
    $repository = new RunPersistenceRepository($this->pdo);
    $runId = $repository->createRun($userId, 'region.the_farm', (int)$fixture['active_squad_id']);
    $ids = $repository->insertNodes($runId, $graph['nodes']);
    $repository->insertEdges($runId, $graph['edges'], $ids);
    $participants = [];
    foreach ($this->rows('SELECT ui.`id`, ui.`unit_type_id`, ui.`level` FROM `squad_units` su
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE su.`squad_id` = ? AND ui.`user_id` = ? ORDER BY su.`position`',
      [(int)$fixture['active_squad_id'], $userId]) as $unit) {
      $type = $content->unitType((string)$unit['unit_type_id']);
      $maximum = (new BaseLevelStatResolver())->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
      $participants[] = ['unit_id' => (int)$unit['id'], 'current_hp' => $maximum];
    }
    $repository->insertParticipatingUnits($runId, $participants);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 5 WHERE `run_id` = ?')->execute([$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = '2026-09-20 11:00:00'
      WHERE `run_id` = ? AND `node_index` IN (0, 1)")->execute([$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `run_id` = ? AND `node_index` IN (2, 3)")
      ->execute([$runId]);
    return [$userId, $runId, $ids, $content];
  }

  private function assertErrorCode(string $expected, callable $operation): void
  {
    try {
      $operation();
      $this->fail('Invalid Shrine operation succeeded.');
    } catch (RunNodeResolutionException $e) { $this->assertSame($expected, $e->errorCode); }
  }

  /** @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
