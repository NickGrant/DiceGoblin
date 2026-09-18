<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Application\RunNodes\LootNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\RestNodeResolutionHandler;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use PDO;
use RuntimeException;

final class FarmLootRestNodeResolutionControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testLootAppliesAuthoredEightTeethPrivatelyAndReplaysExactlyOnce(): void
  {
    [$userId, $runId, $nodes] = $this->readyLoot('loot-success');
    $before = $this->state($userId);

    $first = $this->httpResolve($userId, $runId, $nodes[1], 'loot-resolve-key');

    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $data = $first['body']['data'];
    $this->assertSame('loot', $data['resolution_type'] ?? null);
    $this->assertSame(['teeth' => (int)$before['teeth'] + 8], $data['wallet'] ?? null);
    $this->assertEquals([['reward_type' => 'currency', 'currency_id' => 'teeth', 'amount' => 8]], $data['granted_rewards'] ?? null);
    $this->assertSame([(string)$nodes[2]], $data['newly_available_node_ids'] ?? null);
    $this->assertSame((int)$before['player_revision'] + 1, $data['player_revision'] ?? null);
    foreach (['event.farm_loot_completed', 'reward_definition', 'probability', 'roll', 'run_node:'] as $private) {
      $this->assertStringNotContainsString($private, json_encode($data, JSON_THROW_ON_ERROR));
    }
    $this->assertSame(['completed', 'completed', 'available', 'locked', 'locked'], $this->nodeStatuses($runId));
    $after = $this->state($userId);
    $this->assertSame((int)$before['teeth'] + 8, (int)$after['teeth']);
    foreach (['raw_chaos', 'energy_current', 'energy_last_regen_at'] as $field) $this->assertSame($before[$field], $after[$field]);
    $event = $this->row('SELECT `event_id`, `source_type`, `source_id`, `status`, `result_json`, `applied_at`
      FROM `resolved_events` WHERE `user_id` = ?', [$userId]);
    $this->assertSame(['event.farm_loot_completed', 'run_node', 'run_node:' . $nodes[1], 'applied'],
      [$event['event_id'], $event['source_type'], $event['source_id'], $event['status']]);
    $privateResult = json_decode((string)$event['result_json'], true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame([10000, 8, (int)$before['teeth'], (int)$before['teeth'] + 8], [
      $privateResult['entries'][0]['probability_basis_points'], $privateResult['entries'][0]['grant']['amount'],
      $privateResult['entries'][0]['grant']['balance_before'], $privateResult['entries'][0]['grant']['balance_after'],
    ]);
    $snapshot = $this->snapshot($userId, $runId);
    $replay = $this->httpResolve($userId, $runId, $nodes[1], 'loot-resolve-key');
    $this->assertSame($first['body'], $replay['body']);
    $this->assertSame($snapshot, $this->snapshot($userId, $runId));
    $different = $this->httpResolve($userId, $runId, $nodes[1], 'loot-another-key');
    $this->assertSame([409, 'run_node_already_resolved'], [$different['status'], $different['body']['error']['code'] ?? null]);
    $this->assertSame($snapshot, $this->snapshot($userId, $runId));
  }

  /** @dataProvider invalidLootEventProvider */
  public function testInvalidPersistedLootEventFailsAtomically(?string $eventId): void
  {
    [$userId, $runId, $nodes] = $this->readyLoot('loot-invalid-event');
    $this->pdo?->prepare('UPDATE `run_nodes` SET `event_id` = ? WHERE `id` = ?')->execute([$eventId, $nodes[1]]);
    $before = $this->snapshot($userId, $runId);

    $response = $this->httpResolve($userId, $runId, $nodes[1], 'loot-invalid-event-key');

    $this->assertSame([500, 'run_data_integrity_error'], [$response['status'], $response['body']['error']['code'] ?? null]);
    $this->assertSame($before, $this->snapshot($userId, $runId));
  }

  public function invalidLootEventProvider(): array
  {
    return ['missing' => [null], 'malformed' => ['reward_definition.farm_loot_completed'], 'unknown' => ['event.missing']];
  }

  public function testPostRewardFailureRollsBackEventWalletGraphRevisionAndReceipt(): void
  {
    [$userId, $runId, $nodes] = $this->readyLoot('loot-rollback');
    $before = $this->snapshot($userId, $runId);
    try {
      $this->command($this->throwingClock(), [1])->execute($userId, $runId, $nodes[1], 'loot-rollback-key');
      $this->fail('Expected injected post-reward failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Injected post-handler failure.', $e->getMessage());
    }
    $this->assertSame($before, $this->snapshot($userId, $runId));
  }

  public function testRestFullyRestoresZeroInjuredAndHealthyUnitsUsingCurrentLevelsAndReplaysOnce(): void
  {
    [$userId, $runId, $nodes, $fixture] = $this->readyRest('rest-success');
    $unitIds = $this->participantIds($runId);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 3, `xp` = 17 WHERE `id` = ?')->execute([$unitIds[0]]);
    $maxima = $this->maxima($unitIds);
    $beforeHp = [0, max(0, $maxima[$unitIds[1]] - 3), $maxima[$unitIds[2]], 1, 2];
    $update = $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = ? WHERE `run_id` = ? AND `unit_id` = ?');
    foreach ($unitIds as $index => $unitId) $update?->execute([$beforeHp[$index], $runId, $unitId]);
    $beforeState = $this->state($userId);
    $beforeProgression = $this->progression($unitIds);

    $first = $this->httpResolve($userId, $runId, $nodes[2], 'rest-resolve-key');

    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $data = $first['body']['data'];
    $this->assertSame('rest', $data['resolution_type'] ?? null);
    $this->assertSame([(string)$nodes[3]], $data['newly_available_node_ids'] ?? null);
    $expectedHealing = [];
    foreach ($unitIds as $index => $unitId) $expectedHealing[] = [
      'unit_id' => (string)$unitId, 'hp_before' => $beforeHp[$index],
      'hp_after' => $maxima[$unitId], 'max_hp' => $maxima[$unitId],
    ];
    $this->assertEquals($expectedHealing, $data['healing'] ?? null);
    $this->assertSame(['completed', 'completed', 'completed', 'available', 'locked'], $this->nodeStatuses($runId));
    $this->assertSame(array_values($maxima), array_map('intval', array_column(
      $this->rows('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]), 'current_hp')));
    $this->assertSame($beforeProgression, $this->progression($unitIds));
    $afterState = $this->state($userId);
    foreach (['teeth', 'raw_chaos', 'energy_current', 'energy_last_regen_at'] as $field) $this->assertSame($beforeState[$field], $afterState[$field]);
    $this->assertSame((int)$beforeState['player_revision'] + 1, (int)$afterState['player_revision']);

    $snapshot = $this->snapshot($userId, $runId);
    $replay = $this->httpResolve($userId, $runId, $nodes[2], 'rest-resolve-key');
    $this->assertSame($first['body'], $replay['body']);
    $this->assertSame($snapshot, $this->snapshot($userId, $runId));
    $different = $this->httpResolve($userId, $runId, $nodes[2], 'rest-another-key');
    $this->assertSame([409, 'run_node_already_resolved'], [$different['status'], $different['body']['error']['code'] ?? null]);
  }

  public function testInvalidRestParticipantAndHpFailWithoutPartialHealing(): void
  {
    foreach (['foreign', 'inactive', 'bad_type', 'hp_above_max'] as $corruption) {
      [$userId, $runId, $nodes, $fixture] = $this->readyRest('rest-invalid-' . $corruption);
      $unitId = $this->participantIds($runId)[0];
      if ($corruption === 'foreign') {
        $foreignUserId = ControllerServiceFactory::buildContentAware($this->pdo)['accountCreationService']->createLocal(
          'rest-foreign-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT), 'Other');
        $this->trackUserId($foreignUserId);
        $this->pdo?->prepare('UPDATE `unit_instances` SET `user_id` = ? WHERE `id` = ?')->execute([$foreignUserId, $unitId]);
      } elseif ($corruption === 'inactive') $this->pdo?->prepare("UPDATE `unit_instances` SET `lifecycle_status` = 'retired' WHERE `id` = ?")->execute([$unitId]);
      elseif ($corruption === 'bad_type') $this->pdo?->prepare("UPDATE `unit_instances` SET `unit_type_id` = 'unit_type.missing' WHERE `id` = ?")->execute([$unitId]);
      else $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 999999 WHERE `run_id` = ? AND `unit_id` = ?')->execute([$runId, $unitId]);
      $before = $this->snapshot($userId, $runId);
      $response = $this->httpResolve($userId, $runId, $nodes[2], 'rest-invalid-key-' . $corruption);
      $this->assertSame([500, 'run_data_integrity_error'], [$response['status'], $response['body']['error']['code'] ?? null]);
      $this->assertSame($before, $this->snapshot($userId, $runId));
    }
  }

  public function testPostHealFailureRollsBackEveryHpAndSharedMutation(): void
  {
    [$userId, $runId, $nodes, $fixture] = $this->readyRest('rest-rollback');
    $unitIds = $this->participantIds($runId);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 0 WHERE `run_id` = ?')->execute([$runId]);
    $before = $this->snapshot($userId, $runId);
    try {
      $this->command($this->throwingClock(), [])->execute($userId, $runId, $nodes[2], 'rest-rollback-key');
      $this->fail('Expected injected post-heal failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Injected post-handler failure.', $e->getMessage());
    }
    $this->assertSame($before, $this->snapshot($userId, $runId));
    $this->assertSame(array_fill(0, count($unitIds), 0), array_map('intval', array_column(
      $this->rows('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]), 'current_hp')));
  }

  /** @return array{0:int,1:int,2:list<int>} */
  private function readyLoot(string $prefix): array
  {
    [$userId, $fixture] = $this->fixtureAccount($prefix);
    [$runId, $nodes] = $this->startRun($userId, $prefix . '-start');
    $combat = $this->httpResolve($userId, $runId, $nodes[0], $prefix . '-combat');
    $this->assertSame(200, $combat['status'], json_encode($combat['body']));
    return [$userId, $runId, $nodes];
  }

  /** @return array{0:int,1:int,2:list<int>,3:array<string,mixed>} */
  private function readyRest(string $prefix): array
  {
    [$userId, $fixture] = $this->fixtureAccount($prefix);
    [$runId, $nodes] = $this->startRun($userId, $prefix . '-start');
    $this->assertSame(200, $this->httpResolve($userId, $runId, $nodes[0], $prefix . '-combat')['status']);
    $this->assertSame(200, $this->httpResolve($userId, $runId, $nodes[1], $prefix . '-loot')['status']);
    return [$userId, $runId, $nodes, $fixture];
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(string $prefix): array
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal($prefix . '-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Fighter');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $this->content()))->execute($userId);
    return [$userId, $fixture];
  }

  /** @return array{0:int,1:list<int>} */
  private function startRun(int $userId, string $key): array
  {
    $result = ControllerServiceFactory::buildContentAware($this->pdo)['startRunCommand']->execute(
      $userId, ['region_id' => 'region.the_farm'], $key);
    $runId = (int)$result['run']['id'];
    $nodes = array_map('intval', array_column($this->rows(
      'SELECT `id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'id'));
    return [$runId, $nodes];
  }

  /** @param list<int> $rolls */
  private function command(Clock $clock, array $rolls): ResolveRunNodeCommand
  {
    $content = $this->content();
    $state = new PlayerStateRepository($this->pdo);
    $nodes = new RunNodeResolutionRepository($this->pdo);
    $units = new WarbandUnitRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $rewards = new RewardApplicationService($this->pdo, $content,
      new RewardFinalizer(new ScriptedRewardRollSource($rolls)), new ResolvedEventRepository($this->pdo),
      $state, $units, $unlocks);
    return new ResolveRunNodeCommand($this->pdo, $state, new RunPersistenceRepository($this->pdo), $nodes,
      new IdempotencyRequestRepository($this->pdo), [
        new LootNodeResolutionHandler($nodes, $units, $unlocks, $rewards),
        new RestNodeResolutionHandler($nodes, $units, $content),
      ], $clock);
  }

  private function throwingClock(): Clock
  {
    return new class implements Clock {
      public function now(): DateTimeImmutable { throw new RuntimeException('Injected post-handler failure.'); }
    };
  }

  /** @param list<int> $unitIds @return array<int,int> */
  private function maxima(array $unitIds): array
  {
    $rows = $this->rows('SELECT `id`, `unit_type_id`, `level` FROM `unit_instances` WHERE `id` IN ('
      . implode(',', array_fill(0, count($unitIds), '?')) . ') ORDER BY `id`', $unitIds);
    $resolved = [];
    foreach ($rows as $row) {
      $type = $this->content()->unitType((string)$row['unit_type_id']);
      $resolved[(int)$row['id']] = (new BaseLevelStatResolver())->resolve(
        $type['base_stats'], $type['growth_per_level'], (int)$row['level'])->hp;
    }
    return $resolved;
  }

  /** @param list<int> $unitIds @return list<array<string,mixed>> */
  private function progression(array $unitIds): array
  {
    return $this->rows('SELECT `id`, `level`, `xp` FROM `unit_instances` WHERE `id` IN ('
      . implode(',', array_fill(0, count($unitIds), '?')) . ') ORDER BY `id`', $unitIds);
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function httpResolve(int $userId, int $runId, int $nodeId, string $key): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'node-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'node-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key; $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '';
    return $this->invoke(fn() => (new RunController())->resolveNode((string)$runId, (string)$nodeId));
  }

  /** @return list<string> */
  private function nodeStatuses(int $runId): array
  {
    return array_column($this->rows('SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status');
  }

  /** @return list<int> */
  private function participantIds(int $runId): array
  {
    return array_map('intval', array_column($this->rows(
      'SELECT `unit_id` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]), 'unit_id'));
  }

  /** @return array<string,mixed> */
  private function state(int $userId): array
  {
    return $this->row('SELECT `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`
      FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }

  /** @return array<string,mixed> */
  private function snapshot(int $userId, int $runId): array
  {
    return ['state' => $this->state($userId),
      'nodes' => $this->rows('SELECT `id`, `status`, `completed_at` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]),
      'hp' => $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]),
      'events' => $this->rows('SELECT `event_id`, `source_type`, `source_id`, `status`, `result_json`, `applied_at` FROM `resolved_events` WHERE `user_id` = ? ORDER BY `id`', [$userId]),
      'receipts' => $this->rows("SELECT `idempotency_key`, `result_json` FROM `idempotency_requests` WHERE `user_id` = ? AND `operation_type` = 'resolve_run_node' ORDER BY `idempotency_key`", [$userId])];
  }

  /** @param list<int|string|null> $params @return array<string,mixed> */
  private function row(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  /** @param list<int|string|null> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
}
