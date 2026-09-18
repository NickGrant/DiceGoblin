<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Rewards\RewardApplicationIntegrityException;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Progression\UnitXpResolver;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Domain\Rewards\RewardResultException;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use RuntimeException;

final class RewardApplicationServiceTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testFinalizationAndAllGrantsCommitAtomicallyWithoutRevisionEnergyOrHpMutation(): void
  {
    [$userId, $unitIds, $runId] = $this->playerFixture();
    $rolls = new ScriptedRewardRollSource([1, 10000, 5000, 1, 1]);
    $service = $this->service($rolls);
    $context = $this->context($unitIds);

    $this->pdo?->beginTransaction();
    $result = $service->apply($userId, 'event.test_completed', 'run_node', 'run-node:41', $context);
    $this->pdo?->commit();

    $this->assertSame(5, $rolls->consumed());
    $this->assertSame(['granted', 'granted', 'granted', 'granted', 'already_owned'], array_column($result->entries(), 'outcome'));
    $state = $this->state($userId);
    $this->assertSame(['teeth' => 15, 'raw_chaos' => 5, 'energy_current' => 33, 'player_revision' => 7], $state);
    $this->assertSame(['level' => 2, 'xp' => 40], $this->unitProgression($unitIds[0]));
    $this->assertSame(['level' => 3, 'xp' => 0], $this->unitProgression($unitIds[1]));
    $this->assertSame('1', (string)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitIds[0]]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?', [$userId, 'unlock.region.mountains']));
    $this->assertSame('applied', (string)$this->scalar('SELECT `status` FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `applied_at` IS NOT NULL', [$userId]));
  }

  public function testAppliedRetryReturnsExactPersistedResultWithNoRollsOrSecondGrants(): void
  {
    [$userId, $unitIds] = $this->playerFixture(false);
    $firstRolls = new ScriptedRewardRollSource([1, 1, 1, 1, 1]);
    $this->pdo?->beginTransaction();
    $first = $this->service($firstRolls)->apply($userId, 'event.test_completed', 'test_command', 'retry:1', $this->context($unitIds));
    $this->pdo?->commit();
    $before = [$this->state($userId), $this->unitProgression($unitIds[0]), $this->unitProgression($unitIds[1])];

    $retryRolls = new ScriptedRewardRollSource([]);
    $this->pdo?->beginTransaction();
    $retry = $this->service($retryRolls)->apply($userId, 'event.test_completed', 'test_command', 'retry:1', null);
    $this->pdo?->commit();

    $this->assertSame($first->toArray(), $retry->toArray());
    $this->assertSame(0, $retryRolls->consumed());
    $this->assertSame($before, [$this->state($userId), $this->unitProgression($unitIds[0]), $this->unitProgression($unitIds[1])]);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?', [$userId]));
  }

  public function testStaleWalletAndUnitContextFailBeforeFinalization(): void
  {
    [$userId, $unitIds] = $this->playerFixture(false);
    $staleWallet = new RewardContext(4, 2, $this->unitContext($unitIds), []);
    $this->assertApplicationFailure($userId, 'stale-wallet', $staleWallet);

    $units = $this->unitContext($unitIds);
    $units[0]['xp'] = 89;
    $staleUnit = new RewardContext(5, 2, $units, []);
    $this->assertApplicationFailure($userId, 'stale-unit', $staleUnit);

    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(['teeth' => 5, 'raw_chaos' => 2, 'energy_current' => 33, 'player_revision' => 7], $this->state($userId));
  }

  public function testCrossUserUnitAndRawDatabaseUnlockAreRejected(): void
  {
    [$userId, $unitIds] = $this->playerFixture(false);
    [$otherId, $otherUnits] = $this->playerFixture(false);
    $crossUser = new RewardContext(5, 2, [
      ['unit_id' => $otherUnits[0], 'level' => 1, 'xp' => 90],
    ], []);
    $this->assertApplicationFailure($userId, 'cross-user', $crossUser);

    $this->pdo?->prepare('INSERT INTO `user_unlocks` (`user_id`, `unlock_id`) VALUES (?, ?)')->execute([$otherId, 'unlock.region.mountains']);
    $crossUserUnlock = new RewardContext(5, 2, $this->unitContext($unitIds), ['unlock.region.mountains']);
    $this->assertApplicationFailure($userId, 'cross-user-unlock', $crossUserUnlock);

    $this->pdo?->prepare('INSERT INTO `user_unlocks` (`user_id`, `unlock_id`) VALUES (?, ?)')->execute([$userId, 'unlock.database_only']);
    $rawUnlock = new RewardContext(5, 2, $this->unitContext($unitIds), ['unlock.database_only']);
    $this->assertApplicationFailure($userId, 'raw-unlock', $rawUnlock);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` IN (?, ?)', [$userId, $otherId]));
  }

  public function testCallerRollbackAfterAppliedTransitionRestoresEventAndEveryGrant(): void
  {
    [$userId, $unitIds] = $this->playerFixture(false);
    $before = [$this->state($userId), $this->unitProgression($unitIds[0]), $this->unitProgression($unitIds[1])];

    try {
      $this->pdo?->beginTransaction();
      $this->service(new ScriptedRewardRollSource([1, 1, 1, 1, 1]))
        ->apply($userId, 'event.test_completed', 'test_command', 'rollback:1', $this->context($unitIds));
      throw new RuntimeException('Forced outer command failure.');
    } catch (RuntimeException $e) {
      if ($this->pdo?->inTransaction()) $this->pdo->rollBack();
      $this->assertSame('Forced outer command failure.', $e->getMessage());
    }

    $this->assertSame($before, [$this->state($userId), $this->unitProgression($unitIds[0]), $this->unitProgression($unitIds[1])]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?', [$userId]));
  }

  public function testUnexpectedCommittedFinalizedEventFailsWithoutRollOrGrant(): void
  {
    [$userId, $unitIds] = $this->playerFixture(false);
    $result = (new RewardFinalizer(new ScriptedRewardRollSource([1, 1, 1, 1, 1]), new UnitXpResolver()))
      ->finalize($this->content()->event('event.test_completed'), $this->content()->rewardDefinition('reward_definition.test_completion'), 'test_command', 'orphan:1', $this->context($unitIds));
    $repo = new ResolvedEventRepository($this->pdo);
    $this->pdo?->beginTransaction();
    $repo->insertFinalized($userId, $result);
    $this->pdo?->commit();
    $rolls = new ScriptedRewardRollSource([]);

    $this->pdo?->beginTransaction();
    try {
      $this->service($rolls)->apply($userId, 'event.test_completed', 'test_command', 'orphan:1', $this->context($unitIds));
      $this->fail('Expected finalized-but-unapplied event to fail.');
    } catch (RewardApplicationIntegrityException $e) {
      $this->pdo?->rollBack();
      $this->assertStringContainsString('not applied atomically', $e->getMessage());
    }
    $this->assertSame(0, $rolls->consumed());
    $this->assertSame(['teeth' => 5, 'raw_chaos' => 2, 'energy_current' => 33, 'player_revision' => 7], $this->state($userId));
  }

  public function testApplicationRequiresCallerOwnedTransaction(): void
  {
    [$userId, $unitIds] = $this->playerFixture(false);
    $rolls = new ScriptedRewardRollSource([1, 1, 1, 1, 1]);

    try {
      $this->service($rolls)->apply($userId, 'event.test_completed', 'test_command', 'no-transaction:1', $this->context($unitIds));
      $this->fail('Expected application outside a transaction to fail.');
    } catch (RewardApplicationIntegrityException $e) {
      $this->assertStringContainsString('caller-owned transaction', $e->getMessage());
    }
    $this->assertSame(0, $rolls->consumed());
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
  }

  public function testRepositoryHydrationRejectsColumnJsonIdentityMismatch(): void
  {
    [$userId] = $this->playerFixture(false);
    $payload = [
      'version' => 1, 'event_id' => 'event.other', 'reward_definition_id' => 'reward_definition.test_completion',
      'source_type' => 'test_command', 'source_id' => 'mismatch:1', 'entries' => [],
    ];
    $this->pdo?->prepare('INSERT INTO `resolved_events` (`user_id`, `event_id`, `source_type`, `source_id`, `result_json`) VALUES (?, ?, ?, ?, ?)')
      ->execute([$userId, 'event.test_completed', 'test_command', 'mismatch:1', json_encode($payload, JSON_THROW_ON_ERROR)]);
    $this->expectException(RewardResultException::class);
    (new ResolvedEventRepository($this->pdo))->find($userId, 'event.test_completed', 'test_command', 'mismatch:1');
  }

  private function assertApplicationFailure(int $userId, string $sourceId, RewardContext $context): void
  {
    $rolls = new ScriptedRewardRollSource([1, 1, 1, 1, 1]);
    $this->pdo?->beginTransaction();
    try {
      $this->service($rolls)
        ->apply($userId, 'event.test_completed', 'test_command', $sourceId, $context);
      $this->fail('Expected reward application failure.');
    } catch (RewardApplicationIntegrityException) {
      $this->pdo?->rollBack();
    }
    $this->assertSame(0, $rolls->consumed());
  }

  /** @return array{0:int,1:list<int>,2?:int} */
  private function playerFixture(bool $withRunHp = true): array
  {
    $userId = (new UserRepository($this->pdo))->createUser('Reward Test', null);
    $this->trackUserId($userId);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `raw_chaos`, `energy_current`, `player_revision`) VALUES (?, 5, 2, 33, 7)')->execute([$userId]);
    $unitIds = [
      $this->createUnit($userId, 'First', 1, 90),
      $this->createUnit($userId, 'Second', 2, 150),
    ];
    if (!$withRunHp) return [$userId, $unitIds];
    $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)')->execute([$userId, 'Reward Squad']);
    $squadId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`) VALUES (?, ?, ?)')->execute([$userId, 'region.the_farm', $squadId]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, 1)')->execute([$runId, $unitIds[0]]);
    return [$userId, $unitIds, $runId];
  }

  private function createUnit(int $userId, string $name, int $level, int $xp): int
  {
    $this->pdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`) VALUES (?, ?, ?, ?, ?, ?)')
      ->execute([$userId, 'unit_type.fighter', 'kin.goblin', $name, $level, $xp]);
    return (int)$this->pdo?->lastInsertId();
  }

  /** @param list<int> $unitIds */
  private function context(array $unitIds): RewardContext
  {
    return new RewardContext(5, 2, $this->unitContext($unitIds), []);
  }

  /** @param list<int> $unitIds @return list<array{unit_id:int,level:int,xp:int}> */
  private function unitContext(array $unitIds): array
  {
    return [
      ['unit_id' => $unitIds[1], 'level' => 2, 'xp' => 150],
      ['unit_id' => $unitIds[0], 'level' => 1, 'xp' => 90],
    ];
  }

  private function service(ScriptedRewardRollSource $rolls): RewardApplicationService
  {
    return new RewardApplicationService(
      $this->pdo, $this->content(), new RewardFinalizer($rolls), new ResolvedEventRepository($this->pdo),
      new PlayerStateRepository($this->pdo), new WarbandUnitRepository($this->pdo), new UserUnlockRepository($this->pdo),
    );
  }

  private function content(): ContentRegistry
  {
    static $content = null;
    if ($content instanceof ContentRegistry) return $content;
    $canonical = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $definitions = [];
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability', 'dice_material', 'dice_aspect', 'dice_profile', 'run_node_type', 'run_generation', 'unlock'] as $type) {
      foreach ($canonical->definitionsOfType($type) as $definition) $definitions[] = $definition;
    }
    $definitions[] = ['id' => 'reward_definition.test_completion', 'type' => 'reward_definition', 'entries' => [
      ['key' => 'teeth', 'probability_basis_points' => 10000, 'reward_type' => 'currency', 'config' => ['currency_id' => 'teeth', 'amount' => 10]],
      ['key' => 'raw_chaos', 'probability_basis_points' => 10000, 'reward_type' => 'currency', 'config' => ['currency_id' => 'raw_chaos', 'amount' => 3]],
      ['key' => 'xp', 'probability_basis_points' => 10000, 'reward_type' => 'unit_xp', 'config' => ['target_scope' => 'participating_units', 'amount' => 50]],
      ['key' => 'mountains', 'probability_basis_points' => 10000, 'reward_type' => 'unlock', 'config' => ['unlock_id' => 'unlock.region.mountains']],
      ['key' => 'mountains_again', 'probability_basis_points' => 10000, 'reward_type' => 'unlock', 'config' => ['unlock_id' => 'unlock.region.mountains']],
    ]];
    $definitions[] = ['id' => 'event.test_completed', 'type' => 'event', 'reward_definition_id' => 'reward_definition.test_completion'];
    $root = sys_get_temp_dir() . '/dice-goblins-reward-app-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $path = $root . '/content.json';
    file_put_contents($path, json_encode(['definitions' => $definitions], JSON_THROW_ON_ERROR));
    try { $content = ContentRegistry::load($root); }
    finally { unlink($path); rmdir($root); }
    return $content;
  }

  /** @return array{teeth:int,raw_chaos:int,energy_current:int,player_revision:int} */
  private function state(int $userId): array
  {
    $stmt = $this->pdo?->prepare('SELECT `teeth`, `raw_chaos`, `energy_current`, `player_revision` FROM `user_state` WHERE `user_id` = ?');
    $stmt?->execute([$userId]);
    return $stmt?->fetch(\PDO::FETCH_ASSOC) ?: [];
  }

  /** @return array{level:int,xp:int} */
  private function unitProgression(int $unitId): array
  {
    $stmt = $this->pdo?->prepare('SELECT `level`, `xp` FROM `unit_instances` WHERE `id` = ?');
    $stmt?->execute([$unitId]);
    return $stmt?->fetch(\PDO::FETCH_ASSOC) ?: [];
  }
}
