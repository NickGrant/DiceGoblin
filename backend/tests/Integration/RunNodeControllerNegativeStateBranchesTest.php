<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RunNodeControllerNegativeStateBranchesTest extends TestCase
{
  private ?PDO $pdo = null;
  /** @var array<int,int> */
  private array $userIds = [];
  /** @var array<int,int> */
  private array $regionIds = [];
  /** @var array<int,int> */
  private array $teamIds = [];
  /** @var array<int,int> */
  private array $runIds = [];
  /** @var array<int,int> */
  private array $nodeIds = [];

  protected function setUp(): void
  {
    parent::setUp();
    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run run-node negative branch tests.');
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
      if (count($this->nodeIds) > 0) {
        $this->deleteByIds('run_edges', 'to_node_id', $this->nodeIds);
        $this->deleteByIds('run_edges', 'from_node_id', $this->nodeIds);
        $this->deleteByIds('run_nodes', 'id', $this->nodeIds);
      }
      if (count($this->runIds) > 0) {
        $this->deleteByIds('run_unit_state', 'run_id', $this->runIds);
        $this->deleteByIds('region_runs', 'id', $this->runIds);
      }
      if (count($this->teamIds) > 0) {
        $this->deleteByIds('team_formation', 'team_id', $this->teamIds);
        $this->deleteByIds('team_units', 'team_id', $this->teamIds);
        $this->deleteByIds('teams', 'id', $this->teamIds);
      }
      if (count($this->regionIds) > 0) {
        $this->deleteByIds('regions', 'id', $this->regionIds);
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

  public function testResolveNodeRejectsRunNotActive(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $this->insertTeam($userId, true);
    $runId = $this->insertRun($userId, $regionId, 'failed');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('run_not_active', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeRejectsLockedNode(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $this->insertTeam($userId, true);
    $runId = $this->insertRun($userId, $regionId, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'locked');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('node_not_available', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeRejectsOwnershipMismatch(): void
  {
    $ownerId = $this->insertUser();
    $attackerId = $this->insertUser();
    $regionId = $this->insertRegion();
    $this->insertTeam($ownerId, true);
    $runId = $this->insertRun($ownerId, $regionId, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $attackerId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(403, $res['status']);
    $this->assertSame('forbidden', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeRejectsTeamIdNotOwnedByRequester(): void
  {
    $userId = $this->insertUser();
    $otherUserId = $this->insertUser();
    $regionId = $this->insertRegion();
    $this->insertTeam($userId, true);
    $otherTeamId = $this->insertTeam($otherUserId, true);
    $runId = $this->insertRun($userId, $regionId, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['team_id' => (string)$otherTeamId];

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(400, $res['status']);
    $this->assertSame('validation_error', (string)($res['body']['error']['code'] ?? ''));
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
    $stmt?->execute(["qa_run_node_$token", "QA Run Node $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function insertRegion(): int
  {
    $token = bin2hex(random_bytes(4));
    $stmt = $this->pdo?->prepare('
      INSERT INTO `regions` (`slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`)
      VALUES (?, ?, ?, 1, 5, 1)
    ');
    $stmt?->execute(["qa-node-region-$token", "QA Node Region $token", 'qa_theme']);
    $id = (int)$this->pdo?->lastInsertId();
    $this->regionIds[] = $id;
    return $id;
  }

  private function insertTeam(int $userId, bool $active): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, ?)');
    $stmt?->execute([$userId, 'QA Team', $active ? 1 : 0]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $id;
    return $id;
  }

  private function insertRun(int $userId, int $regionId, string $status): int
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`) VALUES (?, ?, 7777, ?)"
    );
    $stmt?->execute([$userId, $regionId, $status]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->runIds[] = $id;
    return $id;
  }

  private function insertRunNode(int $runId, string $nodeType, string $status): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, 1, ?, ?, NULL, NULL)
    ');
    $stmt?->execute([$runId, $nodeType, $status]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->nodeIds[] = $id;
    return $id;
  }

  /**
   * @param array<int,int> $ids
   */
  private function deleteByIds(string $table, string $idColumn, array $ids): void
  {
    $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
    if (count($ids) === 0) return;
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
