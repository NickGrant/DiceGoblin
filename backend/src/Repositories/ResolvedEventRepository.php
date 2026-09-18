<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use DiceGoblins\Domain\Rewards\FinalizedRewardResult;
use DiceGoblins\Domain\Rewards\ResolvedEventRecord;
use DiceGoblins\Domain\Rewards\RewardResultException;
use PDO;
use RuntimeException;

final class ResolvedEventRepository
{
  public function __construct(private readonly PDO $pdo) {}

  public function find(int $userId, string $eventId, string $sourceType, string $sourceId): ?ResolvedEventRecord
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `user_id`, `event_id`, `source_type`, `source_id`, `result_json`, `status`, `resolved_at`, `applied_at`
      FROM `resolved_events` WHERE `user_id` = ? AND `event_id` = ? AND `source_type` = ? AND `source_id` = ? LIMIT 1');
    $stmt->execute([$userId, $eventId, $sourceType, $sourceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    return $this->hydrate($row, $userId, $eventId, $sourceType, $sourceId);
  }

  public function insertFinalized(int $userId, FinalizedRewardResult $result): int
  {
    $stmt = $this->pdo->prepare('INSERT INTO `resolved_events`
      (`user_id`, `event_id`, `source_type`, `source_id`, `result_json`, `status`)
      VALUES (?, ?, ?, ?, ?, \'finalized\')');
    $stmt->execute([$userId, $result->eventId(), $result->sourceType(), $result->sourceId(), $result->toJson()]);
    return (int)$this->pdo->lastInsertId();
  }

  public function markApplied(int $id, int $userId, FinalizedRewardResult $result): void
  {
    $stmt = $this->pdo->prepare('UPDATE `resolved_events` SET `status` = \'applied\', `applied_at` = UTC_TIMESTAMP()
      WHERE `id` = ? AND `user_id` = ? AND `event_id` = ? AND `source_type` = ? AND `source_id` = ?
        AND `status` = \'finalized\' AND `applied_at` IS NULL AND `result_json` = CAST(? AS JSON)');
    $stmt->execute([$id, $userId, $result->eventId(), $result->sourceType(), $result->sourceId(), $result->toJson()]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Resolved event could not transition to applied.');
  }

  /** @param array<string,mixed> $row */
  private function hydrate(array $row, int $userId, string $eventId, string $sourceType, string $sourceId): ResolvedEventRecord
  {
    $result = FinalizedRewardResult::fromJson((string)$row['result_json']);
    if ((int)$row['user_id'] !== $userId || (string)$row['event_id'] !== $eventId || (string)$row['source_type'] !== $sourceType
      || (string)$row['source_id'] !== $sourceId || $result->eventId() !== $eventId
      || $result->sourceType() !== $sourceType || $result->sourceId() !== $sourceId) {
      throw new RewardResultException('Resolved event row does not match its finalized result.');
    }
    return new ResolvedEventRecord(
      (int)$row['id'], $userId, (string)$row['status'], (string)$row['resolved_at'],
      $row['applied_at'] !== null ? (string)$row['applied_at'] : null, $result,
    );
  }
}
