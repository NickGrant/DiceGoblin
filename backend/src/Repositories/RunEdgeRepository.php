<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\RunEdgeRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;

final class RunEdgeRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array<int,int> to_node_ids
   */
  public function getToNodeIdsFrom(int $runId, int $fromNodeId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `to_node_id`
      FROM `run_edges`
      WHERE `run_id` = ? AND `from_node_id` = ?
    ');
    $stmt->execute([$runId, $fromNodeId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  /**
   * @return array<int,int> connected node ids
   */
  public function getConnectedNodeIds(int $runId, int $nodeId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        CASE
          WHEN `from_node_id` = ? THEN `to_node_id`
          ELSE `from_node_id`
        END AS `connected_node_id`
      FROM `run_edges`
      WHERE `run_id` = ?
        AND (`from_node_id` = ? OR `to_node_id` = ?)
    ');
    $stmt->execute([$nodeId, $runId, $nodeId, $nodeId]);

    return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
  }

  /**
   * Count how many prerequisite parent nodes are NOT cleared.
   */
  public function countUnclearedPrerequisites(int $runId, int $toNodeId): int
  {
    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM `run_edges` re
      JOIN `run_nodes` rn ON rn.`id` = re.`from_node_id`
      WHERE re.`run_id` = ? AND re.`to_node_id` = ?
        AND rn.`status` != \'cleared\'
    ');
    $stmt->execute([$runId, $toNodeId]);
    return (int)$stmt->fetchColumn();
  }
}
