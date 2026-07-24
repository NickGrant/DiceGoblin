<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\PlayerStateRepository;
use PDO;
use RuntimeException;
use Throwable;

final class AcademyService
{
  private const DEFAULT_UNLOCK_COST = 500;
  private const TIER_ONE_UNLOCK_COST = 250;

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerStateRepository,
  ) {}

  /**
   * @return array{
   *   currency_soft:int,
   *   unit_unlocks:array<int,array{unit_type_slug:string,name:string,role:string,cost:int,is_unlocked:bool,is_available:bool,requirements:array<int,array{type:string,label:string,is_met:bool,progress_current:int,progress_target:int}>,total_attack:int,total_defense:int,total_precision:int,total_resolve:int,max_hp:int}>
   * }
   */
  public function buildCatalog(int $userId): array
  {
    $this->requireAcademyUnlocked($userId);

    $this->playerStateRepository->ensurePlayerState($userId);
    $currency = $this->playerStateRepository->getCurrency($userId);
    $unlockService = new UserUnlockService($this->pdo);
    $unlocked = array_fill_keys(
      $unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE),
      true
    );
    $hasCompletedRun = $this->hasCompletedRun($userId);

    $stmt = $this->pdo->query("
      SELECT `slug`, `name`, `role`, `base_stats_json`
      FROM `unit_types`
      WHERE RIGHT(`slug`, 3) IN ('_t1', '_t2')
      ORDER BY CASE
        WHEN RIGHT(`slug`, 3) = '_t1' THEN 1
        WHEN RIGHT(`slug`, 3) = '_t2' THEN 2
        ELSE 3
      END ASC,
      `id` ASC
    ");

    $unitUnlocks = array_map(function (array $row) use ($unlocked, $hasCompletedRun): array {
      $slug = (string)$row['slug'];
      $requirements = $this->requirementsForSlug($slug, $hasCompletedRun);
      return [
        'unit_type_slug' => $slug,
        'name' => (string)$row['name'],
        'role' => (string)$row['role'],
        'cost' => $this->unlockCostForSlug($slug),
        'is_unlocked' => isset($unlocked[$slug]),
        'is_available' => $this->requirementsAreMet($requirements),
        'requirements' => $requirements,
        ...$this->unitTypeStats($row['base_stats_json'] ?? null),
      ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    return [
      'currency_soft' => max(0, (int)($currency['soft'] ?? 0)),
      'unit_unlocks' => $unitUnlocks,
    ];
  }

  /**
   * @return array{unit_type_slug:string,cost:int,currency_soft:int}
   */
  public function unlockUnitType(int $userId, string $unitTypeSlug): array
  {
    try {
      $this->requireAcademyUnlocked($userId);
      $this->pdo->beginTransaction();

      $catalogEntry = $this->loadUnlockableUnitType($unitTypeSlug);
      if ($catalogEntry === null) {
        throw new RuntimeException('Requested unit type is not available for Academy unlocks.');
      }
      if (!$this->requirementsAreMet($this->requirementsForSlug($unitTypeSlug, $this->hasCompletedRun($userId)))) {
        throw new RuntimeException('Complete any run before researching Tier II unit types.');
      }

      $unlockService = new UserUnlockService($this->pdo);
      if ($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $unitTypeSlug)) {
        throw new RuntimeException('Requested unit type is already unlocked.');
      }

      $this->playerStateRepository->ensurePlayerState($userId);
      $state = $this->playerStateRepository->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        throw new RuntimeException('Player state unavailable.');
      }

      $cost = $this->unlockCostForSlug($unitTypeSlug);
      $currentSoft = max(0, (int)($state['currency_soft'] ?? 0));
      if ($currentSoft < $cost) {
        throw new RuntimeException('Not enough soft currency.');
      }

      $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $unitTypeSlug);
      $nextSoft = $currentSoft - $cost;
      $this->playerStateRepository->setCurrency($userId, $nextSoft, max(0, (int)($state['currency_hard'] ?? 0)));

      $this->pdo->commit();

      return [
        'unit_type_slug' => $unitTypeSlug,
        'cost' => $cost,
        'currency_soft' => $nextSoft,
      ];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  private function requireAcademyUnlocked(int $userId): void
  {
    if (!(new UserUnlockService($this->pdo))->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ACADEMY)) {
      throw new RuntimeException('Academy has not been unlocked yet.');
    }
  }

  /**
   * @return array{slug:string,name:string,role:string}|null
   */
  private function loadUnlockableUnitType(string $unitTypeSlug): ?array
  {
    $stmt = $this->pdo->prepare("
      SELECT `slug`, `name`, `role`
      FROM `unit_types`
      WHERE `slug` = ? AND RIGHT(`slug`, 3) IN ('_t1', '_t2')
      LIMIT 1
    ");
    $stmt->execute([$unitTypeSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return null;
    }

    return [
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'role' => (string)$row['role'],
    ];
  }

  private function unlockCostForSlug(string $unitTypeSlug): int
  {
    return str_ends_with($unitTypeSlug, '_t1')
      ? self::TIER_ONE_UNLOCK_COST
      : self::DEFAULT_UNLOCK_COST;
  }

  private function hasCompletedRun(int $userId): bool
  {
    $stmt = $this->pdo->prepare("
      SELECT COUNT(*)
      FROM `region_runs`
      WHERE `user_id` = ? AND `status` = 'completed'
    ");
    $stmt->execute([$userId]);

    return (int)$stmt->fetchColumn() > 0;
  }

  /**
   * @return array<int,array{type:string,label:string,is_met:bool,progress_current:int,progress_target:int}>
   */
  private function requirementsForSlug(string $unitTypeSlug, bool $hasCompletedRun): array
  {
    if (!str_ends_with($unitTypeSlug, '_t2')) {
      return [];
    }

    return [[
      'type' => 'completed_run',
      'label' => 'Complete any run',
      'is_met' => $hasCompletedRun,
      'progress_current' => $hasCompletedRun ? 1 : 0,
      'progress_target' => 1,
    ]];
  }

  /**
   * @param array<int,array{is_met:bool}> $requirements
   */
  private function requirementsAreMet(array $requirements): bool
  {
    foreach ($requirements as $requirement) {
      if (!(bool)($requirement['is_met'] ?? false)) {
        return false;
      }
    }

    return true;
  }

  /**
   * @return array{total_attack:int,total_defense:int,total_precision:int,total_resolve:int,max_hp:int}
   */
  private function unitTypeStats(mixed $baseStatsJson): array
  {
    $stats = is_string($baseStatsJson) && $baseStatsJson !== ''
      ? json_decode($baseStatsJson, true)
      : [];
    $stats = is_array($stats) ? $stats : [];

    return [
      'total_attack' => max(0, (int)($stats['attack'] ?? 0)),
      'total_defense' => max(0, (int)($stats['defense'] ?? 0)),
      'total_precision' => max(0, (int)($stats['precision'] ?? 5)),
      'total_resolve' => max(0, (int)($stats['resolve'] ?? 5)),
      'max_hp' => max(1, (int)($stats['max_hp'] ?? 1)),
    ];
  }
}
