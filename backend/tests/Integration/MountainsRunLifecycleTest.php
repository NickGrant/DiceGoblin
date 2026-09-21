<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Application\RunNodes\BossNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\CombatNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\ExitNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\LootNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\RestNodeResolutionHandler;
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
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use PDO;

final class MountainsRunLifecycleTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testAuthoredMountainsRunUsesSharedResolutionAndTerminalLifecycle(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('mountains-lifecycle');
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    try {
      $services['startRunCommand']->execute($userId, ['region_id' => 'region.mountains'], 'mountains-public-start');
      $this->fail('Mountains public start must remain unavailable.');
    } catch (RunStartException $e) {
      $this->assertSame(['run_region_unsupported', 422], [$e->errorCode, $e->httpStatus]);
    }

    [$runId, $nodeIds] = $this->persistMountainsRun($userId, $fixture);
    $current = (new CurrentRunQuery(new RunPersistenceRepository($this->pdo), new PlayerStateRepository($this->pdo),
      $this->content()))->execute($userId)['run'];
    $this->assertSame('region.mountains', $current['region_id']);
    $this->assertSame([0, 1, 2, 3, 4, 5, 6], array_column($current['nodes'], 'node_index'));
    $this->assertSame([0, 1, 2, 3, 4, 5, 6], array_column(array_column($current['nodes'], 'position'), 'column'));
    $this->assertSame(['available', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked'],
      array_column($current['nodes'], 'status'));

    $command = $this->command($this->fixedResolver('victory'));
    $combatOne = $command->execute($userId, $runId, $nodeIds[0], 'mountains-combat-1');
    $this->assertBattleResolution($combatOne, $runId, $nodeIds[0], $nodeIds[1]);
    $this->assertStatuses($runId, ['completed', 'available', 'locked', 'locked', 'locked', 'locked', 'locked']);

    $teethBefore = (int)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $loot = $command->execute($userId, $runId, $nodeIds[1], 'mountains-loot');
    $this->assertSame([8, $teethBefore + 8, [(string)$nodeIds[2]]], [
      $loot['granted_rewards'][0]['amount'], $loot['wallet']['teeth'], $loot['newly_available_node_ids'],
    ]);
    $lootReplay = $command->execute($userId, $runId, $nodeIds[1], 'mountains-loot');
    $this->assertSame($loot, $lootReplay);
    $this->assertSame($teethBefore + 8, (int)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));

    $combatTwo = $command->execute($userId, $runId, $nodeIds[2], 'mountains-combat-2');
    $this->assertBattleResolution($combatTwo, $runId, $nodeIds[2], $nodeIds[3]);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 1 WHERE `run_id` = ?')->execute([$runId]);
    $rest = $command->execute($userId, $runId, $nodeIds[3], 'mountains-rest');
    $this->assertSame([(string)$nodeIds[4]], $rest['newly_available_node_ids']);
    foreach ($rest['healing'] as $transition) {
      $this->assertSame(1, $transition['hp_before']);
      $this->assertSame($transition['max_hp'], $transition['hp_after']);
    }

    $combatThree = $command->execute($userId, $runId, $nodeIds[4], 'mountains-combat-3');
    $this->assertBattleResolution($combatThree, $runId, $nodeIds[4], $nodeIds[5]);
    $progressBefore = $this->unitProgress($userId);
    $boss = $command->execute($userId, $runId, $nodeIds[5], 'mountains-boss');
    $this->assertBattleResolution($boss, $runId, $nodeIds[5], $nodeIds[6], 'boss');
    $this->assertSame([], $boss['rewards']['unlocks']);
    $participantIds = array_column($this->rows(
      'SELECT `unit_id` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]), 'unit_id');
    $this->assertSame(array_map('strval', $participantIds), array_column($boss['rewards']['unit_xp'], 'unit_id'));
    $this->assertSame(array_fill(0, count($participantIds), 16), array_column($boss['rewards']['unit_xp'], 'amount'));
    $progressAfter = $this->unitProgress($userId);
    $bossReplay = $command->execute($userId, $runId, $nodeIds[5], 'mountains-boss');
    $this->assertSame($boss, $bossReplay);
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
    $this->assertSame(['completed', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked'],
      array_column($this->rows('SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status'));
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

  private function command(CombatResolver $resolver): ResolveRunNodeCommand
  {
    $content = $this->content();
    $state = new PlayerStateRepository($this->pdo);
    $nodes = new RunNodeResolutionRepository($this->pdo);
    $units = new WarbandUnitRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $battles = new BattlePersistenceRepository($this->pdo);
    $combat = new CombatNodeResolutionHandler($nodes, $battles,
      new CombatSnapshotAssembler($content, new SquadRepository($this->pdo), new UnitDetailQuery($units, $content),
        new WarbandDiceRepository($this->pdo)), $resolver, new CombatSeedDeriver());
    $rewards = new RewardApplicationService($this->pdo, $content,
      new RewardFinalizer(new ScriptedRewardRollSource([1, 1])), new ResolvedEventRepository($this->pdo),
      $state, $units, $unlocks);
    return new ResolveRunNodeCommand($this->pdo, $state, new RunPersistenceRepository($this->pdo), $nodes,
      new IdempotencyRequestRepository($this->pdo), [
        $combat, new BossNodeResolutionHandler($combat, $nodes, $units, $unlocks, $rewards),
        new LootNodeResolutionHandler($nodes, $units, $unlocks, $rewards),
        new RestNodeResolutionHandler($nodes, $units, $content), new ExitNodeResolutionHandler($nodes),
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

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
}
