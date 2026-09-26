<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use DiceGoblins\Domain\Inventory\InsufficientInventoryException;
use PDO;
use RuntimeException;

final class UserItemRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return list<array{item_id:string,quantity:int}> */
  public function listPositiveForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('SELECT `item_id`, `quantity` FROM `user_items`
      WHERE `user_id` = ? AND `quantity` > 0 ORDER BY `item_id` ASC');
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
      'item_id' => (string)$row['item_id'],
      'quantity' => (int)$row['quantity'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /** @return array{item_id:string,quantity:int}|null */
  public function lockOwnedStack(int $userId, string $itemId): ?array
  {
    $this->requireTransaction();
    $this->requireIdentity($userId, $itemId);
    $stmt = $this->pdo->prepare('SELECT `item_id`, `quantity` FROM `user_items`
      WHERE `user_id` = ? AND `item_id` = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$userId, $itemId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? ['item_id' => (string)$row['item_id'], 'quantity' => (int)$row['quantity']] : null;
  }

  public function increment(int $userId, string $itemId, int $quantity): int
  {
    $this->requireTransaction();
    $this->requireIdentity($userId, $itemId);
    if ($quantity <= 0) throw new RuntimeException('Inventory increment must be positive.');
    $stmt = $this->pdo->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)');
    $stmt->execute([$userId, $itemId, $quantity]);
    return $this->requiredLockedQuantity($userId, $itemId);
  }

  /** Zero-quantity stacks are deleted rather than retained. */
  public function decrement(int $userId, string $itemId, int $quantity): int
  {
    $this->requireTransaction();
    $this->requireIdentity($userId, $itemId);
    if ($quantity <= 0) throw new RuntimeException('Inventory decrement must be positive.');
    $stack = $this->lockOwnedStack($userId, $itemId);
    if ($stack === null || $stack['quantity'] < $quantity) {
      throw new InsufficientInventoryException('Owned item quantity is insufficient.');
    }
    $remaining = $stack['quantity'] - $quantity;
    if ($remaining === 0) {
      $stmt = $this->pdo->prepare('DELETE FROM `user_items` WHERE `user_id` = ? AND `item_id` = ? AND `quantity` = ?');
      $stmt->execute([$userId, $itemId, $stack['quantity']]);
    } else {
      $stmt = $this->pdo->prepare('UPDATE `user_items` SET `quantity` = ?
        WHERE `user_id` = ? AND `item_id` = ? AND `quantity` = ?');
      $stmt->execute([$remaining, $userId, $itemId, $stack['quantity']]);
    }
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Owned item quantity changed unexpectedly.');
    return $remaining;
  }

  private function requiredLockedQuantity(int $userId, string $itemId): int
  {
    $stack = $this->lockOwnedStack($userId, $itemId);
    if ($stack === null) throw new RuntimeException('Owned item stack is unavailable.');
    return $stack['quantity'];
  }

  private function requireTransaction(): void
  {
    if (!$this->pdo->inTransaction()) throw new RuntimeException('Inventory mutation requires a caller-owned transaction.');
  }

  private function requireIdentity(int $userId, string $itemId): void
  {
    if ($userId <= 0 || $itemId === '') throw new RuntimeException('Inventory identity is invalid.');
  }
}
