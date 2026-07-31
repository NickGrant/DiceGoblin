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
      ['symbol' => 'local_patrol', 'label' => 'Local Patrol', 'category' => 'general', 'encounter_kind' => 'local_regular', 'risk' => 1, 'effect' => 'A familiar patrol stumbles into the wrong machine wake.'],
      ['symbol' => 'local_hardpoint', 'label' => 'Local Hardpoint', 'category' => 'general', 'encounter_kind' => 'local_regular', 'risk' => 2, 'effect' => 'A sturdier local formation answers the noise.'],
      ['symbol' => 'local_horde', 'label' => 'Local Horde', 'category' => 'general', 'encounter_kind' => 'local_regular', 'risk' => 2, 'effect' => 'Too many local fighters arrive at once.'],
      ['symbol' => 'chaos_easy_treasure', 'label' => 'Treasure Crew', 'category' => 'general', 'encounter_kind' => 'treasure', 'risk' => 0, 'effect' => 'A barely hostile treasure crew panics into the path.'],
      ['symbol' => 'chaos_boss_echo', 'label' => 'Boss Echo', 'category' => 'general', 'encounter_kind' => 'boss', 'risk' => 5, 'effect' => 'A boss echo tears loose from somewhere it should not be.'],
      ['symbol' => 'chaos_only_elite', 'label' => 'Wrong Machine Elite', 'category' => 'general', 'encounter_kind' => 'chaos_elite', 'risk' => 5, 'effect' => 'A machine-made threat that only appears in chaos nodes enters the fight.'],
      ['symbol' => 'kobold_raiders', 'label' => 'Kobold Raiders', 'category' => 'mountains', 'encounter_kind' => 'family_regular', 'family_like' => '%kobold%', 'risk' => 2, 'effect' => 'Mountain raiders take advantage of the broken route.'],
      ['symbol' => 'kobold_command', 'label' => 'Kobold Command', 'category' => 'mountains', 'encounter_kind' => 'family_regular', 'family_like' => '%kobold%', 'risk' => 3, 'effect' => 'Kobold commanders arrive with a sharper formation.'],
      ['symbol' => 'frogman_hunters', 'label' => 'Frogman Hunters', 'category' => 'swamps', 'encounter_kind' => 'family_regular', 'family_like' => '%frogman%', 'risk' => 2, 'effect' => 'Swamp hunters pull the fight into their rhythm.'],
      ['symbol' => 'frogman_court', 'label' => 'Bog Court', 'category' => 'swamps', 'encounter_kind' => 'family_regular', 'family_like' => '%frogman%', 'risk' => 3, 'effect' => 'A swamp court gathers around the chaos.'],
    ],
    1 => [
      ['symbol' => 'front_flip', 'label' => 'Front Line Flip', 'category' => 'general', 'risk' => 2, 'effect' => 'Front and back positions trade places before the fight.', 'combat_effect' => ['type' => 'flip_positions', 'side' => 'player']],
      ['symbol' => 'enemy_retreat', 'label' => 'Staggered Retreat', 'category' => 'general', 'risk' => 1, 'effect' => 'Enemies are shoved backward before the first strike.', 'combat_effect' => ['type' => 'shift_positions', 'side' => 'enemy', 'dx' => 1]],
      ['symbol' => 'knife_range', 'label' => 'Knife Range', 'category' => 'general', 'risk' => 2, 'effect' => 'Backline units lose focus in the cramped approach.', 'combat_effect' => ['type' => 'position_stat_modifier', 'side' => 'all', 'position' => 'back', 'stat_multipliers' => ['precision' => 0.9]]],
      ['symbol' => 'machine_fervor', 'label' => 'Machine Fervor', 'category' => 'general', 'risk' => 2, 'effect' => 'Every unit hits harder under the machine-hum.', 'combat_effect' => ['type' => 'stat_modifier', 'side' => 'all', 'stat_multipliers' => ['damage' => 1.1]]],
      ['symbol' => 'softened_blows', 'label' => 'Softened Blows', 'category' => 'general', 'risk' => 1, 'effect' => 'The fight begins under a dulling pressure.', 'combat_effect' => ['type' => 'stat_modifier', 'side' => 'all', 'stat_multipliers' => ['attack' => 0.9]]],
      ['symbol' => 'raw_edge', 'label' => 'Raw Edge', 'category' => 'general', 'risk' => 3, 'effect' => 'Enemies strike harder, but their guard slips.', 'combat_effect' => ['type' => 'stat_modifier', 'side' => 'enemy', 'stat_multipliers' => ['damage' => 1.2, 'defense' => 0.85]]],
      ['symbol' => 'falling_rocks', 'label' => 'Falling Rocks', 'category' => 'mountains', 'risk' => 2, 'effect' => 'A cliff-face sheds stone across the whole fight.', 'combat_effect' => ['type' => 'damage_side', 'side' => 'all', 'damage' => 10]],
      ['symbol' => 'cliffside_scree', 'label' => 'Cliffside Scree', 'category' => 'mountains', 'risk' => 2, 'effect' => 'Loose stone punishes the exposed front.', 'combat_effect' => ['type' => 'position_stat_modifier', 'side' => 'all', 'position' => 'front', 'stat_adders' => ['defense' => -1]]],
      ['symbol' => 'high_ground', 'label' => 'High Ground', 'category' => 'mountains', 'risk' => 2, 'effect' => 'Backline fighters find better angles.', 'combat_effect' => ['type' => 'position_stat_modifier', 'side' => 'all', 'position' => 'back', 'stat_adders' => ['precision' => 2]]],
      ['symbol' => 'bog_drag', 'label' => 'Bog Drag', 'category' => 'swamps', 'risk' => 2, 'effect' => 'The muck drags the front rank off balance.', 'combat_effect' => ['type' => 'position_stat_modifier', 'side' => 'all', 'position' => 'front', 'stat_multipliers' => ['resolve' => 0.85]]],
      ['symbol' => 'mire_breath', 'label' => 'Mire Breath', 'category' => 'swamps', 'risk' => 1, 'effect' => 'The back rank steadies while the mire closes in.', 'combat_effect' => ['type' => 'position_stat_modifier', 'side' => 'player', 'position' => 'back', 'stat_multipliers' => ['defense' => 1.15]]],
    ],
    2 => [
      ['symbol' => 'plain_payout', 'label' => 'Clean Payout', 'category' => 'general', 'risk' => 1, 'effect' => 'Victory pays a clean chaos bounty.', 'reward' => ['soft_multiplier' => 1.0]],
      ['symbol' => 'teeth_rain', 'label' => 'Teeth Rain', 'category' => 'general', 'risk' => 1, 'effect' => 'Victory shakes extra teeth loose.', 'reward' => ['soft_multiplier' => 1.25]],
      ['symbol' => 'guaranteed_loot', 'label' => 'Guaranteed Loot', 'category' => 'general', 'risk' => 1, 'effect' => 'Victory promises an extra die.', 'reward' => ['dice_grants' => [['rarity' => 'common', 'sides' => 6]]]],
      ['symbol' => 'raw_chaos_spark', 'label' => 'Raw Chaos Spark', 'category' => 'general', 'risk' => 2, 'effect' => 'Victory may feed later machine work.', 'reward' => ['raw_chaos' => 3]],
      ['symbol' => 'fat_spark', 'label' => 'Fat Spark', 'category' => 'general', 'risk' => 3, 'effect' => 'Victory can spill a stronger Raw Chaos reward.', 'reward' => ['raw_chaos' => 5]],
      ['symbol' => 'double_or_nothing', 'label' => 'Double Or Nothing', 'category' => 'general', 'risk' => 4, 'effect' => 'Victory pays heavily, defeat pays nothing extra.', 'reward' => ['soft_multiplier' => 1.8]],
      ['symbol' => 'rusted_purse', 'label' => 'Rusted Purse', 'category' => 'general', 'risk' => 0, 'effect' => 'The payout rusts smaller than expected.', 'reward' => ['soft_multiplier' => 0.75]],
      ['symbol' => 'mountain_cache', 'label' => 'Mountain Cache', 'category' => 'mountains', 'risk' => 1, 'effect' => 'Victory may uncover a mountain cache.', 'reward' => ['soft_multiplier' => 1.15, 'dice_grants' => [['rarity' => 'common', 'sides' => 8]]]],
      ['symbol' => 'swamp_cache', 'label' => 'Swamp Cache', 'category' => 'swamps', 'risk' => 1, 'effect' => 'Victory may pull salvage from the muck.', 'reward' => ['soft_multiplier' => 1.15, 'dice_grants' => [['rarity' => 'common', 'sides' => 8]]]],
      ['symbol' => 'thin_pickings', 'label' => 'Thin Pickings', 'category' => 'general', 'risk' => 0, 'effect' => 'The machine eats some of the payout.', 'reward' => ['soft_multiplier' => 0.6]],
    ],
  ];

  private const PAID_REROLL_COSTS = [10, 25, 50];

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array<int,array<int,array<string,mixed>>>
   */
  public function reelCatalog(): array
  {
    return self::REEL_POOLS;
  }

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
        $reels = $this->generateReels($seed, null, null, $this->regionSlugForId((int)$context['run']['region_id']));
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
      $manipulationCount = (int)$existing['manipulation_count'];
      $paidCost = $this->rerollCostForCount($manipulationCount);
      if ($paidCost > 0) {
        $this->chargeSoftCurrency($userId, $paidCost);
      }

      $currentReels = $this->decodeReels((string)$existing['reels_json']);
      $seed = $this->seedFor($userId, $runId, $nodeId, $reelIndex + 10 + ($manipulationCount * 101));
      $reels = $this->generateReels($seed, $reelIndex, $currentReels, $this->regionSlugForId((int)$context['run']['region_id']));
      $stmt = $this->pdo->prepare('
        UPDATE `chaos_encounter_results`
        SET `status` = \'manipulated\',
            `seed` = ?,
            `reels_json` = ?,
            `reward_multiplier` = ?,
            `rerolled_reel_index` = ?,
            `manipulation_count` = `manipulation_count` + 1
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
  private function generateReels(int $seed, ?int $rerollIndex, ?array $currentReels, string $regionSlug): array
  {
    $reels = [];
    foreach (self::REEL_POOLS as $index => $pool) {
      if ($rerollIndex !== null && $index !== $rerollIndex && is_array($currentReels[$index] ?? null)) {
        $reels[$index] = $currentReels[$index];
        continue;
      }

      $eligiblePool = $this->eligiblePoolForRegion($pool, $regionSlug);
      $picked = $this->pickFromPool($eligiblePool, $seed + ($index * 7919));
      if ($rerollIndex === $index && is_array($currentReels[$index] ?? null) && $picked['symbol'] === ($currentReels[$index]['symbol'] ?? null)) {
        $picked = $this->nextPoolSymbol($eligiblePool, (string)$picked['symbol']);
      }

      $reels[$index] = [
        'reel_index' => $index,
        'reel' => ['enemy_family', 'encounter_shape', 'rule_reward'][$index],
        'symbol' => (string)$picked['symbol'],
        'label' => (string)$picked['label'],
        'category' => (string)($picked['category'] ?? 'general'),
        'weight' => 1,
        'risk' => (int)$picked['risk'],
        'effect' => (string)$picked['effect'],
        ...($index === 0 ? [
          'encounter_kind' => (string)($picked['encounter_kind'] ?? 'local_regular'),
          'family_like' => (string)($picked['family_like'] ?? ''),
        ] : []),
        ...($index === 1 && is_array($picked['combat_effect'] ?? null) ? [
          'combat_effect' => $picked['combat_effect'],
        ] : []),
        ...($index === 2 && is_array($picked['reward'] ?? null) ? [
          'reward' => $picked['reward'],
        ] : []),
      ];
    }

    ksort($reels);
    return array_values($reels);
  }

  /**
   * @param array<int,array<string,mixed>> $pool
   * @return array<string,mixed>
   */
  private function pickFromPool(array $pool, int $seed): array
  {
    return $pool[$seed % max(1, count($pool))] ?? $pool[0];
  }

  /**
   * @param array<int,array<string,mixed>> $pool
   * @return array<int,array<string,mixed>>
   */
  private function eligiblePoolForRegion(array $pool, string $regionSlug): array
  {
    $eligible = array_values(array_filter($pool, static function (array $row) use ($regionSlug): bool {
      $category = (string)($row['category'] ?? 'general');
      return $category === 'general' || $category === $regionSlug;
    }));

    return $eligible !== [] ? $eligible : $pool;
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

  private function rerollCostForCount(int $manipulationCount): int
  {
    if ($manipulationCount <= 0) {
      return 0;
    }

    $index = min(count(self::PAID_REROLL_COSTS) - 1, $manipulationCount - 1);
    return self::PAID_REROLL_COSTS[$index] ?? 50;
  }

  private function chargeSoftCurrency(int $userId, int $amount): void
  {
    $amount = max(0, $amount);
    if ($amount <= 0) {
      return;
    }

    $playerStateRepository = new PlayerStateRepository($this->pdo);
    $playerStateRepository->ensurePlayerState($userId);
    $state = $playerStateRepository->getPlayerStateForUpdate($userId);
    if (!is_array($state)) {
      throw new RuntimeException('chaos_reroll_unaffordable');
    }

    $current = max(0, (int)($state['currency_soft'] ?? 0));
    if ($current < $amount) {
      throw new RuntimeException('chaos_reroll_unaffordable');
    }

    $playerStateRepository->setCurrency($userId, $current - $amount, max(0, (int)($state['currency_hard'] ?? 0)));
  }

  private function regionSlugForId(int $regionId): string
  {
    if ($regionId <= 0) {
      return '';
    }

    $stmt = $this->pdo->prepare('SELECT `slug` FROM `regions` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$regionId]);
    $slug = $stmt->fetchColumn();
    return is_string($slug) ? $slug : '';
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
    $nextRerollCost = $this->rerollCostForCount($manipulationCount);

    return [
      'chaos_result' => [
        'id' => (string)($row['id'] ?? ''),
        'status' => $status,
        'seed' => (int)($row['seed'] ?? 0),
        'reels' => $reels,
        'reward_multiplier' => (float)($row['reward_multiplier'] ?? $this->rewardMultiplier($reels)),
        'manipulation' => [
          'available' => !$isConfirmed,
          'rerolled_reel_index' => $row['rerolled_reel_index'] !== null ? (int)$row['rerolled_reel_index'] : null,
          'remaining' => $isConfirmed ? 0 : 1,
          'count' => $manipulationCount,
          'next_cost' => $isConfirmed ? null : $nextRerollCost,
          'next_cost_label' => $nextRerollCost > 0 ? sprintf('%d teeth', $nextRerollCost) : 'Free',
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
    $rewardReel = $this->reelByName($reels, 'rule_reward');
    $reward = is_array($rewardReel['reward'] ?? null) ? $rewardReel['reward'] : [];
    $baseSoft = 8 + ($risk * 2);
    $softMultiplier = is_numeric($reward['soft_multiplier'] ?? null) ? (float)$reward['soft_multiplier'] : 1.0;
    $soft = max(0, min(60, (int)round($baseSoft * max(0.0, $softMultiplier) * max(1.0, $rewardMultiplier))));
    $rawChaos = max(0, (int)($reward['raw_chaos'] ?? 0));
    $diceGrants = is_array($reward['dice_grants'] ?? null) ? array_values(array_filter($reward['dice_grants'], 'is_array')) : [];

    $labels = [sprintf('%d Teeth', $soft)];
    if ($rawChaos > 0) {
      $labels[] = sprintf('%d Raw Chaos', $rawChaos);
    }
    foreach ($diceGrants as $grant) {
      $labels[] = sprintf('1 %s D%d', ucfirst((string)($grant['rarity'] ?? 'common')), max(1, (int)($grant['sides'] ?? 6)));
    }

    return [
      'currency' => [
        'soft' => $soft,
        'raw_chaos' => $rawChaos,
      ],
      'reward_multiplier' => $rewardMultiplier,
      'applied_reels' => $this->appliedReelSummary($reels),
      'combat_modifiers' => $this->combatModifiersFromReels($reels),
      'dice_grants' => $diceGrants,
      'labels' => $labels,
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   * @return array<string,mixed>
   */
  private function reelByName(array $reels, string $name): array
  {
    foreach ($reels as $reel) {
      if (is_array($reel) && (string)($reel['reel'] ?? '') === $name) {
        return $reel;
      }
    }

    return [];
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   * @return list<array<string,mixed>>
   */
  private function combatModifiersFromReels(array $reels): array
  {
    $shape = $this->reelByName($reels, 'encounter_shape');
    $effect = is_array($shape['combat_effect'] ?? null) ? $shape['combat_effect'] : [];
    if ($effect === []) {
      return [];
    }

    return [[
      'source' => 'chaos',
      'label' => (string)($shape['label'] ?? 'Chaos Rule'),
      'description' => (string)($shape['effect'] ?? ''),
      'effect' => $effect,
    ]];
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
    $encounterReel = $this->reelByName($reels, 'enemy_family');
    $kind = (string)($encounterReel['encounter_kind'] ?? 'local_regular');
    $shapeReel = $this->reelByName($reels, 'encounter_shape');
    $shape = (string)($shapeReel['symbol'] ?? '');
    $familyLike = (string)($encounterReel['family_like'] ?? '');

    if ($kind === 'treasure') {
      return $this->selectFromCandidates($this->loadTemplateCandidatesBySlug('%chaos_treasure%'), $shape, $seed);
    }

    if ($kind === 'boss') {
      return $this->selectFromCandidates($this->loadBossTemplateCandidates(), $shape, $seed);
    }

    if ($kind === 'chaos_elite') {
      return $this->selectFromCandidates($this->loadTemplateCandidatesBySlug('%chaos_elite%'), $shape, $seed);
    }

    $candidateGroups = [];
    if ($familyLike !== '') {
      $candidateGroups[] = $this->loadCombatTemplateCandidates($regionId, $familyLike);
      $candidateGroups[] = $this->loadCombatTemplateCandidates(null, $familyLike);
    }
    $candidateGroups[] = $this->loadCombatTemplateCandidates($regionId, '');
    $candidateGroups[] = $this->loadCombatTemplateCandidates(null, '');

    foreach ($candidateGroups as $candidates) {
      if ($candidates === []) {
        continue;
      }

      $ranked = $this->rankCandidatesForShape($candidates, $shape);
      $bestScore = $this->shapeScore($ranked[0], $shape);
      $bestCandidates = array_values(array_filter(
        $ranked,
        fn(array $candidate): bool => $this->shapeScore($candidate, $shape) === $bestScore
      ));
      $index = $seed % count($bestCandidates);
      return (int)$bestCandidates[$index]['id'];
    }

    return null;
  }

  /**
   * @param array<int,array{id:int,slug:string,enemy_set_json:string}> $candidates
   */
  private function selectFromCandidates(array $candidates, string $shape, int $seed): ?int
  {
    if ($candidates === []) {
      return null;
    }

    $ranked = $this->rankCandidatesForShape($candidates, $shape);
    $bestScore = $this->shapeScore($ranked[0], $shape);
    $bestCandidates = array_values(array_filter(
      $ranked,
      fn(array $candidate): bool => $this->shapeScore($candidate, $shape) === $bestScore
    ));
    return (int)$bestCandidates[$seed % count($bestCandidates)]['id'];
  }

  /**
   * @return array<int,array{id:int,slug:string,enemy_set_json:string}>
   */
  private function loadCombatTemplateCandidates(?int $regionId, string $slugLike): array
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
      SELECT `id`, `slug`, `enemy_set_json`
      FROM `encounter_templates`
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY `id` ASC
    ');
    $stmt->execute($params);

    $candidates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $candidates[] = [
        'id' => (int)$row['id'],
        'slug' => (string)($row['slug'] ?? ''),
        'enemy_set_json' => (string)($row['enemy_set_json'] ?? ''),
      ];
    }

    return $candidates;
  }

  /**
   * @return array<int,array{id:int,slug:string,enemy_set_json:string}>
   */
  private function loadTemplateCandidatesBySlug(string $slugLike): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `slug`, `enemy_set_json`
      FROM `encounter_templates`
      WHERE `slug` LIKE ?
      ORDER BY `id` ASC
    ');
    $stmt->execute([$slugLike]);

    $candidates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $candidates[] = [
        'id' => (int)$row['id'],
        'slug' => (string)($row['slug'] ?? ''),
        'enemy_set_json' => (string)($row['enemy_set_json'] ?? ''),
      ];
    }

    return $candidates;
  }

  /**
   * @return array<int,array{id:int,slug:string,enemy_set_json:string}>
   */
  private function loadBossTemplateCandidates(): array
  {
    return $this->loadTemplateCandidatesBySlug('%\\_boss\\_%');
  }

  /**
   * @param array<int,array{id:int,slug:string,enemy_set_json:string}> $candidates
   * @return array<int,array{id:int,slug:string,enemy_set_json:string}>
   */
  private function rankCandidatesForShape(array $candidates, string $shape): array
  {
    usort($candidates, function (array $left, array $right) use ($shape): int {
      $leftScore = $this->shapeScore($left, $shape);
      $rightScore = $this->shapeScore($right, $shape);
      if ($leftScore !== $rightScore) {
        return $rightScore <=> $leftScore;
      }

      return ((int)$left['id']) <=> ((int)$right['id']);
    });

    return $candidates;
  }

  /**
   * @param array{id:int,slug:string,enemy_set_json:string} $candidate
   */
  private function shapeScore(array $candidate, string $shape): int
  {
    $units = $this->extractTemplateUnits((string)$candidate['enemy_set_json']);
    $slugs = array_map(static fn(array $unit): string => (string)($unit['enemy_template_slug'] ?? ''), $units);

    return match ($shape) {
      'horde' => count($units),
      'armored_frontline' => $this->containsAny($slugs, ['shieldbearer', 'bruiser', 'mudwrestler']) ? 10 : 0,
      'ranged_backline' => $this->containsAny($slugs, ['sharpshooter', 'spearhunter', 'slinger']) ? 10 : 0,
      'ambush' => $this->positionSpreadScore($units),
      'chaos_only_elite' => $this->containsAny($slugs, ['chaos_']) ? 10 : 0,
      default => 0,
    };
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function extractTemplateUnits(string $enemySetJson): array
  {
    $enemySet = json_decode($enemySetJson, true);
    if (!is_array($enemySet)) {
      return [];
    }

    if (is_array($enemySet['units'] ?? null)) {
      return array_values(array_filter($enemySet['units'], 'is_array'));
    }

    $units = [];
    $teams = is_array($enemySet['teams'] ?? null) ? $enemySet['teams'] : [];
    foreach ($teams as $team) {
      if (!is_array($team) || !is_array($team['units'] ?? null)) {
        continue;
      }
      foreach ($team['units'] as $unit) {
        if (is_array($unit)) {
          $units[] = $unit;
        }
      }
    }

    return $units;
  }

  /**
   * @param array<int,string> $values
   * @param array<int,string> $needles
   */
  private function containsAny(array $values, array $needles): bool
  {
    foreach ($values as $value) {
      foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($value, $needle)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * @param array<int,array<string,mixed>> $units
   */
  private function positionSpreadScore(array $units): int
  {
    $columns = [];
    foreach ($units as $unit) {
      $pos = is_array($unit['pos'] ?? null) ? $unit['pos'] : [];
      $columns[(int)($pos['x'] ?? 0)] = true;
    }

    return count($columns);
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   * @return array<string,array<string,string>>
   */
  private function appliedReelSummary(array $reels): array
  {
    $summary = [];
    foreach ($reels as $reel) {
      if (!is_array($reel)) {
        continue;
      }

      $key = (string)($reel['reel'] ?? '');
      if ($key === '') {
        continue;
      }

      $summary[$key] = [
        'symbol' => (string)($reel['symbol'] ?? ''),
        'label' => (string)($reel['label'] ?? ''),
        'effect' => (string)($reel['effect'] ?? ''),
      ];
    }

    return $summary;
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
    foreach ($edges->getConnectedNodeIds($runId, $fromNodeId) as $toId) {
      if ($nodes->setAvailableIfLocked($runId, $toId)) {
        $unlocked[] = $toId;
      }
    }

    return $unlocked;
  }
}
