<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class UserObjectiveRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return list<array{objective_id:string,completed_at:?string}> */
  public function listStatesForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('SELECT `objective_id`, `completed_at` FROM `user_objectives`
      WHERE `user_id` = ? ORDER BY `objective_id` ASC');
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
      'objective_id' => (string)$row['objective_id'],
      'completed_at' => $row['completed_at'] === null ? null : (string)$row['completed_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /** @return list<array{objective_id:string,requirement_id:string,current_count:string}> */
  public function listCountsForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('SELECT `objective_id`, `requirement_id`, `current_count`
      FROM `user_objective_requirements` WHERE `user_id` = ? ORDER BY `objective_id`, `requirement_id`');
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
      'objective_id' => (string)$row['objective_id'],
      'requirement_id' => (string)$row['requirement_id'],
      'current_count' => (string)$row['current_count'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  public function insertActiveIfAbsent(int $userId, string $objectiveId): bool
  {
    if ($userId <= 0 || $objectiveId === '') throw new RuntimeException('Objective identity is invalid.');
    $stmt = $this->pdo->prepare('INSERT INTO `user_objectives` (`user_id`, `objective_id`) VALUES (?, ?)
      ON DUPLICATE KEY UPDATE `objective_id` = VALUES(`objective_id`)');
    $stmt->execute([$userId, $objectiveId]);
    return $stmt->rowCount() === 1;
  }

  public function setCount(int $userId, string $objectiveId, string $requirementId, int $count): void
  {
    if ($userId <= 0 || $objectiveId === '' || $requirementId === '' || $count <= 0) {
      throw new RuntimeException('Objective requirement count is invalid.');
    }
    $stmt = $this->pdo->prepare('INSERT INTO `user_objective_requirements`
      (`user_id`, `objective_id`, `requirement_id`, `current_count`) VALUES (?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE `current_count` = VALUES(`current_count`)');
    $stmt->execute([$userId, $objectiveId, $requirementId, $count]);
  }

  public function markCompleted(int $userId, string $objectiveId): bool
  {
    if ($userId <= 0 || $objectiveId === '') throw new RuntimeException('Objective identity is invalid.');
    $stmt = $this->pdo->prepare('UPDATE `user_objectives` SET `completed_at` = CURRENT_TIMESTAMP
      WHERE `user_id` = ? AND `objective_id` = ? AND `completed_at` IS NULL');
    $stmt->execute([$userId, $objectiveId]);
    return $stmt->rowCount() === 1;
  }
}
