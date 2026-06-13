<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class UserUnlockService
{
  public const NAMESPACE_FEATURE = 'feature';
  public const NAMESPACE_UNIT_TYPE = 'unit_type';
  public const FEATURE_ACADEMY = 'academy';
  public const FEATURE_BIGGER_SQUAD = 'bigger_squad';
  public const FEATURE_BIGGEREST_SQUAD = 'biggerest_squad';
  public const FEATURE_SHOP_DISCOUNT = 'shop_discount';
  public const FEATURE_SELL_BONUS = 'sell_bonus';
  public const FEATURE_MARKET_MASTERY = 'market_mastery';
  public const FEATURE_SECOND_DAILY_DEAL = 'second_daily_deal';

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  public function grant(int $userId, string $namespace, string $unlockKey): void
  {
    $namespace = trim($namespace);
    $unlockKey = trim($unlockKey);
    if ($userId <= 0 || $namespace === '' || $unlockKey === '') {
      return;
    }

    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_unlocks` (`user_id`, `unlock_namespace`, `unlock_key`)
      VALUES (?, ?, ?)
    ');
    $stmt->execute([$userId, $namespace, $unlockKey]);
  }

  /**
   * @param list<string> $unlockKeys
   */
  public function grantMany(int $userId, string $namespace, array $unlockKeys): void
  {
    foreach ($unlockKeys as $unlockKey) {
      $this->grant($userId, $namespace, (string)$unlockKey);
    }
  }

  public function isUnlocked(int $userId, string $namespace, string $unlockKey): bool
  {
    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = ? AND `unlock_key` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $namespace, $unlockKey]);
    return (bool)$stmt->fetchColumn();
  }

  /**
   * @return list<string>
   */
  public function listUnlockedKeys(int $userId, string $namespace): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `unlock_key`
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = ?
      ORDER BY `unlock_key` ASC
    ');
    $stmt->execute([$userId, $namespace]);

    return array_values(array_map(
      static fn(mixed $value): string => (string)$value,
      $stmt->fetchAll(PDO::FETCH_COLUMN)
    ));
  }
}
