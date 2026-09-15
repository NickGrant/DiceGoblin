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

  /** @return array{units:int,dice:int,squads:int,active_squad_id:?int,active_members:int,active_runs:int,player_revision:int} */
  public function seedState(int $userId): array
  {
    $stmt = $this->pdo->prepare('SELECT
      (SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?) AS `units`,
      (SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?) AS `dice`,
      (SELECT COUNT(*) FROM `squads` WHERE `user_id` = ?) AS `squads`,
      us.`active_squad_id`, us.`player_revision`,
      (SELECT COUNT(*) FROM `squad_units` su JOIN `squads` s ON s.`id` = su.`squad_id`
        WHERE s.`id` = us.`active_squad_id` AND s.`user_id` = ?) AS `active_members`,
      (SELECT COUNT(*) FROM `runs` WHERE `user_id` = ? AND `status` = \'active\') AS `active_runs`
      FROM `user_state` us WHERE us.`user_id` = ?');
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Fixture user state is unavailable.');
    return [
      'units' => (int)$row['units'], 'dice' => (int)$row['dice'], 'squads' => (int)$row['squads'],
      'active_squad_id' => $row['active_squad_id'] === null ? null : (int)$row['active_squad_id'],
      'active_members' => (int)$row['active_members'], 'active_runs' => (int)$row['active_runs'],
      'player_revision' => (int)$row['player_revision'],
    ];
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
