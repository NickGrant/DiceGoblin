<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ApiControllerCreateRunDomainErrorsTest extends TestCase
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

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run create-run branch integration tests.');
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
    if ($this->pdo !== null && count($this->regionIds) > 0) {
      $this->deleteByIds('regions', 'id', $this->regionIds);
    }

    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  public function testCreateRunReturnsConflictWhenRunAlreadyActive(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion(5, true);
    $this->unlockRegion($userId, $regionId);
    $this->insertTeam($userId, true);
    $this->setEnergy($userId, 50, 50);
    $this->insertActiveRun($userId, $regionId, 1234);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['region_id' => (string)$regionId];

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(409, $res['status']);
    $this->assertSame('run_already_active', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsNotFoundWhenRegionDoesNotExist(): void
  {
    $userId = $this->insertUser();
    $this->insertTeam($userId, true);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['region_id' => '999999'];

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(404, $res['status']);
    $this->assertSame('region_not_found', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsForbiddenWhenRegionDisabled(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion(5, false);
    $this->insertTeam($userId, true);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['region_id' => (string)$regionId];

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(403, $res['status']);
    $this->assertSame('region_disabled', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsForbiddenWhenRegionLocked(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion(5, true);
    $this->insertTeam($userId, true);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['region_id' => (string)$regionId];

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(403, $res['status']);
    $this->assertSame('region_locked', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsValidationErrorWhenNoActiveSquadExists(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion(5, true);
    $this->unlockRegion($userId, $regionId);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['region_id' => (string)$regionId];

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(400, $res['status']);
    $this->assertSame('validation_error', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsInsufficientEnergyWhenCostCannotBePaid(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion(20, true);
    $this->unlockRegion($userId, $regionId);
    $this->insertTeam($userId, true);
    $this->setEnergy($userId, 0, 50);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `teams` WHERE `user_id` = ? AND `is_active` = 1', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `region_unlocks` WHERE `user_id` = ? AND `region_id` = ?', [$userId, $regionId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `energy_current` FROM `energy_state` WHERE `user_id` = ?', [$userId]));

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['region_id' => (string)$regionId];

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(409, $res['status'], json_encode($res['body']));
    $this->assertSame('insufficient_energy', (string)($res['body']['error']['code'] ?? ''), json_encode($res['body']));
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
    $stmt?->execute(["qa_create_run_$token", "QA Create Run $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function insertRegion(int $energyCost, bool $enabled): int
  {
    $token = bin2hex(random_bytes(4));
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `regions` (`slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`) VALUES (?, ?, ?, 1, ?, ?)'
    );
    $stmt?->execute(["qa-region-$token", "QA Region $token", "qa_theme", $energyCost, $enabled ? 1 : 0]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->regionIds[] = $id;
    return $id;
  }

  private function unlockRegion(int $userId, int $regionId): void
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `region_unlocks` (`user_id`, `region_id`) VALUES (?, ?)');
    $stmt?->execute([$userId, $regionId]);
  }

  private function insertTeam(int $userId, bool $active): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, ?)');
    $stmt?->execute([$userId, 'QA Squad', $active ? 1 : 0]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->teamIds[] = $id;
    return $id;
  }

  private function setEnergy(int $userId, int $current, int $max): void
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `energy_state` (`user_id`, `energy_current`, `energy_max`, `regen_rate_per_hour`, `last_regen_at`)
       VALUES (?, ?, ?, 6.00, UTC_TIMESTAMP())
       ON DUPLICATE KEY UPDATE `energy_current` = VALUES(`energy_current`), `energy_max` = VALUES(`energy_max`), `last_regen_at` = VALUES(`last_regen_at`)"
    );
    $stmt?->execute([$userId, $current, $max]);
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

  private function insertActiveRun(int $userId, int $regionId, int $seed): void
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`) VALUES (?, ?, ?, 'active')"
    );
    $stmt?->execute([$userId, $regionId, $seed]);
    $this->runIds[] = (int)$this->pdo?->lastInsertId();
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

  /**
   * @param array<int,int> $userIds
   */
  private function deleteUserOwnedData(array $userIds): void
  {
    $userIds = array_values(array_unique(array_filter($userIds, static fn(int $v): bool => $v > 0)));
    if (count($userIds) === 0) return;
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
