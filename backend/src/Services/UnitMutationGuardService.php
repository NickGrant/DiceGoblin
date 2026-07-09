<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\RunRepository;
use PDO;

final class UnitMutationGuardService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly RunRepository $runRepository,
  ) {}

  public function isUnitMutableForUser(int $userId, int $unitId): bool
  {
    return $this->getLockedUnitIdsForUser($userId, [$unitId]) === [];
  }

  /**
   * @param array<int,int> $unitIds
   * @return array<int,int>
   */
  public function getLockedUnitIdsForUser(int $userId, array $unitIds): array
  {
    $unitIds = array_values(array_unique(array_filter(
      array_map(static fn(mixed $value): int => (int)$value, $unitIds),
      static fn(int $value): bool => $value > 0
    )));
    if ($unitIds === []) {
      return [];
    }

    $activeRun = $this->runRepository->getActiveRunForUser($userId);
    if ($activeRun === null) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `unit_instance_id`
      FROM `run_unit_state`
      WHERE `run_id` = ? AND `unit_instance_id` IN ($placeholders)
    ");
    $stmt->execute(array_merge([(int)$activeRun['run_id']], $unitIds));

    return array_map(
      static fn(mixed $value): int => (int)$value,
      $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
  }
}
