<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\RunBattleIdempotencyTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RunBattleIdempotencyTest extends TestCase
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
  /** @var array<int,int> */
  private array $battleIds = [];

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run idempotency regression tests.');
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
      if (count($this->battleIds) > 0) {
        $this->deleteByIds('battle_rewards', 'battle_id', $this->battleIds);
        $this->deleteByIds('battle_logs', 'battle_id', $this->battleIds);
        $this->deleteByIds('battles', 'id', $this->battleIds);
      }
      if (count($this->nodeIds) > 0) {
        $this->deleteByIds('run_edges', 'to_node_id', $this->nodeIds);
        $this->deleteByIds('run_edges', 'from_node_id', $this->nodeIds);
        $this->deleteByIds('run_nodes', 'id', $this->nodeIds);
      }
      if (count($this->runIds) > 0) {
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

  public function testResolveNodeAndClaimBattleAreIdempotent(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $runNodeController = new RunNodeController();
    $firstResolve = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));
    $secondResolve = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $firstResolve['status']);
    $this->assertSame(200, $secondResolve['status']);
    $firstBattleId = (int)($firstResolve['body']['data']['battle']['battle_id'] ?? 0);
    $secondBattleId = (int)($secondResolve['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $firstBattleId);
    $this->assertSame($firstBattleId, $secondBattleId);
    $this->battleIds[] = $firstBattleId;

    $battleCount = (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `node_id` = ?', [$runId, $nodeId]);
    $rewardCount = (int)$this->scalar('SELECT COUNT(*) FROM `battle_rewards` WHERE `battle_id` = ?', [$firstBattleId]);
    $logCount = (int)$this->scalar('SELECT COUNT(*) FROM `battle_logs` WHERE `battle_id` = ?', [$firstBattleId]);
    $nodeStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nodeId]);

    $this->assertSame(1, $battleCount);
    $this->assertSame(1, $rewardCount);
    $this->assertSame(1, $logCount);
    $this->assertContains($nodeStatus, ['cleared', 'available']);

    $battleController = new BattleController();
    $firstClaim = $this->invoke(fn() => $battleController->claimBattle((string)$firstBattleId));
    $secondClaim = $this->invoke(fn() => $battleController->claimBattle((string)$firstBattleId));

    $this->assertSame(200, $firstClaim['status']);
    $this->assertSame(200, $secondClaim['status']);
    $this->assertSame('claimed', $firstClaim['body']['data']['status'] ?? null);
    $this->assertSame('claimed', $secondClaim['body']['data']['status'] ?? null);

    $battleStatus = (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$firstBattleId]);
    $this->assertSame('claimed', $battleStatus);
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battle_rewards` WHERE `battle_id` = ?', [$firstBattleId]));
  }

  public function testResolveNodeUsesDeterministicSeedDerivedFromRunAndContext(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $runNodeController = new RunNodeController();
    $resolve = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $resolve['status']);
    $battleId = (int)($resolve['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $this->battleIds[] = $battleId;

    $actualSeed = (int)$this->scalar('SELECT `seed` FROM `battles` WHERE `id` = ?', [$battleId]);
    $runSeed = (string)$this->scalar('SELECT `seed` FROM `region_runs` WHERE `id` = ?', [$runId]);

    $expectedSeed = $this->deriveExpectedSeed(
      $userId,
      $runId,
      $runSeed,
      $nodeId,
      $teamId,
      null
    );

    $this->assertSame($expectedSeed, $actualSeed);
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
    $stmt?->execute(["qa_idem_$token", "QA Idempotency $token"]);
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
    $stmt?->execute(["qa-region-$token", "QA Region $token", "qa_theme"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->regionIds[] = $id;
    return $id;
  }

  private function insertTeam(int $userId): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, 1)');
    $stmt?->execute([$userId, 'QA Team']);
    $id = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $id;
    return $id;
  }

  private function insertRun(int $userId, int $regionId): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`)
      VALUES (?, ?, 12345, \'active\')
    ');
    $stmt?->execute([$userId, $regionId]);
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
   * @param array<int,int|string> $params
   */
  private function scalar(string $sql, array $params): int|string
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $value = $stmt?->fetchColumn();
    return is_string($value) || is_int($value) ? $value : (string)$value;
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

  private function deriveExpectedSeed(
    int $userId,
    int $runId,
    string $runSeed,
    int $nodeId,
    int $teamId,
    ?int $encounterTemplateId
  ): int {
    $seedKey = sprintf(
      'seed_v2|user:%d|run:%d|run_seed:%s|node:%d|team:%d|enc:%s',
      $userId,
      $runId,
      $runSeed,
      $nodeId,
      $teamId,
      $encounterTemplateId !== null ? (string)$encounterTemplateId : 'none'
    );

    $rngState = hash('sha256', $seedKey);
    $seedHex = substr($rngState, 0, 15);
    $seed = (int)base_convert($seedHex, 16, 10);
    return $seed > 0 ? $seed : 1;
  }
}
