<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDOException;

final class RewardPersistenceFoundationTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  public function testUnlockRepositoryProvidesSortedIsolatedIdempotentOwnershipPrimitives(): void
  {
    $users = new UserRepository($this->pdo);
    $ownerId = $users->createUser('Unlock Owner', null);
    $otherId = $users->createUser('Other Owner', null);
    $this->trackUserId($ownerId);
    $this->trackUserId($otherId);
    $unlocks = new UserUnlockRepository($this->pdo);

    $this->assertSame([], $unlocks->listIdsForUser($ownerId));
    $this->assertTrue($unlocks->insertIfAbsent($ownerId, 'unlock.region.zz_test'));
    $this->assertTrue($unlocks->insertIfAbsent($ownerId, 'unlock.region.mountains'));
    $this->assertFalse($unlocks->insertIfAbsent($ownerId, 'unlock.region.mountains'));
    $this->assertTrue($unlocks->insertIfAbsent($otherId, 'unlock.region.other_user'));

    $this->assertTrue($unlocks->owns($ownerId, 'unlock.region.mountains'));
    $this->assertFalse($unlocks->owns($ownerId, 'unlock.region.other_user'));
    $this->assertSame(['unlock.region.mountains', 'unlock.region.zz_test'], $unlocks->listIdsForUser($ownerId));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `granted_at` IS NOT NULL', [$ownerId]));
  }

  public function testResolvedEventIdentityJsonAndLifecycleConstraintsAreEnforced(): void
  {
    $userId = (new UserRepository($this->pdo))->createUser('Event Owner', null);
    $this->trackUserId($userId);
    $insert = $this->pdo?->prepare('INSERT INTO `resolved_events`
      (`user_id`, `event_id`, `source_type`, `source_id`, `result_json`, `status`, `resolved_at`, `applied_at`)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $insert?->execute([$userId, 'event.test', 'run_node', 'run-node:41', '{"version":1}', 'finalized', '2026-09-17 10:00:00', null]);
    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.test', 'run_node', 'run-node:41', '{}', 'finalized', '2026-09-17 10:01:00', null]));
    $insert?->execute([$userId, 'event.other', 'run_node', 'run-node:41', '{}', 'finalized', '2026-09-17 10:01:00', null]);
    $insert?->execute([$userId, 'event.test', 'purchase', 'run-node:41', '{}', 'applied', '2026-09-17 10:02:00', '2026-09-17 10:03:00']);

    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.bad_json', 'run_node', 'bad-json', '{', 'finalized', '2026-09-17 10:00:00', null]));
    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.empty_source', 'run_node', '', '{}', 'finalized', '2026-09-17 10:00:00', null]));
    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.bad_status', 'run_node', 'bad-status', '{}', 'pending', '2026-09-17 10:00:00', null]));
    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.bad_finalized', 'run_node', 'bad-finalized', '{}', 'finalized', '2026-09-17 10:00:00', '2026-09-17 10:01:00']));
    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.bad_applied', 'run_node', 'bad-applied', '{}', 'applied', '2026-09-17 10:00:00', null]));
    $this->assertConstraintViolation(fn() => $insert?->execute([$userId, 'event.bad_time', 'run_node', 'bad-time', '{}', 'applied', '2026-09-17 10:00:00', '2026-09-17 09:59:59']));
    $this->assertSame('3', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
  }

  public function testRewardFoundationRowsCascadeWithOwningUser(): void
  {
    $userId = (new UserRepository($this->pdo))->createUser('Cascade Owner', null);
    $this->pdo?->prepare('INSERT INTO `user_unlocks` (`user_id`, `unlock_id`) VALUES (?, ?)')->execute([$userId, 'unlock.region.mountains']);
    $this->pdo?->prepare('INSERT INTO `resolved_events` (`user_id`, `event_id`, `source_type`, `source_id`, `result_json`) VALUES (?, ?, ?, ?, ?)')
      ->execute([$userId, 'event.test', 'run_node', 'run-node:99', '{}']);

    $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$userId]);

    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
  }

  private function assertConstraintViolation(callable $operation): void
  {
    try {
      $operation();
      $this->fail('Expected a database constraint violation.');
    } catch (PDOException $e) {
      $this->assertContains((string)$e->getCode(), ['23000', '22032', 'HY000']);
    }
  }
}
