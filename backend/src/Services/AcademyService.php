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
   *   unit_unlocks:array<int,array{unit_type_slug:string,name:string,role:string,cost:int,is_unlocked:bool}>
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

    $stmt = $this->pdo->query("
      SELECT `slug`, `name`, `role`
      FROM `unit_types`
      WHERE RIGHT(`slug`, 3) IN ('_t1', '_t2')
      ORDER BY CASE
        WHEN RIGHT(`slug`, 3) = '_t1' THEN 1
        WHEN RIGHT(`slug`, 3) = '_t2' THEN 2
        ELSE 3
      END ASC,
      `id` ASC
    ");

    $unitUnlocks = array_map(function (array $row) use ($unlocked): array {
      $slug = (string)$row['slug'];
      return [
        'unit_type_slug' => $slug,
        'name' => (string)$row['name'],
        'role' => (string)$row['role'],
        'cost' => $this->unlockCostForSlug($slug),
        'is_unlocked' => isset($unlocked[$slug]),
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
}
