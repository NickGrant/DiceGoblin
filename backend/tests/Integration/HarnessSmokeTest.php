<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Tests\Support\DatabaseTestCase;

final class HarnessSmokeTest extends DatabaseTestCase
{
  public function testFixtureHelperExecutesSqlAgainstTestDatabase(): void
  {
    $fixture = __DIR__ . '/../Fixtures/smoke_fixture.sql';
    $this->applyFixture($fixture);

    $stmt = $this->testPdo?->query('SELECT COUNT(*) AS c FROM qa_smoke_fixture');
    $row = $stmt?->fetch();

    $this->assertSame('1', (string)($row['c'] ?? '0'));
  }
}
