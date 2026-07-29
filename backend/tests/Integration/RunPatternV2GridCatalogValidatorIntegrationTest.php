<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternV2GridCatalogValidator;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunPatternV2GridCatalogValidatorIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run-pattern V2 grid catalog validator integration tests.';
  }

  public function testMigrationSeededPatternV2DefinitionsValidateFromDatabase(): void
  {
    $this->applyMigration('79_seed_pattern_v2_catalog.sql');
    $this->applyMigration('80_fix_pattern_v2_perimeter_exits.sql');

    $definitions = (new RunPatternCatalogRepository($this->pdo))->listEnabledPatternDefinitions();
    $result = (new RunPatternV2GridCatalogValidator())->validateDefinitions($definitions);

    $this->assertTrue($result['valid'], implode("\n", $result['errors']));
    $this->assertSame([], $result['errors']);
    $this->assertSame(4, $result['pattern_count']);
  }

  private function applyMigration(string $filename): void
  {
    $path = dirname(__DIR__, 2) . '/migrations/' . $filename;
    $sql = file_get_contents($path);
    $this->assertIsString($sql);
    $this->pdo->exec($sql);
  }
}
