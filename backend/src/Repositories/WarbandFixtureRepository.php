<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class WarbandFixtureRepository
{
  public function __construct(private readonly PDO $pdo) {}

  public function lockUserState(int $userId): void
  {
    $stmt = $this->pdo->prepare('SELECT 1 FROM `user_state` WHERE `user_id` = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$userId]);
    if (!(bool)$stmt->fetchColumn()) {
      throw new RuntimeException('Fixture user state is unavailable.');
    }
  }

  public function hasCrossOwnerRelationships(int $userId): bool
  {
    $bindings = $this->pdo->prepare('
      SELECT 1
      FROM `unit_ability_dice` uad
      JOIN `unit_instances` ui ON ui.`id` = uad.`unit_id`
      JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`
      WHERE (ui.`user_id` = ? OR di.`user_id` = ?) AND ui.`user_id` <> di.`user_id`
      LIMIT 1
    ');
    $bindings->execute([$userId, $userId]);
    if ((bool)$bindings->fetchColumn()) return true;

    $formations = $this->pdo->prepare('
      SELECT 1
      FROM `squad_units` su
      JOIN `squads` s ON s.`id` = su.`squad_id`
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE (s.`user_id` = ? OR ui.`user_id` = ?) AND s.`user_id` <> ui.`user_id`
      LIMIT 1
    ');
    $formations->execute([$userId, $userId]);
    return (bool)$formations->fetchColumn();
  }

  public function replaceOwnedWarband(int $userId): void
  {
    $this->pdo->prepare('
      UPDATE `user_state`
      SET `active_squad_id` = NULL
      WHERE `user_id` = ?
        AND `active_squad_id` IN (SELECT `id` FROM `squads` WHERE `user_id` = ?)
    ')->execute([$userId, $userId]);
    $this->pdo->prepare('DELETE FROM `squads` WHERE `user_id` = ?')->execute([$userId]);
    $this->pdo->prepare('DELETE FROM `unit_instances` WHERE `user_id` = ?')->execute([$userId]);
    $this->pdo->prepare('DELETE FROM `dice_instances` WHERE `user_id` = ?')->execute([$userId]);
  }

  public function insertUnit(int $userId, string $unitTypeId, string $kinId, string $name, int $level, int $xp): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $unitTypeId, $kinId, $name, $level, $xp]);
    return (int)$this->pdo->lastInsertId();
  }

  public function insertPromotion(int $unitId, string $fromUnitTypeId, string $toUnitTypeId): void
  {
    $this->pdo->prepare('
      INSERT INTO `unit_promotions` (`unit_id`, `from_unit_type_id`, `to_unit_type_id`)
      VALUES (?, ?, ?)
    ')->execute([$unitId, $fromUnitTypeId, $toUnitTypeId]);
  }

  public function insertOwnedAbility(int $unitId, string $abilityId): void
  {
    $this->pdo->prepare('INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)')
      ->execute([$unitId, $abilityId]);
  }

  public function insertLoadoutAbility(int $unitId, string $abilityId, int $equipOrder): void
  {
    $this->pdo->prepare('
      INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`)
      VALUES (?, ?, ?)
    ')->execute([$unitId, $abilityId, $equipOrder]);
  }

  public function insertDie(int $userId, int $size, string $profileId): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `dice_instances` (`user_id`, `size`, `profile_id`)
      VALUES (?, ?, ?)
    ');
    $stmt->execute([$userId, $size, $profileId]);
    return (int)$this->pdo->lastInsertId();
  }

  public function insertDiceBinding(int $unitId, string $abilityId, int $slotIndex, int $dieId): void
  {
    $this->pdo->prepare('
      INSERT INTO `unit_ability_dice` (`unit_id`, `ability_id`, `slot_index`, `dice_instance_id`)
      VALUES (?, ?, ?, ?)
    ')->execute([$unitId, $abilityId, $slotIndex, $dieId]);
  }

  public function insertSquad(int $userId, string $name): int
  {
    $stmt = $this->pdo->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt->execute([$userId, $name]);
    return (int)$this->pdo->lastInsertId();
  }

  public function insertSquadUnit(int $squadId, int $unitId, int $position): void
  {
    $this->pdo->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, ?)')
      ->execute([$squadId, $unitId, $position]);
  }

  public function setActiveSquadAndIncrementRevision(int $userId, int $squadId): int
  {
    $stmt = $this->pdo->prepare('
      UPDATE `user_state`
      SET `active_squad_id` = ?, `player_revision` = `player_revision` + 1
      WHERE `user_id` = ?
    ');
    $stmt->execute([$squadId, $userId]);
    if ($stmt->rowCount() !== 1) {
      throw new RuntimeException('Fixture user state is unavailable.');
    }
    $revision = $this->pdo->prepare('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?');
    $revision->execute([$userId]);
    return (int)$revision->fetchColumn();
  }
}
