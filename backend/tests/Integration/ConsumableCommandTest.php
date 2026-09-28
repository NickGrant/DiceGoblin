<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DiceGoblins\Application\Commands\ConsumableUseException;
use DiceGoblins\Application\Commands\ConsumableUseIntegrityException;
use DiceGoblins\Application\Commands\HealRunUnitCommand;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\RestoreEnergyCommand;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ConsumableController;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Domain\Energy\EnergyRestoreCalculator;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class ConsumableCommandTest extends IntegrationTestCase
{
  /** @var list<string> */ private array $roots = [];
  protected function supportsVnextBaseline(): bool { return true; }
  protected function tearDown(): void { parent::tearDown(); foreach ($this->roots as $root) $this->removeTree($root); }

  public function testEnergyRestoreMaterializesRegenConsumesOnceAndExactRetrySurvivesLaterStateAndContent(): void
  {
    $userId = $this->user(45, '2026-09-26 12:00:00', 3);
    $this->item($userId, 'item.test.spark', 2);
    $command = $this->energyCommand($this->content(), '2026-09-26T12:12:30Z');
    $first = $command->execute($userId, ['item_id' => 'item.test.spark'], 'energy-restore-key');
    $this->assertSame([54, 50, '2026-09-26T12:12:30Z'], [
      $first['energy']['current'], $first['energy']['normal_max'], $first['energy']['last_regeneration_at'],
    ]);
    $this->assertSame([1, 4], [$first['owned_quantity_after'], $first['player_revision']]);
    $this->pdo?->prepare('UPDATE `user_state` SET `energy_current` = 1 WHERE `user_id` = ?')->execute([$userId]);
    $this->pdo?->prepare('DELETE FROM `user_items` WHERE `user_id` = ?')->execute([$userId]);
    $replay = $this->energyCommand(ContentRegistry::load(dirname(__DIR__, 2) . '/content'), '2026-09-27T12:00:00Z')
      ->execute($userId, ['item_id' => 'item.test.spark'], 'energy-restore-key');
    $this->assertSame($first, $replay);
    $this->assertSame('4', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testEnergyRestoreBelowCapPreservesFractionAndMayReachOrExceedCap(): void
  {
    $content = $this->content();
    $below = $this->user(5, '2026-09-26 12:00:00'); $this->item($below, 'item.test.spark', 1);
    $result = $this->energyCommand($content, '2026-09-26T12:12:30Z')->execute($below, ['item_id' => 'item.test.spark'], 'energy-below-key');
    $this->assertSame(14, $result['energy']['current']);
    $this->assertSame('2026-09-26T12:10:00Z', $result['energy']['last_regeneration_at']);
    $this->assertSame('2026-09-26T12:15:00Z', $result['energy']['next_regeneration_at']);
    $over = $this->user(49, '2026-09-26 12:12:00'); $this->item($over, 'item.test.spark', 1);
    $overResult = $this->energyCommand($content, '2026-09-26T12:12:30Z')->execute($over, ['item_id' => 'item.test.spark'], 'energy-over-key');
    $this->assertSame(56, $overResult['energy']['current']);
    $this->assertNull($overResult['energy']['next_regeneration_at']);
  }

  public function testOwnedEnergyCapabilityAllowsRestoreAboveBaseNormalMaximum(): void
  {
    $userId = $this->user(50, '2026-09-26 12:00:00');
    $this->item($userId, 'item.test.spark', 1);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.capability.energy_max_75');
    $result = $this->energyCommand($this->content(), '2026-09-26T12:00:00Z')
      ->execute($userId, ['item_id' => 'item.test.spark'], 'energy-higher-cap');
    $this->assertSame(75, $result['energy']['normal_max']);
    $this->assertSame(57, $result['energy']['current']);
  }

  public function testEnergyRestoreInterpretsPersistedRegenerationTimestampAsUtc(): void
  {
    $originalTimezone = date_default_timezone_get();
    try {
      date_default_timezone_set('America/Los_Angeles');
      $userId = $this->user(5, '2026-09-26 12:00:00');
      $this->item($userId, 'item.test.spark', 1);

      $result = $this->energyCommand($this->content(), '2026-09-26T12:12:30Z')
        ->execute($userId, ['item_id' => 'item.test.spark'], 'energy-non-utc-process-key');

      $this->assertSame(14, $result['energy']['current']);
      $this->assertSame('2026-09-26T12:10:00Z', $result['energy']['last_regeneration_at']);
      $this->assertSame('2026-09-26T12:15:00Z', $result['energy']['next_regeneration_at']);
    } finally {
      date_default_timezone_set($originalTimezone);
    }
  }

  /** @dataProvider unavailableEnergyProvider */
  public function testFullOverchargedOrRegeneratedToFullEnergyRejectsWithoutMutation(int $current, string $anchor): void
  {
    $userId = $this->user($current, $anchor); $this->item($userId, 'item.test.spark', 1);
    try { $this->energyCommand($this->content(), '2026-09-26T13:00:00Z')->execute($userId, ['item_id' => 'item.test.spark'], 'energy-unavailable-' . $current); $this->fail('Expected unavailable restore.'); }
    catch (ConsumableUseException $e) { $this->assertSame('energy_restore_unavailable', $e->errorCode); }
    $this->assertSame('1', (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public static function unavailableEnergyProvider(): array
  { return [[50, '2026-09-26 13:00:00'], [55, '2026-09-26 13:00:00'], [49, '2026-09-26 12:55:00']]; }

  public function testEnergyWrongEffectMissingInventoryConflictOverflowAndRollbackAreAtomic(): void
  {
    $content = $this->content();
    $wrong = $this->user(10); $this->item($wrong, 'item.test.heal', 1);
    $this->assertCommandError('invalid_consumable', fn() => $this->energyCommand($content)->execute($wrong, ['item_id' => 'item.test.heal'], 'energy-wrong-effect'));
    $missing = $this->user(10);
    $this->assertCommandError('consumable_unavailable', fn() => $this->energyCommand($content)->execute($missing, ['item_id' => 'item.test.spark'], 'energy-missing-item'));
    $overflow = $this->user(10); $this->item($overflow, 'item.test.spark', 1);
    try { $this->energyCommand($this->content(ClientSafeInteger::MAXIMUM))->execute($overflow, ['item_id' => 'item.test.spark'], 'energy-overflow-key'); $this->fail('Expected overflow.'); }
    catch (ConsumableUseIntegrityException) { $this->addToAssertionCount(1); }
    $rollback = $this->user(10); $this->item($rollback, 'item.test.spark', 1);
    try { $this->energyCommand($content, '2026-09-26T12:00:00Z', static fn() => throw new RuntimeException('stop'))
      ->execute($rollback, ['item_id' => 'item.test.spark'], 'energy-rollback-key'); $this->fail('Expected rollback.'); }
    catch (ConsumableUseIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame(['10', '1', '1', '0'], [
      (string)$this->scalar('SELECT `energy_current` FROM `user_state` WHERE `user_id` = ?', [$rollback]),
      (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ?', [$rollback]),
      (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$rollback]),
      (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$rollback]),
    ]);
    $successful = $this->user(10); $this->item($successful, 'item.test.spark', 2);
    $command = $this->energyCommand($content); $command->execute($successful, ['item_id' => 'item.test.spark'], 'energy-conflict-key');
    try { $command->execute($successful, ['item_id' => 'item.test.heal'], 'energy-conflict-key'); $this->fail('Expected conflict.'); }
    catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
  }

  public function testActiveRunParticipantHealingCapsAtMaximumAndReplaysAfterLaterChanges(): void
  {
    [$userId, $runId, $unitId, $maximum] = $this->runFixture(1);
    $this->item($userId, 'item.test.heal', 2); $command = $this->healCommand($this->content());
    $first = $command->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'heal-run-key');
    $this->assertSame(['1', (string)min($maximum, 10), (string)$maximum], [
      (string)$first['unit']['hp_before'], (string)$first['unit']['hp_after'], (string)$first['unit']['max_hp'],
    ]);
    $this->assertSame([1, 2], [$first['owned_quantity_after'], $first['player_revision']]);
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'completed', `ended_at` = UTC_TIMESTAMP() WHERE `id` = ?")->execute([$runId]);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = ? WHERE `run_id` = ? AND `unit_id` = ?')->execute([$maximum, $runId, $unitId]);
    $this->pdo?->prepare('DELETE FROM `user_items` WHERE `user_id` = ?')->execute([$userId]);
    $replay = $this->healCommand(ContentRegistry::load(dirname(__DIR__, 2) . '/content'))
      ->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'heal-run-key');
    $this->assertSame($first, $replay);
    $this->assertSame('2', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testZeroHpCanHealButFullTerminalNonparticipantAndForeignTargetsCannot(): void
  {
    $content = $this->content();
    [$zeroUser, $zeroRun, $zeroUnit] = $this->runFixture(0); $this->item($zeroUser, 'item.test.heal', 1);
    $zero = $this->healCommand($content)->execute($zeroUser, $zeroRun, $zeroUnit, ['item_id' => 'item.test.heal'], 'heal-zero-key');
    $this->assertSame([0, 9], [$zero['unit']['hp_before'], $zero['unit']['hp_after']]);
    [$fullUser, $fullRun, $fullUnit, $maximum] = $this->runFixture(null); $this->item($fullUser, 'item.test.heal', 1);
    $this->assertCommandError('run_heal_unavailable', fn() => $this->healCommand($content)->execute($fullUser, $fullRun, $fullUnit, ['item_id' => 'item.test.heal'], 'heal-full-key'));
    [$foreignUser, $foreignRun, $foreignUnit] = $this->runFixture(1); $other = $this->user(10); $this->item($other, 'item.test.heal', 1);
    $this->assertCommandError('run_not_found', fn() => $this->healCommand($content)->execute($other, $foreignRun, $foreignUnit, ['item_id' => 'item.test.heal'], 'heal-foreign-run'));
    $this->assertCommandError('run_not_found', fn() => $this->healCommand($content)->execute($foreignUser, 999999999, $foreignUnit, ['item_id' => 'item.test.heal'], 'heal-missing-run'));
    $this->assertCommandError('run_unit_not_found', fn() => $this->healCommand($content)->execute($foreignUser, $foreignRun, $fullUnit, ['item_id' => 'item.test.heal'], 'heal-nonparticipant'));
    $this->assertSame((string)$maximum, (string)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$fullRun, $fullUnit]));
  }

  /** @dataProvider terminalRunProvider */
  public function testEveryTerminalRunStatusRejectsWithoutConsumption(string $status): void
  {
    [$userId, $runId, $unitId] = $this->runFixture(1, $status); $this->item($userId, 'item.test.heal', 1);
    $this->assertCommandError('run_heal_unavailable', fn() => $this->healCommand($this->content())
      ->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'heal-terminal-' . $status));
    $this->assertSame('1', (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitId]));
  }

  public static function terminalRunProvider(): array { return [['completed'], ['failed'], ['abandoned']]; }

  public function testHealWrongEffectMissingInventoryDifferentIntentionAndRollbackAreAtomic(): void
  {
    $content = $this->content();
    [$userId, $runId, $unitId] = $this->runFixture(1); $this->item($userId, 'item.test.spark', 1);
    $this->assertCommandError('invalid_consumable', fn() => $this->healCommand($content)->execute($userId, $runId, $unitId, ['item_id' => 'item.test.spark'], 'heal-wrong-effect'));
    $this->assertCommandError('consumable_unavailable', fn() => $this->healCommand($content)->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'heal-missing-item'));
    $this->assertSame('1', (string)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitId]));
    $this->item($userId, 'item.test.heal', 2); $command = $this->healCommand($content);
    $command->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'heal-conflict-key');
    foreach ([[$runId + 1, $unitId, 'item.test.heal'], [$runId, $unitId + 1, 'item.test.heal'], [$runId, $unitId, 'item.test.spark']] as [$otherRun, $otherUnit, $otherItem]) {
      try { $command->execute($userId, $otherRun, $otherUnit, ['item_id' => $otherItem], 'heal-conflict-key'); $this->fail('Expected conflict.'); }
      catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    }
    [$rollbackUser, $rollbackRun, $rollbackUnit] = $this->runFixture(1); $this->item($rollbackUser, 'item.test.heal', 1);
    try { $this->healCommand($content, static fn() => throw new RuntimeException('stop'))
      ->execute($rollbackUser, $rollbackRun, $rollbackUnit, ['item_id' => 'item.test.heal'], 'heal-rollback-key'); $this->fail('Expected rollback.'); }
    catch (ConsumableUseIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame(['1', '1', '1', '0'], [
      (string)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$rollbackRun, $rollbackUnit]),
      (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ?', [$rollbackUser]),
      (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$rollbackUser]),
      (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$rollbackUser]),
    ]);
  }

  public function testRunHpMutationRequiresCallerTransactionAndHttpCommandsRequireSecurityAndExactBodies(): void
  {
    [$userId, $runId, $unitId] = $this->runFixture(1); $repo = new RunNodeResolutionRepository($this->pdo);
    foreach ([fn() => $repo->findOwnedParticipatingUnitForUpdate($userId, $runId, $unitId),
      fn() => $repo->persistParticipatingUnitHp($runId, $unitId, 1, 2)] as $mutation) {
      try { $mutation(); $this->fail('Expected transaction boundary.'); } catch (RuntimeException) { $this->addToAssertionCount(1); }
    }
    $controller = new ConsumableController($this->content());
    $this->setJsonBody(['item_id' => 'item.test.spark']);
    $this->assertSame(401, $this->invoke(fn() => $controller->restoreEnergy())['status']);
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $this->setJsonBody(['item_id' => 'item.test.spark']);
    $this->assertSame(403, $this->invoke(fn() => $controller->restoreEnergy())['status']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf'; $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'consumable-http-key';
    foreach ([[], ['item_id' => 'test.spark'], ['item_id' => 'item.test.spark', 'amount' => 1]] as $body) {
      $this->setJsonBody($body); $response = $this->invoke(fn() => $controller->restoreEnergy());
      $this->assertSame(422, $response['status']); $this->assertSame('invalid_consumable_request', $response['body']['error']['code'] ?? null);
    }
    $this->setJsonBody(['item_id' => 'item.test.heal']);
    $this->assertSame(404, $this->invoke(fn() => $controller->healRunUnit('01', (string)$unitId))['status']);
    $this->pdo?->prepare('UPDATE `user_state` SET `energy_current` = 10, `energy_last_regen_at` = UTC_TIMESTAMP() WHERE `user_id` = ?')->execute([$userId]);
    $this->item($userId, 'item.test.spark', 1); $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'energy-http-success';
    $this->setJsonBody(['item_id' => 'item.test.spark']); $energy = $this->invoke(fn() => $controller->restoreEnergy());
    $this->assertSame(200, $energy['status'], json_encode($energy['body']));
    $this->item($userId, 'item.test.heal', 1); $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'heal-http-success';
    $this->setJsonBody(['item_id' => 'item.test.heal']); $heal = $this->invoke(fn() => $controller->healRunUnit((string)$runId, (string)$unitId));
    $this->assertSame(200, $heal['status'], json_encode($heal['body']));
    $this->assertSame((string)$runId, $heal['body']['data']['run_id'] ?? null);
  }

  private function user(int $energy, string $anchor = '2026-09-26 12:00:00', int $revision = 1): int
  {
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Consumable ' . bin2hex(random_bytes(4))]);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`, `energy_last_regen_at`, `player_revision`) VALUES (?, ?, ?, ?)')
      ->execute([$id, $energy, $anchor, $revision]);
    return $id;
  }

  private function item(int $userId, string $itemId, int $quantity): void
  { $this->pdo?->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)')->execute([$userId, $itemId, $quantity]); }

  /** @return array{int,int,int,int} */
  private function runFixture(?int $hp, string $status = 'active'): array
  {
    $content = $this->content(); $type = $content->unitType('unit_type.bruiser');
    $maximum = (new BaseLevelStatResolver())->resolve($type['base_stats'], $type['growth_per_level'], 1)->hp;
    $userId = $this->user(10);
    $this->pdo?->prepare("INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`) VALUES (?, 'unit_type.bruiser', 'kin.goblin', 'Bruiser')")->execute([$userId]);
    $unitId = (int)$this->pdo?->lastInsertId();
    $ended = $status === 'active' ? null : '2026-09-26 12:00:00';
    $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `status`, `ended_at`) VALUES (?, ?, ?, ?)')->execute([$userId, 'region.the_farm', $status, $ended]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, ?)')->execute([$runId, $unitId, $hp ?? $maximum]);
    return [$userId, $runId, $unitId, $maximum];
  }

  private function energyCommand(ContentRegistry $content, string $now = '2026-09-26T12:00:00Z', ?\Closure $beforeCommit = null): RestoreEnergyCommand
  {
    return new RestoreEnergyCommand($this->pdo, new PlayerStateRepository($this->pdo), new UserItemRepository($this->pdo),
      new IdempotencyRequestRepository($this->pdo), new UserUnlockRepository($this->pdo), $content,
      new EnergyRestoreCalculator(), $this->clock($now), $beforeCommit);
  }

  private function healCommand(ContentRegistry $content, ?\Closure $beforeCommit = null): HealRunUnitCommand
  {
    return new HealRunUnitCommand($this->pdo, new PlayerStateRepository($this->pdo), new RunPersistenceRepository($this->pdo),
      new RunNodeResolutionRepository($this->pdo), new UserItemRepository($this->pdo), new IdempotencyRequestRepository($this->pdo),
      $content, new BaseLevelStatResolver(), $beforeCommit);
  }

  private function clock(string $now): Clock
  { return new class($now) implements Clock { public function __construct(private readonly string $value) {} public function now(): DateTimeImmutable { return new DateTimeImmutable($this->value); } }; }

  private function assertCommandError(string $code, callable $operation): void
  { try { $operation(); $this->fail('Expected consumable failure.'); } catch (ConsumableUseException $e) { $this->assertSame($code, $e->errorCode); } }

  private function content(int $energyAmount = 7): ContentRegistry
  {
    $source = dirname(__DIR__, 2) . '/content'; $root = sys_get_temp_dir() . '/dice-goblins-consumables-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true); $this->roots[] = $root;
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) { if (!$file->isFile()) continue; $relative = substr($file->getPathname(), strlen($source) + 1); $target = $root . '/' . str_replace('\\', '/', $relative); if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true); copy($file->getPathname(), $target); }
    file_put_contents($root . '/items/test-consumables.json', json_encode(['definitions' => [
      ['id' => 'item.test.spark', 'type' => 'item', 'display_name' => 'Spark', 'description' => 'Energy.', 'category' => 'consumable', 'rarity' => 'common', 'icon_key' => 'spark', 'stackable' => true, 'effect' => ['type' => 'energy_restore', 'amount' => $energyAmount]],
      ['id' => 'item.test.heal', 'type' => 'item', 'display_name' => 'Poultice', 'description' => 'Healing.', 'category' => 'consumable', 'rarity' => 'common', 'icon_key' => 'heal', 'stackable' => true, 'effect' => ['type' => 'unit_heal', 'amount' => 9]],
    ]], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }

  private function removeTree(string $root): void
  { if (!is_dir($root)) return; $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST); foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($root); }
}
