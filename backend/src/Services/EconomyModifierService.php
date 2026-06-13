<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class EconomyModifierService
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  public function adjustedShopCostForUser(int $userId, int $baseCost): int
  {
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);

    return self::adjustedShopCost($baseCost, $featureUnlocks);
  }

  public function adjustedSellValueForUser(int $userId, int $baseSellValue): int
  {
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);

    return self::adjustedSellValue($baseSellValue, $featureUnlocks);
  }

  /**
   * @param array<int,string> $featureUnlocks
   */
  public static function adjustedShopCost(int $baseCost, array $featureUnlocks): int
  {
    $normalized = max(1, $baseCost);
    if (in_array(UserUnlockService::FEATURE_MARKET_MASTERY, $featureUnlocks, true)) {
      return max(1, (int)round($normalized * 0.8));
    }

    if (!in_array(UserUnlockService::FEATURE_SHOP_DISCOUNT, $featureUnlocks, true)) {
      return $normalized;
    }

    return max(1, (int)round($normalized * 0.9));
  }

  /**
   * @param array<int,string> $featureUnlocks
   */
  public static function adjustedSellValue(int $baseSellValue, array $featureUnlocks): int
  {
    $normalized = max(1, $baseSellValue);
    if (in_array(UserUnlockService::FEATURE_MARKET_MASTERY, $featureUnlocks, true)) {
      return max(1, (int)round($normalized * 1.2));
    }

    if (!in_array(UserUnlockService::FEATURE_SELL_BONUS, $featureUnlocks, true)) {
      return $normalized;
    }

    return max(1, (int)round($normalized * 1.1));
  }
}
