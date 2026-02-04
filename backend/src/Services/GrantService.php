<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Core\Db;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * GrantService provisions one-time (or versioned) grants for a user in an idempotent way.
 *
 * Contract:
 * - Safe to call on every request.
 * - Uses user_grants unique(user_id, grant_slug) to prevent double-granting under concurrency.
 * - Runs all provisioning in a single transaction.
 */
final class GrantService
{
  // Version this so you can ship starter_pack_v2 later without touching existing users.
  private const STARTER_GRANT_SLUG = 'starter_pack_v1';

  // --- Starter pack policy (adjust to your seed data) ---
  private const STARTING_REGION_SLUG = 'mountains';

  /** @var list<string> */
  private const STARTER_UNIT_TYPE_SLUGS = [
    'frontline_bruiser_t1',
    'backline_marksman_t1',
    'support_banner_t1',
    'control_saboteur_t1',
  ];

  /** @var list<array{rarity:string,sides:int,count:int}> */
  private const STARTER_DICE_SPECS = [
    ['rarity' => 'common',   'sides' => 6, 'count' => 4],
    ['rarity' => 'common',   'sides' => 4, 'count' => 2],
    ['rarity' => 'uncommon', 'sides' => 6, 'count' => 1],
  ];

  public function ensureStarterPackGranted(int $userId): void
  {
    if ($userId <= 0) {
      throw new RuntimeException('Invalid userId.');
    }

    $db = Db::pdo();

    $db->beginTransaction();
    try {
      
      // 1) Attempt to claim the grant.
      $claimed = $this->tryClaimGrant($db, $userId, self::STARTER_GRANT_SLUG);
      if (!$claimed) {
          $db->commit();
          return;
      }

      // 2) Provision starter gameplay content (teams/units/dice/unlocks).
      $teamId = $this->ensureDefaultTeam($db, $userId);

      $this->ensureStartingRegionUnlock($db, $userId, self::STARTING_REGION_SLUG);

      $unitInstanceIds = $this->createStarterUnits($db, $userId, self::STARTER_UNIT_TYPE_SLUGS);
      $this->addUnitsToTeam($db, $teamId, $unitInstanceIds);

      $diceInstanceIds = $this->createStarterDice($db, $userId, self::STARTER_DICE_SPECS);

      // Optional: equip one die per unit in slot 0 to avoid an “empty” first combat.
      $this->equipStarterDice($db, $unitInstanceIds, $diceInstanceIds);

      $db->commit();
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      throw $e;
    }
  }

  private function insertGrantRowOrNoop(PDO $db, int $userId, string $slug): void
  {
    $stmt = $db->prepare(
      "INSERT INTO user_grants (user_id, grant_slug, meta_json)
       VALUES (:user_id, :slug, JSON_OBJECT('source', 'PlayerBootstrapper.ensureBaseline'))"
    );

    try {
      $stmt->execute([':user_id' => $userId, ':slug' => $slug]);
    } catch (PDOException $e) {
      // Duplicate key: already granted -> no-op.
      if ($e->getCode() === '23000') {
        // Important: do NOT keep transaction open; caller expects us to return cleanly.
        $db->commit();
        // Throw a sentinel? No. Just exit early.
        // The caller's transaction is already committed here.
        // Return so ensureStarterPackGranted ends immediately.
        return;
      }
      throw $e;
    }

    // If we successfully inserted, continue provisioning.
  }

    private function tryClaimGrant(PDO $db, int $userId, string $slug): bool
    {
        $stmt = $db->prepare(
            "INSERT INTO user_grants (user_id, grant_slug, meta_json)
            VALUES (:user_id, :slug, JSON_OBJECT('source', 'PlayerBootstrapper.ensureBaseline'))"
        );

        try {
            $stmt->execute([':user_id' => $userId, ':slug' => $slug]);
            return true; // we claimed it
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
            return false; // already claimed
            }
            throw $e;
        }
    }

  private function ensureDefaultTeam(PDO $db, int $userId): int
  {
    // Prefer an existing active team if one exists.
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

      // Ensure it's active and de-activate others (optional but keeps invariants clean).
      if ((int)$row['is_active'] !== 1) {
        $db->prepare("UPDATE teams SET is_active = 0 WHERE user_id = :user_id")
          ->execute([':user_id' => $userId]);

        $db->prepare("UPDATE teams SET is_active = 1 WHERE id = :id")
          ->execute([':id' => $teamId]);
      }

      return $teamId;
    }

    // Otherwise create a default team.
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

  /** @param list<string> $unitTypeSlugs @return list<int> */
  private function createStarterUnits(PDO $db, int $userId, array $unitTypeSlugs): array
  {
    $unitInstanceIds = [];

    foreach ($unitTypeSlugs as $slug) {
      $unitTypeId = $this->getUnitTypeIdBySlug($db, $slug);
      if ($unitTypeId === null) {
        throw new RuntimeException("Starter pack config invalid: missing unit_type slug '{$slug}'.");
      }

      $db->prepare(
        "INSERT INTO unit_instances (user_id, unit_type_id, tier, level, xp, locked)
         VALUES (:user_id, :unit_type_id, 1, 1, 0, 0)"
      )->execute([
        ':user_id' => $userId,
        ':unit_type_id' => $unitTypeId,
      ]);

      $unitInstanceIds[] = (int)$db->lastInsertId();
    }

    return $unitInstanceIds;
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
   * @param list<array{rarity:string,sides:int,count:int}> $diceSpecs
   * @return list<int>
   */
  private function createStarterDice(PDO $db, int $userId, array $diceSpecs): array
  {
    $diceInstanceIds = [];

    $insert = $db->prepare(
      "INSERT INTO dice_instances (user_id, dice_definition_id, display_name)
       VALUES (:user_id, :def_id, NULL)"
    );

    foreach ($diceSpecs as $spec) {
      $defId = $this->getDiceDefinitionId($db, $spec['sides'], $spec['rarity']);
      if ($defId === null) {
        throw new RuntimeException(
          "Starter pack config invalid: missing dice_definition for rarity='{$spec['rarity']}' sides={$spec['sides']}."
        );
      }

      for ($i = 0; $i < (int)$spec['count']; $i++) {
        $insert->execute([
          ':user_id' => $userId,
          ':def_id' => $defId,
        ]);

        $diceInstanceIds[] = (int)$db->lastInsertId();
      }
    }

    return $diceInstanceIds;
  }

  /** @param list<int> $unitInstanceIds @param list<int> $diceInstanceIds */
  private function equipStarterDice(PDO $db, array $unitInstanceIds, array $diceInstanceIds): void
  {
    if (!$unitInstanceIds || !$diceInstanceIds) {
      return;
    }

    $n = min(count($unitInstanceIds), count($diceInstanceIds));

    $stmt = $db->prepare(
      "INSERT IGNORE INTO unit_dice (unit_instance_id, dice_instance_id, slot_index)
       VALUES (:unit_id, :dice_id, :slot_index)"
    );

    for ($i = 0; $i < $n; $i++) {
      $stmt->execute([
        ':unit_id' => (int)$unitInstanceIds[$i],
        ':dice_id' => (int)$diceInstanceIds[$i],
        ':slot_index' => 0,
      ]);
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
