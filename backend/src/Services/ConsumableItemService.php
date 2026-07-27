<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\EnergyRepository;
use PDO;
use RuntimeException;
use Throwable;

final class ConsumableItemService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly ItemInventoryService $itemInventoryService,
    private readonly UnitProgressionService $unitProgressionService = new UnitProgressionService(),
  ) {
  }

  /**
   * @return array{
   *   run_id:string,
   *   unit_instance_id:string,
   *   item:array{item_slug:string,quantity:int,spent_quantity:int},
   *   healing:array{amount:int,hp_before:int,hp_after:int,max_hp:int,is_defeated:bool}
   * }
   */
  public function healRunUnit(int $userId, int $runId, int $unitInstanceId, string $itemSlug): array
  {
    $itemSlug = trim($itemSlug);
    if ($itemSlug === '') {
      throw new RuntimeException('item_slug_required');
    }

    try {
      $this->pdo->beginTransaction();

      $run = $this->activeRunForUpdate($userId, $runId);
      if ($run === null) {
        throw new RuntimeException('run_not_active');
      }

      if ($this->hasUnresolvedBattle($userId, $runId)) {
        throw new RuntimeException('combat_resolution_active');
      }

      $item = $this->healingItemForUpdate($itemSlug);
      if ($item === null) {
        throw new RuntimeException('item_not_healing_consumable');
      }

      $unit = $this->runUnitForUpdate($userId, $runId, $unitInstanceId);
      if ($unit === null) {
        throw new RuntimeException('unit_not_in_run');
      }

      $maxHp = $this->maxHpForUnit($unit);
      $hpBefore = max(0, min($maxHp, (int)$unit['current_hp']));
      if ($hpBefore >= $maxHp) {
        throw new RuntimeException('unit_not_wounded');
      }

      $healAmount = max(1, (int)($item['heal_amount'] ?? 0));
      $hpAfter = min($maxHp, $hpBefore + $healAmount);
      $spent = $this->itemInventoryService->spendBySlugForUpdate($userId, $itemSlug, 1);

      $update = $this->pdo->prepare('
        UPDATE `run_unit_state`
        SET `current_hp` = ?, `is_defeated` = ?
        WHERE `run_id` = ? AND `unit_instance_id` = ?
      ');
      $update->execute([$hpAfter, $hpAfter <= 0 ? 1 : 0, $runId, $unitInstanceId]);

      $this->pdo->commit();

      return [
        'run_id' => (string)$runId,
        'unit_instance_id' => (string)$unitInstanceId,
        'item' => $spent,
        'healing' => [
          'amount' => $hpAfter - $hpBefore,
          'hp_before' => $hpBefore,
          'hp_after' => $hpAfter,
          'max_hp' => $maxHp,
          'is_defeated' => $hpAfter <= 0,
        ],
      ];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array{
   *   item:array{item_slug:string,quantity:int,spent_quantity:int},
   *   energy:array{amount:int,current_before:int,current_after:int,max:int}
   * }
   */
  public function restoreEnergy(int $userId, string $itemSlug): array
  {
    $itemSlug = trim($itemSlug);
    if ($itemSlug === '') {
      throw new RuntimeException('item_slug_required');
    }

    try {
      $this->pdo->beginTransaction();

      $energyRepo = new EnergyRepository($this->pdo);
      $energyRepo->ensureEnergyState($userId);
      $energy = $energyRepo->getEnergyStateForUpdate($userId);
      if (!is_array($energy)) {
        throw new RuntimeException('energy_state_unavailable');
      }

      $effectiveMax = UserUnlockService::resolveEnergyMaxFromFeatureUnlocks(
        (new UserUnlockService($this->pdo))->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE)
      );
      $current = min(max(0, (int)$energy['energy_current']), $effectiveMax);
      if ($current >= $effectiveMax) {
        throw new RuntimeException('energy_full');
      }

      $item = $this->energyItemForUpdate($itemSlug);
      if ($item === null) {
        throw new RuntimeException('item_not_energy_consumable');
      }

      $restoreAmount = max(1, (int)($item['restore_amount'] ?? 0));
      $nextCurrent = min($effectiveMax, $current + $restoreAmount);
      $spent = $this->itemInventoryService->spendBySlugForUpdate($userId, $itemSlug, 1);

      $stmt = $this->pdo->prepare('
        UPDATE `energy_state`
        SET `energy_max` = ?, `energy_current` = ?, `last_regen_at` = UTC_TIMESTAMP()
        WHERE `user_id` = ?
      ');
      $stmt->execute([$effectiveMax, $nextCurrent, $userId]);

      $this->pdo->commit();

      return [
        'item' => $spent,
        'energy' => [
          'amount' => $nextCurrent - $current,
          'current_before' => $current,
          'current_after' => $nextCurrent,
          'max' => $effectiveMax,
        ],
      ];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array<string,mixed>|null
   */
  private function activeRunForUpdate(int $userId, int $runId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `status`
      FROM `region_runs`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$runId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)$row['status'] !== 'active') {
      return null;
    }
    return $row;
  }

  private function hasUnresolvedBattle(int $userId, int $runId): bool
  {
    $stmt = $this->pdo->prepare("
      SELECT COUNT(*)
      FROM `battles`
      WHERE `user_id` = ?
        AND `run_id` = ?
        AND `status` NOT IN ('completed', 'claimed')
    ");
    $stmt->execute([$userId, $runId]);
    return (int)($stmt->fetchColumn() ?: 0) > 0;
  }

  /**
   * @return array{heal_amount:int}|null
   */
  private function healingItemForUpdate(string $itemSlug): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `category`, `is_spendable`, `meta_json`
      FROM `items`
      WHERE `slug` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$itemSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)$row['category'] !== 'consumable' || (int)$row['is_spendable'] !== 1) {
      return null;
    }

    $meta = $this->decodeJsonObject($row['meta_json'] ?? null);
    $effect = is_array($meta['effect'] ?? null) ? $meta['effect'] : [];
    if ((string)($effect['type'] ?? '') !== 'heal_run_unit_hp') {
      return null;
    }

    $amount = (int)($effect['amount'] ?? 0);
    if ($amount <= 0) {
      return null;
    }

    return ['heal_amount' => $amount];
  }

  /**
   * @return array{restore_amount:int}|null
   */
  private function energyItemForUpdate(string $itemSlug): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `category`, `is_spendable`, `meta_json`
      FROM `items`
      WHERE `slug` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$itemSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)$row['category'] !== 'consumable' || (int)$row['is_spendable'] !== 1) {
      return null;
    }

    $meta = $this->decodeJsonObject($row['meta_json'] ?? null);
    $effect = is_array($meta['effect'] ?? null) ? $meta['effect'] : [];
    if ((string)($effect['type'] ?? '') !== 'restore_energy') {
      return null;
    }

    $amount = (int)($effect['amount'] ?? 0);
    if ($amount <= 0) {
      return null;
    }

    return ['restore_amount' => $amount];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function runUnitForUpdate(int $userId, int $runId, int $unitInstanceId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        rus.`current_hp`,
        rus.`is_defeated`,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`max_hp_per_level`,
        sv.`stat_modifiers_json` AS `lineage_stat_modifiers_json`
      FROM `run_unit_state` rus
      JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      LEFT JOIN `splice_variants` sv ON sv.`slug` = ui.`splice_variant_slug`
      WHERE rus.`run_id` = ?
        AND rus.`unit_instance_id` = ?
        AND ui.`user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$runId, $unitInstanceId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  /**
   * @param array<string,mixed> $unit
   */
  private function maxHpForUnit(array $unit): int
  {
    $baseStats = $this->applyStatModifiers(
      $this->decodeJsonObject($unit['base_stats_json'] ?? null),
      $this->decodeJsonObject($unit['lineage_stat_modifiers_json'] ?? null)
    );

    return $this->unitProgressionService->maxHpForLevel(
      $baseStats,
      max(1, (int)($unit['level'] ?? 1)),
      (int)($unit['max_hp_per_level'] ?? 0)
    );
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeJsonObject(mixed $raw): array
  {
    if (is_array($raw)) {
      return $raw;
    }

    if (is_string($raw)) {
      $decoded = json_decode($raw, true);
      return is_array($decoded) ? $decoded : [];
    }

    return [];
  }

  /**
   * @param array<string,mixed> $baseStats
   * @param array<string,mixed> $modifiers
   * @return array<string,mixed>
   */
  private function applyStatModifiers(array $baseStats, array $modifiers): array
  {
    foreach (['attack', 'defense', 'max_hp', 'precision', 'resolve'] as $key) {
      $default = match ($key) {
        'max_hp' => 1,
        'precision', 'resolve' => 5,
        default => 0,
      };
      $baseStats[$key] = max(0, (int)($baseStats[$key] ?? $default) + (int)($modifiers[$key] ?? 0));
    }

    $baseStats['max_hp'] = max(1, (int)($baseStats['max_hp'] ?? 1));
    return $baseStats;
  }
}
