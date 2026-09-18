<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class RunNodeResolutionRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array<string,mixed>|null */
  public function findNodeForUpdate(int $runId, int $nodeId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `run_id`, `node_type_id`, `encounter_id`, `event_id`, `status`, `completed_at`
      FROM `run_nodes` WHERE `run_id` = ? AND `id` = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$runId, $nodeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  public function battleExists(int $runId, int $nodeId): bool
  {
    $stmt = $this->pdo->prepare('SELECT 1 FROM `battles` WHERE `run_id` = ? AND `run_node_id` = ? LIMIT 1');
    $stmt->execute([$runId, $nodeId]);
    return $stmt->fetchColumn() !== false;
  }

  /** @return list<array<string,mixed>> */
  public function listParticipatingUnitsForUpdate(int $runId): array
  {
    $stmt = $this->pdo->prepare('SELECT `run_id`, `unit_id`, `current_hp` FROM `run_unit_state`
      WHERE `run_id` = ? ORDER BY `unit_id` ASC FOR UPDATE');
    $stmt->execute([$runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function persistUnitHp(int $runId, int $unitId, int $currentHp): void
  {
    $stmt = $this->pdo->prepare('UPDATE `run_unit_state` SET `current_hp` = ? WHERE `run_id` = ? AND `unit_id` = ?');
    $stmt->execute([$currentHp, $runId, $unitId]);
    if ($stmt->rowCount() === 0) {
      $check = $this->pdo->prepare('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ? AND `current_hp` = ?');
      $check->execute([$runId, $unitId, $currentHp]);
      if ((int)$check->fetchColumn() !== 1) throw new RuntimeException('Participating unit HP could not be persisted.');
    }
  }

  public function completeNode(int $runId, int $nodeId, DateTimeImmutable $completedAt): void
  {
    $stmt = $this->pdo->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = ?
      WHERE `run_id` = ? AND `id` = ? AND `status` = 'available'");
    $stmt->execute([$completedAt->format('Y-m-d H:i:s'), $runId, $nodeId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Run node could not be completed.');
  }

  /** @return list<int> */
  public function unlockDirectOutgoingNodes(int $runId, int $nodeId): array
  {
    $select = $this->pdo->prepare("SELECT child.`id` FROM `run_edges` edge
      JOIN `run_nodes` child ON child.`run_id` = edge.`run_id` AND child.`id` = edge.`to_node_id`
      WHERE edge.`run_id` = ? AND edge.`from_node_id` = ? AND child.`status` = 'locked'
      ORDER BY child.`id` ASC FOR UPDATE");
    $select->execute([$runId, $nodeId]);
    $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));
    $update = $this->pdo->prepare("UPDATE `run_nodes` SET `status` = 'available'
      WHERE `run_id` = ? AND `id` = ? AND `status` = 'locked'");
    $changed = [];
    foreach ($ids as $id) {
      $update->execute([$runId, $id]);
      if ($update->rowCount() === 1) $changed[] = $id;
    }
    return $changed;
  }

  public function failRun(int $userId, int $runId, DateTimeImmutable $endedAt): void
  {
    $stmt = $this->pdo->prepare("UPDATE `runs` SET `status` = 'failed', `ended_at` = ?
      WHERE `id` = ? AND `user_id` = ? AND `status` = 'active'");
    $stmt->execute([$endedAt->format('Y-m-d H:i:s'), $runId, $userId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Run could not be failed.');
  }
}
