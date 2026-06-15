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

    $currentInstanceTier = (int)$unit['tier'];
    $targetInstanceTier = $currentInstanceTier + 1;
    $currentAuthoredTier = $this->tierFromUnitTypeSlug((string)$unit['slug']);
    $currentFamily = $this->unitTypeStem((string)$unit['slug']);
    $familyProgress = $this->loadPromotionFamilyProgress((int)$unit['id']);
    $familyProgress[$currentFamily] = max($familyProgress[$currentFamily] ?? 0, $currentAuthoredTier);

    $eligibleFamilies = array_fill_keys(array_keys($familyProgress), true);
    foreach ($this->loadUnlockedFamilySlugs($userId) as $familySlug) {
      $eligibleFamilies[$familySlug] = true;
    }

    $options = [];
    foreach (array_keys($eligibleFamilies) as $familySlug) {
      $currentFamilyTier = $familyProgress[$familySlug] ?? 0;
      $nextAuthoredTier = $currentFamilyTier + 1;
      $targetType = $this->findTypeByFamilyAndTier($familySlug, $nextAuthoredTier);
      if (!is_array($targetType)) {
        continue;
      }
      $branchType = $currentFamilyTier > 0
        ? $this->findTypeByFamilyAndTier($familySlug, $currentFamilyTier)
        : $targetType;
      if (!is_array($branchType)) {
        $branchType = $targetType;
      }

      $targetTypeId = (int)($targetType['id'] ?? 0);
      if ($targetTypeId <= 0) {
        continue;
      }

      $mode = $familySlug === $currentFamily && $nextAuthoredTier === ($currentAuthoredTier + 1)
        ? 'chain'
        : 'sideways';

      $options[] = [
        'branch_unit_type_id' => (string)$branchType['id'],
        'branch_unit_type_slug' => (string)$branchType['slug'],
        'branch_unit_type_name' => (string)$branchType['name'],
        'target_unit_type_id' => (string)$targetTypeId,
        'target_unit_type_slug' => (string)$targetType['slug'],
        'target_unit_type_name' => (string)$targetType['name'],
        'target_tier' => $targetInstanceTier,
        'mode' => $mode,
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
   * @return array{id:int,slug:string,name:string}|null
   */
  private function findTypeByFamilyAndTier(string $familySlug, int $authoredTier): ?array
  {
    if ($familySlug === '' || $authoredTier <= 0) {
      return null;
    }

    $targetSlug = "{$familySlug}_t{$authoredTier}";
    $stmt = $this->pdo->prepare('
      SELECT `id`, `slug`, `name`
      FROM `unit_types`
      WHERE `slug` = ?
      LIMIT 1
    ');
    $stmt->execute([$targetSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
      return [
        'id' => (int)$row['id'],
        'slug' => (string)$row['slug'],
        'name' => (string)$row['name'],
      ];
    }

    return null;
  }

  /**
   * @return array<string,int>
   */
  private function loadPromotionFamilyProgress(int $unitId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT DISTINCT ut.`slug`
      FROM `unit_instance_unlocked_abilities` uiua
      JOIN `unit_types` ut ON ut.`id` = uiua.`source_unit_type_id`
      WHERE uiua.`unit_instance_id` = ?
      ORDER BY ut.`slug` ASC
    ');
    $stmt->execute([$unitId]);

    $progress = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $slug = trim((string)($row['slug'] ?? ''));
      $familySlug = $this->unitTypeStem($slug);
      $tier = $this->tierFromUnitTypeSlug($slug);
      if ($familySlug === '' || $tier <= 0) {
        continue;
      }

      $progress[$familySlug] = max($progress[$familySlug] ?? 0, $tier);
    }

    return $progress;
  }

  /**
   * @return list<string>
   */
  private function loadUnlockedFamilySlugs(int $userId): array
  {
    $unlockService = new UserUnlockService($this->pdo);
    $families = [];
    foreach ($unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE) as $slug) {
      $familySlug = $this->unitTypeStem((string)$slug);
      if ($familySlug !== '') {
        $families[$familySlug] = true;
      }
    }

    return array_keys($families);
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
