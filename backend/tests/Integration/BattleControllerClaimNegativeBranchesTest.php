<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BattleControllerClaimNegativeBranchesTest extends TestCase
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
      $this->markTestSkipped('Set TEST_DB_DSN to run claim negative-branch tests.');
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
        $this->deleteByIds('run_nodes', 'id', $this->nodeIds);
      }
      if (count($this->runIds) > 0) {
        $this->deleteByIds('region_runs', 'id', $this->runIds);
      }
      if (count($this->teamIds) > 0) {
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

  public function testClaimRejectsBattleNotCompletedState(): void
  {
    $userId = $this->insertUser();
    [$runId, $nodeId, $teamId] = $this->seedRunGraphScaffold($userId);
    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'pending', 'victory');
    $this->insertBattleRewards($battleId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('battle_not_completed', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testClaimRejectsOwnershipMismatch(): void
  {
    $ownerId = $this->insertUser();
    $attackerId = $this->insertUser();
    [$runId, $nodeId, $teamId] = $this->seedRunGraphScaffold($ownerId);
    $battleId = $this->insertBattle($ownerId, $runId, $nodeId, $teamId, 'completed', 'victory');
    $this->insertBattleRewards($battleId);

    $_SESSION['user_id'] = $attackerId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(403, $res['status']);
    $this->assertSame('forbidden', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testClaimRejectsInvalidOutcomeState(): void
  {
    $userId = $this->insertUser();
    [$runId, $nodeId, $teamId] = $this->seedRunGraphScaffold($userId);
    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'draw');
    $this->insertBattleRewards($battleId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(500, $res['status']);
    $this->assertSame('server_error', (string)($res['body']['error']['code'] ?? ''));
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
    $stmt?->execute(["qa_claim_$token", "QA Claim $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  /**
   * @return array{0:int,1:int,2:int}
   */
  private function seedRunGraphScaffold(int $userId): array
  {
    $token = bin2hex(random_bytes(4));
    $regionStmt = $this->pdo?->prepare(
      'INSERT INTO `regions` (`slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`) VALUES (?, ?, ?, 1, 5, 1)'
    );
    $regionStmt?->execute(["qa-claim-region-$token", "QA Claim Region $token", 'qa_theme']);
    $regionId = (int)$this->pdo?->lastInsertId();
    $this->regionIds[] = $regionId;

    $teamStmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, 1)');
    $teamStmt?->execute([$userId, 'QA Team']);
    $teamId = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $teamId;

    $runStmt = $this->pdo?->prepare(
      "INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`) VALUES (?, ?, 10101, 'active')"
    );
    $runStmt?->execute([$userId, $regionId]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->runIds[] = $runId;

    $nodeStmt = $this->pdo?->prepare(
      "INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`) VALUES (?, 1, 'combat', 'available', NULL, NULL)"
    );
    $nodeStmt?->execute([$runId]);
    $nodeId = (int)$this->pdo?->lastInsertId();
    $this->nodeIds[] = $nodeId;

    return [$runId, $nodeId, $teamId];
  }

  private function insertBattle(int $userId, int $runId, int $nodeId, int $teamId, string $status, string $outcome): int
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `battles` (`user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`)
       VALUES (?, ?, ?, ?, 'combat_v1', 12345, ?, ?, 1, 1)"
    );
    $stmt?->execute([$userId, $runId, $nodeId, $teamId, $status, $outcome]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->battleIds[] = $id;
    return $id;
  }

  private function insertBattleRewards(int $battleId): void
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `battle_rewards` (`battle_id`, `xp_total`, `currency_soft`, `rewards_json`) VALUES (?, 0, 0, '{\"new_dice_instance_ids\":[],\"region_items\":[]}')"
    );
    $stmt?->execute([$battleId]);
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
