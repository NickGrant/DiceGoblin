<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Application\RunNodes\ShrineNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\ContextualEncounterStepAdvancer;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunModifierRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\ContextualEncounterFixture as Fixture;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;
use RuntimeException;
use DiceGoblins\Infrastructure\SystemClock;

final class ContextualEncounterLifecycleTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testContextualStepConsumesOnceSurvivesReloadAndFinalizesOnce(): void
  {
    [$user, $run, $ids, $content] = $this->ready();
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $query = $services['currentRunQuery']; $command = $services['resolveRunNodeCommand'];
    $initial = $query->execute($user);
    $this->assertSame(['continue'], array_column($initial['run']['nodes'][3]['interaction']['actions'], 'id'));
    $request = ['encounter_id' => Fixture::ENCOUNTER_ID, 'action_id' => 'offer_wick'];
    $this->assertCode('invalid_node_request', fn() => $command->execute($user, $run, $ids[3], 'no-wick-item', $request));
    $this->pdo->beginTransaction();
    (new UserItemRepository($this->pdo))->increment($user, Fixture::ITEM_ID, 2);
    $this->pdo->commit();
    $ready = $query->execute($user);
    $interaction = $ready['run']['nodes'][3]['interaction'];
    $this->assertSame(['continue', 'offer_wick'], array_column($interaction['actions'], 'id'));
    $this->assertArrayNotHasKey('step_id', $interaction);
    foreach (['advance_step', 'item.test_ritual_wick', 'quantity', 'effect'] as $hidden) {
      $this->assertStringNotContainsString($hidden, json_encode($ready, JSON_THROW_ON_ERROR));
    }
    $step = $command->execute($user, $run, $ids[3], 'offer-once', $request);
    $this->assertSame(['interaction_step', 'available', null, [], 'kindled'], [
      $step['resolution_type'], $step['node']['status'], $step['node']['completed_at'],
      $step['newly_available_node_ids'], $step['step_id'],
    ]);
    $this->assertSame(1, $this->quantity($user));
    $this->assertSame($step, $command->execute($user, $run, $ids[3], 'offer-once', $request));
    $this->assertSame(1, $this->quantity($user));
    $this->assertCode('invalid_node_request', fn() => $command->execute($user, $run, $ids[3], 'offer-again', $request));
    $reloaded = new PDO((string)getenv('TEST_DB_DSN'), (string)getenv('TEST_DB_USER'),
      (string)getenv('TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $reloadedServices = ControllerServiceFactory::buildContentAware($reloaded, null, $content);
    $middle = $reloadedServices['currentRunQuery']->execute($user);
    $this->assertSame([(string)$ids[2], (string)$ids[3]], $middle['run']['available_node_ids']);
    $this->assertSame('kindled', $middle['run']['nodes'][3]['interaction']['step_id']);
    $this->assertSame(['complete_rite'], array_column($middle['run']['nodes'][3]['interaction']['actions'], 'id'));
    $this->assertSame('The wick burns steadily.', $middle['run']['nodes'][3]['interaction']['body']);
    $finishRequest = ['encounter_id' => Fixture::ENCOUNTER_ID, 'action_id' => 'complete_rite'];
    $final = $reloadedServices['resolveRunNodeCommand']->execute($user, $run, $ids[3], 'finish-once', $finishRequest);
    $this->assertSame('completed', $final['node']['status']);
    $this->assertNull($this->rows('SELECT `encounter_step_id` FROM `run_nodes` WHERE `id` = ?', [$ids[3]])[0]['encounter_step_id']);
    $this->assertSame([(string)$ids[4]], $final['newly_available_node_ids']);
    $this->assertSame($step['player_revision'] + 1, $final['player_revision']);
    $this->assertSame($final, $reloadedServices['resolveRunNodeCommand']->execute($user, $run, $ids[3], 'finish-once', $finishRequest));
    $this->assertCode('run_node_already_resolved', fn() => $command->execute($user, $run, $ids[3], 'finish-again', $finishRequest));
    $this->assertSame([(string)$ids[2], (string)$ids[4]], $query->execute($user)['run']['available_node_ids']);
    $this->assertSame(1, $this->quantity($user));
  }

  public function testLockedForeignTerminalAndCorruptStepFailClosed(): void
  {
    [$user, $run, $ids, $content] = $this->ready();
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $command = $services['resolveRunNodeCommand'];
    $request = ['encounter_id' => Fixture::ENCOUNTER_ID, 'action_id' => 'offer_wick'];
    $other = $services['accountCreationService']->createLocal('wick-other-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Visitor');
    $this->trackUserId($other);
    $this->pdo->beginTransaction(); (new UserItemRepository($this->pdo))->increment($other, Fixture::ITEM_ID, 1); $this->pdo->commit();
    $this->assertSame(['continue'], array_column($services['currentRunQuery']->execute($user)['run']['nodes'][3]['interaction']['actions'], 'id'));
    $this->assertCode('invalid_node_request', fn() => $command->execute($user, $run, $ids[3], 'other-wick', $request));
    $this->assertCode('run_node_not_found', fn() => $command->execute($other, $run, $ids[3], 'foreign-node', $request));
    $this->pdo->prepare("UPDATE `run_nodes` SET `status` = 'locked' WHERE `id` = ?")->execute([$ids[3]]);
    $this->assertCode('run_node_unavailable', fn() => $command->execute($user, $run, $ids[3], 'locked-wick', $request));
    $this->pdo->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = '2026-09-20 12:00:00' WHERE `id` = ?")
      ->execute([$run]);
    $this->assertCode('run_not_active', fn() => $command->execute($user, $run, $ids[3], 'terminal-node', $request));
    $this->pdo->prepare("UPDATE `runs` SET `status` = 'active', `ended_at` = NULL WHERE `id` = ?")->execute([$run]);
    $this->pdo->prepare("UPDATE `run_nodes` SET `status` = 'available', `encounter_step_id` = 'invalid' WHERE `id` = ?")
      ->execute([$ids[3]]);
    try {
      $command->execute($user, $run, $ids[3], 'corrupt-step', $request);
      $this->fail('Corrupt step resolved.');
    } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame(1, $this->quantity($other));
    $this->assertSame(0, $this->quantity($user));
    $this->expectException(CurrentRunIntegrityException::class);
    $services['currentRunQuery']->execute($user);
  }

  public function testItemDisappearingAfterProjectionCannotAdvanceTheStep(): void
  {
    [$user, $run, $ids, $content] = $this->ready();
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $items = new UserItemRepository($this->pdo);
    $this->pdo->beginTransaction(); $items->increment($user, Fixture::ITEM_ID, 1); $this->pdo->commit();
    $this->assertSame(['continue', 'offer_wick'], array_column(
      $services['currentRunQuery']->execute($user)['run']['nodes'][3]['interaction']['actions'], 'id'));
    $revision = $services['currentRunQuery']->execute($user)['player_revision'];
    $this->pdo->beginTransaction(); $items->decrement($user, Fixture::ITEM_ID, 1); $this->pdo->commit();
    $request = ['encounter_id' => Fixture::ENCOUNTER_ID, 'action_id' => 'offer_wick'];
    $this->assertCode('invalid_node_request', fn() => $services['resolveRunNodeCommand']->execute(
      $user, $run, $ids[3], 'vanished-wick', $request));
    $this->assertSame($revision, $services['currentRunQuery']->execute($user)['player_revision']);
    $this->assertSame(['continue'], array_column(
      $services['currentRunQuery']->execute($user)['run']['nodes'][3]['interaction']['actions'], 'id'));
    $this->assertNull($this->rows('SELECT `encounter_step_id` FROM `run_nodes` WHERE `id` = ?', [$ids[3]])[0]['encounter_step_id']);
  }

  public function testCommitFailureRollsBackItemStepRevisionAndReceipt(): void
  {
    [$user, $run, $ids, $content] = $this->ready();
    $items = new UserItemRepository($this->pdo);
    $this->pdo->beginTransaction(); $items->increment($user, Fixture::ITEM_ID, 1); $this->pdo->commit();
    $state = new PlayerStateRepository($this->pdo);
    $before = $state->getPlayerState($user);
    $nodes = new RunNodeResolutionRepository($this->pdo);
    $command = new ResolveRunNodeCommand($this->pdo, $state, new RunPersistenceRepository($this->pdo),
      $nodes, new IdempotencyRequestRepository($this->pdo), [new ShrineNodeResolutionHandler(
        $nodes, new WarbandUnitRepository($this->pdo), $content, new RunModifierRepository($this->pdo),
        new BaseLevelStatResolver())], new SystemClock(),
      static function (): void { throw new RuntimeException('test rollback'); },
      new ContextualEncounterStepAdvancer($content, $items, $nodes));
    try {
      $command->execute($user, $run, $ids[3], 'rollback-wick',
        ['encounter_id' => Fixture::ENCOUNTER_ID, 'action_id' => 'offer_wick']);
      $this->fail('Forced rollback did not fail.');
    } catch (RuntimeException $e) { $this->assertSame('test rollback', $e->getMessage()); }
    $this->assertSame(1, $this->quantity($user));
    $this->assertNull($this->rows('SELECT `encounter_step_id` FROM `run_nodes` WHERE `id` = ?', [$ids[3]])[0]['encounter_step_id']);
    $this->assertSame($before, $state->getPlayerState($user));
    $this->assertNull((new IdempotencyRequestRepository($this->pdo))->getForUser($user, 'rollback-wick'));
  }

  /** @return array{int,int,array<int,int>,ContentRegistry} */
  private function ready(): array
  {
    $content = Fixture::registry();
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $user = $services['accountCreationService']->createLocal('wick-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Pilgrim');
    $this->trackUserId($user);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))->execute($user);
    $graph = (new FixedGraphRunGenerator())->generate($content->runGeneration(Fixture::GENERATION_ID));
    $repository = new RunPersistenceRepository($this->pdo);
    $run = $repository->createRun($user, 'region.the_farm', (int)$fixture['active_squad_id']);
    $ids = $repository->insertNodes($run, $graph['nodes']);
    $repository->insertEdges($run, $graph['edges'], $ids);
    $participants = [];
    foreach ($this->rows('SELECT ui.`id`, ui.`unit_type_id`, ui.`level` FROM `squad_units` su
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id` WHERE su.`squad_id` = ? AND ui.`user_id` = ? ORDER BY su.`position`',
      [(int)$fixture['active_squad_id'], $user]) as $unit) {
      $type = $content->unitType((string)$unit['unit_type_id']);
      $hp = (new BaseLevelStatResolver())->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
      $participants[] = ['unit_id' => (int)$unit['id'], 'current_hp' => $hp];
    }
    $repository->insertParticipatingUnits($run, $participants);
    $this->pdo->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = '2026-09-20 11:00:00'
      WHERE `run_id` = ? AND `node_index` IN (0, 1)")->execute([$run]);
    $this->pdo->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `run_id` = ? AND `node_index` IN (2, 3)")
      ->execute([$run]);
    return [$user, $run, $ids, $content];
  }

  private function quantity(int $user): int
  {
    return (int)($this->rows('SELECT `quantity` FROM `user_items` WHERE `user_id` = ? AND `item_id` = ?',
      [$user, Fixture::ITEM_ID])[0]['quantity'] ?? 0);
  }

  private function assertCode(string $expected, callable $action): void
  {
    try { $action(); $this->fail('Invalid step action succeeded.'); }
    catch (RunNodeResolutionException $e) { $this->assertSame($expected, $e->errorCode); }
  }

  /** @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
