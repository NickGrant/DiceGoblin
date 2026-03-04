<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\BattleRewardsRepository.php
 * Purpose: Project PHP module.
 */

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
      VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([
      $battleId,
      $xpTotal,
      $currencySoft,
      json_encode($rewards, JSON_UNESCAPED_SLASHES),
    ]);
  }

  /**
   * @param array<string,mixed> $rewards
   */
  public function updateRewardsJson(int $battleId, array $rewards): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `battle_rewards`
      SET `rewards_json` = ?
      WHERE `battle_id` = ?
      LIMIT 1
    ');
    $stmt->execute([
      json_encode($rewards, JSON_UNESCAPED_SLASHES),
      $battleId,
    ]);
  }
}
