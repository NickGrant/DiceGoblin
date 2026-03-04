<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\BattleLogRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;

final class BattleLogRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @param array<string,mixed> $log
   */
  public function insert(int $battleId, array $log): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `battle_logs` (`battle_id`, `log_json`)
      VALUES (?, ?)
    ');
    $stmt->execute([$battleId, json_encode($log, JSON_UNESCAPED_SLASHES)]);
  }

  /**
   * Ownership enforced through battles table.
   *
   * @return array{battle_id:string,rules_version:string,log_json:string}|null
   */
  public function getForUser(int $battleId, int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT b.`id` AS battle_id, b.`rules_version`, bl.`log_json`
      FROM `battles` b
      JOIN `battle_logs` bl ON bl.`battle_id` = b.`id`
      WHERE b.`id` = ? AND b.`user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$battleId, $userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
      'battle_id' => (string)$r['battle_id'],
      'rules_version' => (string)$r['rules_version'],
      'log_json' => (string)$r['log_json'],
    ];
  }
}
