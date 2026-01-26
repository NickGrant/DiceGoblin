<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;

final class BattleRewardsRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @param array<string,mixed> $rewards
   */
  public function insert(int $battleId, int $xpTotal, int $currencySoft, array $rewards): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `battle_rewards` (`battle_id`, `xp_total`, `currency_soft`, `rewards_json`)
      VALUES (?, ?, ?, CAST(? AS JSON))
    ');
    $stmt->execute([
      $battleId,
      $xpTotal,
      $currencySoft,
      json_encode($rewards, JSON_UNESCAPED_SLASHES),
    ]);
  }
}
