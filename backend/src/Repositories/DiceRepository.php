<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\DiceRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;
use PDOException;
use RuntimeException;

final class DiceRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Static catalog for dice bases.
   * IDs returned as strings (JS safe).
   *
   * @return array<int, array{id:string,sides:int,rarity:string,slot_capacity:int}>
   */
  public function listDiceDefinitions(): array
  {
    $stmt = $this->pdo->query('
      SELECT `id`, `sides`, `rarity`, `slot_capacity`
      FROM `dice_definitions`
      ORDER BY `id` ASC
    ');

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'id' => (string)$r['id'],
      'sides' => (int)$r['sides'],
      'rarity' => (string)$r['rarity'],
      'slot_capacity' => (int)$r['slot_capacity'],
    ], $rows);
  }

  /**
   * Returns all owned dice instances with attached affix rolls and base definition data.
   *
   * Shape is intended to feed GET /api/v1/profile.
   *
   * @return array<int, array{
   *   id:string,
   *   dice_definition_id:string,
   *   display_name:?string,
   *   rarity:string,
   *   sides:int,
   *   slot_capacity:int,
   *   affixes: array<int, array{affix_definition_id:string,value:float}>
   * }>
   */
  public function getDiceWithAffixesForUser(int $userId): array
  {
    // 1) Dice instances + base definitions
    $stmt = $this->pdo->prepare('
      SELECT
        di.`id`,
        di.`dice_definition_id`,
        di.`display_name`,
        dd.`rarity`,
        dd.`sides`,
        dd.`slot_capacity`
      FROM `dice_instances` di
      JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
      WHERE di.`user_id` = ?
      ORDER BY di.`id` ASC
    ');
    $stmt->execute([$userId]);
    $diceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$diceRows) {
      return [];
    }

    $diceIds = array_map(static fn(array $r): int => (int)$r['id'], $diceRows);

    // 2) Affixes for those instances
    $affixesByDice = $this->getAffixesForDiceInstanceIds($diceIds);

    // 3) Merge
    $out = [];
    foreach ($diceRows as $d) {
      $did = (string)$d['id'];
      $out[] = [
        'id' => $did,
        'dice_definition_id' => (string)$d['dice_definition_id'],
        'display_name' => $d['display_name'] !== null ? (string)$d['display_name'] : null,
        'rarity' => (string)$d['rarity'],
        'sides' => (int)$d['sides'],
        'slot_capacity' => (int)$d['slot_capacity'],
        'affixes' => $affixesByDice[$did] ?? [],
      ];
    }

    return $out;
  }

  /**
   * @return array<string, array<int, array{affix_definition_id:string,value:float}>>
   */
  public function getAffixesForDiceInstanceIds(array $diceInstanceIds): array
  {
    if (count($diceInstanceIds) === 0) {
      return [];
    }

    // Defensive: ensure ints
    $diceInstanceIds = array_values(array_map(static fn($v): int => (int)$v, $diceInstanceIds));

    $placeholders = implode(',', array_fill(0, count($diceInstanceIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `dice_instance_id`, `affix_definition_id`, `value`
      FROM `dice_instance_affixes`
      WHERE `dice_instance_id` IN ($placeholders)
      ORDER BY `dice_instance_id` ASC
    ");
    $stmt->execute($diceInstanceIds);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byDice = [];
    foreach ($rows as $r) {
      $dId = (string)$r['dice_instance_id'];
      if (!isset($byDice[$dId])) {
        $byDice[$dId] = [];
      }
      $byDice[$dId][] = [
        'affix_definition_id' => (string)$r['affix_definition_id'],
        'value' => (float)$r['value'],
      ];
    }

    return $byDice;
  }

  /**
   * Returns equipped dice instance IDs (strings) in slot order.
   *
   * @return array<int, string>
   */
  public function getEquippedDiceIdsForUnit(int $unitInstanceId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `dice_instance_id`
      FROM `unit_dice`
      WHERE `unit_instance_id` = ?
      ORDER BY `slot_index` ASC
    ');
    $stmt->execute([$unitInstanceId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): string => (string)$r['dice_instance_id'], $rows);
  }

  /**
   * Equip a dice instance to a unit.
   *
   * Notes:
   * - Enforces ownership: both unit and dice must belong to user.
   * - Enforces "a die can only be equipped to one unit at a time" at the application layer.
   * - Idempotent: if already equipped to the same unit, returns current equipped list.
   *
   * @return array<int, string> equipped dice IDs in slot order
   */
  public function equipDiceToUnit(int $userId, int $unitInstanceId, int $diceInstanceId): array
  {
    try {
      $this->pdo->beginTransaction();

      $this->assertUnitOwnedByUserForUpdate($userId, $unitInstanceId);
      $this->assertDiceOwnedByUserForUpdate($userId, $diceInstanceId);

      // If already equipped to this unit, treat as idempotent.
      $stmt = $this->pdo->prepare('
        SELECT 1
        FROM `unit_dice`
        WHERE `unit_instance_id` = ? AND `dice_instance_id` = ?
        LIMIT 1
        FOR UPDATE
      ');
      $stmt->execute([$unitInstanceId, $diceInstanceId]);
      if ((bool)$stmt->fetchColumn()) {
        $this->pdo->commit();
        return $this->getEquippedDiceIdsForUnit($unitInstanceId);
      }

      // Prevent a die being equipped to any other unit.
      $stmt = $this->pdo->prepare('
        SELECT `unit_instance_id`
        FROM `unit_dice`
        WHERE `dice_instance_id` = ?
        LIMIT 1
        FOR UPDATE
      ');
      $stmt->execute([$diceInstanceId]);
      $alreadyOnUnit = $stmt->fetchColumn();
      if ($alreadyOnUnit) {
        $this->pdo->rollBack();
        throw new RuntimeException('Dice is already equipped to another unit.');
      }

      $slotIndex = $this->nextAvailableSlotIndexForUnitForUpdate($unitInstanceId);

      $stmt = $this->pdo->prepare('
        INSERT INTO `unit_dice` (`unit_instance_id`, `dice_instance_id`, `slot_index`)
        VALUES (?, ?, ?)
      ');
      $stmt->execute([$unitInstanceId, $diceInstanceId, $slotIndex]);

      $this->pdo->commit();

      return $this->getEquippedDiceIdsForUnit($unitInstanceId);
    } catch (\Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Unequip a dice instance from a unit.
   *
   * Idempotent: if not equipped, returns current equipped list.
   *
   * @return array<int, string> equipped dice IDs in slot order
   */
  public function unequipDiceFromUnit(int $userId, int $unitInstanceId, int $diceInstanceId): array
  {
    try {
      $this->pdo->beginTransaction();

      $this->assertUnitOwnedByUserForUpdate($userId, $unitInstanceId);

      // If not equipped, treat as idempotent.
      $stmt = $this->pdo->prepare('
        SELECT 1
        FROM `unit_dice`
        WHERE `unit_instance_id` = ? AND `dice_instance_id` = ?
        LIMIT 1
        FOR UPDATE
      ');
      $stmt->execute([$unitInstanceId, $diceInstanceId]);
      if (!(bool)$stmt->fetchColumn()) {
        $this->pdo->commit();
        return $this->getEquippedDiceIdsForUnit($unitInstanceId);
      }

      $stmt = $this->pdo->prepare('
        DELETE FROM `unit_dice`
        WHERE `unit_instance_id` = ? AND `dice_instance_id` = ?
      ');
      $stmt->execute([$unitInstanceId, $diceInstanceId]);

      $this->pdo->commit();

      return $this->getEquippedDiceIdsForUnit($unitInstanceId);
    } catch (\Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  // -----------------------------
  // Internals
  // -----------------------------

  private function assertUnitOwnedByUserForUpdate(int $userId, int $unitInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `unit_instances`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$unitInstanceId, $userId]);
    $ok = $stmt->fetchColumn();

    if (!$ok) {
      throw new RuntimeException('Unit not found or not owned by user.');
    }
  }

  private function assertDiceOwnedByUserForUpdate(int $userId, int $diceInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `dice_instances`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$diceInstanceId, $userId]);
    $ok = $stmt->fetchColumn();

    if (!$ok) {
      throw new RuntimeException('Dice not found or not owned by user.');
    }
  }

  /**
   * Finds the smallest non-negative integer not used as a slot_index for this unit.
   * Locks the unit_dice rows for the unit (FOR UPDATE) to avoid concurrent slot collisions.
   */
  private function nextAvailableSlotIndexForUnitForUpdate(int $unitInstanceId): int
  {
    $stmt = $this->pdo->prepare('
      SELECT `slot_index`
      FROM `unit_dice`
      WHERE `unit_instance_id` = ?
      ORDER BY `slot_index` ASC
      FOR UPDATE
    ');
    $stmt->execute([$unitInstanceId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $used = [];
    foreach ($rows as $r) {
      $used[(int)$r['slot_index']] = true;
    }

    $i = 0;
    while (isset($used[$i])) {
      $i++;
    }
    return $i;
  }
}
