<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
  protected ?PDO $testPdo = null;

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';

    if ($dsn === '') {
      $this->markTestSkipped('Set TEST_DB_DSN to run database integration tests.');
    }

    $this->testPdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $this->testPdo->beginTransaction();
  }

  protected function tearDown(): void
  {
    if ($this->testPdo !== null && $this->testPdo->inTransaction()) {
      $this->testPdo->rollBack();
    }
    $this->testPdo = null;

    parent::tearDown();
  }

  protected function applyFixture(string $fixturePath): void
  {
    if ($this->testPdo === null) {
      $this->fail('Test database connection is not initialized.');
    }

    if (!is_file($fixturePath)) {
      $this->fail('Fixture file not found: ' . $fixturePath);
    }

    $sql = (string)file_get_contents($fixturePath);
    if (trim($sql) === '') {
      return;
    }

    $this->testPdo->exec($sql);
  }
}
