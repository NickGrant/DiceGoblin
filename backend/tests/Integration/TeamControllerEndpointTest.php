<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\TeamControllerEndpointTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\TeamController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TeamControllerEndpointTest extends TestCase
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
      $this->markTestSkipped('Set TEST_DB_DSN to run endpoint integration tests.');
    }

    $this->pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    http_response_code(200);
    $this->resetDbSingleton();
  }

  protected function tearDown(): void
  {
    if ($this->pdo !== null) {
      if (count($this->teamIds) > 0) {
        $this->deleteByIds('team_formation', 'team_id', $this->teamIds);
        $this->deleteByIds('team_units', 'team_id', $this->teamIds);
        $this->deleteByIds('teams', 'id', $this->teamIds);
      }
      if (count($this->userIds) > 0) {
        $this->deleteByIds('users', 'id', $this->userIds);
      }
    }

    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  public function testCreateTeamRequiresAuthentication(): void
  {
    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->createTeam());

    $this->assertSame(401, $response['status']);
    $this->assertSame('unauthorized', $response['body']['error']['code'] ?? null);
  }

  public function testCreateTeamRejectsInvalidCsrfWhenAuthenticated(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->createTeam());

    $this->assertSame(403, $response['status']);
    $this->assertSame('csrf_invalid', $response['body']['error']['code'] ?? null);
  }

  public function testActivateTeamRejectsCrossUserOwnership(): void
  {
    $ownerId = $this->insertUser();
    $otherUserId = $this->insertUser();
    $teamId = $this->insertTeam($ownerId, 'Owned Team', 1);

    $_SESSION['user_id'] = $otherUserId;
    $_SESSION['csrf_token'] = 'valid_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_token';

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->activateTeam((string)$teamId));

    $this->assertSame(400, $response['status']);
    $this->assertSame('validation_error', $response['body']['error']['code'] ?? null);
    $this->assertStringContainsString('not owned', (string)($response['body']['error']['message'] ?? ''));
  }

  public function testUpdateTeamRejectsInvalidCsrfWhenAuthenticated(): void
  {
    $ownerId = $this->insertUser();
    $teamId = $this->insertTeam($ownerId, 'Owned Team', 1);

    $_SESSION['user_id'] = $ownerId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->updateTeam((string)$teamId));

    $this->assertSame(403, $response['status']);
    $this->assertSame('csrf_invalid', $response['body']['error']['code'] ?? null);
  }

  /**
   * @return array{status:int,body:array<string,mixed>}
   */
  private function invoke(callable $fn): array
  {
    http_response_code(200);

    ob_start();
    $fn();
    $raw = (string)ob_get_clean();

    $decoded = json_decode($raw, true);
    return [
      'status' => http_response_code(),
      'body' => is_array($decoded) ? $decoded : [],
    ];
  }

  private function insertUser(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`discord_id`, `display_name`) VALUES (?, ?)');
    $stmt?->execute(["qa_ep_$token", "QA Endpoint $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function insertTeam(int $userId, string $name, int $isActive): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, ?)');
    $stmt?->execute([$userId, $name, $isActive]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $id;
    return $id;
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

  private function resetDbSingleton(): void
  {
    $ref = new ReflectionClass(Db::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
  }
}
