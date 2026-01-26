<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class UnitRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Static catalog of unit types.
   *
   * @return array<int, array{id:string,slug:string,name:string,role:string}>
   */
  public function listUnitTypes(): array
  {
    
    $stmt = $this->pdo->query('
      SELECT `id`, `slug`, `name`, `role`
      FROM `unit_types`
      ORDER BY `id` ASC
    ');
  

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'id' => (string)$r['id'],
      'slug' => (string)$r['slug'],
      'name' => (string)$r['name'],
      'role' => (string)$r['role'],
    ], $rows);
  }

  /**
   * Get full unit type definition by id.
   *
   * @return array{
   *   id:string,
   *   slug:string,
   *   name:string,
   *   role:string,
   *   base_stats_json: array<string,mixed>,
   *   ability_set_json: array<string,mixed>,
   *   max_level:int,
   *   growth_attack_per_ability_per_level: float,
   *   growth_defense_per_ability_per_level: float,
   *   growth_max_hp_per_ability_per_level: float
   * }|null
   */
  public function getUnitTypeById(int $unitTypeId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        `id`,
        `slug`,
        `name`,
        `role`,
        `base_stats_json`,
        `ability_set_json`,
        `max_level`,
        `growth_attack_per_ability_per_level`,
        `growth_defense_per_ability_per_level`,
        `growth_max_hp_per_ability_per_level`
      FROM `unit_types`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitTypeId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    // In many PDO configs, JSON columns come back as strings; decode defensively.
    $baseStats = $r['base_stats_json'];
    if (is_string($baseStats)) {
      $decoded = json_decode($baseStats, true);
      $baseStats = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($baseStats)) {
      $baseStats = [];
    }

    $abilitySet = $r['ability_set_json'];
    if (is_string($abilitySet)) {
      $decoded = json_decode($abilitySet, true);
      $abilitySet = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($abilitySet)) {
      $abilitySet = [];
    }

    return [
      'id' => (string)$r['id'],
      'slug' => (string)$r['slug'],
      'name' => (string)$r['name'],
      'role' => (string)$r['role'],
      'base_stats_json' => $baseStats,
      'ability_set_json' => $abilitySet,
      'max_level' => (int)$r['max_level'],
      'growth_attack_per_ability_per_level' => (float)$r['growth_attack_per_ability_per_level'],
      'growth_defense_per_ability_per_level' => (float)$r['growth_defense_per_ability_per_level'],
      'growth_max_hp_per_ability_per_level' => (float)$r['growth_max_hp_per_ability_per_level'],
    ];
  }


  /**
   * Returns all owned unit instances with equipped dice, shaped for GET /api/v1/profile.
   *
   * @return array<int, array{
   *   id:string,
   *   unit_type_id:string,
   *   name:string,
   *   tier:int,
   *   level:int,
   *   xp:int,
   *   locked:bool,
   *   equipped_dice: array<int, array{dice_instance_id:string,slot_index:int}>
   * }>
   */
  public function getUnitsWithEquippedDiceForUser(int $userId): array
  {
    // 1) Units + type name
    $stmt = $this->pdo->prepare('
      SELECT
        ui.`id`,
        ui.`unit_type_id`,
        ut.`name` AS `unit_type_name`,
        ui.`tier`,
        ui.`level`,
        ui.`xp`,
        ui.`locked`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$userId]);
    $unitRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$unitRows) {
      return [];
    }

    $unitIds = array_map(static fn(array $r): int => (int)$r['id'], $unitRows);

    // 2) Equipped dice for those units
    $equippedByUnit = $this->getEquippedDiceForUnitIds($unitIds);

    // 3) Merge
    $out = [];
    foreach ($unitRows as $u) {
      $uid = (string)$u['id'];
      $out[] = [
        'id' => $uid,
        'unit_type_id' => (string)$u['unit_type_id'],
        'name' => (string)$u['unit_type_name'], // convenience; catalog still exists separately
        'tier' => (int)$u['tier'],
        'level' => (int)$u['level'],
        'xp' => (int)$u['xp'],
        'locked' => ((int)$u['locked']) === 1,
        'equipped_dice' => $equippedByUnit[$uid] ?? [],
      ];
    }

    return $out;
  }

  /**
   * @return array{id:string,unit_type_id:string,tier:int,level:int,xp:int,locked:bool}|null
   */
  public function getUnitForUser(int $userId, int $unitInstanceId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`
      FROM `unit_instances`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitInstanceId, $userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'unit_type_id' => (string)$r['unit_type_id'],
      'tier' => (int)$r['tier'],
      'level' => (int)$r['level'],
      'xp' => (int)$r['xp'],
      'locked' => ((int)$r['locked']) === 1,
    ];
  }

  /**
   * @return array<int, string> unit instance ids (strings)
   */
  public function listUnitIdsForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `unit_instances`
      WHERE `user_id` = ?
      ORDER BY `id` ASC
    ');
    $stmt->execute([$userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $r): string => (string)$r['id'], $rows);
  }

  /**
   * Create a new unit instance owned by a user.
   *
   * @return int new unit_instance id
   */
  public function createUnitInstance(
    int $userId,
    int $unitTypeId,
    int $tier = 1,
    int $level = 1,
    int $xp = 0,
    bool $locked = false,
  ): int {
    if ($tier < 1) {
      throw new RuntimeException('Tier must be >= 1.');
    }
    if ($level < 1) {
      throw new RuntimeException('Level must be >= 1.');
    }
    if ($xp < 0) {
      throw new RuntimeException('XP cannot be negative.');
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $unitTypeId, $tier, $level, $xp, $locked ? 1 : 0]);

    return (int)$this->pdo->lastInsertId();
  }

  public function setUnitLocked(int $userId, int $unitInstanceId, bool $locked): void
  {
    $stmt = $this->pdo->prepare('
      UPDATE `unit_instances`
      SET `locked` = ?
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([$locked ? 1 : 0, $unitInstanceId, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Unit not found or not owned by user.');
    }
  }

  public function setUnitLevel(int $userId, int $unitInstanceId, int $level): void
  {
    if ($level < 1) {
      throw new RuntimeException('Level must be >= 1.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `unit_instances`
      SET `level` = ?
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([$level, $unitInstanceId, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Unit not found or not owned by user.');
    }
  }

  public function setUnitTier(int $userId, int $unitInstanceId, int $tier): void
  {
    if ($tier < 1) {
      throw new RuntimeException('Tier must be >= 1.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `unit_instances`
      SET `tier` = ?
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([$tier, $unitInstanceId, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Unit not found or not owned by user.');
    }
  }

  /**
   * Add XP atomically and return new xp.
   * Leveling rules (if any) should live in a service, not the repository.
   */
  public function addXp(int $userId, int $unitInstanceId, int $deltaXp): int
  {
    if ($deltaXp < 0) {
      throw new RuntimeException('deltaXp must be >= 0 (use a separate method if you need XP removal).');
    }

    try {
      $this->pdo->beginTransaction();

      $row = $this->getUnitForUpdate($userId, $unitInstanceId);
      if (!$row) {
        $this->pdo->rollBack();
        throw new RuntimeException('Unit not found or not owned by user.');
      }

      $newXp = (int)$row['xp'] + $deltaXp;

      $stmt = $this->pdo->prepare('
        UPDATE `unit_instances`
        SET `xp` = ?
        WHERE `id` = ? AND `user_id` = ?
      ');
      $stmt->execute([$newXp, $unitInstanceId, $userId]);

      $this->pdo->commit();
      return $newXp;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Returns equipped dice for a set of unit ids.
   *
   * @param array<int,int> $unitInstanceIds
   * @return array<string, array<int, array{dice_instance_id:string,slot_index:int}>>
   */
  public function getEquippedDiceForUnitIds(array $unitInstanceIds): array
  {
    if (count($unitInstanceIds) === 0) {
      return [];
    }

    $unitInstanceIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $unitInstanceIds)));
    $placeholders = implode(',', array_fill(0, count($unitInstanceIds), '?'));

    $stmt = $this->pdo->prepare("
      SELECT `unit_instance_id`, `dice_instance_id`, `slot_index`
      FROM `unit_dice`
      WHERE `unit_instance_id` IN ($placeholders)
      ORDER BY `unit_instance_id` ASC, `slot_index` ASC
    ");
    $stmt->execute($unitInstanceIds);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byUnit = [];
    foreach ($rows as $r) {
      $uId = (string)$r['unit_instance_id'];
      $byUnit[$uId] ??= [];
      $byUnit[$uId][] = [
        'dice_instance_id' => (string)$r['dice_instance_id'],
        'slot_index' => (int)$r['slot_index'],
      ];
    }

    return $byUnit;
  }

  /**
   * Convenience: returns equipped dice for a single unit id.
   *
   * @return array<int, array{dice_instance_id:string,slot_index:int}>
   */
  public function getEquippedDiceForUnit(int $unitInstanceId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `dice_instance_id`, `slot_index`
      FROM `unit_dice`
      WHERE `unit_instance_id` = ?
      ORDER BY `slot_index` ASC
    ');
    $stmt->execute([$unitInstanceId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'dice_instance_id' => (string)$r['dice_instance_id'],
      'slot_index' => (int)$r['slot_index'],
    ], $rows);
  }

  // -----------------------------
  // Internals
  // -----------------------------

  /**
   * @return array{xp:int,tier:int,level:int,locked:int}|null
   */
  private function getUnitForUpdate(int $userId, int $unitInstanceId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `xp`, `tier`, `level`, `locked`
      FROM `unit_instances`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$unitInstanceId, $userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'xp' => (int)$r['xp'],
      'tier' => (int)$r['tier'],
      'level' => (int)$r['level'],
      'locked' => (int)$r['locked'],
    ];
  }
}
