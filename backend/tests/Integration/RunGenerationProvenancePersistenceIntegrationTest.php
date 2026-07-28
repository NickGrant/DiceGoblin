<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunGenerationProvenancePersistenceIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run generation provenance persistence integration tests.';
  }

  public function testCreateRunGraphPersistsOptionalGenerationMetadata(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->seededRegionId('mountains');

    $created = (new RunRepository($this->pdo))->createRunGraph(
      $userId,
      $regionId,
      '12345',
      [
        ['node_index' => 0, 'node_type' => 'combat', 'status' => 'available', 'meta' => ['col' => 0, 'row' => 0]],
        ['node_index' => 1, 'node_type' => 'boss', 'status' => 'locked', 'meta' => ['col' => 1, 'row' => 0]],
        ['node_index' => 2, 'node_type' => 'exit', 'status' => 'locked', 'meta' => ['col' => 2, 'row' => 0]],
      ],
      [
        ['from' => 0, 'to' => 1],
        ['from' => 1, 'to' => 2],
      ],
      [
        'generator_version' => 'pattern-v1',
        'profile_version' => 1,
        'catalog_hash' => str_repeat('b', 64),
        'generation_attempt' => 0,
        'node_count' => 3,
        'validation_failures' => [],
      ]
    );

    $stmt = $this->pdo->prepare('
      SELECT
        `generator_version`,
        `generation_profile_version`,
        `pattern_catalog_hash`,
        `generation_attempt`,
        `generation_summary_json`
      FROM `region_runs`
      WHERE `id` = ?
    ');
    $stmt->execute([(int)$created['run_id']]);
    $row = $stmt->fetch();
    $this->assertIsArray($row);

    $this->assertSame('pattern-v1', (string)$row['generator_version']);
    $this->assertSame(1, (int)$row['generation_profile_version']);
    $this->assertSame(str_repeat('b', 64), (string)$row['pattern_catalog_hash']);
    $this->assertSame(0, (int)$row['generation_attempt']);

    $summary = json_decode((string)$row['generation_summary_json'], true);
    $this->assertIsArray($summary);
    $this->assertSame(3, (int)$summary['node_count']);
    $this->assertSame([], $summary['validation_failures']);
  }

  private function seededRegionId(string $slug): int
  {
    $regionId = (int)$this->scalar('SELECT `id` FROM `regions` WHERE `slug` = ? LIMIT 1', [$slug]);
    $this->assertGreaterThan(0, $regionId, sprintf('Seeded region `%s` must exist.', $slug));
    return $regionId;
  }
}
