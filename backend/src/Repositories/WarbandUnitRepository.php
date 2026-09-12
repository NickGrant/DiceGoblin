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

  /** @return array<string,mixed>|null */
  public function getActiveForUser(int $userId, int $unitId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`
      FROM `unit_instances`
      WHERE `id` = ? AND `user_id` = ? AND `lifecycle_status` = \'active\'
      LIMIT 1
    ');
    $stmt->execute([$unitId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  /** @return array<int,array<string,mixed>> */
  public function listPromotions(int $unitId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `from_unit_type_id`, `to_unit_type_id`, `promoted_at`
      FROM `unit_promotions`
      WHERE `unit_id` = ?
      ORDER BY `promoted_at` ASC, `id` ASC
    ');
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listOwnedAbilities(int $unitId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `ability_id`, `unlocked_at`
      FROM `unit_abilities`
      WHERE `unit_id` = ?
      ORDER BY `unlocked_at` ASC, `ability_id` ASC
    ');
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listLoadout(int $unitId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `ability_id`, `equip_order`
      FROM `unit_ability_loadout`
      WHERE `unit_id` = ?
      ORDER BY `equip_order` ASC
    ');
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listDiceBindings(int $unitId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        uad.`ability_id`, uad.`slot_index`, uad.`dice_instance_id`,
        di.`user_id` AS `die_user_id`, di.`size`, di.`profile_id`, di.`lifecycle_status` AS `die_lifecycle_status`
      FROM `unit_ability_dice` uad
      JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`
      WHERE uad.`unit_id` = ?
      ORDER BY uad.`ability_id` ASC, uad.`slot_index` ASC
    ');
    $stmt->execute([$unitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
