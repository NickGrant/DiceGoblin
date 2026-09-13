<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;
use PDOException;

final class VnextRunPersistenceTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  public function testOneActiveRunInvariantIsPerUserAndAllowsTerminalHistory(): void
  {
    $firstUser = $this->createUser('First runner');
    $secondUser = $this->createUser('Second runner');
    $firstSquad = $this->createSquad($firstUser, 'First squad');
    $secondSquad = $this->createSquad($secondUser, 'Second squad');

    $firstActive = $this->createRun($firstUser, $firstSquad, 'region.farm');
    $secondActive = $this->createRun($secondUser, $secondSquad, 'region.farm');

    $this->assertSame((string)$firstUser, (string)$this->scalar('SELECT `active_user_id` FROM `runs` WHERE `id` = ?', [$firstActive]));
    $this->assertSame((string)$secondUser, (string)$this->scalar('SELECT `active_user_id` FROM `runs` WHERE `id` = ?', [$secondActive]));
    $this->assertConstraintViolation(fn() => $this->createRun($firstUser, $firstSquad, 'region.mountains'));

    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = UTC_TIMESTAMP() WHERE `id` = ?")->execute([$firstActive]);
    $replacement = $this->createRun($firstUser, $firstSquad, 'region.mountains');
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = UTC_TIMESTAMP() WHERE `id` = ?")->execute([$replacement]);
    $this->createTerminalRun($firstUser, $firstSquad, 'region.farm', 'abandoned');
    $this->createTerminalRun($firstUser, $firstSquad, 'region.farm', 'abandoned');

    $this->assertSame('4', (string)$this->scalar("SELECT COUNT(*) FROM `runs` WHERE `user_id` = ? AND `status` = 'abandoned'", [$firstUser]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(`active_user_id`) FROM `runs` WHERE `user_id` = ?', [$firstUser]));

    $generated = $this->fetchOne(
      "SELECT EXTRA, GENERATION_EXPRESSION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'runs' AND COLUMN_NAME = 'active_user_id'",
      [],
    );
    $this->assertStringContainsString('STORED GENERATED', strtoupper((string)($generated['EXTRA'] ?? '')));
    $this->assertStringContainsString('status', strtolower((string)($generated['GENERATION_EXPRESSION'] ?? '')));
    $this->assertSame('1', (string)$this->scalar(
      "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'runs' AND INDEX_NAME = 'uq_runs_one_active_per_user' AND NON_UNIQUE = 0",
      [],
    ));
  }

  public function testUserAndSquadForeignKeysHaveDeliberateDeleteBehavior(): void
  {
    $userId = $this->createUser('Owner');
    $squadId = $this->createSquad($userId, 'Participating squad');
    $runId = $this->createRun($userId, $squadId, 'region.farm');

    $this->assertConstraintViolation(fn() => $this->createRun(999999999, null, 'region.farm'));
    $this->assertConstraintViolation(fn() => $this->createRun($userId, 999999999, 'region.farm', 'abandoned'));

    $this->pdo?->prepare('DELETE FROM `squads` WHERE `id` = ?')->execute([$squadId]);
    $run = $this->fetchOne('SELECT `squad_id`, `status`, `ended_at` FROM `runs` WHERE `id` = ?', [$runId]);
    $this->assertNull($run['squad_id'] ?? null);
    $this->assertSame('active', $run['status'] ?? null);
    $this->assertNull($run['ended_at'] ?? null);

    $this->assertConstraintViolation(fn() => $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$userId]));
    $this->pdo?->prepare('DELETE FROM `runs` WHERE `id` = ?')->execute([$runId]);
    $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$userId]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `runs` WHERE `id` = ?', [$runId]));
  }

  public function testNodesUseRunLocalIdentityAndPersistAuthoredIdsAndGeneratedMetadata(): void
  {
    $userId = $this->createUser('Node owner');
    $squadId = $this->createSquad($userId, 'Node squad');
    $firstRun = $this->createTerminalRun($userId, $squadId, 'region.farm', 'abandoned');
    $secondRun = $this->createRun($userId, $squadId, 'region.mountains');

    $firstNode = $this->createNode($firstRun, 0, 'node_type.combat', 'encounter.farm.mud', ['column' => 0, 'row' => 2]);
    $secondNode = $this->createNode($secondRun, 0, 'node_type.rest', null, ['column' => 4, 'row' => 1]);

    $this->assertNotSame($firstNode, $secondNode);
    $this->assertConstraintViolation(fn() => $this->createNode($firstRun, 0, 'node_type.loot'));

    $node = $this->fetchOne('SELECT `node_type_id`, `encounter_id`, `generated_metadata` FROM `run_nodes` WHERE `id` = ?', [$firstNode]);
    $this->assertSame('node_type.combat', $node['node_type_id'] ?? null);
    $this->assertSame('encounter.farm.mud', $node['encounter_id'] ?? null);
    $this->assertEquals(['column' => 0, 'row' => 2], json_decode((string)($node['generated_metadata'] ?? ''), true));

    foreach (['regions', 'node_types', 'encounters', 'encounter_templates', 'run_patterns', 'region_generation_rules'] as $catalog) {
      $this->assertSame('0', (string)$this->scalar(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$catalog],
      ));
    }
  }

  public function testEdgesRequireSameRunEndpointsAndRejectLogicalDuplicates(): void
  {
    $userId = $this->createUser('Edge owner');
    $squadId = $this->createSquad($userId, 'Edge squad');
    $firstRun = $this->createTerminalRun($userId, $squadId, 'region.farm', 'abandoned');
    $secondRun = $this->createRun($userId, $squadId, 'region.mountains');
    $from = $this->createNode($firstRun, 0, 'node_type.combat');
    $to = $this->createNode($firstRun, 1, 'node_type.loot');
    $foreign = $this->createNode($secondRun, 0, 'node_type.rest');

    $this->createEdge($firstRun, $from, $to, ['controlPoints' => [[1, 2]]]);
    $this->assertConstraintViolation(fn() => $this->createEdge($firstRun, $from, $to));
    $this->assertConstraintViolation(fn() => $this->createEdge($firstRun, $from, $foreign));
    $this->assertConstraintViolation(fn() => $this->createEdge($firstRun, $from, 999999999));
    $this->assertConstraintViolation(fn() => $this->createEdge($firstRun, $from, $from));

    $edgeMetadata = (string)$this->scalar(
      'SELECT `generated_metadata` FROM `run_edges` WHERE `run_id` = ? AND `from_node_id` = ? AND `to_node_id` = ?',
      [$firstRun, $from, $to],
    );
    $this->assertSame(['controlPoints' => [[1, 2]]], json_decode($edgeMetadata, true));
  }

  public function testRunUnitStateIsUniqueNullableAndProtectsParticipatingUnits(): void
  {
    $userId = $this->createUser('Unit state owner');
    $squadId = $this->createSquad($userId, 'Unit state squad');
    $runId = $this->createRun($userId, $squadId, 'region.farm');
    $unitId = $this->createUnit($userId);

    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`) VALUES (?, ?)')->execute([$runId, $unitId]);
    $this->assertNull($this->fetchOne('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitId])['current_hp'] ?? null);
    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, ?)')->execute([$runId, $unitId, 10]),
    );
    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`) VALUES (?, ?)')->execute([$runId, 999999999]),
    );
    $this->assertConstraintViolation(fn() => $this->pdo?->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$unitId]));

    $columns = $this->tableColumns('run_unit_state');
    foreach (['attack', 'defense', 'precision', 'resolve', 'speed', 'max_hp'] as $deferredColumn) {
      $this->assertNotContains($deferredColumn, $columns);
    }

    $this->pdo?->prepare('DELETE FROM `runs` WHERE `id` = ?')->execute([$runId]);
    $this->pdo?->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$unitId]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  public function testDeletingRunCascadesGeneratedGraphAndUnitState(): void
  {
    $userId = $this->createUser('Cleanup owner');
    $squadId = $this->createSquad($userId, 'Cleanup squad');
    $runId = $this->createRun($userId, $squadId, 'region.farm');
    $unitId = $this->createUnit($userId);
    $from = $this->createNode($runId, 0, 'node_type.combat');
    $to = $this->createNode($runId, 1, 'node_type.exit');
    $this->createEdge($runId, $from, $to);
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, ?)')->execute([$runId, $unitId, null]);

    $this->pdo?->prepare('DELETE FROM `runs` WHERE `id` = ?')->execute([$runId]);

    foreach (['run_nodes', 'run_edges', 'run_unit_state'] as $table) {
      $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `$table` WHERE `run_id` = ?", [$runId]));
    }
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  private function createUser(string $name): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)');
    $stmt?->execute([$name . ' ' . bin2hex(random_bytes(4))]);
    $userId = (int)$this->pdo?->lastInsertId();
    $this->trackUserId($userId);
    return $userId;
  }

  private function createSquad(int $userId, string $name): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt?->execute([$userId, $name]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function createRun(int $userId, ?int $squadId, string $regionId, string $status = 'active'): int
  {
    $endedAt = $status === 'active' ? null : '2026-09-13 12:00:00';
    $stmt = $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`, `status`, `ended_at`) VALUES (?, ?, ?, ?, ?)');
    $stmt?->execute([$userId, $regionId, $squadId, $status, $endedAt]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function createTerminalRun(int $userId, ?int $squadId, string $regionId, string $status): int
  {
    return $this->createRun($userId, $squadId, $regionId, $status);
  }

  private function createUnit(int $userId): int
  {
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`) VALUES (?, ?, ?, ?)',
    );
    $stmt?->execute([$userId, 'unit_type.grunt', 'kin.basic', 'Run Goblin']);
    return (int)$this->pdo?->lastInsertId();
  }

  /** @param array<string,mixed>|null $metadata */
  private function createNode(
    int $runId,
    int $nodeIndex,
    string $nodeTypeId,
    ?string $encounterId = null,
    ?array $metadata = null,
  ): int {
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type_id`, `encounter_id`, `generated_metadata`) VALUES (?, ?, ?, ?, ?)',
    );
    $stmt?->execute([$runId, $nodeIndex, $nodeTypeId, $encounterId, $metadata === null ? null : json_encode($metadata)]);
    return (int)$this->pdo?->lastInsertId();
  }

  /** @param array<string,mixed>|null $metadata */
  private function createEdge(int $runId, int $fromNodeId, int $toNodeId, ?array $metadata = null): void
  {
    $this->pdo?->prepare(
      'INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`, `generated_metadata`) VALUES (?, ?, ?, ?)',
    )->execute([$runId, $fromNodeId, $toNodeId, $metadata === null ? null : json_encode($metadata)]);
  }

  /** @param array<int,int|string> $params */
  private function fetchOne(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  /** @return array<int,string> */
  private function tableColumns(string $table): array
  {
    $stmt = $this->pdo?->prepare(
      'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
    );
    $stmt?->execute([$table]);
    return $stmt?->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }

  private function assertConstraintViolation(callable $operation): void
  {
    try {
      $operation();
      $this->fail('Expected the database constraint to reject the operation.');
    } catch (PDOException $e) {
      $this->assertContains((string)$e->getCode(), ['23000', 'HY000']);
    }
  }
}
