<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class UserCodexRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return list<string> */
  public function listIdsForUser(int $userId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('SELECT `codex_id` FROM `user_codex_ownership` WHERE `user_id` = ? ORDER BY `codex_id` ASC' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$userId]);
    return array_map(static fn(mixed $id): string => (string)$id, $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  public function owns(int $userId, string $codexId): bool
  {
    $stmt = $this->pdo->prepare('SELECT 1 FROM `user_codex_ownership` WHERE `user_id` = ? AND `codex_id` = ? LIMIT 1');
    $stmt->execute([$userId, $codexId]);
    return $stmt->fetchColumn() !== false;
  }

  public function insertIfAbsent(int $userId, string $codexId): bool
  {
    if ($userId <= 0 || $codexId === '') throw new RuntimeException('Codex ownership identity is invalid.');
    $stmt = $this->pdo->prepare('INSERT INTO `user_codex_ownership` (`user_id`, `codex_id`) VALUES (?, ?)
      ON DUPLICATE KEY UPDATE `codex_id` = VALUES(`codex_id`)');
    $stmt->execute([$userId, $codexId]);
    return $stmt->rowCount() === 1;
  }
}
