<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class IntegrationTestCase extends TestCase
{
  protected ?PDO $pdo = null;

  /** @var array<int,int> */
  private array $trackedUserIds = [];

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped($this->integrationSkipMessage());
    }

    $this->pdo = new PDO($dsn, getenv('TEST_DB_USER') ?: '', getenv('TEST_DB_PASS') ?: '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $this->resetHttpGlobals();
    $this->resetDbSingleton();
  }

  protected function tearDown(): void
  {
    if ($this->pdo !== null && $this->trackedUserIds !== []) {
      $ids = array_values(array_unique($this->trackedUserIds));
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $deleteRuns = $this->pdo->prepare("DELETE FROM `runs` WHERE `user_id` IN ($placeholders)");
      $deleteRuns->execute($ids);
      $deleteUsers = $this->pdo->prepare("DELETE FROM `users` WHERE `id` IN ($placeholders)");
      $deleteUsers->execute($ids);
    }

    $this->trackedUserIds = [];
    $this->resetHttpGlobals();
    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run integration tests.';
  }

  /** @param array<string,mixed> $body */
  protected function setJsonBody(array $body): void
  {
    $_POST = [];
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /** @return array{status:int,body:array<string,mixed>} */
  protected function invoke(callable $fn): array
  {
    http_response_code(200);
    ob_start();
    try {
      $fn();
      $raw = (string)ob_get_clean();
    } finally {
      $_POST = [];
      unset($_SERVER['DICE_GOBLINS_TEST_RAW_BODY']);
    }

    $decoded = json_decode($raw, true);
    return [
      'status' => http_response_code(),
      'body' => is_array($decoded) ? $decoded : [],
    ];
  }

  protected function trackUserId(int $userId): void
  {
    if ($userId > 0) {
      $this->trackedUserIds[] = $userId;
    }
  }

  /** @param array<int,int|string> $params @return int|string */
  protected function scalar(string $sql, array $params): int|string
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $value = $stmt?->fetchColumn();
    return is_string($value) || is_int($value) ? $value : (string)$value;
  }

  private function resetHttpGlobals(): void
  {
    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    unset($_SERVER['DICE_GOBLINS_TEST_RAW_BODY']);
    http_response_code(200);
  }

  private function resetDbSingleton(): void
  {
    $ref = new ReflectionClass(Db::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
  }
}
