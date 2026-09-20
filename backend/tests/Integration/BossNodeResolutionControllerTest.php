<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Closure;
use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Application\RunNodes\BossNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\CombatNodeResolutionHandler;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
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
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use PDO;
use RuntimeException;

final class BossNodeResolutionControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  /** @dataProvider mountainsOwnershipProvider */
  public function testFarmBossVictoryAppliesXpMountainsAndUnlocksOnlyExitExactlyOnce(bool $preOwned): void
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal(
      'boss-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT), 'Boss Fighter');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $services['contentRegistry']))->execute($userId);
    $participantIds = array_map('intval', array_values($fixture['unit_ids']));
    sort($participantIds, SORT_NUMERIC);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 1, `xp` = 90 WHERE `id` = ?')->execute([$participantIds[0]]);
    if ($preOwned) {
      $this->pdo?->prepare("INSERT INTO `user_unlocks` (`user_id`, `unlock_id`, `granted_at`) VALUES (?, 'unlock.region.mountains', UTC_TIMESTAMP())")
        ->execute([$userId]);
    }
    $run = $services['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], 'boss-start-key');
    $runId = (int)$run['run']['id'];
    $nodes = $this->rows('SELECT `id`, `encounter_id`, `event_id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]);
    $bossId = (int)$nodes[3]['id']; $exitId = (int)$nodes[4]['id'];
    $this->assertSame(['encounter.the_farm_mud_boss_1', 'event.farm_boss_completed'],
      [$nodes[3]['encounter_id'], $nodes[3]['event_id']]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = CASE WHEN `id` = ? THEN 'available' ELSE `status` END WHERE `run_id` = ?")
      ->execute([$bossId, $runId]);
    $unitsBefore = $this->rows('SELECT ui.`id`, ui.`level`, ui.`xp`, rus.`current_hp` FROM `unit_instances` ui
      JOIN `run_unit_state` rus ON rus.`unit_id` = ui.`id` AND rus.`run_id` = ?
      WHERE ui.`user_id` = ? ORDER BY ui.`id`', [$runId, $userId]);
    $revision = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);

    $first = $this->resolve($userId, $runId, $bossId, 'boss-resolve-key');
    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $data = $first['body']['data'];
    $this->assertSame(['boss', 'victory', 'active'], [$data['resolution_type'], $data['battle']['outcome'], $data['run']['status']]);
    $this->assertSame([(string)$exitId], $data['newly_available_node_ids']);
    $this->assertSame($revision + 1, $data['player_revision']);
    $this->assertEquals([['unlock_id' => 'unlock.region.mountains',
      'outcome' => $preOwned ? 'already_owned' : 'granted']], $data['rewards']['unlocks']);
    $this->assertSame(array_map('strval', array_column($unitsBefore, 'id')),
      array_column($data['rewards']['unit_xp'], 'unit_id'));
    $this->assertSame(array_fill(0, count($unitsBefore), 16), array_column($data['rewards']['unit_xp'], 'amount'));
    $firstTransition = $data['rewards']['unit_xp'][0];
    $this->assertSame([1, 90, 2, 6], [$firstTransition['level_before'], $firstTransition['xp_before'],
      $firstTransition['level_after'], $firstTransition['xp_after']]);
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `run_node_id` = ?', [$runId, $bossId]));
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.mountains'", [$userId]));
    $this->assertSame('applied', $this->scalar("SELECT `status` FROM `resolved_events` WHERE `user_id` = ? AND `event_id` = 'event.farm_boss_completed'", [$userId]));
    $this->assertSame(['completed', 'available'], array_column($this->rows(
      'SELECT `status` FROM `run_nodes` WHERE `id` IN (?, ?) ORDER BY `node_index`', [$bossId, $exitId]), 'status'));
    foreach (['event.farm_boss_completed', 'reward_definition.', 'probability', 'roll', 'run_node:'] as $private) {
      $this->assertStringNotContainsString($private, json_encode($data, JSON_THROW_ON_ERROR));
    }

    $_SESSION['user_id'] = $userId;
    $current = $this->invoke(fn() => (new RunController())->current());
    $this->assertSame(200, $current['status'], json_encode($current['body']));
    $currentData = $current['body']['data'];
    $currentNodes = array_column($currentData['run']['nodes'], null, 'id');
    $this->assertSame(['active', 'completed', (string)$data['battle']['id'], 'available'], [
      $currentData['run']['status'], $currentNodes[(string)$bossId]['status'],
      $currentNodes[(string)$bossId]['battle_id'], $currentNodes[(string)$exitId]['status'],
    ]);
    $persistedHp = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
    $this->assertSame(array_map(static fn(array $row): array => [
      'unit_id' => (string)$row['unit_id'], 'current_hp' => (int)$row['current_hp'],
    ], $persistedHp), $currentData['run']['units']);
    $encodedCurrent = json_encode($currentData, JSON_THROW_ON_ERROR);
    foreach (['encounter.the_farm_mud_boss_1', 'event.farm_boss_completed', 'enemy_unit_type.mudking',
      'reward_definition.', 'unlock.region.mountains', 'probability', 'roll', 'run_node:'] as $private) {
      $this->assertStringNotContainsString($private, $encodedCurrent);
    }

    $snapshot = $this->rows('SELECT ui.`id`, ui.`level`, ui.`xp`, rus.`current_hp` FROM `unit_instances` ui
      JOIN `run_unit_state` rus ON rus.`unit_id` = ui.`id` AND rus.`run_id` = ? WHERE ui.`user_id` = ? ORDER BY ui.`id`', [$runId, $userId]);
    $replay = $this->resolve($userId, $runId, $bossId, 'boss-resolve-key');
    $this->assertSame($first['body'], $replay['body']);
    $this->assertSame($snapshot, $this->rows('SELECT ui.`id`, ui.`level`, ui.`xp`, rus.`current_hp` FROM `unit_instances` ui
      JOIN `run_unit_state` rus ON rus.`unit_id` = ui.`id` AND rus.`run_id` = ? WHERE ui.`user_id` = ? ORDER BY ui.`id`', [$runId, $userId]));
    $different = $this->resolve($userId, $runId, $bossId, 'boss-other-key');
    $this->assertSame([409, 'run_node_already_resolved'], [$different['status'], $different['body']['error']['code'] ?? null]);
  }

  public function mountainsOwnershipProvider(): array
  {
    return ['new unlock' => [false], 'already owned' => [true]];
  }

  /** @dataProvider failedOutcomeProvider */
  public function testBossDefeatAndStalematePersistFailureWithoutRewards(string $outcome): void
  {
    [$userId, $fixture, $runId, $bossId, $exitId] = $this->bossFixture('boss-' . $outcome);
    $before = $this->stateSnapshot($userId, $runId);
    $resolver = $this->fixedResolver($outcome);

    $result = $this->command($resolver, $this->fixedClock('2026-09-19 14:15:16'))->execute(
      $userId, $runId, $bossId, 'boss-' . $outcome . '-key',
    );

    $this->assertSame(['boss', $outcome, null, 'completed', [], 'failed', '2026-09-19T14:15:16Z'], [
      $result['resolution_type'], $result['battle']['outcome'], $result['rewards'], $result['node']['status'],
      $result['newly_available_node_ids'], $result['run']['status'], $result['run']['ended_at'],
    ]);
    $this->assertSame(1, $resolver->calls);
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ?', [$runId]));
    $this->assertSame($result['terminal_player_hp'], $this->hpByUnit($runId));
    if ($outcome === 'defeat') $this->assertSame([0], array_values(array_unique($result['terminal_player_hp'])));
    $this->assertSame(['failed', '2026-09-19 14:15:16'], array_values($this->row(
      'SELECT `status`, `ended_at` FROM `runs` WHERE `id` = ?', [$runId])));
    $this->assertSame(['completed', 'locked'], array_column($this->rows(
      'SELECT `status` FROM `run_nodes` WHERE `id` IN (?, ?) ORDER BY `node_index`', [$bossId, $exitId]), 'status'));
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `event_id` = 'event.farm_boss_completed'", [$userId]));
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.mountains'", [$userId]));
    $this->assertSame($before['units'], $this->unitProgress($userId));
    $this->assertSame((int)$before['state']['player_revision'] + 1,
      (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function failedOutcomeProvider(): array
  {
    return ['defeat' => ['defeat'], 'stalemate' => ['stalemate']];
  }

  public function testDefeatedParticipantStillReceivesBossXpWithoutHpAdjustment(): void
  {
    [$userId, $fixture, $runId, $bossId] = $this->bossFixture('boss-defeated-xp');
    $unitId = (int)$this->scalar('SELECT `unit_id` FROM `squad_units` WHERE `squad_id` = ? ORDER BY `position` LIMIT 1',
      [(int)$fixture['active_squad_id']]);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 1, `xp` = 90 WHERE `id` = ?')->execute([$unitId]);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 1 WHERE `run_id` = ? AND `unit_id` = ?')
      ->execute([$runId, $unitId]);
    $resolver = $this->fixedResolver('victory', $unitId);

    $result = $this->command($resolver, $this->fixedClock('2026-09-19 15:00:00'))->execute(
      $userId, $runId, $bossId, 'boss-defeated-xp-key',
    );
    $transition = array_values(array_filter($result['rewards']['unit_xp'],
      static fn(array $row): bool => $row['unit_id'] === (string)$unitId))[0];
    $this->assertSame(0, $result['terminal_player_hp'][(string)$unitId]);
    $this->assertSame(0, (int)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitId]));
    $this->assertSame([(string)$unitId, 16, 1, 90, 2, 6], [
      $transition['unit_id'], $transition['amount'], $transition['level_before'], $transition['xp_before'],
      $transition['level_after'], $transition['xp_after'],
    ]);
    $this->assertSame([2, 6], array_map('intval', array_values($this->row(
      'SELECT `level`, `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]))));
  }

  public function testBossSameKeyReplayIsExactlyOnceAcrossCombatRewardsAndGraph(): void
  {
    [$userId, , $runId, $bossId, $exitId] = $this->bossFixture('boss-idempotent');
    $resolver = $this->fixedResolver('victory');
    $command = $this->command($resolver, $this->fixedClock('2026-09-19 16:00:00'));
    $first = $command->execute($userId, $runId, $bossId, 'boss-exact-key');
    $snapshot = $this->stateSnapshot($userId, $runId);
    $replay = $command->execute($userId, $runId, $bossId, 'boss-exact-key');

    $this->assertSame($first, $replay);
    $this->assertSame(1, $resolver->calls);
    $this->assertSame($snapshot, $this->stateSnapshot($userId, $runId));
    $this->assertSame('1', $snapshot['battles']);
    $this->assertSame('1', $snapshot['resolved_events']);
    $this->assertSame('1', $snapshot['mountains']);
    $this->assertSame('available', $this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$exitId]));
  }

  /** @dataProvider invalidIdentityProvider */
  public function testInvalidBossEncounterOrEventIdentityRollsBackAtomically(string $column, mixed $value): void
  {
    [$userId, , $runId, $bossId] = $this->bossFixture('boss-identity-' . $column);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `{$column}` = ? WHERE `id` = ?")->execute([$value, $bossId]);
    $before = $this->stateSnapshot($userId, $runId);

    $response = $this->resolve($userId, $runId, $bossId, 'boss-invalid-' . bin2hex(random_bytes(3)));

    $this->assertSame([500, 'run_data_integrity_error'], [$response['status'], $response['body']['error']['code'] ?? null]);
    $this->assertSame($before, $this->stateSnapshot($userId, $runId));
  }

  public function invalidIdentityProvider(): array
  {
    return [
      'missing encounter' => ['encounter_id', null],
      'unknown encounter' => ['encounter_id', 'encounter.missing'],
      'missing event' => ['event_id', null],
      'malformed event' => ['event_id', 'reward_definition.farm_boss_completion'],
    ];
  }

  public function testBossEventWithUnsupportedRewardTypeRollsBackAtomically(): void
  {
    [$userId, , $runId, $bossId] = $this->bossFixture('boss-unsupported-reward');
    $this->pdo?->prepare("UPDATE `run_nodes` SET `event_id` = 'event.farm_loot_completed' WHERE `id` = ?")
      ->execute([$bossId]);
    $before = $this->stateSnapshot($userId, $runId);

    $response = $this->resolve($userId, $runId, $bossId, 'boss-unsupported-reward-key');

    $this->assertSame([500, 'run_data_integrity_error'], [$response['status'], $response['body']['error']['code'] ?? null]);
    $this->assertSame($before, $this->stateSnapshot($userId, $runId));
  }

  public function testInjectedPostCombatAndPostRewardFailuresRollBackParentTransaction(): void
  {
    [$combatUser, , $combatRun, $combatBoss] = $this->bossFixture('boss-post-combat');
    $combatBefore = $this->stateSnapshot($combatUser, $combatRun);
    try {
      $this->command($this->fixedResolver('victory'), $this->fixedClock('2026-09-19 17:00:00'),
        static fn() => throw new RuntimeException('Injected post-combat failure.'))
        ->execute($combatUser, $combatRun, $combatBoss, 'boss-post-combat-key');
      $this->fail('Expected injected post-combat failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Injected post-combat failure.', $e->getMessage());
    }
    $this->assertSame($combatBefore, $this->stateSnapshot($combatUser, $combatRun));

    [$rewardUser, , $rewardRun, $rewardBoss] = $this->bossFixture('boss-post-reward');
    $rewardBefore = $this->stateSnapshot($rewardUser, $rewardRun);
    try {
      $this->command($this->fixedResolver('victory'), $this->fixedClock('2026-09-19 17:30:00'), null,
        static fn() => throw new RuntimeException('Injected post-reward failure.'))
        ->execute($rewardUser, $rewardRun, $rewardBoss, 'boss-post-reward-key');
      $this->fail('Expected injected post-reward failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Boss reward resolution failed.', $e->getMessage());
      $this->assertSame('Injected post-reward failure.', $e->getPrevious()?->getMessage());
    }
    $this->assertSame($rewardBefore, $this->stateSnapshot($rewardUser, $rewardRun));
  }

  /** @return array{0:int,1:array<string,mixed>,2:int,3:int,4:int} */
  private function bossFixture(string $token): array
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal(
      $token . '-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT), 'Boss Fighter');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo),
      $services['contentRegistry']))->execute($userId);
    $run = $services['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], $token . '-start-key');
    $runId = (int)$run['run']['id'];
    $nodes = $this->rows('SELECT `id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]);
    $bossId = (int)$nodes[3]['id']; $exitId = (int)$nodes[4]['id'];
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` = ?")->execute([$bossId]);
    return [$userId, $fixture, $runId, $bossId, $exitId];
  }

  private function command(
    CombatResolver $resolver, Clock $clock, ?Closure $afterCombat = null, ?Closure $afterRewards = null,
  ): ResolveRunNodeCommand
  {
    $content = $this->content();
    $units = new WarbandUnitRepository($this->pdo);
    $nodes = new RunNodeResolutionRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $combat = new CombatNodeResolutionHandler($nodes, new BattlePersistenceRepository($this->pdo),
      new CombatSnapshotAssembler($content, new SquadRepository($this->pdo), new UnitDetailQuery($units, $content),
        new WarbandDiceRepository($this->pdo)), $resolver, new CombatSeedDeriver());
    $rewards = new RewardApplicationService($this->pdo, $content,
      new RewardFinalizer(new ScriptedRewardRollSource([1, 1])), new ResolvedEventRepository($this->pdo),
      new PlayerStateRepository($this->pdo), $units, $unlocks);
    return new ResolveRunNodeCommand($this->pdo, new PlayerStateRepository($this->pdo),
      new RunPersistenceRepository($this->pdo), $nodes, new IdempotencyRequestRepository($this->pdo),
      [new BossNodeResolutionHandler($combat, $nodes, $units, $unlocks, $rewards, $afterCombat, $afterRewards)], $clock);
  }

  private function fixedResolver(string $outcome, ?int $defeatedUnitId = null): CombatResolver
  {
    return new class($outcome, $defeatedUnitId) implements CombatResolver {
      public int $calls = 0;
      public function __construct(private readonly string $outcome, private readonly ?int $defeatedUnitId) {}
      public function resolve(CombatInput $input): CombatResult {
        $this->calls++;
        $terminal = [];
        $defeatedPlayer = false;
        foreach ($input->combatants as $key => $unit) {
          $hp = match ($this->outcome) {
            'defeat' => $unit['side'] === 'player' ? 0 : $unit['current_hp'],
            'victory' => $unit['side'] === 'enemy' || ($this->defeatedUnitId !== null
              && $unit['side'] === 'player' && !$defeatedPlayer) ? 0 : $unit['current_hp'],
            default => $unit['current_hp'],
          };
          if ($this->outcome === 'victory' && $this->defeatedUnitId !== null && $unit['side'] === 'player' && !$defeatedPlayer) {
            $defeatedPlayer = true;
          }
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

  private function fixedClock(string $time): Clock
  {
    return new class(new DateTimeImmutable($time, new DateTimeZone('UTC'))) implements Clock {
      public function __construct(private readonly DateTimeImmutable $time) {}
      public function now(): DateTimeImmutable { return $this->time; }
    };
  }

  /** @return array<string,mixed> */
  private function stateSnapshot(int $userId, int $runId): array
  {
    return [
      'state' => $this->row('SELECT `teeth`, `raw_chaos`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      'run' => $this->row('SELECT `status`, `ended_at` FROM `runs` WHERE `id` = ?', [$runId]),
      'nodes' => $this->rows('SELECT `id`, `status`, `completed_at` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]),
      'hp' => $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]),
      'units' => $this->unitProgress($userId),
      'battles' => (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ?', [$runId]),
      'resolved_events' => (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `event_id` = 'event.farm_boss_completed'", [$userId]),
      'mountains' => (string)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.mountains'", [$userId]),
      'receipts' => (string)$this->scalar("SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ? AND `operation_type` = 'resolve_run_node'", [$userId]),
    ];
  }

  /** @return list<array<string,mixed>> */
  private function unitProgress(int $userId): array
  {
    return $this->rows('SELECT `id`, `level`, `xp` FROM `unit_instances` WHERE `user_id` = ? ORDER BY `id`', [$userId]);
  }

  /** @return array<string,int> */
  private function hpByUnit(int $runId): array
  {
    $result = [];
    foreach ($this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]) as $row) {
      $result[(string)$row['unit_id']] = (int)$row['current_hp'];
    }
    return $result;
  }

  /** @param list<int|string> $params @return array<string,mixed> */
  private function row(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }

  /** @return array{status:int,body:array<string,mixed>} */
  private function resolve(int $userId, int $runId, int $nodeId, string $key): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'boss-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'boss-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key; $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '';
    return $this->invoke(fn() => (new RunController())->resolveNode((string)$runId, (string)$nodeId));
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  { $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []; }
}
