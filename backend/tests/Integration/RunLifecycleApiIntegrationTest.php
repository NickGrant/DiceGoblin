<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RunLifecycleApiIntegrationTest extends TestCase
{
  private ?PDO $pdo = null;
  /** @var array<int,int> */
  private array $userIds = [];

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run lifecycle integration tests.');
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
    if ($this->pdo !== null && count($this->userIds) > 0) {
      $this->deleteUserOwnedData($this->userIds);
    }

    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  public function testStartRunResolveNodeAndClaimBattleLifecycleContracts(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $apiController = new ApiController();
    $sessionRes = $this->invoke(fn() => $apiController->session());
    $this->assertSame(200, $sessionRes['status'], json_encode($sessionRes['body']));
    $this->assertSame(true, $sessionRes['body']['ok'] ?? null);
    $this->assertSame(true, $sessionRes['body']['data']['authenticated'] ?? null);

    $createRes = $this->invoke(fn() => $apiController->createRun());
    $this->assertSame(200, $createRes['status'], json_encode($createRes['body']));
    $this->assertSame(true, $createRes['body']['ok'] ?? null);

    $runId = $this->fetchActiveRunId($userId);
    $this->assertGreaterThan(0, $runId);

    $nodeId = $this->fetchAvailableNodeId($runId);
    $this->assertGreaterThan(0, $nodeId);

    $runNodeController = new RunNodeController();
    $resolveRes = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $resolveRes['status'], json_encode($resolveRes['body']));
    $this->assertSame(true, $resolveRes['body']['ok'] ?? null);

    $battle = is_array($resolveRes['body']['data']['battle'] ?? null) ? $resolveRes['body']['data']['battle'] : [];
    $battleId = (int)($battle['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $this->assertContains((string)($battle['outcome'] ?? ''), ['victory', 'defeat']);

    $statusAfterResolve = (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$battleId]);
    $this->assertSame('completed', $statusAfterResolve);

    $battleController = new BattleController();
    $firstClaim = $this->invoke(fn() => $battleController->claimBattle((string)$battleId));
    $secondClaim = $this->invoke(fn() => $battleController->claimBattle((string)$battleId));

    $this->assertSame(200, $firstClaim['status'], json_encode($firstClaim['body']));
    $this->assertSame(200, $secondClaim['status'], json_encode($secondClaim['body']));
    $this->assertSame(true, $firstClaim['body']['ok'] ?? null);

    $firstData = is_array($firstClaim['body']['data'] ?? null) ? $firstClaim['body']['data'] : [];
    $secondData = is_array($secondClaim['body']['data'] ?? null) ? $secondClaim['body']['data'] : [];
    $this->assertSame('claimed', (string)($firstData['status'] ?? ''));
    $this->assertSame($firstData, $secondData, 'Claim payload should be idempotent across repeated calls.');

    $this->assertArrayHasKey('xp', $firstData);
    $this->assertArrayHasKey('rewards', $firstData);
    $this->assertArrayHasKey('updated_run_unit_state', $firstData);
    $this->assertArrayHasKey('run_resolution', $firstData);

    $statusAfterClaim = (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$battleId]);
    $this->assertSame('claimed', $statusAfterClaim);

    $runStatus = (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $this->assertContains($runStatus, ['active', 'completed', 'failed', 'abandoned']);
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
    $stmt?->execute(["qa_lifecycle_$token", "QA Lifecycle $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function fetchActiveRunId(int $userId): int
  {
    return (int)$this->scalar(
      'SELECT `id` FROM `region_runs` WHERE `user_id` = ? AND `status` = \'active\' ORDER BY `id` DESC LIMIT 1',
      [$userId]
    );
  }

  private function fetchAvailableNodeId(int $runId): int
  {
    return (int)$this->scalar(
      'SELECT `id` FROM `run_nodes` WHERE `run_id` = ? AND `status` = \'available\' ORDER BY `node_index` ASC LIMIT 1',
      [$runId]
    );
  }

  /**
   * @param array<int,int|string> $params
   * @return int|string
   */
  private function scalar(string $sql, array $params): int|string
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
    if (count($userIds) === 0) {
      return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $this->execDeleteByUserIds("DELETE br FROM `battle_rewards` br JOIN `battles` b ON b.`id` = br.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE bl FROM `battle_logs` bl JOIN `battles` b ON b.`id` = bl.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `battles` WHERE `user_id` IN ($placeholders)", $userIds);

    $this->execDeleteByUserIds("DELETE re FROM `run_edges` re JOIN `region_runs` rr ON rr.`id` = re.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE rus FROM `run_unit_state` rus JOIN `region_runs` rr ON rr.`id` = rus.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE rn FROM `run_nodes` rn JOIN `region_runs` rr ON rr.`id` = rn.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `region_runs` WHERE `user_id` IN ($placeholders)", $userIds);

    $this->execDeleteByUserIds("DELETE tf FROM `team_formation` tf JOIN `teams` t ON t.`id` = tf.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE tu FROM `team_units` tu JOIN `teams` t ON t.`id` = tu.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE ud FROM `unit_dice` ud JOIN `unit_instances` ui ON ui.`id` = ud.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE dia FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` IN ($placeholders)", $userIds);

    $this->execDeleteByUserIds("DELETE FROM `user_grants` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `unit_promotions` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `user_region_items` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `region_unlocks` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `dice_instances` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `unit_instances` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `teams` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `energy_state` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `player_state` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `users` WHERE `id` IN ($placeholders)", $userIds);
  }

  /**
   * @param array<int,int> $userIds
   */
  private function execDeleteByUserIds(string $sql, array $userIds): void
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
