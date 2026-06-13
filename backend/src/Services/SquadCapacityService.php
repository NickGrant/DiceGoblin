<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class SquadCapacityService
{
  public const DEFAULT_CAP = 4;
  public const BIGGER_SQUAD_CAP = 6;
  public const BIGGEREST_SQUAD_CAP = 9;

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  public function getCapForUser(int $userId): int
  {
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);

    return self::resolveCapFromFeatureUnlocks($featureUnlocks);
  }

  /**
   * @param array<int,string> $featureUnlocks
   */
  public static function resolveCapFromFeatureUnlocks(array $featureUnlocks): int
  {
    if (in_array(UserUnlockService::FEATURE_BIGGEREST_SQUAD, $featureUnlocks, true)) {
      return self::BIGGEREST_SQUAD_CAP;
    }

    if (in_array(UserUnlockService::FEATURE_BIGGER_SQUAD, $featureUnlocks, true)) {
      return self::BIGGER_SQUAD_CAP;
    }

    return self::DEFAULT_CAP;
  }
}
