<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Services\EnergyService.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Services;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Repositories\EnergyRepository;
use RuntimeException;
use Throwable;

final class EnergyService
{
  public function __construct(
    private readonly EnergyRepository $energyRepo,
  ) {}

  /**
   * Applies regeneration if enough time has elapsed since last_regen_at.
   *
   * Returns a payload suitable for GET /api/v1/profile:
   *  [
   *    'current' => int,
   *    'max' => int,
   *    'regen_rate_per_hour' => float,
   *    'last_regen_at' => string (ISO-8601 UTC with Z)
   *  ]
   */
  public function regenIfNeeded(int $userId): array
  {
    try {
      // Regen wants a stable read/modify/write; do it with row locking.
      // EnergyRepository provides begin/commit safe methods too, but keeping the transaction
      // here makes the service logic explicit.
      $pdo = $this->energyRepoPdo();
      $pdo->beginTransaction();

      $row = $this->energyRepo->getEnergyStateForUpdate($userId);
      if (!$row) {
        $pdo->rollBack();
        throw new RuntimeException('Energy state not found.');
      }

      $current = (int)$row['energy_current'];
      $max = (int)$row['energy_max'];
      $rate = (float)$row['regen_rate_per_hour'];

      $lastSql = (string)$row['last_regen_at']; // 'Y-m-d H:i:s'
      $last = new DateTimeImmutable($lastSql, new DateTimeZone('UTC'));
      $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

      // Fail-safe defaults
      if ($max < 0) $max = 0;
      if ($current < 0) $current = 0;
      if ($current > $max) $current = $max;

      // No regen configured or already full.
      if ($rate <= 0.0 || $max === 0 || $current >= $max) {
        // Optional: if full, "touch" last_regen_at to avoid banking huge regen on long idle.
        // (This keeps last_regen_at moving forward; you can remove this if you prefer.)
        if ($current >= $max) {
          $this->energyRepo->touchLastRegenAtNow($userId);
          $last = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        $pdo->commit();

        return [
          'current' => $current,
          'max' => $max,
          'regen_rate_per_hour' => $rate,
          'last_regen_at' => $this->toIsoUtc($last),
        ];
      }

      $deltaSeconds = max(0, $now->getTimestamp() - $last->getTimestamp());

      // Convert rate (per hour) to a discrete tick interval in seconds.
      // Example: 12/hour => 300 seconds per energy.
      $secondsPerEnergy = (int)floor(3600.0 / $rate);
      if ($secondsPerEnergy <= 0) {
        // Extremely high regen_rate_per_hour; clamp to 1 second ticks.
        $secondsPerEnergy = 1;
      }

      $regenTicks = (int)floor($deltaSeconds / $secondsPerEnergy);
      if ($regenTicks <= 0) {
        $pdo->commit();
        return [
          'current' => $current,
          'max' => $max,
          'regen_rate_per_hour' => $rate,
          'last_regen_at' => $this->toIsoUtc($last),
        ];
      }

      $newCurrent = min($max, $current + $regenTicks);

      // Advance last_regen_at by only the amount of time consumed by regen ticks.
      // This prevents fractional leftover time from being lost.
      $advanceSeconds = $regenTicks * $secondsPerEnergy;
      $newLast = $last->modify('+' . $advanceSeconds . ' seconds');

      // If we hit max, you can choose to set last_regen_at = now (prevents leftover banking).
      // Here we keep the tick-advanced timestamp, which preserves leftover time accurately.
      // If you prefer "no banking when full", uncomment:
      // if ($newCurrent >= $max) { $newLast = $now; }

      $this->energyRepo->setEnergyCurrentAndLastRegenAt($userId, $newCurrent, $newLast->format('Y-m-d H:i:s'));

      $pdo->commit();

      return [
        'current' => $newCurrent,
        'max' => $max,
        'regen_rate_per_hour' => $rate,
        'last_regen_at' => $this->toIsoUtc($newLast),
      ];
    } catch (Throwable $e) {
      $pdo = $this->energyRepoPdo();
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Spend energy if available.
   *
   * Returns:
   *  - true if spent
   *  - false if insufficient energy
   *
   * Note: This does NOT auto-regen. Call regenIfNeeded() first if your flow expects it.
   */
  public function consumeIfAvailable(int $userId, int $amount): bool
  {
    if ($amount <= 0) {
      return true;
    }

    return $this->energyRepo->consumeEnergyIfAvailable($userId, $amount);
  }

  /**
   * Get energy payload without applying regen (useful for debugging).
   */
  public function getSnapshot(int $userId): array
  {
    $row = $this->energyRepo->getEnergyState($userId);
    if (!$row) {
      throw new RuntimeException('Energy state not found.');
    }

    $last = new DateTimeImmutable((string)$row['last_regen_at'], new DateTimeZone('UTC'));

    return [
      'current' => (int)$row['energy_current'],
      'max' => (int)$row['energy_max'],
      'regen_rate_per_hour' => (float)$row['regen_rate_per_hour'],
      'last_regen_at' => $this->toIsoUtc($last),
    ];
  }

  // -----------------------------
  // Internals
  // -----------------------------

  /**
   * EnergyRepository holds the PDO, but does not expose it.
   * We access it via reflection-safe hack avoidance by requiring the repository to provide a PDO accessor.
   *
   * If you don't want this method, preferred approach is:
   *  - pass PDO into EnergyService and let both share it, or
   *  - add EnergyRepository::pdo() getter.
   *
   * For now, implement EnergyRepository::pdo() and call it here.
   */
  private function energyRepoPdo(): \PDO
  {
    if (!method_exists($this->energyRepo, 'pdo')) {
      throw new RuntimeException('EnergyRepository must expose pdo() for transactional services.');
    }

    /** @var \PDO $pdo */
    $pdo = $this->energyRepo->pdo();
    return $pdo;
  }

  private function toIsoUtc(DateTimeImmutable $dt): string
  {
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
  }
}
