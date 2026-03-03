<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BattleResolutionAndClaimIntegrationTest extends TestCase
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
  /** @var array<int,int> */
  private array $unitIds = [];

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run integration tests.');
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
        $this->deleteByIds('run_unit_state', 'run_id', $this->runIds);
        $this->deleteByIds('region_runs', 'id', $this->runIds);
      }
      if (count($this->teamIds) > 0) {
        $this->deleteByIds('team_formation', 'team_id', $this->teamIds);
        $this->deleteByIds('team_units', 'team_id', $this->teamIds);
        $this->deleteByIds('teams', 'id', $this->teamIds);
      }
      if (count($this->unitIds) > 0) {
        $this->deleteByIds('unit_instances', 'id', $this->unitIds);
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

  public function testResolveNodeUsesDeterministicEngineAndPersistsCanonicalLog(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 11223344);
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $first = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));
    $second = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $first['status']);
    $this->assertSame(200, $second['status']);

    $battleId = (int)($first['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $this->assertSame($battleId, (int)($second['body']['data']['battle']['battle_id'] ?? 0));
    $this->battleIds[] = $battleId;

    $logRaw = $this->scalar('SELECT `log_json` FROM `battle_logs` WHERE `battle_id` = ?', [$battleId]);
    $log = json_decode((string)$logRaw, true);
    $this->assertIsArray($log);

    $meta = is_array($log['meta'] ?? null) ? $log['meta'] : [];
    $events = is_array($log['events'] ?? null) ? $log['events'] : [];

    $this->assertSame('deterministic_v1', (string)($meta['engine'] ?? ''));
    $this->assertGreaterThan(0, (int)($meta['rng']['seed'] ?? 0));
    $this->assertSame((string)$runId, (string)($meta['run_id'] ?? ''));
    $this->assertSame((string)$nodeId, (string)($meta['node_id'] ?? ''));

    $eventTypes = array_map(
      static fn($event): string => is_array($event) ? (string)($event['type'] ?? '') : '',
      $events
    );
    $this->assertContains('battle_start', $eventTypes);
    $this->assertContains('battle_end', $eventTypes);
    $this->assertNotContains('note', $eventTypes, 'Placeholder note event should not be present.');
  }

  public function testClaimBattleAppliesXpOnceAndReturnsIdempotentSnapshot(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 55667788);
    $nodeId = $this->insertRunNode($runId, 'combat', 'cleared');

    [$unitTypeId, $maxLevel] = $this->pickUnitTypeForProgressTest();

    $eligibleUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $maxedUnitId = $this->insertUnit($userId, $unitTypeId, $maxLevel, 5);
    $defeatedUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);

    $this->insertTeamUnit($teamId, $eligibleUnitId);
    $this->insertTeamUnit($teamId, $maxedUnitId);
    $this->insertTeamUnit($teamId, $defeatedUnitId);

    $this->insertRunUnitState($runId, $eligibleUnitId, 12, false);
    $this->insertRunUnitState($runId, $maxedUnitId, 14, false);
    $this->insertRunUnitState($runId, $defeatedUnitId, 0, true);

    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'completed', 'victory', 99112233, 60, 3);
    $this->insertBattleRewards($battleId, 20, 0, [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $first = $this->invoke(fn() => $controller->claimBattle((string)$battleId));
    $second = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(200, $first['status']);
    $this->assertSame(200, $second['status']);

    $firstData = is_array($first['body']['data'] ?? null) ? $first['body']['data'] : [];
    $secondData = is_array($second['body']['data'] ?? null) ? $second['body']['data'] : [];

    $this->assertSame('claimed', (string)($firstData['status'] ?? ''));
    $this->assertSame($firstData, $secondData, 'Claim response should be idempotent across repeated calls.');

    $xp = is_array($firstData['xp'] ?? null) ? $firstData['xp'] : [];
    $applied = is_array($xp['applied_unit_instance_ids'] ?? null) ? $xp['applied_unit_instance_ids'] : [];
    $ignored = is_array($xp['ignored_at_cap_unit_instance_ids'] ?? null) ? $xp['ignored_at_cap_unit_instance_ids'] : [];

    $this->assertContains((string)$eligibleUnitId, $applied);
    $this->assertContains((string)$maxedUnitId, $ignored);
    $this->assertNotContains((string)$defeatedUnitId, $applied);
    $this->assertNotContains((string)$defeatedUnitId, $ignored);

    $this->assertSame('20', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$eligibleUnitId]));
    $this->assertSame('5', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$maxedUnitId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$defeatedUnitId]));

    $rewardsRaw = $this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$battleId]);
    $rewards = json_decode((string)$rewardsRaw, true);
    $this->assertIsArray($rewards);
    $this->assertArrayHasKey('claim_snapshot', $rewards);
    $this->assertIsArray($rewards['claim_snapshot']);
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
    $stmt?->execute(["qa_role_$token", "QA Role $token"]);
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
    $stmt?->execute(["qa-region-$token", "QA Region $token", 'qa_theme']);
    $id = (int)$this->pdo?->lastInsertId();
    $this->regionIds[] = $id;
    return $id;
  }

  private function insertTeam(int $userId): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, 1)');
    $stmt?->execute([$userId, 'QA Squad']);
    $id = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $id;
    return $id;
  }

  private function insertRun(int $userId, int $regionId, int $seed): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`)
      VALUES (?, ?, ?, \'active\')
    ');
    $stmt?->execute([$userId, $regionId, $seed]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->runIds[] = $id;
    return $id;
  }

  private function insertRunNode(int $runId, string $nodeType, string $status): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, ?, ?, ?, NULL, NULL)
    ');
    $stmt?->execute([$runId, random_int(1, 9999), $nodeType, $status]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->nodeIds[] = $id;
    return $id;
  }

  /** @return array{0:int,1:int} */
  private function pickUnitTypeForProgressTest(): array
  {
    $stmt = $this->pdo?->query('SELECT `id`, `max_level` FROM `unit_types` ORDER BY `id` ASC LIMIT 1');
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    $this->assertIsArray($row, 'Expected seeded unit_types rows in test database.');
    return [(int)$row['id'], max(1, (int)$row['max_level'])];
  }

  private function insertUnit(int $userId, int $unitTypeId, int $level, int $xp): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, ?, ?, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId, $level, $xp]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->unitIds[] = $id;
    return $id;
  }

  private function insertTeamUnit(int $teamId, int $unitId): void
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
    $stmt?->execute([$teamId, $unitId]);
  }

  private function insertRunUnitState(int $runId, int $unitId, int $hp, bool $isDefeated): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt?->execute([$runId, $unitId, $hp, $isDefeated ? 1 : 0, '{}', '[]']);
  }

  private function insertBattle(
    int $userId,
    int $runId,
    int $nodeId,
    int $teamId,
    string $status,
    string $outcome,
    int $seed,
    int $ticks,
    int $rounds
  ): int {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `battles` (`user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`)
      VALUES (?, ?, ?, ?, \'combat_v1\', ?, ?, ?, ?, ?)
    ');
    $stmt?->execute([$userId, $runId, $nodeId, $teamId, $seed, $status, $outcome, $ticks, $rounds]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->battleIds[] = $id;
    return $id;
  }

  /**
   * @param array<string,mixed> $rewards
   */
  private function insertBattleRewards(int $battleId, int $xpTotal, int $currencySoft, array $rewards): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `battle_rewards` (`battle_id`, `xp_total`, `currency_soft`, `rewards_json`)
      VALUES (?, ?, ?, ?)
    ');
    $stmt?->execute([$battleId, $xpTotal, $currencySoft, json_encode($rewards, JSON_UNESCAPED_SLASHES)]);
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
}
