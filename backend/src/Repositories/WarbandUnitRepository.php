<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;

final class WarbandUnitRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array<int,array<string,mixed>> */
  public function listActiveForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`
      FROM `unit_instances`
      WHERE `user_id` = ? AND `lifecycle_status` = \'active\'
      ORDER BY `id` ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @param array<int,int> $unitIds
   *  @return array<int,array<string,mixed>>
   */
  public function listActiveByIdsForUser(int $userId, array $unitIds, bool $forUpdate = false): array
  {
    $unitIds = array_values(array_unique($unitIds));
    if ($unitIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `id`, `user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`
      FROM `unit_instances`
      WHERE `user_id` = ? AND `lifecycle_status` = 'active' AND `id` IN ($placeholders)
      ORDER BY `id` ASC" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute(array_merge([$userId], $unitIds));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<string,mixed>|null */
  public function getActiveForUser(int $userId, int $unitId, bool $forUpdate = false): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`
      FROM `unit_instances`
      WHERE `id` = ? AND `user_id` = ? AND `lifecycle_status` = \'active\'
      LIMIT 1
    ' . ($forUpdate ? 'FOR UPDATE' : ''));
    $stmt->execute([$unitId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  /** @return array<int,array<string,mixed>> */
  public function listPromotions(int $unitId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `from_unit_type_id`, `to_unit_type_id`, `promoted_at`
      FROM `unit_promotions`
      WHERE `unit_id` = ?
      ORDER BY `promoted_at` ASC, `id` ASC
    ' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listOwnedAbilities(int $unitId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `ability_id`, `unlocked_at`
      FROM `unit_abilities`
      WHERE `unit_id` = ?
      ORDER BY `unlocked_at` ASC, `ability_id` ASC
    ' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listLoadout(int $unitId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `ability_id`, `equip_order`
      FROM `unit_ability_loadout`
      WHERE `unit_id` = ?
      ORDER BY `equip_order` ASC
    ' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listDiceBindings(int $unitId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        uad.`ability_id`, uad.`slot_index`, uad.`dice_instance_id`,
        di.`user_id` AS `die_user_id`, di.`size`, di.`profile_id`, di.`lifecycle_status` AS `die_lifecycle_status`
      FROM `unit_ability_dice` uad
      JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`
      WHERE uad.`unit_id` = ?
      ORDER BY uad.`ability_id` ASC, uad.`slot_index` ASC
    ' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function updateDisplayName(int $userId, int $unitId, string $name): void
  {
    $stmt = $this->pdo->prepare('UPDATE `unit_instances` SET `display_name` = ? WHERE `id` = ? AND `user_id` = ? AND `lifecycle_status` = \'active\'');
    $stmt->execute([$name, $unitId, $userId]);
    if ($stmt->rowCount() !== 1) {
      throw new \RuntimeException('Required unit is unavailable.');
    }
  }

  /**
   * @param list<array{ability_id:string,dice_instance_ids:list<int>}> $abilities
   */
  public function replaceLoadout(int $unitId, array $abilities): void
  {
    $this->pdo->prepare('DELETE FROM `unit_ability_dice` WHERE `unit_id` = ?')->execute([$unitId]);
    $this->pdo->prepare('DELETE FROM `unit_ability_loadout` WHERE `unit_id` = ?')->execute([$unitId]);

    $loadout = $this->pdo->prepare('INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, ?, ?)');
    $binding = $this->pdo->prepare('INSERT INTO `unit_ability_dice` (`unit_id`, `ability_id`, `slot_index`, `dice_instance_id`) VALUES (?, ?, ?, ?)');
    foreach ($abilities as $equipOrder => $ability) {
      $loadout->execute([$unitId, $ability['ability_id'], $equipOrder]);
      foreach ($ability['dice_instance_ids'] as $slotIndex => $dieId) {
        $binding->execute([$unitId, $ability['ability_id'], $slotIndex, $dieId]);
      }
    }
  }
}
