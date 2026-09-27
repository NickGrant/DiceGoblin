<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ActiveRunConfigurationLockedException;
use DiceGoblins\Application\Commands\ActiveRunConfigurationPolicy;
use DiceGoblins\Application\Commands\DiceLifecycleCommand;
use DiceGoblins\Application\Commands\DiceLifecycleException;
use DiceGoblins\Application\Commands\DiceLifecycleIntegrityException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Queries\DiceCollectionQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\WarbandController;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Support\ClientSafeInteger;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class DiceLifecycleCommandTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testSellAndSalvageCreditWalletTransitionRecordAndRemoveFromActiveCollection(): void
  {
    $userId = $this->user(2, 3, 7);
    $sellId = $this->die($userId, 8, 'dice_profile.bone_plain');
    $salvageId = $this->die($userId, 6, 'dice_profile.wood_plain');
    $command = $this->command();

    $sold = $command->sell($userId, $sellId, 'dice-sell-success');
    $this->assertSame(['19', '21', '8', 'sold'], [(string)$sold['teeth_awarded'], (string)$sold['teeth'],
      (string)$sold['player_revision'], $sold['lifecycle_status']]);
    $salvaged = $command->salvage($userId, $salvageId, 'dice-salvage-success');
    $this->assertSame(['4', '7', '9', 'salvaged'], [(string)$salvaged['raw_chaos_awarded'], (string)$salvaged['raw_chaos'],
      (string)$salvaged['player_revision'], $salvaged['lifecycle_status']]);

    $this->assertSame(['sold', 'salvaged'], $this->column('SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` IN (?, ?) ORDER BY `id`', [$sellId, $salvageId]));
    $this->assertSame([], (new DiceCollectionQuery(new WarbandDiceRepository($this->pdo), $this->content()))->execute($userId));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]));
  }

  public function testAspectsDoNotChangeVnextLifecycleValuation(): void
  {
    $plainUser = $this->user(); $aspectUser = $this->user();
    $plain = $this->die($plainUser, 8, 'dice_profile.cardboard_plain');
    $aspect = $this->die($aspectUser, 8, 'dice_profile.cardboard_striking');
    $plainResult = $this->command()->sell($plainUser, $plain, 'dice-plain-value');
    $aspectResult = $this->command()->sell($aspectUser, $aspect, 'dice-aspect-value');
    $this->assertSame(14, $plainResult['teeth_awarded']);
    $this->assertSame($plainResult['teeth_awarded'], $aspectResult['teeth_awarded']);
  }

  /** @dataProvider operationProvider */
  public function testEquippedDieRejectsWithoutMutation(string $operation): void
  {
    $userId = $this->user(5, 6, 3); $dieId = $this->die($userId, 6, 'dice_profile.cardboard_plain');
    $this->bind($userId, $dieId);
    $this->assertLifecycleError('die_equipped', fn() => $this->command()->{$operation}($userId, $dieId, 'equipped-' . $operation));
    $this->assertState($userId, $dieId, 'active', 5, 6, 3, 0);
  }

  /** @dataProvider operationProvider */
  public function testActiveRunParticipatingEquippedDieIsConfigurationLocked(string $operation): void
  {
    $userId = $this->user(5, 6, 3); $dieId = $this->die($userId, 6, 'dice_profile.cardboard_plain');
    $unitId = $this->bind($userId, $dieId); $this->activeRun($userId, $unitId);
    try { $this->command()->{$operation}($userId, $dieId, 'active-run-' . $operation); $this->fail('Expected active-run lock.'); }
    catch (ActiveRunConfigurationLockedException) { $this->addToAssertionCount(1); }
    $this->assertState($userId, $dieId, 'active', 5, 6, 3, 0);
  }

  public function testUnboundDieCanSellWhileAnotherRunIsActive(): void
  {
    $userId = $this->user(); $participantDie = $this->die($userId, 4, 'dice_profile.cardboard_plain');
    $unitId = $this->bind($userId, $participantDie); $this->activeRun($userId, $unitId);
    $unbound = $this->die($userId, 4, 'dice_profile.cardboard_plain');
    $result = $this->command()->sell($userId, $unbound, 'unbound-active-run');
    $this->assertSame(6, $result['teeth_awarded']);
    $this->assertSame('sold', $this->scalar('SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` = ?', [$unbound]));
  }

  public function testMissingForeignAndTerminalDiceAreOwnershipSafe(): void
  {
    $owner = $this->user(); $other = $this->user(); $foreign = $this->die($owner, 4, 'dice_profile.cardboard_plain');
    foreach ([$foreign, 999999999] as $dieId) {
      $this->assertLifecycleError('die_not_found', fn() => $this->command()->sell($other, $dieId, 'not-found-' . $dieId));
    }
    $this->command()->sell($owner, $foreign, 'terminal-first');
    $this->assertLifecycleError('die_not_found', fn() => $this->command()->salvage($owner, $foreign, 'terminal-second'));
    $this->assertSame('sold', $this->scalar('SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` = ?', [$foreign]));
  }

  /** @dataProvider operationProvider */
  public function testCurrencyOverflowRejectsAtomically(string $operation): void
  {
    $currency = $operation === 'sell' ? 'teeth' : 'raw_chaos';
    $userId = $this->user($currency === 'teeth' ? ClientSafeInteger::MAXIMUM : 0,
      $currency === 'raw_chaos' ? ClientSafeInteger::MAXIMUM : 0, 4);
    $dieId = $this->die($userId, 20, 'dice_profile.gemstone_plain');
    $this->assertLifecycleError('currency_overflow', fn() => $this->command()->{$operation}($userId, $dieId, 'overflow-' . $operation));
    $this->assertSame('active', $this->scalar('SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` = ?', [$dieId]));
    $this->assertSame('4', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testExactRetryReturnsOriginalReceiptAfterLaterDieContentAndWalletChanges(): void
  {
    $userId = $this->user(10, 0, 2); $dieId = $this->die($userId, 8, 'dice_profile.bone_plain');
    $first = $this->command()->sell($userId, $dieId, 'exact-retry-key');
    $this->pdo?->prepare("UPDATE `dice_instances` SET `profile_id` = 'dice_profile.missing' WHERE `id` = ?")->execute([$dieId]);
    $this->pdo?->prepare('UPDATE `user_state` SET `teeth` = 999 WHERE `user_id` = ?')->execute([$userId]);
    $retry = $this->command()->sell($userId, $dieId, 'exact-retry-key');
    $this->assertSame($first, $retry);
    $this->assertSame('3', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
  }

  public function testSameKeyWithDifferentDieOrOperationConflicts(): void
  {
    $userId = $this->user(); $first = $this->die($userId, 4, 'dice_profile.cardboard_plain');
    $second = $this->die($userId, 4, 'dice_profile.cardboard_plain'); $command = $this->command();
    $command->sell($userId, $first, 'dice-conflict-key');
    foreach ([fn() => $command->sell($userId, $second, 'dice-conflict-key'),
      fn() => $command->salvage($userId, $first, 'dice-conflict-key')] as $operation) {
      try { $operation(); $this->fail('Expected idempotency conflict.'); }
      catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    }
    $this->assertSame('active', $this->scalar('SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` = ?', [$second]));
  }

  public function testInjectedFailureRollsBackLifecycleCurrencyRevisionAndReceipt(): void
  {
    $userId = $this->user(3, 4, 5); $dieId = $this->die($userId, 8, 'dice_profile.bone_plain');
    try { $this->command(static fn() => throw new RuntimeException('stop'))->sell($userId, $dieId, 'rollback-die-key');
      $this->fail('Expected rollback.'); }
    catch (DiceLifecycleIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertState($userId, $dieId, 'active', 3, 4, 5, 0);
  }

  public function testRepositoryTransitionsRequireCallerTransactionAndHttpBoundaryRequiresSecurity(): void
  {
    $userId = $this->user(); $dieId = $this->die($userId, 4, 'dice_profile.cardboard_plain');
    $repository = new WarbandDiceRepository($this->pdo);
    foreach ([fn() => $repository->findActiveOwnedForUpdate($userId, $dieId),
      fn() => $repository->listLifecycleBindingsForUpdate($dieId),
      fn() => $repository->transitionActiveLifecycle($userId, $dieId, 'sold'),
      fn() => (new PlayerStateRepository($this->pdo))->applyCurrencyTransition($userId, 'teeth', 0, 1)] as $operation) {
      try { $operation(); $this->fail('Expected transaction requirement.'); }
      catch (RuntimeException) { $this->addToAssertionCount(1); }
    }
    $controller = new WarbandController();
    $this->assertSame(401, $this->invoke(fn() => $controller->sellDie((string)$dieId))['status']);
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $this->assertSame(403, $this->invoke(fn() => $controller->sellDie((string)$dieId))['status']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf'; $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'http-dice-key';
    $this->assertSame(404, $this->invoke(fn() => $controller->sellDie('01'))['status']);
    $success = $this->invoke(fn() => $controller->salvageDie((string)$dieId));
    $this->assertSame(200, $success['status'], json_encode($success['body']));
    $this->assertSame((string)$dieId, $success['body']['data']['dice_id'] ?? null);
  }

  public static function operationProvider(): array { return [['sell'], ['salvage']]; }

  private function command(?\Closure $beforeCommit = null): DiceLifecycleCommand
  {
    $runs = new RunPersistenceRepository($this->pdo);
    return new DiceLifecycleCommand($this->pdo, new PlayerStateRepository($this->pdo), new WarbandDiceRepository($this->pdo),
      new IdempotencyRequestRepository($this->pdo), new ActiveRunConfigurationPolicy($runs), $this->content(), $beforeCommit);
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }

  private function user(int $teeth = 0, int $rawChaos = 0, int $revision = 1): int
  {
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Dice ' . bin2hex(random_bytes(4))]);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `raw_chaos`, `energy_current`, `player_revision`) VALUES (?, ?, ?, 50, ?)')
      ->execute([$id, $teeth, $rawChaos, $revision]);
    return $id;
  }

  private function die(int $userId, int $size, string $profileId): int
  {
    $this->pdo?->prepare('INSERT INTO `dice_instances` (`user_id`, `size`, `profile_id`) VALUES (?, ?, ?)')
      ->execute([$userId, $size, $profileId]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function bind(int $userId, int $dieId): int
  {
    $this->pdo?->prepare("INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`) VALUES (?, 'unit_type.bruiser', 'kin.goblin', 'Bound')")
      ->execute([$userId]);
    $unitId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare("INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, 'ability.basic_attack_melee')")->execute([$unitId]);
    $this->pdo?->prepare("INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, 'ability.basic_attack_melee', 0)")->execute([$unitId]);
    $this->pdo?->prepare("INSERT INTO `unit_ability_dice` (`unit_id`, `ability_id`, `slot_index`, `dice_instance_id`) VALUES (?, 'ability.basic_attack_melee', 0, ?)")
      ->execute([$unitId, $dieId]);
    return $unitId;
  }

  private function activeRun(int $userId, int $unitId): void
  {
    $this->pdo?->prepare("INSERT INTO `squads` (`user_id`, `name`) VALUES (?, 'Active')")->execute([$userId]);
    $squadId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare("INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`) VALUES (?, 'region.the_farm', ?)")->execute([$userId, $squadId]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, 10)')->execute([$runId, $unitId]);
  }

  private function assertLifecycleError(string $code, callable $operation): void
  { try { $operation(); $this->fail('Expected die lifecycle failure.'); } catch (DiceLifecycleException $e) { $this->assertSame($code, $e->errorCode); } }

  private function assertState(int $userId, int $dieId, string $status, int $teeth, int $rawChaos, int $revision, int $receipts): void
  {
    $this->assertSame([$status, (string)$teeth, (string)$rawChaos, (string)$revision, (string)$receipts], [
      $this->scalar('SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` = ?', [$dieId]),
      (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      (string)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]),
    ]);
  }

  /** @param list<mixed> $parameters @return list<string> */
  private function column(string $sql, array $parameters): array
  { $stmt = $this->pdo?->prepare($sql); $stmt?->execute($parameters); return array_map('strval', $stmt?->fetchAll(\PDO::FETCH_COLUMN) ?: []); }
}
