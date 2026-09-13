<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

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

  /** @param list<int> $unitIds */
  public function insertParticipatingUnits(int $runId, array $unitIds): void
  {
    $stmt = $this->pdo->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, NULL)');
    foreach ($unitIds as $unitId) $stmt->execute([$runId, $unitId]);
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
