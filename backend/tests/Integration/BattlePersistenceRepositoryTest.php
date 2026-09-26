<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\VnextBattleFixture;
use PDO;
use PDOException;
use RuntimeException;

final class BattlePersistenceRepositoryTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testRepositoryRoundTripsExactPayloadWithoutOwningTransactionOrMutatingRunState(): void
  {
    [$userId, $unitId, $runId, $nodeId] = $this->scenario();
    $this->pdo?->prepare('INSERT INTO `idempotency_requests`
      (`user_id`, `idempotency_key`, `operation_type`, `request_hash`, `result_json`) VALUES (?, ?, ?, ?, ?)')
      ->execute([$userId, 'existing-key', 'run.start', str_repeat('a', 64), '{}']);
    $before = $this->mutableState($userId, $runId, $nodeId, $unitId);
    $repository = new BattlePersistenceRepository($this->pdo);

    $this->assertFalse($this->pdo?->inTransaction());
    $battleId = $repository->insertFinalized($runId, $nodeId, VnextBattleFixture::battle($unitId));
    $this->assertFalse($this->pdo?->inTransaction());

    $byId = $repository->findById($battleId);
    $byNode = $repository->findForRunNode($runId, $nodeId);
    $this->assertNotNull($byId);
    $this->assertNotNull($byNode);
    $this->assertSame($battleId, $byNode?->id);
    $this->assertEquals(VnextBattleFixture::input(), $byId?->battle->inputSnapshot);
    $this->assertEquals(VnextBattleFixture::manifest($unitId), $byId?->battle->manifestArray());
    $this->assertEquals(VnextBattleFixture::result(), $byId?->battle->result);
    $this->assertSame($before, $this->mutableState($userId, $runId, $nodeId, $unitId));
    $this->assertSame('0', (string)$this->scalar(
      "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'battle_rewards'",
      [],
    ));
    $this->assertSame('0', (string)$this->scalar(
      'SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?',
      [$userId],
    ));
  }

  public function testDatabaseEnforcesSameRunNodeAndOneFinalizedBattlePerNode(): void
  {
    [$userId, $unitId, $runId, $firstNode] = $this->scenario();
    $secondNode = $this->createNode($runId, 1);
    $otherRun = $this->createRun($userId, null, 'abandoned');
    $foreignNode = $this->createNode($otherRun, 0);
    $repository = new BattlePersistenceRepository($this->pdo);
    $battle = VnextBattleFixture::battle($unitId);

    $repository->insertFinalized($runId, $firstNode, $battle);
    $this->assertConstraintViolation(fn() => $repository->insertFinalized($runId, $firstNode, $battle));
    $secondBattleId = $repository->insertFinalized($runId, $secondNode, $battle);
    $this->assertGreaterThan(0, $secondBattleId);
    $this->assertConstraintViolation(fn() => $repository->insertFinalized($runId, $foreignNode, $battle));
    $this->assertConstraintViolation(fn() => $repository->insertFinalized(999999999, $firstNode, $battle));
  }

  public function testDeletingRunCascadesFinalizedBattles(): void
  {
    [, $unitId, $runId, $nodeId] = $this->scenario();
    $battleId = (new BattlePersistenceRepository($this->pdo))->insertFinalized(
      $runId, $nodeId, VnextBattleFixture::battle($unitId),
    );

    $this->pdo?->prepare('DELETE FROM `runs` WHERE `id` = ?')->execute([$runId]);

    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `id` = ?', [$battleId]));
  }

  public function testRepositoryRejectsStoredPayloadThatNoLongerMatchesVersionColumns(): void
  {
    [, $unitId, $runId, $nodeId] = $this->scenario();
    $repository = new BattlePersistenceRepository($this->pdo);
    $battleId = $repository->insertFinalized($runId, $nodeId, VnextBattleFixture::battle($unitId));
    $this->pdo?->prepare("UPDATE `battles` SET `result_json` = JSON_SET(`result_json`, '$.engine_version', 2) WHERE `id` = ?")
      ->execute([$battleId]);

    $this->expectException(RuntimeException::class);
    $repository->findById($battleId);
  }

  /** @return array{int,int,int,int} */
  private function scenario(): array
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)');
    $stmt?->execute(['Battle owner ' . bin2hex(random_bytes(4))]);
    $userId = (int)$this->pdo?->lastInsertId();
    $this->trackUserId($userId);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`, `player_revision`) VALUES (?, 17, 4)')
      ->execute([$userId]);
    $this->pdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`) VALUES (?, ?, ?, ?)')
      ->execute([$userId, 'unit_type.bruiser', 'kin.gutter', 'Bash']);
    $unitId = (int)$this->pdo?->lastInsertId();
    $runId = $this->createRun($userId, null, 'active');
    $nodeId = $this->createNode($runId, 0);
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, 20)')
      ->execute([$runId, $unitId]);
    return [$userId, $unitId, $runId, $nodeId];
  }

  private function createRun(int $userId, ?int $squadId, string $status): int
  {
    $endedAt = $status === 'active' ? null : '2026-09-15 12:00:00';
    $stmt = $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`, `status`, `ended_at`) VALUES (?, ?, ?, ?, ?)');
    $stmt?->execute([$userId, 'region.the_farm', $squadId, $status, $endedAt]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function createNode(int $runId, int $index): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type_id`, `encounter_id`, `status`) VALUES (?, ?, ?, ?, ?)');
    $stmt?->execute([$runId, $index, 'node_type.combat', 'encounter.the_farm_mud_combat_1', 'available']);
    return (int)$this->pdo?->lastInsertId();
  }

  /** @return array<string,mixed> */
  private function mutableState(int $userId, int $runId, int $nodeId, int $unitId): array
  {
    return [
      'user_state' => $this->fetchOne('SELECT `teeth`, `raw_chaos`, `energy_current`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      'run' => $this->fetchOne('SELECT `status`, `ended_at` FROM `runs` WHERE `id` = ?', [$runId]),
      'node' => $this->fetchOne('SELECT `status`, `completed_at` FROM `run_nodes` WHERE `id` = ?', [$nodeId]),
      'unit' => $this->fetchOne('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitId]),
      'idempotency_count' => (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]),
    ];
  }

  /** @param list<int> $params @return array<string,mixed> */
  private function fetchOne(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  private function assertConstraintViolation(callable $operation): void
  {
    try {
      $operation();
      $this->fail('Expected MySQL to reject the battle row.');
    } catch (PDOException $e) {
      $this->assertContains((string)$e->getCode(), ['23000', 'HY000']);
    }
  }
}
