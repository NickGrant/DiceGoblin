<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Core\Db;
use DiceGoblins\Services\GrantService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GrantServiceStarterPackInvariantsTest extends TestCase
{
  private ?PDO $pdo = null;
  /** @var array<int,int> */
  private array $userIds = [];
  /** @var array<int,int> */
  private array $teamIds = [];

  protected function setUp(): void
  {
    parent::setUp();
    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run GrantService integration tests.');
    }

    $this->pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $this->resetDbSingleton();
  }

  protected function tearDown(): void
  {
    if ($this->pdo !== null && count($this->userIds) > 0) {
      $this->deleteUserOwnedData($this->userIds);
    }

    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  public function testStarterPackSeedDependenciesExist(): void
  {
    $farm = (int)$this->scalar("SELECT COUNT(*) FROM `regions` WHERE `slug` = 'the_farm'");
    $mountains = (int)$this->scalar("SELECT COUNT(*) FROM `regions` WHERE `slug` = 'mountains'");
    $this->assertGreaterThan(0, $farm);
    $this->assertGreaterThan(0, $mountains);

    $unitTypeCount = (int)$this->scalar(
      "SELECT COUNT(*) FROM `unit_types` WHERE `slug` IN ('frontline_bruiser_t1','backline_marksman_t1','support_banner_t1','control_saboteur_t1')"
    );
    $this->assertSame(4, $unitTypeCount);

    $diceDefs = (int)$this->scalar(
      "SELECT COUNT(*) FROM `dice_definitions` WHERE `rarity` = 'common' AND `sides` = 4"
    );
    $this->assertGreaterThanOrEqual(1, $diceDefs);

    $enemyLoadouts = (int)$this->scalar(
      "SELECT COUNT(*) FROM `enemy_templates` WHERE `equipped_abilities_json` IS NOT NULL"
    );
    $this->assertGreaterThan(0, $enemyLoadouts);
  }

  public function testEnsureStarterPackGrantedIsIdempotentForCleanUser(): void
  {
    $userId = $this->insertUser();
    $service = new GrantService();

    $service->ensureStarterPackGranted($userId);
    $service->ensureStarterPackGranted($userId);

    $grantCount = (int)$this->scalar(
      "SELECT COUNT(*) FROM `user_grants` WHERE `user_id` = ? AND `grant_slug` = 'starter_pack_v1'",
      [$userId]
    );
    $this->assertSame(1, $grantCount);

    $teamCount = (int)$this->scalar("SELECT COUNT(*) FROM `teams` WHERE `user_id` = ?", [$userId]);
    $activeTeams = (int)$this->scalar("SELECT COUNT(*) FROM `teams` WHERE `user_id` = ? AND `is_active` = 1", [$userId]);
    $unitCount = (int)$this->scalar("SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?", [$userId]);
    $diceCount = (int)$this->scalar("SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?", [$userId]);
    $unlockCount = (int)$this->scalar(
      "SELECT COUNT(*) FROM `region_unlocks` ru JOIN `regions` r ON r.`id` = ru.`region_id` WHERE ru.`user_id` = ? AND r.`slug` = 'the_farm'",
      [$userId]
    );

    $this->assertSame(1, $teamCount);
    $this->assertSame(1, $activeTeams);
    $this->assertSame(4, $unitCount);
    $this->assertSame(5, $diceCount);
    $this->assertSame(1, $unlockCount);

    $namedUnits = (int)$this->scalar(
      "SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ? AND `display_name` IS NOT NULL AND `display_name` <> ''",
      [$userId]
    );
    $this->assertSame(4, $namedUnits);

    $unlockedAbilities = (int)$this->scalar(
      "SELECT COUNT(*) FROM `unit_instance_unlocked_abilities` uia
       JOIN `unit_instances` ui ON ui.`id` = uia.`unit_instance_id`
       WHERE ui.`user_id` = ?",
      [$userId]
    );
    $this->assertGreaterThanOrEqual(8, $unlockedAbilities);

    $equippedAbilities = (int)$this->scalar(
      "SELECT COUNT(*) FROM `unit_instance_equipped_abilities` uea
       JOIN `unit_instances` ui ON ui.`id` = uea.`unit_instance_id`
       WHERE ui.`user_id` = ?",
      [$userId]
    );
    $this->assertSame(8, $equippedAbilities);

    $abilityDice = (int)$this->scalar(
      "SELECT COUNT(*) FROM `unit_ability_dice` uad
       JOIN `unit_instances` ui ON ui.`id` = uad.`unit_instance_id`
       WHERE ui.`user_id` = ?",
      [$userId]
    );
    $this->assertSame(5, $abilityDice);
  }

  public function testEnsureStarterPackUsesExistingActiveTeamWithoutCreatingAnother(): void
  {
    $userId = $this->insertUser();
    $existingTeamId = $this->insertTeam($userId, 'Prebuilt Squad', true);

    $service = new GrantService();
    $service->ensureStarterPackGranted($userId);

    $teamCount = (int)$this->scalar("SELECT COUNT(*) FROM `teams` WHERE `user_id` = ?", [$userId]);
    $activeTeamId = (int)$this->scalar("SELECT `id` FROM `teams` WHERE `user_id` = ? AND `is_active` = 1 LIMIT 1", [$userId]);
    $teamUnitCount = (int)$this->scalar("SELECT COUNT(*) FROM `team_units` WHERE `team_id` = ?", [$existingTeamId]);

    $this->assertSame(1, $teamCount);
    $this->assertSame($existingTeamId, $activeTeamId);
    $this->assertSame(4, $teamUnitCount);
  }

  public function testEnsureStarterPackAssignsImmutableAffixesWithinRarityBounds(): void
  {
    $userId = $this->insertUser();
    $service = new GrantService();

    $service->ensureStarterPackGranted($userId);

    $rows = $this->pdo?->query(
      "SELECT
         di.`id`,
         dd.`rarity`,
         dd.`slot_capacity`,
         COUNT(dia.`affix_definition_id`) AS `affix_count`
       FROM `dice_instances` di
       JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
       LEFT JOIN `dice_instance_affixes` dia ON dia.`dice_instance_id` = di.`id`
       WHERE di.`user_id` = {$userId}
       GROUP BY di.`id`, dd.`rarity`, dd.`slot_capacity`
       ORDER BY di.`id` ASC"
    )?->fetchAll(PDO::FETCH_ASSOC) ?? [];

    $this->assertCount(5, $rows);

    foreach ($rows as $row) {
      $this->assertSame((int)$row['slot_capacity'], (int)$row['affix_count']);
    }

    $violations = (int)$this->scalar(
      "SELECT COUNT(*)
       FROM `dice_instance_affixes` dia
       JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id`
       JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
       JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`
       WHERE di.`user_id` = ?
         AND (
           CASE ad.`rarity`
             WHEN 'common' THEN 0
             WHEN 'uncommon' THEN 1
             WHEN 'rare' THEN 2
             WHEN 'epic' THEN 3
             WHEN 'legendary' THEN 4
             ELSE 99
           END
         ) > (
           CASE dd.`rarity`
             WHEN 'common' THEN 0
             WHEN 'uncommon' THEN 1
             WHEN 'rare' THEN 2
             WHEN 'epic' THEN 3
             WHEN 'legendary' THEN 4
             ELSE -1
           END
         )",
      [$userId]
    );

    $this->assertSame(0, $violations);
  }

  private function insertUser(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`discord_id`, `display_name`) VALUES (?, ?)');
    $stmt?->execute(["qa_grant_$token", "QA Grant $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function insertTeam(int $userId, string $name, bool $active): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, ?)');
    $stmt?->execute([$userId, $name, $active ? 1 : 0]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $id;
    return $id;
  }

  /**
   * @param array<int,int|string> $params
   * @return int|string
   */
  private function scalar(string $sql, array $params = []): int|string
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $value = $stmt?->fetchColumn();
    return is_string($value) || is_int($value) ? $value : (string)$value;
  }

  /**
   * @param array<int,int> $userIds
   */
  private function deleteUserOwnedData(array $userIds): void
  {
    $userIds = array_values(array_unique(array_filter($userIds, static fn(int $v): bool => $v > 0)));
    if (count($userIds) === 0) return;
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $this->execDelete("DELETE br FROM `battle_rewards` br JOIN `battles` b ON b.`id` = br.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE bl FROM `battle_logs` bl JOIN `battles` b ON b.`id` = bl.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `battles` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE re FROM `run_edges` re JOIN `region_runs` rr ON rr.`id` = re.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE rus FROM `run_unit_state` rus JOIN `region_runs` rr ON rr.`id` = rus.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE rn FROM `run_nodes` rn JOIN `region_runs` rr ON rr.`id` = rn.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `region_runs` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE tf FROM `team_formation` tf JOIN `teams` t ON t.`id` = tf.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE tu FROM `team_units` tu JOIN `teams` t ON t.`id` = tu.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE uad FROM `unit_ability_dice` uad JOIN `unit_instances` ui ON ui.`id` = uad.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE uea FROM `unit_instance_equipped_abilities` uea JOIN `unit_instances` ui ON ui.`id` = uea.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE uua FROM `unit_instance_unlocked_abilities` uua JOIN `unit_instances` ui ON ui.`id` = uua.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE ud FROM `unit_dice` ud JOIN `unit_instances` ui ON ui.`id` = ud.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE dia FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `user_grants` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `unit_promotions` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `user_region_items` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `region_unlocks` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `dice_instances` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `unit_instances` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `teams` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `energy_state` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `player_state` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDelete("DELETE FROM `users` WHERE `id` IN ($placeholders)", $userIds);
  }

  /**
   * @param array<int,int> $userIds
   */
  private function execDelete(string $sql, array $userIds): void
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($userIds);
  }

  private function resetDbSingleton(): void
  {
    $ref = new ReflectionClass(Db::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
  }
}
