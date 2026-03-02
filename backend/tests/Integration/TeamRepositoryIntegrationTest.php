<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\TeamRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TeamRepositoryIntegrationTest extends TestCase
{
  private ?PDO $pdo = null;
  private ?TeamRepository $repo = null;

  /** @var array<int,int> */
  private array $userIds = [];
  /** @var array<int,int> */
  private array $teamIds = [];
  /** @var array<int,int> */
  private array $unitIds = [];
  /** @var array<int,int> */
  private array $unitTypeIds = [];

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';

    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run database integration tests.');
    }

    $this->pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $this->repo = new TeamRepository($this->pdo);

    $this->assertRequiredTablesExist();
  }

  protected function tearDown(): void
  {
    if ($this->pdo !== null) {
      $this->cleanupInsertedData();
    }

    $this->repo = null;
    $this->pdo = null;
    parent::tearDown();
  }

  public function testCreateTeamAndActivateTeamEnforceSingleActiveTeam(): void
  {
    $userId = $this->insertUser();
    $firstTeamId = $this->repo()->createTeam($userId, 'Alpha', true);
    $secondTeamId = $this->repo()->createTeam($userId, 'Beta', false);
    $this->teamIds[] = $firstTeamId;
    $this->teamIds[] = $secondTeamId;

    $this->repo()->setActiveTeam($userId, $secondTeamId);

    $active = $this->repo()->getActiveTeamForUser($userId);
    $this->assertNotNull($active);
    $this->assertSame((string)$secondTeamId, $active['id']);

    $teams = $this->repo()->listTeamsForUser($userId);
    $first = array_values(array_filter($teams, static fn(array $t): bool => $t['id'] === (string)$firstTeamId))[0] ?? null;
    $second = array_values(array_filter($teams, static fn(array $t): bool => $t['id'] === (string)$secondTeamId))[0] ?? null;

    $this->assertNotNull($first);
    $this->assertNotNull($second);
    $this->assertFalse((bool)$first['is_active']);
    $this->assertTrue((bool)$second['is_active']);
  }

  public function testSetActiveTeamRejectsCrossUserAccess(): void
  {
    $ownerId = $this->insertUser();
    $otherUserId = $this->insertUser();
    $teamId = $this->repo()->createTeam($ownerId, 'Owner Team', true);
    $this->teamIds[] = $teamId;

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Team not found or not owned by user.');
    $this->repo()->setActiveTeam($otherUserId, $teamId);
  }

  public function testSetTeamUnitsAndFormationRespectOwnershipAndPlacementRules(): void
  {
    $userId = $this->insertUser();
    $otherUserId = $this->insertUser();
    $teamId = $this->repo()->createTeam($userId, 'Formation Team', true);
    $this->teamIds[] = $teamId;

    $userUnitA = $this->insertUnitInstance($userId);
    $userUnitB = $this->insertUnitInstance($userId);
    $otherUsersUnit = $this->insertUnitInstance($otherUserId);

    $this->repo()->setTeamUnits($userId, $teamId, [$userUnitA, $userUnitB]);
    $this->repo()->setFormationCell($userId, $teamId, 'A1', $userUnitA);
    $this->repo()->setFormationCell($userId, $teamId, 'B2', $userUnitB);

    $profileTeams = $this->repo()->getTeamsWithMembershipAndFormationForUser($userId);
    $this->assertCount(1, $profileTeams);
    $this->assertEqualsCanonicalizing(
      [(string)$userUnitA, (string)$userUnitB],
      $profileTeams[0]['unit_ids']
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Unit not found or not owned by user.');
    $this->repo()->setTeamUnits($userId, $teamId, [$otherUsersUnit]);
  }

  private function repo(): TeamRepository
  {
    if ($this->repo === null) {
      $this->fail('Repository not initialized.');
    }
    return $this->repo;
  }

  private function assertRequiredTablesExist(): void
  {
    $required = ['users', 'teams', 'team_units', 'team_formation', 'unit_types', 'unit_instances'];
    $placeholders = implode(',', array_fill(0, count($required), '?'));
    $sql = "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ($placeholders)";
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($required);
    $count = (int)($stmt?->fetch()['c'] ?? 0);

    if ($count !== count($required)) {
      $this->markTestSkipped('Required schema tables are missing in TEST_DB_DSN database.');
    }
  }

  private function insertUser(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`discord_id`, `display_name`) VALUES (?, ?)');
    $stmt?->execute(["qa_$token", "QA $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function insertUnitType(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_types`
      (`slug`, `name`, `role`, `base_stats_json`, `ability_set_json`, `max_level`, `attack_per_level`, `defense_per_level`, `max_hp_per_level`)
      VALUES (?, ?, ?, ?, ?, 50, 1, 1, 5)
    ');
    $stmt?->execute([
      "qa_unit_$token",
      "QA Unit $token",
      'fighter',
      json_encode(['attack' => 5, 'defense' => 3, 'max_hp' => 20], JSON_THROW_ON_ERROR),
      json_encode(['active' => [], 'passive' => []], JSON_THROW_ON_ERROR),
    ]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->unitTypeIds[] = $id;
    return $id;
  }

  private function insertUnitInstance(int $userId): int
  {
    $unitTypeId = $this->insertUnitType();
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, 0, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->unitIds[] = $id;
    return $id;
  }

  private function cleanupInsertedData(): void
  {
    if ($this->pdo === null) {
      return;
    }

    if (count($this->teamIds) > 0) {
      $this->deleteByIds('team_formation', 'team_id', $this->teamIds);
      $this->deleteByIds('team_units', 'team_id', $this->teamIds);
      $this->deleteByIds('teams', 'id', $this->teamIds);
    }

    if (count($this->unitIds) > 0) {
      $this->deleteByIds('unit_instances', 'id', $this->unitIds);
    }

    if (count($this->unitTypeIds) > 0) {
      $this->deleteByIds('unit_types', 'id', $this->unitTypeIds);
    }

    if (count($this->userIds) > 0) {
      $this->deleteByIds('users', 'id', $this->userIds);
    }
  }

  /**
   * @param array<int,int> $ids
   */
  private function deleteByIds(string $table, string $idColumn, array $ids): void
  {
    $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
    if (count($ids) === 0) {
      return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $this->pdo?->prepare("DELETE FROM `$table` WHERE `$idColumn` IN ($placeholders)");
    $stmt?->execute($ids);
  }
}
