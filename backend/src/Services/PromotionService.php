<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class PromotionService
{
  private UnitLoadoutService $unitLoadoutService;

  public function __construct(
    private readonly PDO $pdo,
    ?UnitLoadoutService $unitLoadoutService = null,
  ) {
    $this->unitLoadoutService = $unitLoadoutService ?? new UnitLoadoutService($pdo);
  }

  /**
   * @return array{id:int,unit_type_id:int,tier:int,level:int,max_level:int,slug:string,name:string}|null
   */
  public function getPromotionUnitSnapshot(int $userId, int $unitId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT ui.`id`, ui.`unit_type_id`, ui.`tier`, ui.`level`, ut.`max_level`, ut.`slug`, ut.`name`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`id` = ? AND ui.`user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return null;
    }

    return [
      'id' => (int)$row['id'],
      'unit_type_id' => (int)$row['unit_type_id'],
      'tier' => (int)$row['tier'],
      'level' => (int)$row['level'],
      'max_level' => (int)$row['max_level'],
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
    ];
  }

  /**
   * @param array{id:int,unit_type_id:int,tier:int,level:int,max_level:int,slug:string,name:string} $unit
   * @return list<array{
   *   branch_unit_type_id:string,
   *   branch_unit_type_slug:string,
   *   branch_unit_type_name:string,
   *   target_unit_type_id:string,
   *   target_unit_type_slug:string,
   *   target_unit_type_name:string,
   *   target_tier:int,
   *   mode:string
   * }>
   */
  public function listPromotionOptions(int $userId, array $unit): array
  {
    if ($unit['level'] < $unit['max_level']) {
      return [];
    }

    $currentTier = (int)$unit['tier'];
    $targetTier = $currentTier + 1;
    $currentTierTypes = $this->loadUnitTypesForTier($currentTier);
    $targetTierTypes = $this->loadUnitTypesForTier($targetTier);
    if (count($targetTierTypes) === 0) {
      return [];
    }

    $allowedBranchIds = $currentTier === 1
      ? array_map(static fn(array $row): int => (int)$row['id'], $currentTierTypes)
      : $this->loadPromotionBranchHistoryIds((int)$unit['id'], $currentTier);
    if (!in_array((int)$unit['unit_type_id'], $allowedBranchIds, true)) {
      $allowedBranchIds[] = (int)$unit['unit_type_id'];
    }

    $currentByStem = [];
    foreach ($currentTierTypes as $type) {
      $currentByStem[$this->unitTypeStem((string)$type['slug'])] = $type;
    }

    $options = [];
    foreach ($targetTierTypes as $targetType) {
      $stem = $this->unitTypeStem((string)$targetType['slug']);
      $branchType = $currentByStem[$stem] ?? null;
      if (!is_array($branchType)) {
        continue;
      }
      if (!in_array((int)$branchType['id'], $allowedBranchIds, true)) {
        continue;
      }

      $options[] = [
        'branch_unit_type_id' => (string)$branchType['id'],
        'branch_unit_type_slug' => (string)$branchType['slug'],
        'branch_unit_type_name' => (string)$branchType['name'],
        'target_unit_type_id' => (string)$targetType['id'],
        'target_unit_type_slug' => (string)$targetType['slug'],
        'target_unit_type_name' => (string)$targetType['name'],
        'target_tier' => $targetTier,
        'mode' => (int)$branchType['id'] === (int)$unit['unit_type_id'] ? 'chain' : 'sideways',
      ];
    }

    usort($options, static function (array $a, array $b): int {
      if ($a['mode'] !== $b['mode']) {
        return $a['mode'] === 'chain' ? -1 : 1;
      }
      return strcmp((string)$a['branch_unit_type_name'], (string)$b['branch_unit_type_name']);
    });

    return $options;
  }

  /**
   * @param list<int> $secondaryIds
   * @return array{
   *   branch_unit_type_id:string,
   *   branch_unit_type_slug:string,
   *   branch_unit_type_name:string,
   *   target_unit_type_id:string,
   *   target_unit_type_slug:string,
   *   target_unit_type_name:string,
   *   target_tier:int,
   *   mode:string
   * }
   */
  public function promoteUnit(int $userId, int $primaryId, array $secondaryIds, int $destinationUnitTypeId = 0): array
  {
    $allIds = [$primaryId, ...$secondaryIds];
    $units = $this->loadPromotionUnitsForUpdate($userId, $allIds);
    if (count($units) !== 3) {
      throw new RuntimeException('promotion_requirements_not_met');
    }

    $first = reset($units);
    foreach ($units as $unit) {
      if ((int)$unit['unit_type_id'] !== (int)$first['unit_type_id'] || (int)$unit['tier'] !== (int)$first['tier']) {
        throw new RuntimeException('promotion_requirements_not_met');
      }
      if ((int)$unit['level'] < (int)$unit['max_level']) {
        throw new RuntimeException('promotion_requirements_not_met');
      }
    }

    $primaryUnit = $this->getPromotionUnitSnapshot($userId, $primaryId);
    if (!is_array($primaryUnit)) {
      throw new RuntimeException('promotion_requirements_not_met');
    }

    $promotionTarget = $this->resolvePromotionTargetOption(
      $this->listPromotionOptions($userId, $primaryUnit),
      $destinationUnitTypeId
    );
    if (!is_array($promotionTarget)) {
      throw new RuntimeException('promotion_requirements_not_met');
    }

    // Persist the unit's current authored package before swapping types so
    // promotions preserve cumulative ability history even for older rows that
    // never had their unlocked catalog fully backfilled.
    $this->unitLoadoutService->ensureUnlockedCatalogForUnit($primaryId);

    $update = $this->pdo->prepare('
      UPDATE `unit_instances`
      SET `unit_type_id` = ?, `tier` = ?, `level` = 1, `xp` = 0
      WHERE `id` = ? AND `user_id` = ?
    ');
    $update->execute([
      (int)$promotionTarget['target_unit_type_id'],
      (int)$promotionTarget['target_tier'],
      $primaryId,
      $userId,
    ]);
    $this->unitLoadoutService->initializeUnit($primaryId, (int)$promotionTarget['target_unit_type_id']);

    $this->detachAndDeleteUnits($userId, $secondaryIds);

    $promo = $this->pdo->prepare('
      INSERT INTO `unit_promotions` (`user_id`, `result_unit_instance_id`, `consumed_units_json`, `consumed_region_item_id`)
      VALUES (?, ?, ?, NULL)
    ');
    $promo->execute([$userId, $primaryId, json_encode(array_map('strval', $secondaryIds), JSON_UNESCAPED_UNICODE)]);

    return $promotionTarget;
  }

  /**
   * @param list<array{
   *   branch_unit_type_id:string,
   *   branch_unit_type_slug:string,
   *   branch_unit_type_name:string,
   *   target_unit_type_id:string,
   *   target_unit_type_slug:string,
   *   target_unit_type_name:string,
   *   target_tier:int,
   *   mode:string
   * }> $options
   * @return array{
   *   branch_unit_type_id:string,
   *   branch_unit_type_slug:string,
   *   branch_unit_type_name:string,
   *   target_unit_type_id:string,
   *   target_unit_type_slug:string,
   *   target_unit_type_name:string,
   *   target_tier:int,
   *   mode:string
   * }|null
   */
  private function resolvePromotionTargetOption(array $options, int $requestedTargetUnitTypeId): ?array
  {
    if ($requestedTargetUnitTypeId > 0) {
      foreach ($options as $option) {
        if ((int)$option['target_unit_type_id'] === $requestedTargetUnitTypeId) {
          return $option;
        }
      }
      return null;
    }

    foreach ($options as $option) {
      if ((string)$option['mode'] === 'chain') {
        return $option;
      }
    }

    return $options[0] ?? null;
  }

  /**
   * @param list<int> $unitIds
   * @return list<array<string,mixed>>
   */
  private function loadPromotionUnitsForUpdate(int $userId, array $unitIds): array
  {
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], $unitIds);
    $stmt = $this->pdo->prepare("
      SELECT ui.`id`, ui.`unit_type_id`, ui.`tier`, ui.`level`, ut.`max_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
      FOR UPDATE
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byId = [];
    foreach ($rows as $row) {
      $byId[(int)$row['id']] = $row;
    }

    $ordered = [];
    foreach ($unitIds as $unitId) {
      if (isset($byId[$unitId])) {
        $ordered[] = $byId[$unitId];
      }
    }

    return $ordered;
  }

  /**
   * @return list<array{id:int,slug:string,name:string}>
   */
  private function loadUnitTypesForTier(int $tier): array
  {
    $stmt = $this->pdo->query('SELECT `id`, `slug`, `name` FROM `unit_types` ORDER BY `id` ASC');
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $slug = (string)($row['slug'] ?? '');
      if ($this->tierFromUnitTypeSlug($slug) !== $tier) {
        continue;
      }
      $out[] = [
        'id' => (int)$row['id'],
        'slug' => $slug,
        'name' => (string)($row['name'] ?? $slug),
      ];
    }

    return $out;
  }

  /**
   * @return list<int>
   */
  private function loadPromotionBranchHistoryIds(int $unitId, int $tier): array
  {
    $stmt = $this->pdo->prepare('
      SELECT DISTINCT ut.`id`, ut.`slug`
      FROM `unit_instance_unlocked_abilities` uiua
      JOIN `unit_types` ut ON ut.`id` = uiua.`source_unit_type_id`
      WHERE uiua.`unit_instance_id` = ?
      ORDER BY ut.`id` ASC
    ');
    $stmt->execute([$unitId]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $typeId = (int)($row['id'] ?? 0);
      $slug = (string)($row['slug'] ?? '');
      if ($typeId <= 0 || $this->tierFromUnitTypeSlug($slug) !== $tier) {
        continue;
      }
      $ids[] = $typeId;
    }

    return array_values(array_unique($ids));
  }

  private function tierFromUnitTypeSlug(string $slug): int
  {
    if (preg_match('/_t(\d+)$/', trim($slug), $matches) === 1) {
      return (int)($matches[1] ?? 0);
    }

    return 0;
  }

  private function unitTypeStem(string $slug): string
  {
    return preg_replace('/_t\d+$/', '', trim($slug)) ?? trim($slug);
  }

  /**
   * @param list<int> $unitIds
   */
  private function detachAndDeleteUnits(int $userId, array $unitIds): void
  {
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));

    $stmt = $this->pdo->prepare("DELETE FROM `unit_dice` WHERE `unit_instance_id` IN ($placeholders)");
    $stmt->execute($unitIds);

    $stmt = $this->pdo->prepare("
      UPDATE `team_formation`
      SET `unit_instance_id` = NULL
      WHERE `unit_instance_id` IN ($placeholders)
        AND `team_id` IN (SELECT `id` FROM `teams` WHERE `user_id` = ?)
    ");
    $stmt->execute(array_merge($unitIds, [$userId]));

    $stmt = $this->pdo->prepare("
      DELETE FROM `team_units`
      WHERE `unit_instance_id` IN ($placeholders)
        AND `team_id` IN (SELECT `id` FROM `teams` WHERE `user_id` = ?)
    ");
    $stmt->execute(array_merge($unitIds, [$userId]));

    $stmt = $this->pdo->prepare("DELETE FROM `run_unit_state` WHERE `unit_instance_id` IN ($placeholders)");
    $stmt->execute($unitIds);

    $stmt = $this->pdo->prepare("
      DELETE FROM `unit_instances`
      WHERE `user_id` = ? AND `id` IN ($placeholders)
    ");
    $stmt->execute(array_merge([$userId], $unitIds));
  }
}
