<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use PDO;
use RuntimeException;

final class UnitLoadoutService
{
  private const MAX_EQUIP_BUDGET = 20;

  public function __construct(
    private readonly PDO $pdo,
    private readonly AbilityRegistry $abilityRegistry = new AbilityRegistry(),
  ) {}

  public function ensureStateForUser(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT ui.`id` AS `unit_instance_id`, ui.`unit_type_id`, ut.`slug` AS `unit_type_slug`, ut.`ability_set_json`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $this->initializeUnitFromRow($row);
    }
  }

  public function initializeUnit(int $unitInstanceId, int $unitTypeId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT ui.`id` AS `unit_instance_id`, ui.`unit_type_id`, ut.`slug` AS `unit_type_slug`, ut.`ability_set_json`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`id` = ? AND ui.`unit_type_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitInstanceId, $unitTypeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      throw new RuntimeException('Unit not found for loadout initialization.');
    }

    $this->initializeUnitFromRow($row);
  }

  /**
   * @return list<array{ability_id:string,slot_index:int}>
   */
  public function listDefaultAbilityDiceSlotsForUnitType(int $unitTypeId): array
  {
    $stmt = $this->pdo->prepare('SELECT `ability_set_json` FROM `unit_types` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$unitTypeId]);
    $abilitySetRaw = $stmt->fetchColumn();
    if ($abilitySetRaw === false) {
      throw new RuntimeException('Unit type not found for default slot planning.');
    }

    $abilitySet = $this->decodeAbilitySet($abilitySetRaw);
    $slots = [];
    foreach ($this->orderedActives($abilitySet) as $abilityId) {
      $slotCount = $this->slotCountForAbility($abilityId);
      for ($slotIndex = 0; $slotIndex < $slotCount; $slotIndex++) {
        $slots[] = [
          'ability_id' => $abilityId,
          'slot_index' => $slotIndex,
        ];
      }
    }

    return $slots;
  }

  public function assignDieToAbilitySlot(int $unitInstanceId, string $abilityId, int $slotIndex, int $diceInstanceId): void
  {
    $context = $this->loadUnitContext($unitInstanceId);
    $this->assertAbilityUnlockedForUnit($unitInstanceId, $abilityId);
    $this->assertAbilitySlotLegal($abilityId, $slotIndex);
    $this->assertDiceOwnedByUser((int)$context['user_id'], $diceInstanceId);
    $this->assertDiceBindingAvailable($unitInstanceId, $abilityId, $slotIndex, $diceInstanceId);

    $stmt = $this->pdo->prepare('
      INSERT INTO `unit_ability_dice` (`unit_instance_id`, `ability_id`, `slot_index`, `dice_instance_id`)
      VALUES (?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `dice_instance_id` = VALUES(`dice_instance_id`),
        `updated_at` = CURRENT_TIMESTAMP
    ');
    $stmt->execute([$unitInstanceId, $abilityId, $slotIndex, $diceInstanceId]);
  }

  /**
   * @param list<string> $abilityIds
   */
  public function replaceEquippedAbilities(int $unitInstanceId, array $abilityIds): void
  {
    $this->loadUnitContext($unitInstanceId);
    $normalizedAbilityIds = [];
    foreach ($abilityIds as $abilityId) {
      $normalizedAbilityId = trim((string)$abilityId);
      if ($normalizedAbilityId === '') {
        continue;
      }

      $this->assertAbilityUnlockedForUnit($unitInstanceId, $normalizedAbilityId);
      $speedCost = $this->speedCostForAbility($normalizedAbilityId);
      if ($speedCost <= 0) {
        throw new RuntimeException('Only active abilities with a positive speed cost can be equipped.');
      }

      $normalizedAbilityIds[] = $normalizedAbilityId;
    }

    $totalBudget = array_sum(array_map(fn(string $abilityId): int => $this->speedCostForAbility($abilityId), $normalizedAbilityIds));
    if ($totalBudget > self::MAX_EQUIP_BUDGET) {
      throw new RuntimeException('Equipped abilities exceed the 20-point speed budget.');
    }

    $startedTransaction = !$this->pdo->inTransaction();
    if ($startedTransaction) {
      $this->pdo->beginTransaction();
    }
    try {
      $deleteStmt = $this->pdo->prepare('DELETE FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ?');
      $deleteStmt->execute([$unitInstanceId]);
      $this->insertEquippedAbilities($unitInstanceId, $normalizedAbilityIds);
      if ($startedTransaction) {
        $this->pdo->commit();
      }
    } catch (\Throwable $e) {
      if ($startedTransaction && $this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @param array<string,mixed> $row
   */
  private function initializeUnitFromRow(array $row): void
  {
    $unitInstanceId = (int)($row['unit_instance_id'] ?? 0);
    $unitTypeId = (int)($row['unit_type_id'] ?? 0);
    $unitTypeSlug = (string)($row['unit_type_slug'] ?? '');
    if ($unitInstanceId <= 0 || $unitTypeId <= 0 || $unitTypeSlug === '') {
      throw new RuntimeException('Invalid unit row for loadout initialization.');
    }

    $abilitySet = $this->decodeAbilitySet($row['ability_set_json'] ?? null);
    $sourceTier = $this->tierFromSlug($unitTypeSlug);
    $catalog = $this->catalogAbilities($abilitySet);

    if (count($catalog) > 0) {
      $insertUnlocked = $this->pdo->prepare('
        INSERT IGNORE INTO `unit_instance_unlocked_abilities` (`unit_instance_id`, `ability_id`, `source_tier`, `source_unit_type_id`)
        VALUES (?, ?, ?, ?)
      ');
      foreach ($catalog as $abilityId) {
        $insertUnlocked->execute([$unitInstanceId, $abilityId, $sourceTier, $unitTypeId]);
      }
    }

    $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ?');
    $countStmt->execute([$unitInstanceId]);
    $existingEquippedCount = (int)$countStmt->fetchColumn();
    if ($existingEquippedCount > 0) {
      return;
    }

    $defaultEquipped = $this->orderedActives($abilitySet);
    if (count($defaultEquipped) === 0) {
      return;
    }

    $this->replaceEquippedAbilities($unitInstanceId, $defaultEquipped);
  }

  /**
   * @param mixed $abilitySetRaw
   * @return array<string,mixed>
   */
  private function decodeAbilitySet(mixed $abilitySetRaw): array
  {
    if (is_string($abilitySetRaw)) {
      $decoded = json_decode($abilitySetRaw, true);
      return is_array($decoded) ? $decoded : [];
    }

    return is_array($abilitySetRaw) ? $abilitySetRaw : [];
  }

  /**
   * @param array<string,mixed> $abilitySet
   * @return list<string>
   */
  private function catalogAbilities(array $abilitySet): array
  {
    $ordered = [];
    foreach (['actives', 'passives'] as $key) {
      $values = $abilitySet[$key] ?? [];
      if (!is_array($values)) {
        continue;
      }
      foreach ($values as $value) {
        $abilityId = trim((string)$value);
        if ($abilityId === '' || in_array($abilityId, $ordered, true)) {
          continue;
        }
        if (!$this->abilityRegistry->has($abilityId)) {
          continue;
        }
        $ordered[] = $abilityId;
      }
    }

    return $ordered;
  }

  /**
   * @param array<string,mixed> $abilitySet
   * @return list<string>
   */
  private function orderedActives(array $abilitySet): array
  {
    $out = [];
    $values = $abilitySet['actives'] ?? [];
    if (!is_array($values)) {
      return $out;
    }

    foreach ($values as $value) {
      $abilityId = trim((string)$value);
      if ($abilityId === '' || in_array($abilityId, $out, true)) {
        continue;
      }
      if (!$this->abilityRegistry->has($abilityId)) {
        continue;
      }
      $out[] = $abilityId;
    }

    return $out;
  }

  private function speedCostForAbility(string $abilityId): int
  {
    if (!$this->abilityRegistry->has($abilityId)) {
      return 0;
    }

    $definition = $this->abilityRegistry->get($abilityId);
    return max(0, (int)($definition->speed ?? 0));
  }

  private function slotCountForAbility(string $abilityId): int
  {
    if (!$this->abilityRegistry->has($abilityId)) {
      return 0;
    }

    $definition = $this->abilityRegistry->get($abilityId);
    return max(0, (int)($definition->diceCost ?? 0));
  }

  /**
   * @param list<string> $abilityIds
   */
  private function insertEquippedAbilities(int $unitInstanceId, array $abilityIds): void
  {
    if (count($abilityIds) === 0) {
      return;
    }

    $insertEquipped = $this->pdo->prepare('
      INSERT INTO `unit_instance_equipped_abilities` (`unit_instance_id`, `ability_id`, `equip_order`, `speed_cost`)
      VALUES (?, ?, ?, ?)
    ');
    foreach ($abilityIds as $index => $abilityId) {
      $speedCost = $this->speedCostForAbility($abilityId);
      $insertEquipped->execute([$unitInstanceId, $abilityId, $index, $speedCost]);
    }
  }

  /**
   * @return array{unit_instance_id:int,user_id:int,unit_type_id:int}
   */
  private function loadUnitContext(int $unitInstanceId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id` AS `unit_instance_id`, `user_id`, `unit_type_id`
      FROM `unit_instances`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitInstanceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      throw new RuntimeException('Unit not found for loadout update.');
    }

    return [
      'unit_instance_id' => (int)$row['unit_instance_id'],
      'user_id' => (int)$row['user_id'],
      'unit_type_id' => (int)$row['unit_type_id'],
    ];
  }

  private function assertAbilityUnlockedForUnit(int $unitInstanceId, string $abilityId): void
  {
    if (!$this->abilityRegistry->has($abilityId)) {
      throw new RuntimeException("Unknown ability '{$abilityId}'.");
    }

    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `unit_instance_unlocked_abilities`
      WHERE `unit_instance_id` = ? AND `ability_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitInstanceId, $abilityId]);
    if (!$stmt->fetchColumn()) {
      throw new RuntimeException("Ability '{$abilityId}' is not unlocked for this unit.");
    }
  }

  private function assertAbilitySlotLegal(string $abilityId, int $slotIndex): void
  {
    if ($slotIndex < 0) {
      throw new RuntimeException('Ability slot index must be zero or greater.');
    }

    $slotCount = $this->slotCountForAbility($abilityId);
    if ($slotCount <= 0) {
      throw new RuntimeException("Ability '{$abilityId}' does not accept dice slot assignments.");
    }

    if ($slotIndex >= $slotCount) {
      throw new RuntimeException("Ability slot index '{$slotIndex}' exceeds the configured slot count.");
    }
  }

  private function assertDiceOwnedByUser(int $userId, int $diceInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `dice_instances`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$diceInstanceId, $userId]);
    if (!$stmt->fetchColumn()) {
      throw new RuntimeException('Dice must be owned by the same user as the target unit.');
    }
  }

  private function assertDiceBindingAvailable(int $unitInstanceId, string $abilityId, int $slotIndex, int $diceInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT `unit_instance_id`, `ability_id`, `slot_index`
      FROM `unit_ability_dice`
      WHERE `dice_instance_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$diceInstanceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return;
    }

    if (
      (int)$row['unit_instance_id'] === $unitInstanceId
      && (string)$row['ability_id'] === $abilityId
      && (int)$row['slot_index'] === $slotIndex
    ) {
      return;
    }

    throw new RuntimeException('Dice is already assigned to another ability slot.');
  }

  private function tierFromSlug(string $unitTypeSlug): int
  {
    if (preg_match('/_t(\d+)$/', $unitTypeSlug, $matches) !== 1) {
      return 1;
    }

    return max(1, (int)$matches[1]);
  }
}
