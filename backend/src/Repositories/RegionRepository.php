<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\RegionRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class RegionRepository
{
  /** @var array<string,string> */
  private const REGION_COMPLETION_UNLOCKS = [
    'the_farm' => 'mountains',
    'mountains' => 'swamps',
  ];

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * List regions (optionally only enabled).
   *
   * @return array<int, array{
   *   id:string,
   *   slug:string,
   *   name:string,
   *   theme:string,
   *   recommended_level:int,
   *   energy_cost:int,
   *   is_enabled:bool
   * }>
   */
  public function listRegions(bool $onlyEnabled = true): array
  {
    if ($onlyEnabled) {
      $stmt = $this->pdo->query('
        SELECT `id`, `slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`
        FROM `regions`
        WHERE `is_enabled` = 1
        ORDER BY `id` ASC
      ');
    } else {
      $stmt = $this->pdo->query('
        SELECT `id`, `slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`
        FROM `regions`
        ORDER BY `id` ASC
      ');
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'id' => (string)$r['id'],
      'slug' => (string)$r['slug'],
      'name' => (string)$r['name'],
      'theme' => (string)$r['theme'],
      'recommended_level' => (int)$r['recommended_level'],
      'energy_cost' => (int)$r['energy_cost'],
      'is_enabled' => ((int)$r['is_enabled']) === 1,
    ], $rows);
  }

  /**
   * @return array{
   *   id:string,
   *   slug:string,
   *   name:string,
   *   theme:string,
   *   recommended_level:int,
   *   energy_cost:int,
   *   is_enabled:bool
   * }|null
   */
  public function getRegionById(int $regionId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`
      FROM `regions`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$regionId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'slug' => (string)$r['slug'],
      'name' => (string)$r['name'],
      'theme' => (string)$r['theme'],
      'recommended_level' => (int)$r['recommended_level'],
      'energy_cost' => (int)$r['energy_cost'],
      'is_enabled' => ((int)$r['is_enabled']) === 1,
    ];
  }

  /**
   * @return array{
   *   id:string,
   *   slug:string,
   *   name:string,
   *   theme:string,
   *   recommended_level:int,
   *   energy_cost:int,
   *   is_enabled:bool
   * }|null
   */
  public function getRegionBySlug(string $slug): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`
      FROM `regions`
      WHERE `slug` = ?
      LIMIT 1
    ');
    $stmt->execute([$slug]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'slug' => (string)$r['slug'],
      'name' => (string)$r['name'],
      'theme' => (string)$r['theme'],
      'recommended_level' => (int)$r['recommended_level'],
      'energy_cost' => (int)$r['energy_cost'],
      'is_enabled' => ((int)$r['is_enabled']) === 1,
    ];
  }

  /**
   * Convenience: return the energy_cost for a region.
   */
  public function getEnergyCost(int $regionId): ?int
  {
    $stmt = $this->pdo->prepare('
      SELECT `energy_cost`
      FROM `regions`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$regionId]);

    $cost = $stmt->fetchColumn();
    if ($cost === false) {
      return null;
    }
    return (int)$cost;
  }

  /**
   * Returns region unlocks for user, joined with region metadata.
   *
   * @return array<int, array{
   *   region_id:string,
   *   region_slug:string,
   *   region_name:string,
   *   unlocked_at:string
   * }>
   */
  public function getUnlocksForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        ru.`region_id`,
        r.`slug` AS `region_slug`,
        r.`name` AS `region_name`,
        r.`theme` AS `region_theme`,
        r.`recommended_level`,
        r.`energy_cost`,
        ru.`unlocked_at`
      FROM `region_unlocks` ru
      JOIN `regions` r ON r.`id` = ru.`region_id`
      WHERE ru.`user_id` = ?
      ORDER BY ru.`region_id` ASC
    ');
    $stmt->execute([$userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unlockedSlugs = array_values(array_map(
      static fn(array $row): string => (string)$row['region_slug'],
      $rows,
    ));

    return array_map(fn(array $r): array => [
      'region_id' => (string)$r['region_id'],
      'region_slug' => (string)$r['region_slug'],
      'region_name' => (string)$r['region_name'],
      'region_theme' => (string)$r['region_theme'],
      'recommended_level' => (int)$r['recommended_level'],
      'energy_cost' => (int)$r['energy_cost'],
      'unlocked_at' => (string)$r['unlocked_at'],
      'is_completed' => $this->isRegionCompleted((string)$r['region_slug'], $unlockedSlugs),
    ], $rows);
  }

  /**
   * Returns the canonical enabled-region catalog decorated with user unlock state.
   *
   * @return array<int, array{
   *   id:string,
   *   slug:string,
   *   name:string,
   *   theme:string,
   *   recommended_level:int,
   *   energy_cost:int,
   *   is_enabled:bool,
   *   is_unlocked:bool,
   *   is_completed:bool,
   *   unlocked_at:?string
   * }>
   */
  public function listRegionsWithUserState(int $userId, bool $onlyEnabled = true): array
  {
    $enabledClause = $onlyEnabled ? 'WHERE r.`is_enabled` = 1' : '';
    $stmt = $this->pdo->prepare("
      SELECT
        r.`id`,
        r.`slug`,
        r.`name`,
        r.`theme`,
        r.`recommended_level`,
        r.`energy_cost`,
        r.`is_enabled`,
        ru.`unlocked_at`
      FROM `regions` r
      LEFT JOIN `region_unlocks` ru
        ON ru.`region_id` = r.`id`
       AND ru.`user_id` = ?
      $enabledClause
      ORDER BY r.`id` ASC
    ");
    $stmt->execute([$userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unlockedSlugs = array_values(array_map(
      static fn(array $row): string => (string)$row['slug'],
      array_values(array_filter(
        $rows,
        static fn(array $row): bool => isset($row['unlocked_at']) && $row['unlocked_at'] !== null && $row['unlocked_at'] !== '',
      )),
    ));

    return array_map(fn(array $row): array => [
      'id' => (string)$row['id'],
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'theme' => (string)$row['theme'],
      'recommended_level' => (int)$row['recommended_level'],
      'energy_cost' => (int)$row['energy_cost'],
      'is_enabled' => ((int)$row['is_enabled']) === 1,
      'is_unlocked' => isset($row['unlocked_at']) && $row['unlocked_at'] !== null && $row['unlocked_at'] !== '',
      'is_completed' => $this->isRegionCompleted((string)$row['slug'], $unlockedSlugs),
      'unlocked_at' => isset($row['unlocked_at']) && $row['unlocked_at'] !== null
        ? (string)$row['unlocked_at']
        : null,
    ], $rows);
  }

  public function getNextRegionSlug(string $slug): ?string
  {
    return self::REGION_COMPLETION_UNLOCKS[$slug] ?? null;
  }

  /**
   * @return array<int, string> region_ids (strings) unlocked by user
   */
  public function getUnlockedRegionIds(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `region_id`
      FROM `region_unlocks`
      WHERE `user_id` = ?
      ORDER BY `region_id` ASC
    ');
    $stmt->execute([$userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): string => (string)$r['region_id'], $rows);
  }

  public function isRegionUnlocked(int $userId, int $regionId): bool
  {
    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `region_unlocks`
      WHERE `user_id` = ? AND `region_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $regionId]);

    return (bool)$stmt->fetchColumn();
  }

  /**
   * Unlock a region for a user (idempotent).
   * Uses INSERT .. ON DUPLICATE KEY UPDATE to avoid TOCTOU races.
   */
  public function unlockRegion(int $userId, int $regionId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `region_unlocks` (`user_id`, `region_id`)
      VALUES (?, ?)
      ON DUPLICATE KEY UPDATE `user_id` = `user_id`
    ');
    $stmt->execute([$userId, $regionId]);
  }

  /**
   * Locking variant: asserts region exists (and optionally enabled) before unlocking.
   * This is useful for endpoints where you accept regionId from client input.
   */
  public function unlockRegionIfValid(int $userId, int $regionId, bool $requireEnabled = true): void
  {
    try {
      $this->pdo->beginTransaction();

      if ($requireEnabled) {
        $stmt = $this->pdo->prepare('
          SELECT 1 FROM `regions` WHERE `id` = ? AND `is_enabled` = 1 LIMIT 1 FOR UPDATE
        ');
      } else {
        $stmt = $this->pdo->prepare('
          SELECT 1 FROM `regions` WHERE `id` = ? LIMIT 1 FOR UPDATE
        ');
      }
      $stmt->execute([$regionId]);

      if (!(bool)$stmt->fetchColumn()) {
        $this->pdo->rollBack();
        throw new RuntimeException('Region not found (or disabled).');
      }

      $this->unlockRegion($userId, $regionId);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @param array<int,string> $unlockedSlugs
   */
  private function isRegionCompleted(string $slug, array $unlockedSlugs): bool
  {
    $nextSlug = $this->getNextRegionSlug($slug);
    if ($nextSlug === null) {
      return false;
    }

    return in_array($nextSlug, $unlockedSlugs, true);
  }
}
