<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Services\GrantService.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Services;

use DiceGoblins\Core\Db;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class GrantService
{
  private const STARTER_GRANT_SLUG = 'starter_pack_v1';

  /** @var list<string> */
  private const STARTING_REGION_SLUGS = ['the_farm'];

  /** @var list<string> */
  private const STARTER_UNIT_UNLOCK_SLUGS = [
    'backline_marksman_t1',
    'frontline_bruiser_t1',
  ];

  /** @var list<array{unit_type_slug:string,formation_cell:string,equipped_abilities:list<string>}> */
  private const STARTER_UNITS = [
    [
      'unit_type_slug' => 'frontline_bruiser_t1',
      'formation_cell' => 'A3',
      'equipped_abilities' => ['basic_attack_melee', 'heavy_strike', 'basic_attack_melee'],
    ],
    [
      'unit_type_slug' => 'backline_marksman_t1',
      'formation_cell' => 'A1',
      'equipped_abilities' => ['basic_attack_ranged', 'aimed_shot', 'basic_attack_ranged'],
    ],
    [
      'unit_type_slug' => 'frontline_bruiser_t1',
      'formation_cell' => 'C3',
      'equipped_abilities' => ['basic_attack_melee', 'heavy_strike', 'basic_attack_melee'],
    ],
    [
      'unit_type_slug' => 'backline_marksman_t1',
      'formation_cell' => 'C1',
      'equipped_abilities' => ['basic_attack_ranged', 'aimed_shot', 'basic_attack_ranged'],
    ],
  ];

  private ?DiceAffixService $diceAffixService = null;
  private ?UnitLoadoutService $unitLoadoutService = null;
  private ?UnitNameGenerator $unitNameGenerator = null;
  private ?UserUnlockService $userUnlockService = null;

  public function ensureStarterPackGranted(int $userId): void
  {
    if ($userId <= 0) {
      throw new RuntimeException('Invalid userId.');
    }

    $db = Db::pdo();
    $this->diceAffixService ??= new DiceAffixService($db);
    $this->unitLoadoutService ??= new UnitLoadoutService($db);
    $this->unitNameGenerator ??= new UnitNameGenerator();
    $this->userUnlockService ??= new UserUnlockService($db);

    $db->beginTransaction();
    try {
      foreach (self::STARTING_REGION_SLUGS as $regionSlug) {
        $this->ensureStartingRegionUnlock($db, $userId, $regionSlug);
      }
      $this->ensureStarterUnitUnlocks($userId);

      $claimed = $this->tryClaimGrant($db, $userId, self::STARTER_GRANT_SLUG);
      if (!$claimed) {
        $db->commit();
        return;
      }

      $teamId = $this->ensureDefaultTeam($db, $userId);

      $starterUnits = $this->createStarterUnits($db, $userId, self::STARTER_UNITS);
      $unitInstanceIds = array_map(
        static fn(array $unit): int => (int)$unit['unit_instance_id'],
        $starterUnits
      );
      $this->addUnitsToTeam($db, $teamId, $unitInstanceIds);
      $this->seedStarterFormation($db, $teamId, $starterUnits);

      $starterAbilityTargets = $this->buildStarterAbilityTargets($starterUnits);
      $diceInstanceIds = $this->createStarterDice($db, $userId, count($starterAbilityTargets));
      $this->assignStarterDice($db, $starterAbilityTargets, $diceInstanceIds);

      $db->commit();
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      throw $e;
    }
  }

  private function tryClaimGrant(PDO $db, int $userId, string $slug): bool
  {
    $stmt = $db->prepare(
      "INSERT INTO user_grants (user_id, grant_slug, meta_json)
       VALUES (:user_id, :slug, JSON_OBJECT('source', 'PlayerBootstrapper.ensureBaseline'))"
    );

    try {
      $stmt->execute([':user_id' => $userId, ':slug' => $slug]);
      return true;
    } catch (PDOException $e) {
      if ($e->getCode() === '23000') {
        return false;
      }
      throw $e;
    }
  }

  private function ensureDefaultTeam(PDO $db, int $userId): int
  {
    $stmt = $db->prepare(
      "SELECT id, is_active
       FROM teams
       WHERE user_id = :user_id
       ORDER BY is_active DESC, id ASC
       LIMIT 1"
    );
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();

    if ($row) {
      $teamId = (int)$row['id'];
      if ((int)$row['is_active'] !== 1) {
        $db->prepare("UPDATE teams SET is_active = 0 WHERE user_id = :user_id")
          ->execute([':user_id' => $userId]);

        $db->prepare("UPDATE teams SET is_active = 1 WHERE id = :id")
          ->execute([':id' => $teamId]);
      }

      return $teamId;
    }

    $db->prepare(
      "INSERT INTO teams (user_id, name, is_active)
       VALUES (:user_id, 'Main', 1)"
    )->execute([':user_id' => $userId]);

    return (int)$db->lastInsertId();
  }

  private function ensureStartingRegionUnlock(PDO $db, int $userId, string $regionSlug): void
  {
    $regionId = $this->getRegionIdBySlug($db, $regionSlug);
    if ($regionId === null) {
      throw new RuntimeException("Starter pack config invalid: missing region slug '{$regionSlug}'.");
    }

    $db->prepare(
      "INSERT IGNORE INTO region_unlocks (user_id, region_id)
       VALUES (:user_id, :region_id)"
    )->execute([
      ':user_id' => $userId,
      ':region_id' => $regionId,
    ]);
  }

  private function ensureStarterUnitUnlocks(int $userId): void
  {
    $this->userUnlockService?->grantMany($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, self::STARTER_UNIT_UNLOCK_SLUGS);
  }

  /**
   * @param list<array{unit_type_slug:string,formation_cell:string,equipped_abilities:list<string>}> $starterUnitConfigs
   * @return list<array{
   *   unit_instance_id:int,
   *   unit_type_id:int,
   *   unit_type_slug:string,
   *   formation_cell:string,
   *   equipped_abilities:list<string>
   * }>
   */
  private function createStarterUnits(PDO $db, int $userId, array $starterUnitConfigs): array
  {
    $starterUnits = [];

    foreach ($starterUnitConfigs as $config) {
      $slug = (string)$config['unit_type_slug'];
      $unitTypeId = $this->getUnitTypeIdBySlug($db, $slug);
      if ($unitTypeId === null) {
        throw new RuntimeException("Starter pack config invalid: missing unit_type slug '{$slug}'.");
      }

      $db->prepare(
        "INSERT INTO unit_instances (user_id, unit_type_id, display_name, tier, level, xp, locked)
         VALUES (:user_id, :unit_type_id, :display_name, 1, 1, 0, 0)"
      )->execute([
        ':user_id' => $userId,
        ':unit_type_id' => $unitTypeId,
        ':display_name' => $this->unitNameGenerator?->generate(),
      ]);

      $unitInstanceId = (int)$db->lastInsertId();
      $this->unitLoadoutService?->initializeUnit($unitInstanceId, $unitTypeId);
      $this->unitLoadoutService?->replaceEquippedAbilities($unitInstanceId, $config['equipped_abilities']);

      $starterUnits[] = [
        'unit_instance_id' => $unitInstanceId,
        'unit_type_id' => $unitTypeId,
        'unit_type_slug' => $slug,
        'formation_cell' => (string)$config['formation_cell'],
        'equipped_abilities' => $config['equipped_abilities'],
      ];
    }

    return $starterUnits;
  }

  /** @param list<int> $unitInstanceIds */
  private function addUnitsToTeam(PDO $db, int $teamId, array $unitInstanceIds): void
  {
    if (!$unitInstanceIds) {
      return;
    }

    $stmt = $db->prepare(
      "INSERT IGNORE INTO team_units (team_id, unit_instance_id)
       VALUES (:team_id, :unit_instance_id)"
    );

    foreach ($unitInstanceIds as $unitInstanceId) {
      $stmt->execute([
        ':team_id' => $teamId,
        ':unit_instance_id' => $unitInstanceId,
      ]);
    }
  }

  /**
   * @param list<array{
   *   unit_instance_id:int,
   *   unit_type_id:int,
   *   unit_type_slug:string,
   *   formation_cell:string,
   *   equipped_abilities:list<string>
   * }> $starterUnits
   */
  private function seedStarterFormation(PDO $db, int $teamId, array $starterUnits): void
  {
    $stmt = $db->prepare('
      INSERT INTO `team_formation` (`team_id`, `cell`, `unit_instance_id`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `unit_instance_id` = VALUES(`unit_instance_id`)
    ');

    foreach ($starterUnits as $starterUnit) {
      $stmt->execute([
        $teamId,
        (string)$starterUnit['formation_cell'],
        (int)$starterUnit['unit_instance_id'],
      ]);
    }
  }

  /** @return list<int> */
  private function createStarterDice(PDO $db, int $userId, int $count): array
  {
    $diceInstanceIds = [];
    if ($count <= 0) {
      return $diceInstanceIds;
    }

    $defId = $this->getDiceDefinitionId($db, 4, 'common');
    if ($defId === null) {
      throw new RuntimeException("Starter pack config invalid: missing common d4 dice definition.");
    }

    $insert = $db->prepare(
      "INSERT INTO dice_instances (user_id, dice_definition_id, display_name)
       VALUES (:user_id, :def_id, NULL)"
    );

    for ($i = 0; $i < $count; $i++) {
      $insert->execute([
        ':user_id' => $userId,
        ':def_id' => $defId,
      ]);

      $diceInstanceId = (int)$db->lastInsertId();
      $this->diceAffixService?->assignAffixesToDiceInstance($diceInstanceId);
      $diceInstanceIds[] = $diceInstanceId;
    }

    return $diceInstanceIds;
  }

  /**
   * @param list<array{unit_instance_id:int,unit_type_id:int}> $starterUnits
   * @return list<array{unit_instance_id:int,ability_id:string,ability_slot_index:int}>
   */
  private function buildStarterAbilityTargets(array $starterUnits): array
  {
    $targets = [];

    foreach ($starterUnits as $starterUnit) {
      $slots = $this->unitLoadoutService?->listDefaultAbilityDiceSlotsForUnitType((int)$starterUnit['unit_type_id']) ?? [];
      foreach ($slots as $slot) {
        $targets[] = [
          'unit_instance_id' => (int)$starterUnit['unit_instance_id'],
          'ability_id' => (string)$slot['ability_id'],
          'ability_slot_index' => (int)$slot['slot_index'],
        ];
      }
    }

    return $targets;
  }

  /**
   * @param list<array{unit_instance_id:int,ability_id:string,ability_slot_index:int}> $starterAbilityTargets
   * @param list<int> $diceInstanceIds
   */
  private function assignStarterDice(PDO $db, array $starterAbilityTargets, array $diceInstanceIds): void
  {
    if (!$starterAbilityTargets || !$diceInstanceIds) {
      return;
    }

    $n = min(count($starterAbilityTargets), count($diceInstanceIds));
    for ($i = 0; $i < $n; $i++) {
      $target = $starterAbilityTargets[$i];
      $diceId = (int)$diceInstanceIds[$i];

      $this->unitLoadoutService?->assignDieToAbilitySlot(
        (int)$target['unit_instance_id'],
        (string)$target['ability_id'],
        (int)$target['ability_slot_index'],
        $diceId
      );
    }
  }

  private function getRegionIdBySlug(PDO $db, string $slug): ?int
  {
    $stmt = $db->prepare("SELECT id FROM regions WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
  }

  private function getUnitTypeIdBySlug(PDO $db, string $slug): ?int
  {
    $stmt = $db->prepare("SELECT id FROM unit_types WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
  }

  private function getDiceDefinitionId(PDO $db, int $sides, string $rarity): ?int
  {
    $stmt = $db->prepare(
      "SELECT id
       FROM dice_definitions
       WHERE sides = :sides AND rarity = :rarity
       LIMIT 1"
    );
    $stmt->execute([':sides' => $sides, ':rarity' => $rarity]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
  }
}
