<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class UserUnlockRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return list<string> */
  public function listIdsForUser(int $userId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('SELECT `unlock_id` FROM `user_unlocks` WHERE `user_id` = ? ORDER BY `unlock_id` ASC' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$userId]);
    return array_map(static fn(mixed $id): string => (string)$id, $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  public function owns(int $userId, string $unlockId): bool
  {
    $stmt = $this->pdo->prepare('SELECT 1 FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ? LIMIT 1');
    $stmt->execute([$userId, $unlockId]);
    return $stmt->fetchColumn() !== false;
  }

  public function insertIfAbsent(int $userId, string $unlockId): bool
  {
    if ($userId <= 0 || $unlockId === '') throw new RuntimeException('Unlock ownership identity is invalid.');
    $stmt = $this->pdo->prepare('INSERT INTO `user_unlocks` (`user_id`, `unlock_id`) VALUES (?, ?)
      ON DUPLICATE KEY UPDATE `unlock_id` = VALUES(`unlock_id`)');
    $stmt->execute([$userId, $unlockId]);
    return $stmt->rowCount() === 1;
  }
}
