<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ApiControllerMutationSecurityTest extends TestCase
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
    if ($this->pdo !== null && count($this->userIds) > 0) {
      $placeholders = implode(',', array_fill(0, count($this->userIds), '?'));
      $stmt = $this->pdo->prepare("DELETE FROM `users` WHERE `id` IN ($placeholders)");
      $stmt->execute($this->userIds);
    }

    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $this->resetDbSingleton();
    $this->pdo = null;
    parent::tearDown();
  }

  public function testCreateRunRequiresAuthentication(): void
  {
    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->createRun());

    $this->assertSame(401, $response['status']);
    $this->assertSame('unauthorized', $response['body']['error']['code'] ?? null);
  }

  public function testCreateRunRejectsInvalidCsrfWhenAuthenticated(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->createRun());

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
    $stmt?->execute(["qa_api_$token", "QA API $token"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->userIds[] = $id;
    return $id;
  }

  private function resetDbSingleton(): void
  {
    $ref = new ReflectionClass(Db::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
  }
}
