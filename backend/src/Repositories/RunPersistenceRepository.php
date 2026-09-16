<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;

final class RunPersistenceRepository
{
  public function __construct(private readonly PDO $pdo) {}

  public function findActiveRunIdForUser(int $userId): ?int
  {
    $stmt = $this->pdo->prepare("SELECT `id` FROM `runs` WHERE `user_id` = ? AND `status` = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
  }

  /** @return array<string,mixed>|null */
  public function findActiveRunForUser(int $userId): ?array
  {
    $stmt = $this->pdo->prepare("SELECT r.`id`, r.`user_id`, r.`region_id`, r.`squad_id`, r.`status`,
        r.`created_at`, r.`ended_at`, s.`user_id` AS `squad_user_id`
      FROM `runs` r LEFT JOIN `squads` s ON s.`id` = r.`squad_id`
      WHERE r.`user_id` = ? AND r.`status` = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  /** @return array<string,mixed>|null */
  public function findOwnedRunForUpdate(int $userId, int $runId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT r.`id`, r.`user_id`, r.`region_id`, r.`squad_id`, r.`status`,
        r.`created_at`, r.`ended_at`, s.`user_id` AS `squad_user_id`
      FROM `runs` r LEFT JOIN `squads` s ON s.`id` = r.`squad_id`
      WHERE r.`id` = ? AND r.`user_id` = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$runId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  /** @return list<array<string,mixed>> */
  public function listNodes(int $runId): array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `run_id`, `node_index`, `node_type_id`, `status`, `completed_at`,
        `generated_metadata` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index` ASC, `id` ASC');
    $stmt->execute([$runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function listEdges(int $runId): array
  {
    $stmt = $this->pdo->prepare('SELECT `run_id`, `from_node_id`, `to_node_id` FROM `run_edges`
      WHERE `run_id` = ? ORDER BY `from_node_id` ASC, `to_node_id` ASC');
    $stmt->execute([$runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function listParticipatingUnits(int $runId): array
  {
    $stmt = $this->pdo->prepare('SELECT rus.`run_id`, rus.`unit_id`, rus.`current_hp`, ui.`user_id` AS `unit_user_id`
      FROM `run_unit_state` rus LEFT JOIN `unit_instances` ui ON ui.`id` = rus.`unit_id`
      WHERE rus.`run_id` = ? ORDER BY rus.`unit_id` ASC');
    $stmt->execute([$runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function abandon(int $userId, int $runId, DateTimeImmutable $endedAt): void
  {
    $stmt = $this->pdo->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = ?
      WHERE `id` = ? AND `user_id` = ? AND `status` = 'active'");
    $stmt->execute([$endedAt->format('Y-m-d H:i:s'), $runId, $userId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Active run could not be abandoned.');
  }

  public function createRun(int $userId, string $regionId, int $squadId): int
  {
    $stmt = $this->pdo->prepare("INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`, `status`) VALUES (?, ?, ?, 'active')");
    $stmt->execute([$userId, $regionId, $squadId]);
    return (int)$this->pdo->lastInsertId();
  }

  /** @param list<array<string,mixed>> $nodes @return array<int,int> node index to persisted id */
  public function insertNodes(int $runId, array $nodes): array
  {
    $stmt = $this->pdo->prepare('INSERT INTO `run_nodes`
      (`run_id`, `node_index`, `node_type_id`, `encounter_id`, `status`, `generated_metadata`)
      VALUES (?, ?, ?, ?, ?, ?)');
    $ids = [];
    foreach ($nodes as $node) {
      $index = (int)$node['node_index'];
      $stmt->execute([
        $runId,
        $index,
        $node['node_type_id'],
        $node['encounter_id'],
        $node['status'],
        $this->encodeMetadata($node['generated_metadata'] ?? null),
      ]);
      $ids[$index] = (int)$this->pdo->lastInsertId();
    }
    return $ids;
  }

  /** @param list<array<string,mixed>> $edges @param array<int,int> $nodeIds */
  public function insertEdges(int $runId, array $edges, array $nodeIds): void
  {
    $stmt = $this->pdo->prepare('INSERT INTO `run_edges`
      (`run_id`, `from_node_id`, `to_node_id`, `generated_metadata`) VALUES (?, ?, ?, ?)');
    foreach ($edges as $edge) {
      $from = $edge['from_node_index'];
      $to = $edge['to_node_index'];
      if (!is_int($from) || !is_int($to) || !isset($nodeIds[$from], $nodeIds[$to])) {
        throw new RuntimeException('Generated edge endpoint could not be persisted.');
      }
      $stmt->execute([$runId, $nodeIds[$from], $nodeIds[$to], $this->encodeMetadata($edge['generated_metadata'] ?? null)]);
    }
  }

  /** @param list<array{unit_id:int,current_hp:int}> $units */
  public function insertParticipatingUnits(int $runId, array $units): void
  {
    $stmt = $this->pdo->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, ?)');
    foreach ($units as $unit) $stmt->execute([$runId, $unit['unit_id'], $unit['current_hp']]);
  }

  private function encodeMetadata(mixed $metadata): ?string
  {
    if ($metadata === null) return null;
    try {
      return json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $e) {
      throw new RuntimeException('Generated run metadata could not be encoded.', 0, $e);
    }
  }
}
