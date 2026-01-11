<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class EnergyRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Fetch energy state for a user (no locks).
   *
   * @return array{
   *   user_id:string,
   *   energy_current:int,
   *   energy_max:int,
   *   regen_rate_per_hour:float,
   *   last_regen_at:string
   * }|null
   */
  public function getEnergyState(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `user_id`, `energy_current`, `energy_max`, `regen_rate_per_hour`, `last_regen_at`
      FROM `energy_state`
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
      'energy_current' => (int)$row['energy_current'],
      'energy_max' => (int)$row['energy_max'],
      'regen_rate_per_hour' => (float)$row['regen_rate_per_hour'],
      'last_regen_at' => (string)$row['last_regen_at'], // 'Y-m-d H:i:s'
    ];
  }

  /**
   * Fetch energy state and lock it for update.
   * Call inside an open transaction.
   *
   * @return array{
   *   user_id:string,
   *   energy_current:int,
   *   energy_max:int,
   *   regen_rate_per_hour:float,
   *   last_regen_at:string
   * }|null
   */
  public function getEnergyStateForUpdate(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `user_id`, `energy_current`, `energy_max`, `regen_rate_per_hour`, `last_regen_at`
      FROM `energy_state`
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
      'user_id' => (string)$row['user_id'],
      'energy_current' => (int)$row['energy_current'],
      'energy_max' => (int)$row['energy_max'],
      'regen_rate_per_hour' => (float)$row['regen_rate_per_hour'],
      'last_regen_at' => (string)$row['last_regen_at'],
    ];
  }

  /**
   * Create the baseline row if missing.
   * Uses UTC_TIMESTAMP() for last_regen_at.
   */
  public function ensureEnergyState(
    int $userId,
    int $energyCurrent = 50,
    int $energyMax = 50,
    float $regenRatePerHour = 12.0,
  ): void {
    $existsStmt = $this->pdo->prepare('SELECT 1 FROM `energy_state` WHERE `user_id` = ?');
    $existsStmt->execute([$userId]);

    if ((bool)$existsStmt->fetchColumn()) {
      return;
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `energy_state` (`user_id`, `energy_current`, `energy_max`, `regen_rate_per_hour`, `last_regen_at`)
      VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
    ');
    $stmt->execute([$userId, $energyCurrent, $energyMax, $regenRatePerHour]);
  }

  /**
   * Update only current energy (does NOT change last_regen_at).
   * Use when consuming/spending energy; regen timestamp is a business decision.
   */
  public function setEnergyCurrent(int $userId, int $energyCurrent): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `energy_state`
      SET `energy_current` = ?
      WHERE `user_id` = ?
    ');
    $stmt->execute([$energyCurrent, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Energy state row not found.');
    }
  }

  /**
   * Update current energy and last_regen_at (string in 'Y-m-d H:i:s', UTC).
   * This is the typical write when applying regeneration.
   */
  public function setEnergyCurrentAndLastRegenAt(int $userId, int $energyCurrent, string $lastRegenAtSql): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `energy_state`
      SET `energy_current` = ?, `last_regen_at` = ?
      WHERE `user_id` = ?
    ');
    $stmt->execute([$energyCurrent, $lastRegenAtSql, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Energy state row not found.');
    }
  }

  /**
   * Touch last_regen_at to now (UTC) without changing energy_current.
   * Useful when you detect energy_current >= energy_max and want to prevent “banked regen”.
   */
  public function touchLastRegenAtNow(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `energy_state`
      SET `last_regen_at` = UTC_TIMESTAMP()
      WHERE `user_id` = ?
    ');
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Energy state row not found.');
    }
  }

  /**
   * Atomically spend energy if available.
   *
   * - Locks the user row (FOR UPDATE)
   * - Decrements energy_current if energy_current >= $amount
   * - Does NOT modify last_regen_at (leave that decision to EnergyService)
   *
   * @return bool true if spent, false if insufficient energy
   */
  public function consumeEnergyIfAvailable(int $userId, int $amount): bool
  {
    if ($amount <= 0) {
      return true;
    }

    try {
      $this->pdo->beginTransaction();

      $row = $this->getEnergyStateForUpdate($userId);
      if (!$row) {
        $this->pdo->rollBack();
        throw new RuntimeException('Energy state row not found.');
      }

      $current = (int)$row['energy_current'];
      if ($current < $amount) {
        $this->pdo->commit();
        return false;
      }

      $newCurrent = $current - $amount;

      $stmt = $this->pdo->prepare('
        UPDATE `energy_state`
        SET `energy_current` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([$newCurrent, $userId]);

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
   * Atomically set max + clamp current if needed.
   * Call when upgrading max energy.
   */
  public function setEnergyMaxAndClamp(int $userId, int $energyMax): void
  {
    try {
      $this->pdo->beginTransaction();

      $row = $this->getEnergyStateForUpdate($userId);
      if (!$row) {
        $this->pdo->rollBack();
        throw new RuntimeException('Energy state row not found.');
      }

      $current = (int)$row['energy_current'];
      $newCurrent = min($current, $energyMax);

      $stmt = $this->pdo->prepare('
        UPDATE `energy_state`
        SET `energy_max` = ?, `energy_current` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([$energyMax, $newCurrent, $userId]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Atomically set regen rate (per hour).
   * Does not touch last_regen_at.
   */
  public function setRegenRatePerHour(int $userId, float $regenRatePerHour): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `energy_state`
      SET `regen_rate_per_hour` = ?
      WHERE `user_id` = ?
    ');
    $stmt->execute([$regenRatePerHour, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Energy state row not found.');
    }
  }

  public function pdo(): \PDO
  {
    return $this->pdo;
  }
}
