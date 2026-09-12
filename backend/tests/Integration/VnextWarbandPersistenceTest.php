<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;
use PDOException;

final class VnextWarbandPersistenceTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  public function testAuthoredStableIdsAreStoredWithoutSqlCatalogsOrDerivedStats(): void
  {
    $userId = $this->createUser();
    $unitId = $this->createUnit($userId, 'unit_type.saboteur', 'kin.pig', 'Soot');
    $dieId = $this->createDie($userId, 12, 'dice_profile.glass_example');

    $unit = $this->fetchOne('SELECT `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status` FROM `unit_instances` WHERE `id` = ?', [$unitId]);
    $this->assertSame('unit_type.saboteur', $unit['unit_type_id'] ?? null);
    $this->assertSame('kin.pig', $unit['kin_id'] ?? null);
    $this->assertSame('Soot', $unit['display_name'] ?? null);
    $this->assertSame('active', $unit['lifecycle_status'] ?? null);

    $die = $this->fetchOne('SELECT `size`, `profile_id`, `lifecycle_status` FROM `dice_instances` WHERE `id` = ?', [$dieId]);
    $this->assertSame('12', (string)($die['size'] ?? ''));
    $this->assertSame('dice_profile.glass_example', $die['profile_id'] ?? null);
    $this->assertSame('active', $die['lifecycle_status'] ?? null);

    $unitColumns = $this->tableColumns('unit_instances');
    foreach (['hp', 'attack', 'defense', 'precision', 'resolve', 'speed', 'tier', 'locked'] as $rejectedColumn) {
      $this->assertNotContains($rejectedColumn, $unitColumns);
    }

    $dieColumns = $this->tableColumns('dice_instances');
    foreach (['material', 'rarity', 'aspects', 'dice_definition_id'] as $rejectedColumn) {
      $this->assertNotContains($rejectedColumn, $dieColumns);
    }

    foreach (['unit_types', 'kins', 'abilities', 'dice_profiles', 'teams', 'team_units', 'team_formation'] as $rejectedTable) {
      $this->assertSame('0', (string)$this->scalar(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$rejectedTable],
      ));
    }
  }

  public function testOwnedRootsRequireExistingUsersAndActiveSquadIsNullable(): void
  {
    $userId = $this->createUser();
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, ?)')->execute([$userId, 10]);
    $this->assertNull($this->fetchOne('SELECT `active_squad_id` FROM `user_state` WHERE `user_id` = ?', [$userId])['active_squad_id'] ?? null);

    $missingUserId = 999999999;
    $this->assertConstraintViolation(fn() => $this->createUnit($missingUserId));
    $this->assertConstraintViolation(fn() => $this->createDie($missingUserId));
    $this->assertConstraintViolation(fn() => $this->createSquad($missingUserId));

    $squadId = $this->createSquad($userId);
    $unitId = $this->createUnit($userId);
    $this->assertConstraintViolation(fn() => $this->insertSquadUnit(999999999, $unitId, 0));
    $this->assertConstraintViolation(fn() => $this->insertSquadUnit($squadId, 999999999, 0));
    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare('INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)')->execute([999999999, 'ability.orphan']),
    );
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$squadId, $userId]);
    $this->assertSame((string)$squadId, (string)$this->scalar('SELECT `active_squad_id` FROM `user_state` WHERE `user_id` = ?', [$userId]));

    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([999999999, $userId]),
    );

    $this->pdo?->prepare('DELETE FROM `squads` WHERE `id` = ?')->execute([$squadId]);
    $this->assertNull($this->fetchOne('SELECT `active_squad_id` FROM `user_state` WHERE `user_id` = ?', [$userId])['active_squad_id'] ?? null);
  }

  public function testFormationEnforcesNinePositionsAndSameSquadUniqueness(): void
  {
    $userId = $this->createUser();
    $allPositionsSquadId = $this->createSquad($userId, 'All positions');
    $units = [];
    for ($position = 0; $position <= 8; $position++) {
      $unitId = $this->createUnit($userId, 'unit_type.position_' . $position, 'kin.basic', 'Goblin ' . $position);
      $units[] = $unitId;
      $this->insertSquadUnit($allPositionsSquadId, $unitId, $position);
    }
    $this->assertSame('9', (string)$this->scalar('SELECT COUNT(*) FROM `squad_units` WHERE `squad_id` = ?', [$allPositionsSquadId]));

    $boundsSquadId = $this->createSquad($userId, 'Bounds');
    $this->assertConstraintViolation(fn() => $this->insertSquadUnit($boundsSquadId, $this->createUnit($userId), -1));
    $this->assertConstraintViolation(fn() => $this->insertSquadUnit($boundsSquadId, $this->createUnit($userId), 9));

    $uniquenessSquadId = $this->createSquad($userId, 'Uniqueness');
    $this->insertSquadUnit($uniquenessSquadId, $units[0], 0);
    $this->assertConstraintViolation(fn() => $this->insertSquadUnit($uniquenessSquadId, $units[1], 0));
    $this->assertConstraintViolation(fn() => $this->insertSquadUnit($uniquenessSquadId, $units[0], 1));

    $otherSquadId = $this->createSquad($userId, 'Shared unit');
    $this->insertSquadUnit($otherSquadId, $units[0], 8);
    $this->assertSame('3', (string)$this->scalar('SELECT COUNT(*) FROM `squad_units` WHERE `unit_id` = ?', [$units[0]]));
  }

  public function testPromotionAbilityLoadoutAndExactDiceBindingsRemainNormalized(): void
  {
    $userId = $this->createUser();
    $unitId = $this->createUnit($userId, 'unit_type.raider', 'kin.basic', 'Gnash');
    $dieId = $this->createDie($userId, 6, 'dice_profile.basic_d6');

    $this->pdo?->prepare(
      'INSERT INTO `unit_promotions` (`unit_id`, `from_unit_type_id`, `to_unit_type_id`) VALUES (?, ?, ?)',
    )->execute([$unitId, 'unit_type.grunt', 'unit_type.raider']);
    $this->pdo?->prepare(
      'INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?), (?, ?)',
    )->execute([$unitId, 'ability.slash', $unitId, 'ability.taunt']);
    $this->pdo?->prepare(
      'INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, ?, ?), (?, ?, ?)',
    )->execute([$unitId, 'ability.taunt', 0, $unitId, 'ability.slash', 1]);

    $binding = $this->pdo?->prepare(
      'INSERT INTO `unit_ability_dice` (`unit_id`, `ability_id`, `slot_index`, `dice_instance_id`) VALUES (?, ?, ?, ?)',
    );
    $binding?->execute([$unitId, 'ability.slash', 0, $dieId]);
    $binding?->execute([$unitId, 'ability.taunt', 1, $dieId]);

    $loadout = $this->pdo?->prepare('SELECT `ability_id` FROM `unit_ability_loadout` WHERE `unit_id` = ? ORDER BY `equip_order`');
    $loadout?->execute([$unitId]);
    $this->assertSame(['ability.taunt', 'ability.slash'], $loadout?->fetchAll(PDO::FETCH_COLUMN));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `unit_ability_dice` WHERE `dice_instance_id` = ?', [$dieId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_promotions` WHERE `unit_id` = ?', [$unitId]));

    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare(
        'INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)',
      )->execute([$unitId, 'ability.slash']),
    );
    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare(
        'INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, ?, ?)',
      )->execute([$unitId, 'ability.not_owned', 2]),
    );
    $this->assertConstraintViolation(
      fn() => $this->pdo?->prepare(
        'INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, ?, ?)',
      )->execute([$unitId, 'ability.slash', 0]),
    );
    $this->assertConstraintViolation(
      fn() => $binding?->execute([$unitId, 'ability.slash', 0, $dieId]),
    );
    $this->assertConstraintViolation(
      fn() => $binding?->execute([$unitId, 'ability.slash', 2, 999999999]),
    );
  }

  public function testDeletesCannotLeaveInvalidWarbandRelationships(): void
  {
    $userId = $this->createUser();
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, ?)')->execute([$userId, 10]);
    $unitId = $this->createUnit($userId);
    $dieId = $this->createDie($userId);
    $squadId = $this->createSquad($userId);
    $this->insertSquadUnit($squadId, $unitId, 0);
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$squadId, $userId]);
    $this->pdo?->prepare('INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)')->execute([$unitId, 'ability.slash']);
    $this->pdo?->prepare('INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, ?, 0)')->execute([$unitId, 'ability.slash']);
    $this->pdo?->prepare('INSERT INTO `unit_ability_dice` (`unit_id`, `ability_id`, `slot_index`, `dice_instance_id`) VALUES (?, ?, 0, ?)')->execute([$unitId, 'ability.slash', $dieId]);
    $this->pdo?->prepare('INSERT INTO `unit_promotions` (`unit_id`, `from_unit_type_id`, `to_unit_type_id`) VALUES (?, ?, ?)')->execute([$unitId, 'unit_type.grunt', 'unit_type.raider']);

    $this->pdo?->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$unitId]);
    foreach (['unit_promotions', 'unit_abilities', 'unit_ability_loadout', 'unit_ability_dice'] as $table) {
      $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `$table` WHERE `unit_id` = ?", [$unitId]));
    }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `squad_units` WHERE `unit_id` = ?', [$unitId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `id` = ?', [$dieId]));

    $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$userId]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `squads` WHERE `user_id` = ?', [$userId]));
  }

  private function createUser(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)');
    $stmt?->execute(['QA Goblin ' . $token]);
    $userId = (int)$this->pdo?->lastInsertId();
    $this->trackUserId($userId);
    return $userId;
  }

  private function createUnit(
    int $userId,
    string $unitTypeId = 'unit_type.grunt',
    string $kinId = 'kin.basic',
    string $displayName = 'QA Unit',
  ): int {
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`) VALUES (?, ?, ?, ?)',
    );
    $stmt?->execute([$userId, $unitTypeId, $kinId, $displayName]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function createDie(int $userId, int $size = 6, string $profileId = 'dice_profile.basic_d6'): int
  {
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `dice_instances` (`user_id`, `size`, `profile_id`) VALUES (?, ?, ?)',
    );
    $stmt?->execute([$userId, $size, $profileId]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function createSquad(int $userId, string $name = 'QA Squad'): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt?->execute([$userId, $name]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertSquadUnit(int $squadId, int $unitId, int $position): void
  {
    $this->pdo?->prepare(
      'INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, ?)',
    )->execute([$squadId, $unitId, $position]);
  }

  /** @param array<int,int|string> $params */
  private function fetchOne(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  /** @return array<int,string> */
  private function tableColumns(string $table): array
  {
    $stmt = $this->pdo?->prepare(
      'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
    );
    $stmt?->execute([$table]);
    return $stmt?->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }

  private function assertConstraintViolation(callable $operation): void
  {
    try {
      $operation();
      $this->fail('Expected the database constraint to reject the operation.');
    } catch (PDOException $e) {
      $this->assertContains((string)$e->getCode(), ['22003', '23000', 'HY000']);
    }
  }
}
