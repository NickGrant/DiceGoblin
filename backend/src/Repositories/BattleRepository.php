<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class BattleRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array{id:string,status:string,outcome:string,ticks:int,rounds:int,rules_version:string}|null
   */
  public function getByRunNode(int $runId, int $nodeId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `status`, `outcome`, `ticks`, `rounds`, `rules_version`
      FROM `battles`
      WHERE `run_id` = ? AND `node_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$runId, $nodeId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
      'id' => (string)$r['id'],
      'status' => (string)$r['status'],
      'outcome' => (string)$r['outcome'],
      'ticks' => (int)$r['ticks'],
      'rounds' => (int)$r['rounds'],
      'rules_version' => (string)$r['rules_version'],
    ];
  }

  /**
   * Create completed battle row.
   * Returns new battle id.
   */
  public function createCompleted(
    int $userId,
    int $runId,
    int $nodeId,
    int $teamId,
    int|string $seed,
    string $outcome,
    int $ticks,
    int $rounds,
    string $rulesVersion = 'combat_v1'
  ): int {
    if (!in_array($outcome, ['victory', 'defeat'], true)) {
      throw new RuntimeException('Invalid battle outcome.');
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `battles` (
        `user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`
      ) VALUES (?, ?, ?, ?, ?, ?, \'completed\', ?, ?, ?)
    ');
    $stmt->execute([
      $userId,
      $runId,
      $nodeId,
      $teamId,
      $rulesVersion,
      (string)$seed,
      $outcome,
      $ticks,
      $rounds,
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  /**
   * Lock + fetch battle + rewards for claiming (ownership enforced).
   *
   * @return array{
   *   id:string,status:string,outcome:string,rules_version:string,run_id:string,team_id:string,seed:string,
   *   xp_total:int,rewards_json:string
   * }|null
   */
  public function getForClaimForUpdate(int $battleId, int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT b.`id`, b.`status`, b.`outcome`, b.`rules_version`, b.`run_id`, b.`team_id`, b.`seed`,
             br.`xp_total`, br.`rewards_json`
      FROM `battles` b
      JOIN `battle_rewards` br ON br.`battle_id` = b.`id`
      WHERE b.`id` = ? AND b.`user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$battleId, $userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    return [
      'id' => (string)$r['id'],
      'status' => (string)$r['status'],
      'outcome' => (string)$r['outcome'],
      'rules_version' => (string)$r['rules_version'],
      'run_id' => (string)$r['run_id'],
      'team_id' => (string)$r['team_id'],
      'seed' => (string)$r['seed'],
      'xp_total' => (int)$r['xp_total'],
      'rewards_json' => (string)$r['rewards_json'],
    ];
  }

  /**
   * Mark claimed. Returns true if transition happened; false if already claimed / not completed.
   */
  public function markClaimedIfCompleted(int $battleId, int $userId): bool
  {
    $stmt = $this->pdo->prepare('
      UPDATE `battles`
      SET `status` = \'claimed\'
      WHERE `id` = ? AND `user_id` = ? AND `status` = \'completed\'
    ');
    $stmt->execute([$battleId, $userId]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Deletes a battle and its dependent artifacts for retry flows.
   * Must be called inside an open transaction.
   */
  public function deleteBattleForRetry(int $battleId, int $userId): void
  {
    $stmt = $this->pdo->prepare('
      DELETE FROM `battle_rewards`
      WHERE `battle_id` = ? AND EXISTS (
        SELECT 1 FROM `battles` b WHERE b.`id` = ? AND b.`user_id` = ?
      )
    ');
    $stmt->execute([$battleId, $battleId, $userId]);

    $stmt = $this->pdo->prepare('
      DELETE FROM `battle_logs`
      WHERE `battle_id` = ? AND EXISTS (
        SELECT 1 FROM `battles` b WHERE b.`id` = ? AND b.`user_id` = ?
      )
    ');
    $stmt->execute([$battleId, $battleId, $userId]);

    $stmt = $this->pdo->prepare('
      DELETE FROM `battles`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$battleId, $userId]);
  }
}
