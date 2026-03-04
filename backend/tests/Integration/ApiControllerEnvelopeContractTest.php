<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\ApiControllerEnvelopeContractTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ApiControllerEnvelopeContractTest extends TestCase
{
  private ?PDO $pdo = null;
  /** @var array<int,int> */
  private array $userIds = [];
  /** @var array<int,int> */
  private array $regionIds = [];
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
      if (count($this->userIds) > 0) {
        $this->deleteUserOwnedData($this->userIds);
      }

      if (count($this->regionIds) > 0) {
        $this->deleteByIds('regions', 'id', $this->regionIds);
      }
    }

    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  public function testSessionReturnsAuthenticatedSuccessEnvelope(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->session());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertSame(true, $data['authenticated'] ?? null);
    $this->assertIsString($data['csrf_token'] ?? null);
    $this->assertNotSame('', (string)($data['csrf_token'] ?? ''));
    $this->assertIsArray($data['user'] ?? null);
    $this->assertIsString($data['user']['id'] ?? null);
  }

  public function testProfileReturnsSuccessEnvelopeWithContractKeys(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertIsString($data['server_time_iso'] ?? null);
    $this->assertIsArray($data['squads'] ?? null);
    $this->assertIsArray($data['units'] ?? null);
    $this->assertIsArray($data['dice'] ?? null);
    $this->assertIsArray($data['currency'] ?? null);
    $this->assertIsInt($data['currency']['soft'] ?? null);
    $this->assertIsInt($data['currency']['hard'] ?? null);
    $this->assertIsArray($data['energy'] ?? null);
    $this->assertIsInt($data['energy']['current'] ?? null);
    $this->assertIsInt($data['energy']['max'] ?? null);
    $this->assertIsNumeric($data['energy']['regen_rate_per_hour'] ?? null);
    $this->assertIsString($data['energy']['last_regen_at'] ?? null);
    $this->assertIsArray($data['region_unlocks'] ?? null);
    $this->assertIsArray($data['region_items'] ?? null);
    $this->assertArrayHasKey('active_run', $data);
  }

  public function testCurrentRunReturnsSuccessEnvelopeWhenNoActiveRun(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertNull($data['run'] ?? null);
    $this->assertNull($data['map'] ?? null);
  }

  public function testCurrentRunReturnsSuccessEnvelopeWithRunMapArrays(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId);
    $nodeA = $this->insertRunNode($runId, 0, 'combat', 'available');
    $nodeB = $this->insertRunNode($runId, 1, 'loot', 'locked');
    $this->insertRunEdge($runId, $nodeA, $nodeB);

    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertIsArray($data['run'] ?? null);
    $this->assertIsArray($data['map'] ?? null);
    $this->assertIsArray($data['map']['nodes'] ?? null);
    $this->assertIsArray($data['map']['edges'] ?? null);
    $this->assertArrayHasKey('run_unit_state', $data);
    $this->assertIsArray($data['run_unit_state']);
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
    $stmt?->execute(["qa_env_$token", "QA Envelope $token"]);
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
    $stmt?->execute(["qa-envelope-region-$token", "QA Envelope Region $token", 'qa_theme']);
    $id = (int)$this->pdo?->lastInsertId();
    $this->regionIds[] = $id;
    return $id;
  }

  private function insertRun(int $userId, int $regionId): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`)
      VALUES (?, ?, 555111, \'active\')
    ');
    $stmt?->execute([$userId, $regionId]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->runIds[] = $id;
    return $id;
  }

  private function insertRunNode(int $runId, int $nodeIndex, string $nodeType, string $status): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, ?, ?, ?, NULL, ?)
    ');
    $stmt?->execute([$runId, $nodeIndex, $nodeType, $status, '{"col":0,"row":0}']);
    $id = (int)$this->pdo?->lastInsertId();
    $this->nodeIds[] = $id;
    return $id;
  }

  private function insertRunEdge(int $runId, int $fromNodeId, int $toNodeId): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`)
      VALUES (?, ?, ?)
    ');
    $stmt?->execute([$runId, $fromNodeId, $toNodeId]);
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

    // Battle artifacts
    $this->execDeleteByUserIds("DELETE br FROM `battle_rewards` br JOIN `battles` b ON b.`id` = br.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE bl FROM `battle_logs` bl JOIN `battles` b ON b.`id` = bl.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `battles` WHERE `user_id` IN ($placeholders)", $userIds);

    // Run graph/state
    $this->execDeleteByUserIds("DELETE re FROM `run_edges` re JOIN `region_runs` rr ON rr.`id` = re.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE rus FROM `run_unit_state` rus JOIN `region_runs` rr ON rr.`id` = rus.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE rn FROM `run_nodes` rn JOIN `region_runs` rr ON rr.`id` = rn.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `region_runs` WHERE `user_id` IN ($placeholders)", $userIds);

    // Team + unit/dice linkage
    $this->execDeleteByUserIds("DELETE tf FROM `team_formation` tf JOIN `teams` t ON t.`id` = tf.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE tu FROM `team_units` tu JOIN `teams` t ON t.`id` = tu.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE ud FROM `unit_dice` ud JOIN `unit_instances` ui ON ui.`id` = ud.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE dia FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` IN ($placeholders)", $userIds);

    // User-owned primitives and progression
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
