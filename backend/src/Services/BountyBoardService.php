<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\PlayerStateRepository;
use PDO;
use RuntimeException;
use Throwable;

final class BountyBoardService
{
  private const ACTIVE_LIMIT = 3;

  private const DEFINITIONS = [
    [
      'slug' => 'clear-any-run-once',
      'title' => 'Finish the Route',
      'description' => 'Complete any run and make it back with the loot.',
      'category' => 'region',
      'objective' => ['event' => 'run_completed', 'target' => 1],
      'reward' => ['currency_soft' => 30],
      'sort_order' => 10,
    ],
    [
      'slug' => 'claim-three-victories',
      'title' => 'Three Good Scraps',
      'description' => 'Claim rewards from three victorious battles.',
      'category' => 'hunting',
      'objective' => ['event' => 'battle_victory_claimed', 'target' => 3],
      'reward' => ['currency_soft' => 45],
      'sort_order' => 20,
    ],
    [
      'slug' => 'clear-farm-once',
      'title' => 'Farm Work',
      'description' => 'Complete a run through The Farm.',
      'category' => 'challenge',
      'objective' => ['event' => 'run_completed', 'region_slug' => 'the_farm', 'target' => 1],
      'reward' => ['currency_soft' => 50],
      'sort_order' => 30,
    ],
  ];

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerStateRepository,
  ) {}

  /**
   * @return array{active_limit:int,active_count:int,bounties:array<int,array<string,mixed>>}
   */
  public function board(int $userId): array
  {
    $this->ensureDefinitions();
    $this->syncProgress($userId);

    return [
      'active_limit' => self::ACTIVE_LIMIT,
      'active_count' => $this->activeCount($userId),
      'bounties' => $this->listBounties($userId),
    ];
  }

  /**
   * @return array<string,mixed>
   */
  public function accept(int $userId, string $slug): array
  {
    $this->ensureDefinitions();
    $definition = $this->definitionBySlug($slug);
    if ($definition === null) {
      throw new RuntimeException('bounty_not_found');
    }

    if ($this->activeCount($userId) >= self::ACTIVE_LIMIT) {
      throw new RuntimeException('bounty_active_limit');
    }

    $existing = $this->userBountyForDefinition($userId, (int)$definition['id']);
    if ($existing !== null) {
      throw new RuntimeException('bounty_already_accepted');
    }

    $progress = $this->progressForObjective($userId, $this->decodeJson((string)$definition['objective_json']));
    $status = $progress['current'] >= $progress['target'] ? 'completed' : 'accepted';
    $stmt = $this->pdo->prepare('
      INSERT INTO `user_bounties` (
        `user_id`, `bounty_definition_id`, `status`, `progress_json`, `completed_at`
      ) VALUES (?, ?, ?, ?, ' . ($status === 'completed' ? 'UTC_TIMESTAMP()' : 'NULL') . ')
    ');
    $stmt->execute([
      $userId,
      (int)$definition['id'],
      $status,
      json_encode($progress, JSON_UNESCAPED_SLASHES),
    ]);

    return [
      'active_limit' => self::ACTIVE_LIMIT,
      'active_count' => $this->activeCount($userId),
      'bounties' => $this->listBounties($userId),
    ];
  }

  /**
   * @return array<string,mixed>
   */
  public function syncProgress(int $userId): array
  {
    $this->ensureDefinitions();
    foreach ($this->acceptedBountyRows($userId) as $row) {
      $progress = $this->progressForObjective($userId, $this->decodeJson((string)$row['objective_json']));
      $status = $progress['current'] >= $progress['target'] ? 'completed' : 'accepted';
      $stmt = $this->pdo->prepare('
        UPDATE `user_bounties`
        SET `progress_json` = ?,
            `status` = ?,
            `completed_at` = CASE
              WHEN ? = \'completed\' AND `completed_at` IS NULL THEN UTC_TIMESTAMP()
              ELSE `completed_at`
            END
        WHERE `id` = ? AND `user_id` = ? AND `status` IN (\'accepted\', \'completed\')
      ');
      $stmt->execute([
        json_encode($progress, JSON_UNESCAPED_SLASHES),
        $status,
        $status,
        (int)$row['user_bounty_id'],
        $userId,
      ]);
    }

    return [
      'active_limit' => self::ACTIVE_LIMIT,
      'active_count' => $this->activeCount($userId),
      'bounties' => $this->listBounties($userId),
    ];
  }

  /**
   * @return array<string,mixed>
   */
  public function claim(int $userId, int $userBountyId): array
  {
    $this->ensureDefinitions();
    $this->syncProgress($userId);

    try {
      $this->pdo->beginTransaction();

      $stmt = $this->pdo->prepare('
        SELECT ub.`id`, ub.`status`, bd.`reward_json`
        FROM `user_bounties` ub
        JOIN `bounty_definitions` bd ON bd.`id` = ub.`bounty_definition_id`
        WHERE ub.`id` = ? AND ub.`user_id` = ?
        LIMIT 1
        FOR UPDATE
      ');
      $stmt->execute([$userBountyId, $userId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $this->pdo->rollBack();
        throw new RuntimeException('bounty_not_found');
      }

      $reward = $this->decodeJson((string)$row['reward_json']);
      if ((string)$row['status'] === 'claimed') {
        $this->pdo->commit();
        return $this->claimedPayload($userId, $userBountyId, $reward, false);
      }

      if ((string)$row['status'] !== 'completed') {
        $this->pdo->rollBack();
        throw new RuntimeException('bounty_not_completed');
      }

      $currencySoft = max(0, (int)($reward['currency_soft'] ?? 0));
      if ($currencySoft > 0) {
        $this->playerStateRepository->ensurePlayerState($userId);
        $currency = $this->playerStateRepository->getPlayerStateForUpdate($userId);
        if ($currency === null) {
          $this->pdo->rollBack();
          throw new RuntimeException('Player state row not found.');
        }

        $newSoft = (int)$currency['currency_soft'] + $currencySoft;
        $updateCurrency = $this->pdo->prepare('
          UPDATE `player_state`
          SET `currency_soft` = ?
          WHERE `user_id` = ?
        ');
        $updateCurrency->execute([$newSoft, $userId]);
      }

      $update = $this->pdo->prepare('
        UPDATE `user_bounties`
        SET `status` = \'claimed\', `claimed_at` = UTC_TIMESTAMP()
        WHERE `id` = ? AND `user_id` = ?
      ');
      $update->execute([$userBountyId, $userId]);

      $this->pdo->commit();
      return $this->claimedPayload($userId, $userBountyId, $reward, true);
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  private function ensureDefinitions(): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `bounty_definitions` (
        `slug`, `title`, `description`, `category`, `objective_json`, `reward_json`, `is_enabled`, `sort_order`
      ) VALUES (?, ?, ?, ?, ?, ?, 1, ?)
      ON DUPLICATE KEY UPDATE
        `title` = VALUES(`title`),
        `description` = VALUES(`description`),
        `category` = VALUES(`category`),
        `objective_json` = VALUES(`objective_json`),
        `reward_json` = VALUES(`reward_json`),
        `is_enabled` = 1,
        `sort_order` = VALUES(`sort_order`)
    ');

    foreach (self::DEFINITIONS as $definition) {
      $stmt->execute([
        $definition['slug'],
        $definition['title'],
        $definition['description'],
        $definition['category'],
        json_encode($definition['objective'], JSON_UNESCAPED_SLASHES),
        json_encode($definition['reward'], JSON_UNESCAPED_SLASHES),
        (int)$definition['sort_order'],
      ]);
    }
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function listBounties(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        bd.`id` AS `definition_id`,
        bd.`slug`,
        bd.`title`,
        bd.`description`,
        bd.`category`,
        bd.`objective_json`,
        bd.`reward_json`,
        ub.`id` AS `user_bounty_id`,
        ub.`status`,
        ub.`progress_json`,
        ub.`accepted_at`,
        ub.`completed_at`,
        ub.`claimed_at`
      FROM `bounty_definitions` bd
      LEFT JOIN `user_bounties` ub
        ON ub.`bounty_definition_id` = bd.`id`
       AND ub.`user_id` = ?
      WHERE bd.`is_enabled` = 1
      ORDER BY bd.`sort_order` ASC, bd.`id` ASC
    ');
    $stmt->execute([$userId]);

    return array_map(fn(array $row): array => $this->mapBountyRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function acceptedBountyRows(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT ub.`id` AS `user_bounty_id`, bd.`objective_json`
      FROM `user_bounties` ub
      JOIN `bounty_definitions` bd ON bd.`id` = ub.`bounty_definition_id`
      WHERE ub.`user_id` = ?
        AND ub.`status` IN (\'accepted\', \'completed\')
    ');
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  private function activeCount(int $userId): int
  {
    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM `user_bounties`
      WHERE `user_id` = ?
        AND `status` IN (\'accepted\', \'completed\')
    ');
    $stmt->execute([$userId]);

    return (int)$stmt->fetchColumn();
  }

  /**
   * @return array<string,mixed>|null
   */
  private function definitionBySlug(string $slug): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `objective_json`
      FROM `bounty_definitions`
      WHERE `slug` = ? AND `is_enabled` = 1
      LIMIT 1
    ');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
  }

  /**
   * @return array<string,mixed>|null
   */
  private function userBountyForDefinition(int $userId, int $definitionId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `status`
      FROM `user_bounties`
      WHERE `user_id` = ? AND `bounty_definition_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $definitionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
  }

  /**
   * @param array<string,mixed> $objective
   * @return array{current:int,target:int}
   */
  private function progressForObjective(int $userId, array $objective): array
  {
    $target = max(1, (int)($objective['target'] ?? 1));
    $event = (string)($objective['event'] ?? '');
    $current = match ($event) {
      'run_completed' => $this->completedRunCount($userId, $objective['region_slug'] ?? null),
      'battle_victory_claimed' => $this->claimedVictoryCount($userId),
      default => 0,
    };

    return [
      'current' => min(max(0, $current), $target),
      'target' => $target,
    ];
  }

  private function completedRunCount(int $userId, mixed $regionSlug): int
  {
    if (is_string($regionSlug) && $regionSlug !== '') {
      $stmt = $this->pdo->prepare('
        SELECT COUNT(*)
        FROM `region_runs` rr
        JOIN `regions` r ON r.`id` = rr.`region_id`
        WHERE rr.`user_id` = ?
          AND rr.`status` = \'completed\'
          AND r.`slug` = ?
      ');
      $stmt->execute([$userId, $regionSlug]);
      return (int)$stmt->fetchColumn();
    }

    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM `region_runs`
      WHERE `user_id` = ?
        AND `status` = \'completed\'
    ');
    $stmt->execute([$userId]);

    return (int)$stmt->fetchColumn();
  }

  private function claimedVictoryCount(int $userId): int
  {
    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM `battles`
      WHERE `user_id` = ?
        AND `status` = \'claimed\'
        AND `outcome` = \'victory\'
    ');
    $stmt->execute([$userId]);

    return (int)$stmt->fetchColumn();
  }

  /**
   * @return array<string,mixed>
   */
  private function mapBountyRow(array $row): array
  {
    $progress = $row['progress_json'] !== null
      ? $this->decodeJson((string)$row['progress_json'])
      : $this->progressForObjective(0, $this->decodeJson((string)$row['objective_json']));
    $status = $row['status'] !== null ? (string)$row['status'] : 'available';

    return [
      'definition_id' => (string)$row['definition_id'],
      'user_bounty_id' => $row['user_bounty_id'] !== null ? (string)$row['user_bounty_id'] : null,
      'slug' => (string)$row['slug'],
      'title' => (string)$row['title'],
      'description' => (string)$row['description'],
      'category' => (string)$row['category'],
      'objective' => $this->decodeJson((string)$row['objective_json']),
      'reward' => $this->decodeJson((string)$row['reward_json']),
      'status' => $status,
      'progress' => [
        'current' => (int)($progress['current'] ?? 0),
        'target' => max(1, (int)($progress['target'] ?? 1)),
      ],
      'can_accept' => $status === 'available',
      'can_claim' => $status === 'completed',
      'accepted_at' => $row['accepted_at'] !== null ? (string)$row['accepted_at'] : null,
      'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
      'claimed_at' => $row['claimed_at'] !== null ? (string)$row['claimed_at'] : null,
    ];
  }

  /**
   * @param array<string,mixed> $reward
   * @return array<string,mixed>
   */
  private function claimedPayload(int $userId, int $userBountyId, array $reward, bool $newlyClaimed): array
  {
    return [
      'user_bounty_id' => (string)$userBountyId,
      'status' => 'claimed',
      'newly_claimed' => $newlyClaimed,
      'reward' => $reward,
      'currency' => $this->playerStateRepository->getCurrency($userId),
      'board' => $this->board($userId),
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeJson(string $json): array
  {
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
  }
}
