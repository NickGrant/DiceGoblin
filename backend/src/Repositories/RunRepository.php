<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\RunRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class RunRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Get the user's active run (if any).
   *
   * @return array{
   *   run_id:string,
   *   region_id:string,
   *   seed:string,
   *   status:string,
   *   started_at:string,
   *   ended_at:?string
   * }|null
   */
  public function getActiveRunForUser(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `region_id`, `seed`, `status`, `started_at`, `ended_at`
      FROM `region_runs`
      WHERE `user_id` = ?
        AND `status` = \'active\'
      ORDER BY `id` DESC
      LIMIT 1
    ');
    $stmt->execute([$userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'run_id' => (string)$r['id'],
      'region_id' => (string)$r['region_id'],
      'seed' => (string)$r['seed'],
      'status' => (string)$r['status'],
      'started_at' => (string)$r['started_at'],
      'ended_at' => $r['ended_at'] !== null ? (string)$r['ended_at'] : null,
    ];
  }

  /**
   * Get a run by id and assert ownership by user.
   *
   * @return array{
   *   id:string,
   *   user_id:string,
   *   region_id:string,
   *   seed:string,
   *   status:string,
   *   started_at:string,
   *   ended_at:?string
   * }|null
   */
  public function getRunForUser(int $userId, int $runId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `user_id`, `region_id`, `seed`, `status`, `started_at`, `ended_at`
      FROM `region_runs`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$runId, $userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'user_id' => (string)$r['user_id'],
      'region_id' => (string)$r['region_id'],
      'seed' => (string)$r['seed'],
      'status' => (string)$r['status'],
      'started_at' => (string)$r['started_at'],
      'ended_at' => $r['ended_at'] !== null ? (string)$r['ended_at'] : null,
    ];
  }

  /**
   * Create a new run row.
   * Note: does not create nodes/edges. Use createRunGraph() or do it in a service.
   *
   * @return int runId
   */
  public function createRun(int $userId, int $regionId, int|string $seed): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`)
      VALUES (?, ?, ?, \'active\')
    ');
    $stmt->execute([$userId, $regionId, (string)$seed]);

    return (int)$this->pdo->lastInsertId();
  }

  /**
   * Mark a run as completed/failed/abandoned and set ended_at.
   * If $endedAtSqlUtc is null, uses UTC_TIMESTAMP().
   */
  public function endRun(int $userId, int $runId, string $status, ?string $endedAtSqlUtc = null): void
  {
    if (!in_array($status, ['completed', 'failed', 'abandoned'], true)) {
      throw new RuntimeException('Invalid run end status.');
    }

    if ($endedAtSqlUtc === null) {
      $stmt = $this->pdo->prepare('
        UPDATE `region_runs`
        SET `status` = ?, `ended_at` = UTC_TIMESTAMP()
        WHERE `id` = ? AND `user_id` = ? AND `status` = \'active\'
      ');
      $stmt->execute([$status, $runId, $userId]);
    } else {
      $stmt = $this->pdo->prepare('
        UPDATE `region_runs`
        SET `status` = ?, `ended_at` = ?
        WHERE `id` = ? AND `user_id` = ? AND `status` = \'active\'
      ');
      $stmt->execute([$status, $endedAtSqlUtc, $runId, $userId]);
    }

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Run not found, not owned, or not active.');
    }
  }

  /**
   * End any active runs for a user (useful if you enforce "at most one active run").
   * Returns number of rows updated.
   */
  public function abandonActiveRunsForUser(int $userId): int
  {
    $stmt = $this->pdo->prepare('
      UPDATE `region_runs`
      SET `status` = \'abandoned\', `ended_at` = UTC_TIMESTAMP()
      WHERE `user_id` = ? AND `status` = \'active\'
    ');
    $stmt->execute([$userId]);

    return (int)$stmt->rowCount();
  }

  /**
   * Create a full run graph (region_runs + run_nodes + run_edges) transactionally.
   *
   * $nodes input shape:
   *  [
   *    [
   *      'node_index' => 0,
   *      'node_type' => 'combat'|'loot'|'rest'|'boss',
   *      'status' => 'locked'|'available'|'cleared' (optional; default locked),
   *      'encounter_template_id' => int|null (optional),
   *      'meta' => array|string|null (optional; will be JSON-encoded if array),
   *    ],
   *    ...
   *  ]
   *
   * $edges input shape (by node_index):
   *  [
   *    ['from' => 0, 'to' => 1],
   *    ['from' => 0, 'to' => 2],
   *    ...
   *  ]
   *
   * @return array{run_id:int, node_id_by_index:array<int,int>}
   */
  public function createRunGraph(
    int $userId,
    int $regionId,
    int|string $seed,
    array $nodes,
    array $edges
  ): array {
    $ownsTx = false;

    try {
      if (!$this->pdo->inTransaction()) {
        $this->pdo->beginTransaction();
        $ownsTx = true;
      }

      $runId = $this->createRun($userId, $regionId, (string)$seed);

      $nodeIdByIndex = $this->insertRunNodes($runId, $nodes);

      $this->insertRunEdgesByIndex($runId, $nodeIdByIndex, $edges);

      if ($ownsTx) {
        $this->pdo->commit();
      }

      return ['run_id' => $runId, 'node_id_by_index' => $nodeIdByIndex];
    } catch (Throwable $e) {
      if ($ownsTx && $this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }


  /**
   * Fetch all run nodes for a run.
   *
   * @return array<int, array{
   *   id:string,
   *   run_id:string,
   *   node_index:int,
   *   node_type:string,
   *   status:string,
   *   encounter_template_id:?string,
   *   meta_json:?string
   * }>
   */
  public function getRunNodes(int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`
      FROM `run_nodes`
      WHERE `run_id` = ?
      ORDER BY `node_index` ASC
    ');
    $stmt->execute([$runId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'id' => (string)$r['id'],
      'run_id' => (string)$r['run_id'],
      'node_index' => (int)$r['node_index'],
      'node_type' => (string)$r['node_type'],
      'status' => (string)$r['status'],
      'encounter_template_id' => $r['encounter_template_id'] !== null ? (string)$r['encounter_template_id'] : null,
      'meta_json' => $r['meta_json'] !== null ? (string)$r['meta_json'] : null,
    ], $rows);
  }

  /**
   * Fetch run edges for a run (node ids).
   *
   * @return array<int, array{from_node_id:string,to_node_id:string}>
   */
  public function getRunEdges(int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `from_node_id`, `to_node_id`
      FROM `run_edges`
      WHERE `run_id` = ?
      ORDER BY `from_node_id` ASC, `to_node_id` ASC
    ');
    $stmt->execute([$runId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'from_node_id' => (string)$r['from_node_id'],
      'to_node_id' => (string)$r['to_node_id'],
    ], $rows);
  }

  /**
   * Update a node status by node id (and run_id safety).
   */
  public function setNodeStatus(int $runId, int $nodeId, string $status): void
  {
    if (!in_array($status, ['locked', 'available', 'cleared'], true)) {
      throw new RuntimeException('Invalid node status.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `run_nodes`
      SET `status` = ?
      WHERE `run_id` = ? AND `id` = ?
    ');
    $stmt->execute([$status, $runId, $nodeId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Run node not found.');
    }
  }

  /**
   * Update a node status by node_index (and run_id safety).
   */
  public function setNodeStatusByIndex(int $runId, int $nodeIndex, string $status): void
  {
    if (!in_array($status, ['locked', 'available', 'cleared'], true)) {
      throw new RuntimeException('Invalid node status.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `run_nodes`
      SET `status` = ?
      WHERE `run_id` = ? AND `node_index` = ?
    ');
    $stmt->execute([$status, $runId, $nodeIndex]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Run node not found.');
    }
  }

  /**
   * Get run_unit_state rows for a run.
   *
   * @return array<int, array{
   *   unit_instance_id:string,
   *   current_hp:int,
   *   is_defeated:bool,
   *   cooldowns_json:string,
   *   status_effects_json:string,
   *   updated_at:string
   * }>
   */
  public function getRunUnitState(int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`, `updated_at`
      FROM `run_unit_state`
      WHERE `run_id` = ?
      ORDER BY `unit_instance_id` ASC
    ');
    $stmt->execute([$runId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'unit_instance_id' => (string)$r['unit_instance_id'],
      'current_hp' => (int)$r['current_hp'],
      'is_defeated' => ((int)$r['is_defeated']) === 1,
      'cooldowns_json' => (string)$r['cooldowns_json'],
      'status_effects_json' => (string)$r['status_effects_json'],
      'updated_at' => (string)$r['updated_at'],
    ], $rows);
  }

  /**
   * Upsert a single unit's run state.
   * This is useful after a battle to persist HP/cooldowns/effects.
   *
   * IMPORTANT:
   * cooldowns_json and status_effects_json should be valid JSON strings.
   */
  public function upsertRunUnitState(
    int $runId,
    int $unitInstanceId,
    int $currentHp,
    bool $isDefeated,
    string $cooldownsJson,
    string $statusEffectsJson,
  ): void {
    $stmt = $this->pdo->prepare('
      INSERT INTO `run_unit_state` (
        `run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`
      ) VALUES (?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `current_hp` = VALUES(`current_hp`),
        `is_defeated` = VALUES(`is_defeated`),
        `cooldowns_json` = VALUES(`cooldowns_json`),
        `status_effects_json` = VALUES(`status_effects_json`)
    ');
    $stmt->execute([
      $runId,
      $unitInstanceId,
      $currentHp,
      $isDefeated ? 1 : 0,
      $cooldownsJson,
      $statusEffectsJson,
    ]);
  }

  /**
   * Bulk insert run_unit_state for a set of units.
   *
   * $rows input shape:
   *  [
   *    [
   *      'unit_instance_id' => 123,
   *      'current_hp' => 25,
   *      'is_defeated' => false,
   *      'cooldowns_json' => '{}',
   *      'status_effects_json' => '[]',
   *    ],
   *    ...
   *  ]
   */
  public function insertRunUnitStateBulk(int $runId, array $rows): void
  {
    if (count($rows) === 0) {
      return;
    }

    $valuesSql = [];
    $params = [];

    foreach ($rows as $r) {
      $valuesSql[] = '(?, ?, ?, ?, ?, ?)';
      $params[] = $runId;
      $params[] = (int)$r['unit_instance_id'];
      $params[] = (int)$r['current_hp'];
      $params[] = !empty($r['is_defeated']) ? 1 : 0;
      $params[] = (string)$r['cooldowns_json'];
      $params[] = (string)$r['status_effects_json'];
    }

    $sql = '
      INSERT INTO `run_unit_state` (
        `run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`
      ) VALUES ' . implode(',', $valuesSql) . '
      ON DUPLICATE KEY UPDATE
        `current_hp` = VALUES(`current_hp`),
        `is_defeated` = VALUES(`is_defeated`),
        `cooldowns_json` = VALUES(`cooldowns_json`),
        `status_effects_json` = VALUES(`status_effects_json`)
    ';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }

  /**
   * Lock and fetch run_unit_state rows for mutation flows.
   *
   * @return array<int, array{
   *   unit_instance_id:string,
   *   current_hp:int,
   *   is_defeated:bool,
   *   cooldowns_json:string,
   *   status_effects_json:string,
   *   updated_at:string
   * }>
   */
  public function getRunUnitStateForUpdate(int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`, `updated_at`
      FROM `run_unit_state`
      WHERE `run_id` = ?
      ORDER BY `unit_instance_id` ASC
      FOR UPDATE
    ');
    $stmt->execute([$runId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'unit_instance_id' => (string)$r['unit_instance_id'],
      'current_hp' => (int)$r['current_hp'],
      'is_defeated' => ((int)$r['is_defeated']) === 1,
      'cooldowns_json' => (string)$r['cooldowns_json'],
      'status_effects_json' => (string)$r['status_effects_json'],
      'updated_at' => (string)$r['updated_at'],
    ], $rows);
  }

  /**
   * Seed run_unit_state from a squad snapshot at run start.
   *
   * Copies all units currently in the team into run-scoped state using
   * each unit's computed max HP at current level.
   */
  public function seedRunUnitStateFromTeam(int $runId, int $userId, int $teamId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT
        ui.`id` AS `unit_instance_id`,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`max_hp_per_level`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE tu.`team_id` = ? AND ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$teamId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) === 0) {
      return;
    }

    $seedRows = [];
    foreach ($rows as $row) {
      $baseStatsRaw = $row['base_stats_json'];
      $baseStats = is_string($baseStatsRaw) ? json_decode($baseStatsRaw, true) : $baseStatsRaw;
      if (!is_array($baseStats)) {
        $baseStats = [];
      }

      $level = max(1, (int)$row['level']);
      $baseMaxHp = max(1, (int)($baseStats['max_hp'] ?? 1));
      $maxHpPerLevel = max(0, (int)$row['max_hp_per_level']);
      $maxHp = $baseMaxHp + (($level - 1) * $maxHpPerLevel);

      $seedRows[] = [
        'unit_instance_id' => (int)$row['unit_instance_id'],
        'current_hp' => $maxHp,
        'is_defeated' => false,
        'cooldowns_json' => '{}',
        'status_effects_json' => '[]',
      ];
    }

    $this->insertRunUnitStateBulk($runId, $seedRows);
  }

  /**
   * Apply run end cleanup rules and optionally reset defeated units XP to 0.
   *
   * @return array<int,int> defeated unit ids that had XP reset
   */
  public function applyRunEndCleanup(int $runId, int $userId, bool $resetDefeatedXp): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        rus.`unit_instance_id`,
        rus.`is_defeated`,
        rus.`current_hp`,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`max_hp_per_level`
      FROM `run_unit_state` rus
      JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE rus.`run_id` = ? AND ui.`user_id` = ?
      ORDER BY rus.`unit_instance_id` ASC
      FOR UPDATE
    ');
    $stmt->execute([$runId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $defeatedIds = [];
    foreach ($rows as $row) {
      $unitId = (int)$row['unit_instance_id'];
      $wasDefeated = ((int)$row['is_defeated']) === 1 || ((int)$row['current_hp']) <= 0;
      if ($resetDefeatedXp && $wasDefeated) {
        $defeatedIds[] = $unitId;
      }

      $baseStats = json_decode((string)$row['base_stats_json'], true);
      if (!is_array($baseStats)) {
        $baseStats = [];
      }
      $level = max(1, (int)$row['level']);
      $baseMaxHp = max(1, (int)($baseStats['max_hp'] ?? 1));
      $maxHpPerLevel = max(0, (int)$row['max_hp_per_level']);
      $maxHp = $baseMaxHp + (($level - 1) * $maxHpPerLevel);

      $this->upsertRunUnitState(
        $runId,
        $unitId,
        $maxHp,
        false,
        '{}',
        '[]'
      );
    }

    if ($resetDefeatedXp && count($defeatedIds) > 0) {
      $defeatedIds = array_values(array_unique($defeatedIds));
      $placeholders = implode(',', array_fill(0, count($defeatedIds), '?'));
      $params = array_merge([$userId], $defeatedIds);
      $xpStmt = $this->pdo->prepare("
        UPDATE `unit_instances`
        SET `xp` = 0
        WHERE `user_id` = ? AND `id` IN ($placeholders)
      ");
      $xpStmt->execute($params);
    }

    return $defeatedIds;
  }

  // -----------------------------
  // Internals: graph inserts
  // -----------------------------

  /**
   * @param array<int, array{
   *   node_index:int,
   *   node_type:string,
   *   status?:string,
   *   encounter_template_id?:int|null,
   *   meta?:array|string|null
   * }> $nodes
   *
   * @return array<int,int> nodeIdByIndex (node_index => node_id)
   */
  private function insertRunNodes(int $runId, array $nodes): array
  {
    if (count($nodes) === 0) {
      throw new RuntimeException('Run graph requires at least one node.');
    }

    // Build multi-row insert.
    $valuesSql = [];
    $params = [];

    foreach ($nodes as $n) {
      $nodeIndex = (int)$n['node_index'];
      $nodeType = (string)$n['node_type'];
      $status = isset($n['status']) ? (string)$n['status'] : 'locked';
      $encounterTemplateId = $n['encounter_template_id'] ?? null;

      if (!in_array($nodeType, ['combat', 'loot', 'rest', 'boss'], true)) {
        throw new RuntimeException('Invalid node_type: ' . $nodeType);
      }
      if (!in_array($status, ['locked', 'available', 'cleared'], true)) {
        throw new RuntimeException('Invalid node status: ' . $status);
      }

      $metaJson = null;
      if (array_key_exists('meta', $n)) {
        $meta = $n['meta'];
        if (is_array($meta)) {
          $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($meta)) {
          $metaJson = $meta; // caller-provided JSON
        } elseif ($meta === null) {
          $metaJson = null;
        } else {
          throw new RuntimeException('Invalid meta field; expected array|string|null.');
        }
      }

      $valuesSql[] = '(?, ?, ?, ?, ?, ?)';
      $params[] = $runId;
      $params[] = $nodeIndex;
      $params[] = $nodeType;
      $params[] = $status;
      $params[] = $encounterTemplateId !== null ? (int)$encounterTemplateId : null;
      $params[] = $metaJson;
    }

    $sql = '
      INSERT INTO `run_nodes` (
        `run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`
      ) VALUES ' . implode(',', $valuesSql);

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch ids for mapping.
    $stmt = $this->pdo->prepare('
      SELECT `id`, `node_index`
      FROM `run_nodes`
      WHERE `run_id` = ?
    ');
    $stmt->execute([$runId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $r) {
      $map[(int)$r['node_index']] = (int)$r['id'];
    }

    return $map;
  }

  /**
   * Insert edges by node_index using a node_id map.
   *
   * @param array<int,int> $nodeIdByIndex
   * @param array<int, array{from:int,to:int}> $edges
   */
  private function insertRunEdgesByIndex(int $runId, array $nodeIdByIndex, array $edges): void
  {
    if (count($edges) === 0) {
      return;
    }

    $valuesSql = [];
    $params = [];

    foreach ($edges as $e) {
      $fromIdx = (int)$e['from'];
      $toIdx = (int)$e['to'];

      if (!isset($nodeIdByIndex[$fromIdx]) || !isset($nodeIdByIndex[$toIdx])) {
        throw new RuntimeException('Edge references unknown node_index.');
      }

      $valuesSql[] = '(?, ?, ?)';
      $params[] = $runId;
      $params[] = $nodeIdByIndex[$fromIdx];
      $params[] = $nodeIdByIndex[$toIdx];
    }

    $sql = '
      INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`)
      VALUES ' . implode(',', $valuesSql);

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }
}
