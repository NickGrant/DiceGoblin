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
   *   encounter_template_id:?string
   * }|null
   */
  public function getForUpdate(int $runId, int $nodeId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `run_id`, `node_type`, `status`, `encounter_template_id`
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
}
