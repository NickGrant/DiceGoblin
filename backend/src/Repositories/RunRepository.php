<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\RunRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;
use DiceGoblins\Services\UnitProgressionService;
use RuntimeException;
use Throwable;

final class RunRepository
{
  private UnitProgressionService $unitProgression;

  public function __construct(
    private readonly PDO $pdo,
    ?UnitProgressionService $unitProgression = null,
  ) {
    $this->unitProgression = $unitProgression ?? new UnitProgressionService();
  }

  /**
   * Get the user's active run (if any).
   *
   * @return array{
   *   run_id:string,
   *   region_id:string,
   *   seed:string,
   *   status:string,
   *   started_at:string,
   *   ended_at:?string,
   *   generator_version:?string,
   *   generation_profile_version:?int,
   *   pattern_catalog_hash:?string,
   *   generation_attempt:?int,
   *   generation_summary:array<string,mixed>|null
   * }|null
   */
  public function getActiveRunForUser(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        `id`,
        `region_id`,
        `seed`,
        `status`,
        `started_at`,
        `ended_at`,
        `generator_version`,
        `generation_profile_version`,
        `pattern_catalog_hash`,
        `generation_attempt`,
        `generation_summary_json`
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
      'generator_version' => $r['generator_version'] !== null ? (string)$r['generator_version'] : null,
      'generation_profile_version' => $r['generation_profile_version'] !== null ? (int)$r['generation_profile_version'] : null,
      'pattern_catalog_hash' => $r['pattern_catalog_hash'] !== null ? (string)$r['pattern_catalog_hash'] : null,
      'generation_attempt' => $r['generation_attempt'] !== null ? (int)$r['generation_attempt'] : null,
      'generation_summary' => $this->decodeGenerationSummary($r['generation_summary_json'] ?? null),
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function decodeGenerationSummary(mixed $summaryJson): ?array
  {
    if (!is_string($summaryJson) || trim($summaryJson) === '') {
      return null;
    }

    $decoded = json_decode($summaryJson, true);
    return is_array($decoded) ? $decoded : null;
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
   *      'node_type' => 'combat'|'loot'|'rest'|'boss'|'exit'|'dialogue'|'hazard'|'shrine'|'chaos',
   *      'status' => 'locked'|'available'|'cleared' (optional; default locked),
   *      'encounter_template_id' => int|null (optional),
   *      'meta' => array|string|null (optional; will be JSON-encoded if array),
   *    ],
   *    ...
   *  ]
   *
   * $edges input shape (by node_index):
   *  [
   *    ['from' => 0, 'to' => 1, 'meta' => ['through' => [['x' => 1, 'y' => 0]]]],
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
    array $edges,
    ?array $generation = null
  ): array {
    $ownsTx = false;

    try {
      if (!$this->pdo->inTransaction()) {
        $this->pdo->beginTransaction();
        $ownsTx = true;
      }

      $runId = $this->createRun($userId, $regionId, (string)$seed);
      if ($generation !== null) {
        $this->updateRunGenerationMetadata($runId, $generation);
      }

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
   * @param array<string,mixed> $generation
   */
  public function updateRunGenerationMetadata(int $runId, array $generation): void
  {
    $summaryJson = $generation['generation_summary_json'] ?? $generation;
    if (is_array($summaryJson)) {
      $summaryJson = json_encode($summaryJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($summaryJson !== null && !is_string($summaryJson)) {
      throw new RuntimeException('Invalid generation summary metadata.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `region_runs`
      SET
        `generator_version` = ?,
        `generation_profile_version` = ?,
        `pattern_catalog_hash` = ?,
        `generation_attempt` = ?,
        `generation_summary_json` = ?
      WHERE `id` = ?
    ');
    $stmt->execute([
      $generation['generator_version'] ?? null,
      isset($generation['profile_version']) ? (int)$generation['profile_version'] : null,
      $generation['catalog_hash'] ?? null,
      isset($generation['generation_attempt']) ? (int)$generation['generation_attempt'] : null,
      $summaryJson,
      $runId,
    ]);
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
   * @return array<int, array{from_node_id:string,to_node_id:string,meta_json:?string}>
   */
  public function getRunEdges(int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `from_node_id`, `to_node_id`, `meta_json`
      FROM `run_edges`
      WHERE `run_id` = ?
      ORDER BY `from_node_id` ASC, `to_node_id` ASC
    ');
    $stmt->execute([$runId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'from_node_id' => (string)$r['from_node_id'],
      'to_node_id' => (string)$r['to_node_id'],
      'meta_json' => $r['meta_json'] !== null ? (string)$r['meta_json'] : null,
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
   * Return player-facing summaries for effects that still affect the current run.
   *
   * @return array<int,array{
   *   id:string,
   *   node_id:string,
   *   node_type:string,
   *   label:string,
   *   detail:string,
   *   persistence:string,
   *   source:string
   * }>
   */
  public function getActiveRunEffects(int $runId): array
  {
    return $this->activeRunEffectsFromUnitState($runId);
  }

  /**
   * @return array<int,array{
   *   id:string,
   *   node_id:string,
   *   node_type:string,
   *   label:string,
   *   detail:string,
   *   persistence:string,
   *   source:string
   * }>
   */
  private function activeRunEffectsFromUnitState(int $runId): array
  {
    $rows = $this->getRunUnitState($runId);
    $summaries = [];

    foreach ($rows as $row) {
      $rawEffects = json_decode((string)($row['status_effects_json'] ?? ''), true);
      if (!is_array($rawEffects)) {
        continue;
      }

      foreach ($rawEffects as $rawEffect) {
        if (!is_array($rawEffect)) {
          continue;
        }

        $effect = $this->normalizeActiveRunStatusEffect($rawEffect);
        if ($effect === null) {
          continue;
        }

        $key = $this->activeRunStatusEffectKey($effect);
        if (!isset($summaries[$key])) {
          $summaries[$key] = [
            'effect' => $effect,
            'unit_ids' => [],
          ];
        }
        $summaries[$key]['unit_ids'][] = (string)$row['unit_instance_id'];
      }
    }

    $out = [];
    foreach ($summaries as $key => $summary) {
      $effect = $summary['effect'];
      $source = $this->humanizeId((string)$effect['source']);
      $nodeType = in_array((string)$effect['source'], ['shrine', 'hazard'], true) ? (string)$effect['source'] : 'run';
      $unitCount = count(array_unique($summary['unit_ids']));
      $out[] = [
        'id' => 'run-status-' . $key,
        'node_id' => '',
        'node_type' => $nodeType,
        'label' => $source . ' Battle Effect',
        'detail' => $this->describeActiveRunStatusEffect($effect, $unitCount),
        'persistence' => 'next combat',
        'source' => $source,
      ];
    }

    return $out;
  }

  /**
   * @param array<string,mixed> $rawEffect
   * @return array{type:string,source:string,remaining_combats:int,stat_multipliers:array<string,float>,stat_adders:array<string,int>}|null
   */
  private function normalizeActiveRunStatusEffect(array $rawEffect): ?array
  {
    $type = trim((string)($rawEffect['type'] ?? ''));
    if (!in_array($type, ['squad_damage_next_combat', 'stat_modifier_next_combat', 'squad_stat_modifier_next_combat', 'run_stat_modifier_next_combat'], true)) {
      return null;
    }

    $remainingCombats = max(0, (int)($rawEffect['remaining_combats'] ?? 0));
    if ($remainingCombats <= 0) {
      return null;
    }

    $statMultipliers = $this->normalizeFloatMap($rawEffect['stat_multipliers'] ?? []);
    $statAdders = $this->normalizeIntMap($rawEffect['stat_adders'] ?? []);
    if ($type === 'squad_damage_next_combat') {
      $damageMultiplier = (float)($rawEffect['damage_multiplier'] ?? 1.0);
      if ($damageMultiplier > 0.0 && abs($damageMultiplier - 1.0) > 0.0001) {
        $statMultipliers['damage'] = $damageMultiplier;
      }
    }

    $statMultipliers = array_intersect_key($statMultipliers, array_flip(['attack', 'defense', 'precision', 'resolve', 'damage']));
    $statAdders = array_intersect_key($statAdders, array_flip(['attack', 'defense', 'precision', 'resolve']));
    if ($statMultipliers === [] && $statAdders === []) {
      return null;
    }

    return [
      'type' => $type,
      'source' => trim((string)($rawEffect['source'] ?? 'run')) ?: 'run',
      'remaining_combats' => $remainingCombats,
      'stat_multipliers' => $statMultipliers,
      'stat_adders' => $statAdders,
    ];
  }

  /**
   * @param array{type:string,source:string,remaining_combats:int,stat_multipliers:array<string,float>,stat_adders:array<string,int>} $effect
   */
  private function activeRunStatusEffectKey(array $effect): string
  {
    return sha1(json_encode([
      'type' => $effect['type'],
      'source' => $effect['source'],
      'remaining_combats' => $effect['remaining_combats'],
      'stat_multipliers' => $effect['stat_multipliers'],
      'stat_adders' => $effect['stat_adders'],
    ], JSON_UNESCAPED_SLASHES) ?: (string)microtime(true));
  }

  /**
   * @param array{type:string,source:string,remaining_combats:int,stat_multipliers:array<string,float>,stat_adders:array<string,int>} $effect
   */
  private function describeActiveRunStatusEffect(array $effect, int $unitCount): string
  {
    $parts = $this->describeModifierParts($effect['stat_multipliers'], $effect['stat_adders']);
    $unitCopy = $unitCount === 1 ? '1 unit' : $unitCount . ' units';
    $scope = $effect['remaining_combats'] === 1 ? 'the next combat' : $effect['remaining_combats'] . ' combats';
    return ($parts !== [] ? implode(', ', $parts) : 'Combat modifiers') . ' for ' . $unitCopy . ' during ' . $scope . '.';
  }

  /**
   * @param array<string,float> $statMultipliers
   * @param array<string,int> $statAdders
   * @return list<string>
   */
  private function describeModifierParts(array $statMultipliers, array $statAdders): array
  {
    $parts = [];
    foreach ($statMultipliers as $stat => $multiplier) {
      if (abs($multiplier - 1.0) <= 0.0001) {
        continue;
      }
      $bonus = (int)round(($multiplier - 1.0) * 100);
      $parts[] = ($bonus >= 0 ? '+' : '') . $bonus . '% ' . $this->humanizeId($stat);
    }
    foreach ($statAdders as $stat => $amount) {
      if ($amount === 0) {
        continue;
      }
      $parts[] = ($amount > 0 ? '+' : '') . $amount . ' ' . $this->humanizeId($stat);
    }

    return $parts;
  }

  /**
   * @return array<string,float>
   */
  private function normalizeFloatMap(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $key => $raw) {
      if (is_numeric($raw)) {
        $out[(string)$key] = (float)$raw;
      }
    }

    return $out;
  }

  /**
   * @return array<string,int>
   */
  private function normalizeIntMap(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $key => $raw) {
      if (is_numeric($raw)) {
        $out[(string)$key] = (int)$raw;
      }
    }

    return $out;
  }

  private function humanizeId(string $value): string
  {
    $segments = preg_split('/[_#\s-]+/', $value) ?: [];
    $segments = array_values(array_filter($segments, static fn(string $segment): bool => $segment !== ''));
    return implode(' ', array_map(static fn(string $segment): string => ucfirst($segment), $segments));
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
   * @param array<int,int> $unitInstanceIds
   */
  public function deleteRunUnitStateByUnitIds(int $runId, array $unitInstanceIds): void
  {
    $unitInstanceIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $unitInstanceIds)));
    if (count($unitInstanceIds) === 0) {
      return;
    }

    $placeholders = implode(',', array_fill(0, count($unitInstanceIds), '?'));
    $params = array_merge([$runId], $unitInstanceIds);

    $stmt = $this->pdo->prepare("
      DELETE FROM `run_unit_state`
      WHERE `run_id` = ? AND `unit_instance_id` IN ($placeholders)
    ");
    $stmt->execute($params);
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
      $level = max(1, (int)$row['level']);
      $maxHp = $this->unitProgression->maxHpForLevel(
        $row['base_stats_json'],
        $level,
        (int)$row['max_hp_per_level']
      );

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

      $level = max(1, (int)$row['level']);
      $maxHp = $this->unitProgression->maxHpForLevel(
        $row['base_stats_json'],
        $level,
        (int)$row['max_hp_per_level']
      );

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

    $this->applyAutoLevelForRunUnits($runId, $userId);

    return $defeatedIds;
  }

  /**
   * Apply backend-authoritative auto-level progression for all units in a run snapshot.
   *
   * @return array<int,array{id:string,from_level:int,to_level:int,from_xp:int,to_xp:int}>
   */
  public function applyAutoLevelForRunUnits(int $runId, int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        ui.`id` AS `unit_instance_id`,
        ui.`tier`,
        ui.`level`,
        ui.`xp`,
        ut.`max_level`
      FROM `run_unit_state` rus
      JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE rus.`run_id` = ? AND ui.`user_id` = ?
      ORDER BY ui.`id` ASC
      FOR UPDATE
    ');
    $stmt->execute([$runId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = [];
    foreach ($rows as $row) {
      $unitId = (int)$row['unit_instance_id'];
      $tier = max(1, (int)$row['tier']);
      $level = max(1, (int)$row['level']);
      $xp = max(0, (int)$row['xp']);
      $maxLevel = max(1, (int)$row['max_level']);

      $fromLevel = $level;
      $fromXp = $xp;

      ['level' => $level, 'xp' => $xp] = $this->unitProgression->resolveAutoLevel($tier, $level, $xp, $maxLevel);

      if ($level !== $fromLevel || $xp !== $fromXp) {
        $update = $this->pdo->prepare('
          UPDATE `unit_instances`
          SET `level` = ?, `xp` = ?
          WHERE `id` = ? AND `user_id` = ?
        ');
        $update->execute([$level, $xp, $unitId, $userId]);

        $updated[] = [
          'id' => (string)$unitId,
          'from_level' => $fromLevel,
          'to_level' => $level,
          'from_xp' => $fromXp,
          'to_xp' => $xp,
        ];
      }
    }

    return $updated;
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

      if (!in_array($nodeType, ['combat', 'loot', 'rest', 'boss', 'exit', 'dialogue', 'hazard', 'shrine', 'chaos'], true)) {
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
   * @param array<int, array<string,mixed>> $edges
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

      $meta = $this->edgeMetaJson($e);

      $valuesSql[] = '(?, ?, ?, ?)';
      $params[] = $runId;
      $params[] = $nodeIdByIndex[$fromIdx];
      $params[] = $nodeIdByIndex[$toIdx];
      $params[] = $meta;
    }

    $sql = '
      INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`, `meta_json`)
      VALUES ' . implode(',', $valuesSql);

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }

  /**
   * @param array<string,mixed> $edge
   */
  private function edgeMetaJson(array $edge): ?string
  {
    $meta = is_array($edge['meta'] ?? null) ? $edge['meta'] : [];
    if (is_array($edge['through'] ?? null)) {
      $meta['through'] = array_values(array_filter($edge['through'], 'is_array'));
    }

    if ($meta === []) {
      return null;
    }

    $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
      throw new RuntimeException('Invalid run edge metadata.');
    }

    return $json;
  }
}
