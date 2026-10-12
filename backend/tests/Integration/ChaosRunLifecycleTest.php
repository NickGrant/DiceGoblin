<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Application\RunNodes\ChaosNodeResolutionHandler;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Infrastructure\SystemClock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\ChaosContentFixture;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

final class ChaosRunLifecycleTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testBothServerSelectedOutcomesFinalizeOnceAndRetainTheFrontier(): void
  {
    foreach ([1 => 'mend', 2 => 'wound'] as $roll => $outcomeId) {
      [$userId, $runId, $ids, $content] = $this->readyChaos($outcomeId);
      $query = ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['currentRunQuery'];
      $read = $query->execute($userId)['run'];
      $interaction = $read['nodes'][3]['interaction'];
      $this->assertSame(['encounter_id', 'title', 'body', 'prompt', 'actions'], array_keys($interaction));
      $this->assertSame(ChaosContentFixture::ENCOUNTER_ID, $interaction['encounter_id']);
      $this->assertSame([['id' => 'accept', 'label' => 'ACCEPT SURGE']], $interaction['actions']);
      $serialized = json_encode($read, JSON_THROW_ON_ERROR);
      foreach (['random_participating_hp', 'weight', 'mend', 'wound', 'damage_all_participating_units'] as $private) {
        $this->assertStringNotContainsString($private, $serialized);
      }
      $this->assertSame([(string)$ids[2], (string)$ids[3]], $read['available_node_ids']);
      $rolls = 0;
      $command = $this->command($content, static function (int $total) use ($roll, &$rolls): int {
        ++$rolls;
        if ($total !== 2) throw new \RuntimeException('Unexpected Chaos weight total.');
        return $roll;
      });
      $before = (new PlayerStateRepository($this->pdo))->getPlayerState($userId);
      $request = ['encounter_id' => ChaosContentFixture::ENCOUNTER_ID, 'action_id' => 'accept'];
      $result = $command->execute($userId, $runId, $ids[3], 'chaos-' . $outcomeId, $request);
      $this->assertSame(['chaos', 'accept', $outcomeId, [(string)$ids[4]], $before['player_revision'] + 1], [
        $result['resolution_type'], $result['action_id'], $result['outcome_id'],
        $result['newly_available_node_ids'], $result['player_revision'],
      ]);
      $this->assertArrayNotHasKey('wallet', $result);
      $this->assertArrayNotHasKey('granted_rewards', $result);
      $this->assertArrayNotHasKey('weight', $result);
      $this->assertCount(5, $result['hp_changes']);
      foreach ($result['hp_changes'] as $change) {
        $this->assertSame(5, $change['hp_before']);
        $this->assertSame($outcomeId === 'mend' ? $change['max_hp'] : 3, $change['hp_after']);
      }
      $this->assertSame(1, $rolls);
      $this->assertSame($result, $command->execute($userId, $runId, $ids[3], 'chaos-' . $outcomeId, $request));
      $this->assertSame(1, $rolls);
      $this->assertErrorCode('run_node_already_resolved',
        fn() => $command->execute($userId, $runId, $ids[3], 'second-' . $outcomeId, $request));
      $after = (new PlayerStateRepository($this->pdo))->getPlayerState($userId);
      $this->assertSame($before['teeth'], $after['teeth']);
      $this->assertSame($before['raw_chaos'], $after['raw_chaos']);
      $this->assertSame($before['player_revision'] + 1, $after['player_revision']);
      $final = $query->execute($userId)['run'];
      $this->assertSame([(string)$ids[2], (string)$ids[4]], $final['available_node_ids']);
      $this->assertArrayNotHasKey('interaction', $final['nodes'][3]);
      $this->assertSame(array_column($result['hp_changes'], 'hp_after'), array_column($final['units'], 'current_hp'));
      $reloaded = new PDO((string)getenv('TEST_DB_DSN'), (string)getenv('TEST_DB_USER'),
        (string)getenv('TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      $this->assertSame($final, (new CurrentRunQuery(new RunPersistenceRepository($reloaded),
        new PlayerStateRepository($reloaded), $content))->execute($userId)['run']);
    }
  }

  public function testInvalidUnavailableForeignAndCorruptChaosAttemptsDoNotMutateOrRoll(): void
  {
    [$userId, $runId, $ids, $content] = $this->readyChaos('guards');
    $rolls = 0;
    $command = $this->command($content, static function (int $total) use (&$rolls): int { ++$rolls; return 1; });
    $request = ['encounter_id' => ChaosContentFixture::ENCOUNTER_ID, 'action_id' => 'accept'];
    $before = (new PlayerStateRepository($this->pdo))->getPlayerState($userId);
    $hpBefore = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'locked' WHERE `id` = ?")->execute([$ids[3]]);
    $this->assertErrorCode('run_node_unavailable', fn() => $command->execute($userId, $runId, $ids[3], 'chaos-locked', $request));
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` = ?")->execute([$ids[3]]);
    foreach ([
      ['encounter_id' => ChaosContentFixture::ENCOUNTER_ID, 'action_id' => 'missing'],
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'accept'],
    ] as $index => $invalid) {
      $this->assertErrorCode('invalid_node_request',
        fn() => $command->execute($userId, $runId, $ids[3], 'invalid-' . $index, $invalid));
    }
    $other = ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['accountCreationService']
      ->createLocal('chaos-foreign-' . bin2hex(random_bytes(4)) . '@example.test',
        password_hash('test-password', PASSWORD_DEFAULT), 'Visitor');
    $this->trackUserId($other);
    $this->assertErrorCode('run_node_not_found', fn() => $command->execute($other, $runId, $ids[3], 'chaos-foreign', $request));
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = '2026-09-20 12:00:00' WHERE `id` = ?")
      ->execute([$runId]);
    $this->assertErrorCode('run_not_active', fn() => $command->execute($userId, $runId, $ids[3], 'chaos-terminal', $request));
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'active', `ended_at` = NULL WHERE `id` = ?")->execute([$runId]);
    foreach ([null, 'encounter.missing', 'encounter.the_farm_rest'] as $index => $encounterId) {
      $this->pdo?->prepare('UPDATE `run_nodes` SET `encounter_id` = ? WHERE `id` = ?')
        ->execute([$encounterId, $ids[3]]);
      try {
        ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['currentRunQuery']->execute($userId);
        $this->fail('Invalid persisted Chaos association was projected.');
      } catch (CurrentRunIntegrityException) { $this->addToAssertionCount(1); }
      try {
        $command->execute($userId, $runId, $ids[3], 'corrupt-' . $index,
          ['encounter_id' => $encounterId ?? ChaosContentFixture::ENCOUNTER_ID, 'action_id' => 'accept']);
        $this->fail('Invalid persisted Chaos association was resolved.');
      } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    }
    $this->pdo?->prepare('UPDATE `run_nodes` SET `encounter_id` = ? WHERE `id` = ?')
      ->execute([ChaosContentFixture::ENCOUNTER_ID, $ids[3]]);
    $this->pdo?->prepare('UPDATE `runs` SET `region_id` = ? WHERE `id` = ?')
      ->execute(['region.mountains', $runId]);
    try {
      $command->execute($userId, $runId, $ids[3], 'wrong-region', $request);
      $this->fail('Foreign-region Chaos was resolved.');
    } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame(0, $rolls);
    $this->assertSame($before, (new PlayerStateRepository($this->pdo))->getPlayerState($userId));
    $this->assertSame($hpBefore, $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]));
  }

  private function command(ContentRegistry $content, \Closure $chooseRoll): ResolveRunNodeCommand
  {
    $nodes = new RunNodeResolutionRepository($this->pdo);
    return new ResolveRunNodeCommand($this->pdo, new PlayerStateRepository($this->pdo),
      new RunPersistenceRepository($this->pdo), $nodes, new IdempotencyRequestRepository($this->pdo),
      [new ChaosNodeResolutionHandler($nodes, new WarbandUnitRepository($this->pdo), $content,
        new BaseLevelStatResolver(), $chooseRoll)], new SystemClock());
  }

  /** @return array{0:int,1:int,2:array<int,int>,3:ContentRegistry} */
  private function readyChaos(string $token): array
  {
    $content = ChaosContentFixture::registry();
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $userId = $services['accountCreationService']->createLocal('chaos-' . $token . '-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Pilgrim');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))
      ->execute($userId);
    $graph = (new FixedGraphRunGenerator())->generate($content->runGeneration(ChaosContentFixture::GENERATION_ID));
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
      $this->fail('Invalid Chaos operation succeeded.');
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
