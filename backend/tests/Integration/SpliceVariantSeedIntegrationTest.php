<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Tests\Support\IntegrationTestCase;

final class SpliceVariantSeedIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run splice variant seed integration tests.';
  }

  public function testLaunchSpliceVariantsAreSeededWithWeightsAndModifiers(): void
  {
    $stmt = $this->pdo?->query('SELECT `slug`, `grant_weight`, `stat_modifiers_json` FROM `splice_variants` ORDER BY `slug` ASC');
    $rows = is_object($stmt) ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    $this->assertCount(5, $rows);

    $bySlug = [];
    foreach ($rows as $row) {
      $bySlug[(string)$row['slug']] = $row;
      $modifiers = json_decode((string)$row['stat_modifiers_json'], true);
      $this->assertIsArray($modifiers);
      $this->assertArrayHasKey('precision', $modifiers);
      $this->assertArrayHasKey('resolve', $modifiers);
      $this->assertGreaterThan(0, (int)($row['grant_weight'] ?? 0));
    }

    $this->assertArrayHasKey('basic_goblin', $bySlug);
    $this->assertArrayHasKey('rat_splice', $bySlug);
    $this->assertArrayHasKey('toad_splice', $bySlug);
    $this->assertArrayHasKey('bat_splice', $bySlug);
    $this->assertArrayHasKey('pig_kin', $bySlug);
  }
}
