<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\PlayerStateRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class PlayerStateRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Fetch the full player_state row (no locks).
   *
   * @return array{
   *   user_id:string,
   *   currency_soft:int,
   *   currency_hard:int,
   *   last_login_at:?string,
   *   created_at:string,
   *   updated_at:string
   * }|null
   */
  public function getPlayerState(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `user_id`, `currency_soft`, `currency_hard`, `last_login_at`, `created_at`, `updated_at`
      FROM `player_state`
      WHERE `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      return null;
    }

    return [
      'user_id' => (string)$row['user_id'],
      'currency_soft' => (int)$row['currency_soft'],
      'currency_hard' => (int)$row['currency_hard'],
      'last_login_at' => $row['last_login_at'] !== null ? (string)$row['last_login_at'] : null,
      'created_at' => (string)$row['created_at'],
      'updated_at' => (string)$row['updated_at'],
    ];
  }

  /**
   * Fetch player_state and lock it for update.
   * Call inside an open transaction.
   *
   * @return array{currency_soft:int,currency_hard:int,last_login_at:?string}|null
   */
  public function getPlayerStateForUpdate(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `currency_soft`, `currency_hard`, `last_login_at`
      FROM `player_state`
      WHERE `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      return null;
    }

    return [
      'currency_soft' => (int)$row['currency_soft'],
      'currency_hard' => (int)$row['currency_hard'],
      'last_login_at' => $row['last_login_at'] !== null ? (string)$row['last_login_at'] : null,
    ];
  }

  /**
   * Ensure baseline row exists.
   *
   * Uses a single INSERT .. ON DUPLICATE KEY UPDATE to avoid TOCTOU races.
   */
  public function ensurePlayerState(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `player_state` (`user_id`, `currency_soft`, `currency_hard`, `last_login_at`)
      VALUES (?, 0, 0, NULL)
      ON DUPLICATE KEY UPDATE `user_id` = `user_id`
    ');
    $stmt->execute([$userId]);
  }

  /**
   * Convenience method used by profile hydration.
   *
   * @return array{soft:int,hard:int}
   */
  public function getCurrency(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `currency_soft`, `currency_hard`
      FROM `player_state`
      WHERE `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      // Prefer explicit bootstrap, but return a safe value for callers.
      return ['soft' => 0, 'hard' => 0];
    }

    return [
      'soft' => (int)$row['currency_soft'],
      'hard' => (int)$row['currency_hard'],
    ];
  }

  /**
   * Hard set currency values (no locks). Typically called inside a service transaction.
   */
  public function setCurrency(int $userId, int $soft, int $hard): void
  {
    if ($soft < 0 || $hard < 0) {
      throw new RuntimeException('Currency cannot be negative.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `player_state`
      SET `currency_soft` = ?, `currency_hard` = ?
      WHERE `user_id` = ?
    ');
    $stmt->execute([$soft, $hard, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Player state row not found.');
    }
  }

  /**
   * Add (or subtract) currency atomically and return the new balances.
   * Negative deltas are allowed but will never drive balances below zero.
   *
   * @return array{soft:int,hard:int}
   */
  public function addCurrencyClamped(int $userId, int $deltaSoft, int $deltaHard): array
  {
    try {
      $this->pdo->beginTransaction();

      $row = $this->getPlayerStateForUpdate($userId);
      if (!$row) {
        $this->pdo->rollBack();
        throw new RuntimeException('Player state row not found.');
      }

      $soft = (int)$row['currency_soft'];
      $hard = (int)$row['currency_hard'];

      $newSoft = $soft + $deltaSoft;
      $newHard = $hard + $deltaHard;

      if ($newSoft < 0) $newSoft = 0;
      if ($newHard < 0) $newHard = 0;

      $stmt = $this->pdo->prepare('
        UPDATE `player_state`
        SET `currency_soft` = ?, `currency_hard` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([$newSoft, $newHard, $userId]);

      $this->pdo->commit();

      return ['soft' => $newSoft, 'hard' => $newHard];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Spend currency atomically if the player has enough.
   *
   * @return bool true if spent, false if insufficient funds
   */
  public function spendCurrencyIfAvailable(int $userId, int $softCost, int $hardCost = 0): bool
  {
    if ($softCost < 0 || $hardCost < 0) {
      throw new RuntimeException('Currency costs cannot be negative.');
    }

    if ($softCost === 0 && $hardCost === 0) {
      return true;
    }

    try {
      $this->pdo->beginTransaction();

      $row = $this->getPlayerStateForUpdate($userId);
      if (!$row) {
        $this->pdo->rollBack();
        throw new RuntimeException('Player state row not found.');
      }

      $soft = (int)$row['currency_soft'];
      $hard = (int)$row['currency_hard'];

      if ($soft < $softCost || $hard < $hardCost) {
        $this->pdo->commit();
        return false;
      }

      $newSoft = $soft - $softCost;
      $newHard = $hard - $hardCost;

      $stmt = $this->pdo->prepare('
        UPDATE `player_state`
        SET `currency_soft` = ?, `currency_hard` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([$newSoft, $newHard, $userId]);

      $this->pdo->commit();
      return true;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Track last login as UTC.
   * (Call from Auth flow or SessionService as desired.)
   */
  public function touchLastLoginAtNow(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `player_state`
      SET `last_login_at` = UTC_TIMESTAMP()
      WHERE `user_id` = ?
    ');
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Player state row not found.');
    }
  }
}
