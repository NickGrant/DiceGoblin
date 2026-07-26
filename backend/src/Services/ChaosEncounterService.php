<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunEdgeRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use PDO;
use RuntimeException;
use Throwable;

final class ChaosEncounterService
{
  /** @var array<int,array<int,array<string,mixed>>> */
  private const REEL_POOLS = [
    0 => [
      ['symbol' => 'pigs', 'label' => 'Pigs', 'weight' => 30, 'risk' => 1, 'effect' => 'Pig-family pressure.'],
      ['symbol' => 'kobolds', 'label' => 'Kobolds', 'weight' => 30, 'risk' => 2, 'effect' => 'Trap-ready kobold pressure.'],
      ['symbol' => 'frogmen', 'label' => 'Frogmen', 'weight' => 25, 'risk' => 2, 'effect' => 'Swamp attrition pressure.'],
      ['symbol' => 'mixed', 'label' => 'Mixed Mob', 'weight' => 15, 'risk' => 3, 'effect' => 'A messy mixed-family pull.'],
    ],
    1 => [
      ['symbol' => 'horde', 'label' => 'Horde', 'weight' => 30, 'risk' => 2, 'effect' => 'More bodies than usual.'],
      ['symbol' => 'armored_frontline', 'label' => 'Armored Frontline', 'weight' => 25, 'risk' => 2, 'effect' => 'A tougher front rank.'],
      ['symbol' => 'ranged_backline', 'label' => 'Ranged Backline', 'weight' => 25, 'risk' => 2, 'effect' => 'Backline pressure.'],
      ['symbol' => 'ambush', 'label' => 'Ambush', 'weight' => 20, 'risk' => 3, 'effect' => 'A dangerous opening position.'],
    ],
    2 => [
      ['symbol' => 'bolstered_enemies', 'label' => 'Bolstered Enemies', 'weight' => 25, 'risk' => 3, 'effect' => 'Enemies begin with a small advantage.'],
      ['symbol' => 'volatile_dice', 'label' => 'Volatile Dice', 'weight' => 25, 'risk' => 2, 'effect' => 'Dice volatility increases risk and payout.'],
      ['symbol' => 'guaranteed_loot', 'label' => 'Guaranteed Loot', 'weight' => 30, 'risk' => 1, 'effect' => 'Victory promises extra loot.'],
      ['symbol' => 'raw_chaos_spark', 'label' => 'Raw Chaos Spark', 'weight' => 20, 'risk' => 2, 'effect' => 'Victory can feed later chaos systems.'],
    ],
  ];

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array<string,mixed>
   */
  public function generate(int $userId, int $runId, int $nodeId): array
  {
    try {
      $this->pdo->beginTransaction();
      $context = $this->lockRunNodeContext($userId, $runId, $nodeId);
      $existing = $this->findForNodeForUpdate($nodeId);

      if ($existing === null) {
        $seed = $this->seedFor($userId, $runId, $nodeId, 0);
        $reels = $this->generateReels($seed, null, null);
        $this->insertResult($userId, $runId, $nodeId, $seed, $reels);
        $existing = $this->findForNodeForUpdate($nodeId);
      }

      $this->pdo->commit();
      return $this->mapResult($existing ?? [], $context);
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array<string,mixed>
   */
  public function rerollOneReel(int $userId, int $runId, int $nodeId, int $reelIndex): array
  {
    if (!array_key_exists($reelIndex, self::REEL_POOLS)) {
      throw new RuntimeException('invalid_reel_index');
    }

    try {
      $this->pdo->beginTransaction();
      $context = $this->lockRunNodeContext($userId, $runId, $nodeId);
      $existing = $this->findForNodeForUpdate($nodeId);
      if ($existing === null) {
        $this->pdo->rollBack();
        throw new RuntimeException('chaos_result_not_generated');
      }
      if ((string)$existing['status'] === 'confirmed') {
        $this->pdo->rollBack();
        throw new RuntimeException('chaos_result_confirmed');
      }
      if ((int)$existing['manipulation_count'] >= 1) {
        $this->pdo->rollBack();
        throw new RuntimeException('chaos_reroll_spent');
      }

      $currentReels = $this->decodeReels((string)$existing['reels_json']);
      $seed = $this->seedFor($userId, $runId, $nodeId, $reelIndex + 10);
      $reels = $this->generateReels($seed, $reelIndex, $currentReels);
      $stmt = $this->pdo->prepare('
        UPDATE `chaos_encounter_results`
        SET `status` = \'manipulated\',
            `seed` = ?,
            `reels_json` = ?,
            `reward_multiplier` = ?,
            `rerolled_reel_index` = ?,
            `manipulation_count` = 1
        WHERE `id` = ?
      ');
      $stmt->execute([
        $seed,
        json_encode($reels, JSON_UNESCAPED_SLASHES),
        $this->rewardMultiplier($reels),
        $reelIndex,
        (int)$existing['id'],
      ]);

      $updated = $this->findForNodeForUpdate($nodeId);
      $this->pdo->commit();
      return $this->mapResult($updated ?? [], $context);
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array<string,mixed>
   */
  public function finalize(int $userId, int $runId, int $nodeId): array
  {
    try {
      $this->pdo->beginTransaction();
      $context = $this->lockRunNodeContext($userId, $runId, $nodeId);
      $existing = $this->findForNodeForUpdate($nodeId);
      if ($existing === null) {
        $this->pdo->rollBack();
        throw new RuntimeException('chaos_result_not_generated');
      }

      $rewards = $this->decodeFinalizedRewards($existing['finalized_rewards_json'] ?? null);
      if ((string)$existing['status'] !== 'confirmed' || $rewards === null) {
        $reels = $this->decodeReels((string)$existing['reels_json']);
        $rewards = $this->buildFinalizedRewards($reels, (float)$existing['reward_multiplier']);
        $this->bindEncounterTemplate($runId, $nodeId, (int)$context['run']['region_id'], $reels, (int)$existing['seed']);

        $stmt = $this->pdo->prepare('
          UPDATE `chaos_encounter_results`
          SET `status` = \'confirmed\',
              `finalized_rewards_json` = ?,
              `finalized_at` = UTC_TIMESTAMP()
          WHERE `id` = ?
        ');
        $stmt->execute([
          json_encode($rewards, JSON_UNESCAPED_SLASHES),
          (int)$existing['id'],
        ]);
      } elseif (($context['node']['encounter_template_id'] ?? null) === null) {
        $reels = $this->decodeReels((string)$existing['reels_json']);
        $this->bindEncounterTemplate($runId, $nodeId, (int)$context['run']['region_id'], $reels, (int)$existing['seed']);
      }

      $updated = $this->findForNodeForUpdate($nodeId) ?? $existing;
      $mapped = $this->mapResult($updated, $context);
      $mapped['completion'] = $this->completionCopy($mapped['chaos_result']['summary']['title'] ?? 'Chaos Result');
      $mapped['rewards'] = $rewards;
      $mapped['next'] = ['unlocked_node_ids' => []];

      $this->pdo->commit();
      return $mapped;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array{run:array<string,mixed>,node:array<string,mixed>}
   */
  private function lockRunNodeContext(int $userId, int $runId, int $nodeId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        rr.`id` AS `run_id`,
        rr.`region_id`,
        rr.`status` AS `run_status`,
        rn.`id` AS `node_id`,
        rn.`node_type`,
        rn.`status` AS `node_status`,
        rn.`encounter_template_id`
      FROM `region_runs` rr
      JOIN `run_nodes` rn ON rn.`run_id` = rr.`id`
      WHERE rr.`id` = ?
        AND rr.`user_id` = ?
        AND rn.`id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$runId, $userId, $nodeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      throw new RuntimeException('run_node_not_found');
    }
    if ((string)$row['run_status'] !== 'active') {
      throw new RuntimeException('run_not_active');
    }
    if ((string)$row['node_status'] === 'locked') {
      throw new RuntimeException('node_not_available');
    }
    if ((string)$row['node_type'] !== 'chaos') {
      throw new RuntimeException('invalid_chaos_node');
    }

    return [
      'run' => [
        'id' => (string)$row['run_id'],
        'region_id' => (string)$row['region_id'],
        'status' => (string)$row['run_status'],
      ],
      'node' => [
        'id' => (string)$row['node_id'],
        'node_type' => (string)$row['node_type'],
        'status' => (string)$row['node_status'],
        'encounter_template_id' => $row['encounter_template_id'] !== null ? (string)$row['encounter_template_id'] : null,
      ],
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function findForNodeForUpdate(int $nodeId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT *
      FROM `chaos_encounter_results`
      WHERE `node_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$nodeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   */
  private function insertResult(int $userId, int $runId, int $nodeId, int $seed, array $reels): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `chaos_encounter_results` (
        `user_id`, `run_id`, `node_id`, `status`, `seed`, `reels_json`, `reward_multiplier`
      ) VALUES (?, ?, ?, \'generated\', ?, ?, ?)
    ');
    $stmt->execute([
      $userId,
      $runId,
      $nodeId,
      $seed,
      json_encode($reels, JSON_UNESCAPED_SLASHES),
      $this->rewardMultiplier($reels),
    ]);
  }

  /**
   * @param array<int,array<string,mixed>>|null $currentReels
   * @return array<int,array<string,mixed>>
   */
  private function generateReels(int $seed, ?int $rerollIndex, ?array $currentReels): array
  {
    $reels = [];
    foreach (self::REEL_POOLS as $index => $pool) {
      if ($rerollIndex !== null && $index !== $rerollIndex && is_array($currentReels[$index] ?? null)) {
        $reels[$index] = $currentReels[$index];
        continue;
      }

      $picked = $this->weightedPick($pool, $seed + ($index * 7919));
      if ($rerollIndex === $index && is_array($currentReels[$index] ?? null) && $picked['symbol'] === ($currentReels[$index]['symbol'] ?? null)) {
        $picked = $this->nextPoolSymbol($pool, (string)$picked['symbol']);
      }

      $reels[$index] = [
        'reel_index' => $index,
        'reel' => ['enemy_family', 'encounter_shape', 'rule_reward'][$index],
        'symbol' => (string)$picked['symbol'],
        'label' => (string)$picked['label'],
        'weight' => (int)$picked['weight'],
        'risk' => (int)$picked['risk'],
        'effect' => (string)$picked['effect'],
      ];
    }

    ksort($reels);
    return array_values($reels);
  }

  /**
   * @param array<int,array<string,mixed>> $pool
   * @return array<string,mixed>
   */
  private function weightedPick(array $pool, int $seed): array
  {
    $total = array_sum(array_map(static fn(array $row): int => max(1, (int)$row['weight']), $pool));
    $roll = $seed % max(1, $total);
    foreach ($pool as $row) {
      $roll -= max(1, (int)$row['weight']);
      if ($roll < 0) {
        return $row;
      }
    }

    return $pool[0];
  }

  /**
   * @param array<int,array<string,mixed>> $pool
   * @return array<string,mixed>
   */
  private function nextPoolSymbol(array $pool, string $symbol): array
  {
    foreach ($pool as $index => $row) {
      if ((string)$row['symbol'] === $symbol) {
        return $pool[($index + 1) % count($pool)];
      }
    }

    return $pool[0];
  }

  private function seedFor(int $userId, int $runId, int $nodeId, int $salt): int
  {
    $hex = substr(hash('sha256', implode('|', ['chaos_v1', $userId, $runId, $nodeId, $salt])), 0, 8);
    return (int)(hexdec($hex) % 2147483647);
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   */
  private function rewardMultiplier(array $reels): float
  {
    $risk = array_sum(array_map(static fn(array $row): int => max(0, (int)($row['risk'] ?? 0)), $reels));
    return round(1.0 + min(9, $risk) * 0.15, 2);
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function decodeReels(string $json): array
  {
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
  }

  /**
   * @param array<string,mixed> $row
   * @param array{run:array<string,mixed>,node:array<string,mixed>} $context
   * @return array<string,mixed>
   */
  private function mapResult(array $row, array $context): array
  {
    $reels = $this->decodeReels((string)($row['reels_json'] ?? '[]'));
    $finalizedRewards = $this->decodeFinalizedRewards($row['finalized_rewards_json'] ?? null);
    $status = (string)($row['status'] ?? 'generated');
    $isConfirmed = $status === 'confirmed';
    $manipulationCount = (int)($row['manipulation_count'] ?? 0);

    return [
      'chaos_result' => [
        'id' => (string)($row['id'] ?? ''),
        'status' => $status,
        'seed' => (int)($row['seed'] ?? 0),
        'reels' => $reels,
        'reward_multiplier' => (float)($row['reward_multiplier'] ?? $this->rewardMultiplier($reels)),
        'manipulation' => [
          'available' => !$isConfirmed && $manipulationCount < 1,
          'rerolled_reel_index' => $row['rerolled_reel_index'] !== null ? (int)$row['rerolled_reel_index'] : null,
          'remaining' => $isConfirmed ? 0 : max(0, 1 - $manipulationCount),
        ],
        'summary' => $this->summary($reels),
        'finalized_rewards' => $finalizedRewards,
        'finalized_at' => isset($row['finalized_at']) && $row['finalized_at'] !== null ? (string)$row['finalized_at'] : null,
      ],
      'run' => $context['run'],
      'node' => $context['node'],
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   * @return array<string,string>
   */
  private function summary(array $reels): array
  {
    return [
      'title' => implode(' + ', array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $reels)),
      'effect' => implode(' ', array_map(static fn(array $row): string => (string)($row['effect'] ?? ''), $reels)),
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   * @return array<string,mixed>
   */
  private function buildFinalizedRewards(array $reels, float $rewardMultiplier): array
  {
    $risk = array_sum(array_map(static fn(array $row): int => max(0, (int)($row['risk'] ?? 0)), $reels));
    $symbols = array_map(static fn(array $row): string => (string)($row['symbol'] ?? ''), $reels);
    $baseSoft = 8 + ($risk * 2) + (in_array('guaranteed_loot', $symbols, true) ? 4 : 0);
    $soft = max(8, min(40, (int)round($baseSoft * max(1.0, $rewardMultiplier))));
    $rawChaos = in_array('raw_chaos_spark', $symbols, true)
      ? max(1, min(5, (int)ceil($rewardMultiplier * 2)))
      : 0;

    $labels = [sprintf('%d Teeth', $soft)];
    if ($rawChaos > 0) {
      $labels[] = sprintf('%d Raw Chaos', $rawChaos);
    }

    return [
      'currency' => [
        'soft' => $soft,
        'raw_chaos' => $rawChaos,
      ],
      'reward_multiplier' => $rewardMultiplier,
      'labels' => $labels,
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   */
  private function bindEncounterTemplate(int $runId, int $nodeId, int $regionId, array $reels, int $seed): void
  {
    $templateId = $this->selectEncounterTemplateId($regionId, $reels, $seed);
    if ($templateId === null) {
      throw new RuntimeException('chaos_no_encounter_template');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `run_nodes`
      SET `encounter_template_id` = ?
      WHERE `run_id` = ?
        AND `id` = ?
        AND `node_type` = \'chaos\'
        AND `encounter_template_id` IS NULL
      LIMIT 1
    ');
    $stmt->execute([$templateId, $runId, $nodeId]);
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   */
  private function selectEncounterTemplateId(int $regionId, array $reels, int $seed): ?int
  {
    $family = (string)($reels[0]['symbol'] ?? '');
    $familyLike = match ($family) {
      'pigs' => '%pig%',
      'kobolds' => '%kobold%',
      'frogmen' => '%frogman%',
      default => '',
    };

    $candidateGroups = [];
    if ($familyLike !== '') {
      $candidateGroups[] = $this->loadCombatTemplateIds($regionId, $familyLike);
      $candidateGroups[] = $this->loadCombatTemplateIds(null, $familyLike);
    }
    $candidateGroups[] = $this->loadCombatTemplateIds($regionId, '');
    $candidateGroups[] = $this->loadCombatTemplateIds(null, '');

    foreach ($candidateGroups as $candidates) {
      if ($candidates === []) {
        continue;
      }

      $index = $seed % count($candidates);
      return $candidates[$index];
    }

    return null;
  }

  /**
   * @return array<int,int>
   */
  private function loadCombatTemplateIds(?int $regionId, string $slugLike): array
  {
    $where = ['`slug` LIKE \'%\\_combat\\_%\''];
    $params = [];
    if ($regionId !== null) {
      $where[] = '`region_id` = ?';
      $params[] = $regionId;
    }
    if ($slugLike !== '') {
      $where[] = '`slug` LIKE ?';
      $params[] = $slugLike;
    }

    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `encounter_templates`
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY `id` ASC
    ');
    $stmt->execute($params);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $ids[] = (int)$row['id'];
    }

    return $ids;
  }

  /**
   * @param array<string,mixed> $rewards
   */
  private function applyFinalizedRewards(int $userId, array $rewards): void
  {
    $currency = is_array($rewards['currency'] ?? null) ? $rewards['currency'] : [];
    $soft = max(0, (int)($currency['soft'] ?? 0));
    $rawChaos = max(0, (int)($currency['raw_chaos'] ?? 0));
    $stmt = $this->pdo->prepare('
      UPDATE `player_state`
      SET `currency_soft` = `currency_soft` + ?,
          `currency_raw_chaos` = `currency_raw_chaos` + ?
      WHERE `user_id` = ?
    ');
    $stmt->execute([$soft, $rawChaos, $userId]);
  }

  /**
   * @return array<string,mixed>|null
   */
  private function decodeFinalizedRewards(mixed $json): ?array
  {
    if (!is_string($json) || $json === '') {
      return null;
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
  }

  /**
   * @return array<string,string>
   */
  private function completionCopy(string $title): array
  {
    return [
      'title' => 'Chaos Settled',
      'message' => sprintf('%s is locked in. The fight is ready.', $title),
    ];
  }

  /**
   * @return array<int,int>
   */
  private function unlockFromNode(
    RunEdgeRepository $edges,
    RunNodeRepository $nodes,
    int $runId,
    int $fromNodeId
  ): array {
    $unlocked = [];
    foreach ($edges->getToNodeIdsFrom($runId, $fromNodeId) as $toId) {
      if ($nodes->setAvailableIfLocked($runId, $toId)) {
        $unlocked[] = $toId;
      }
    }

    return $unlocked;
  }
}
