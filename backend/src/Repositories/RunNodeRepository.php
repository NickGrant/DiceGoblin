<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\RunNodeRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;

final class RunNodeRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Lock + fetch node row for a run.
   *
   * @return array{
   *   id:string,
   *   run_id:string,
   *   node_type:string,
   *   status:string,
   *   encounter_template_id:?string,
   *   meta_json:?string
   * }|null
   */
  public function getForUpdate(int $runId, int $nodeId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `run_id`, `node_type`, `status`, `encounter_template_id`, `meta_json`
      FROM `run_nodes`
      WHERE `id` = ? AND `run_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$nodeId, $runId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
      'id' => (string)$r['id'],
      'run_id' => (string)$r['run_id'],
      'node_type' => (string)$r['node_type'],
      'status' => (string)$r['status'],
      'encounter_template_id' => $r['encounter_template_id'] !== null ? (string)$r['encounter_template_id'] : null,
      'meta_json' => $r['meta_json'] !== null ? (string)$r['meta_json'] : null,
    ];
  }

  /**
   * Lock + fetch the first node row for a run by node_type.
   *
   * @return array{
   *   id:string,
   *   run_id:string,
   *   node_type:string,
   *   status:string,
   *   encounter_template_id:?string
   * }|null
   */
  public function getFirstByTypeForUpdate(int $runId, string $nodeType): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `run_id`, `node_type`, `status`, `encounter_template_id`
      FROM `run_nodes`
      WHERE `run_id` = ? AND `node_type` = ?
      ORDER BY `node_index` ASC
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$runId, $nodeType]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
      'id' => (string)$r['id'],
      'run_id' => (string)$r['run_id'],
      'node_type' => (string)$r['node_type'],
      'status' => (string)$r['status'],
      'encounter_template_id' => $r['encounter_template_id'] !== null ? (string)$r['encounter_template_id'] : null,
    ];
  }

  public function markCleared(int $runId, int $nodeId): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `run_nodes`
      SET `status` = \'cleared\'
      WHERE `id` = ? AND `run_id` = ? AND `status` != \'cleared\'
    ');
    $stmt->execute([$nodeId, $runId]);
  }

  public function setAvailableIfLocked(int $runId, int $nodeId): bool
  {
    $stmt = $this->pdo->prepare('
      UPDATE `run_nodes`
      SET `status` = \'available\'
      WHERE `id` = ? AND `run_id` = ? AND `status` = \'locked\'
    ');
    $stmt->execute([$nodeId, $runId]);
    return $stmt->rowCount() > 0;
  }

  /**
   * @return array<int,string>
   */
  public function listAvailableNodeIds(int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `run_nodes`
      WHERE `run_id` = ? AND `status` = \'available\'
      ORDER BY `node_index` ASC
    ');
    $stmt->execute([$runId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  /**
   * Reopen any locked node that is connected to at least one cleared node.
   *
   * This preserves the intended OR-style branch progression for visual map
   * connections, including branches that travel up-left or otherwise look
   * backward on the rendered map, and also self-heals stale availability if an
   * earlier unlock step was missed.
   *
   * @return array<int,string>
   */
  public function syncAvailableNodesFromClearedParents(int $runId): array
  {
    $select = $this->pdo->prepare('
      SELECT DISTINCT child.`id`
      FROM `run_nodes` child
      JOIN `run_edges` re
        ON re.`run_id` = child.`run_id`
       AND (re.`to_node_id` = child.`id` OR re.`from_node_id` = child.`id`)
      JOIN `run_nodes` neighbor
        ON neighbor.`run_id` = child.`run_id`
       AND neighbor.`id` = CASE
          WHEN re.`to_node_id` = child.`id` THEN re.`from_node_id`
          ELSE re.`to_node_id`
        END
      WHERE child.`run_id` = ?
        AND child.`status` = \'locked\'
        AND neighbor.`status` = \'cleared\'
    ');
    $select->execute([$runId]);
    $nodeIds = array_values(array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN)));

    if (count($nodeIds) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
    $params = array_merge([$runId], $nodeIds);

    $update = $this->pdo->prepare("
      UPDATE `run_nodes`
      SET `status` = 'available'
      WHERE `run_id` = ? AND `id` IN ($placeholders) AND `status` = 'locked'
    ");
    $update->execute($params);

    return array_map('strval', $nodeIds);
  }
}
