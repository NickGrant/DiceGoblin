<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ExpandedCombatStatsSeedIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run expanded combat stat seed integration tests.';
  }

  public function testSeededUnitTypesExposeAuthoredPrecisionAndResolve(): void
  {
    $stmt = $this->pdo?->query('
      SELECT `slug`, `base_stats_json`
      FROM `unit_types`
      ORDER BY `slug` ASC
    ');
    $rows = $stmt?->fetchAll() ?: [];
    $this->assertNotSame([], $rows, 'Expected seeded unit types.');

    $bySlug = [];
    foreach ($rows as $row) {
      $slug = (string)($row['slug'] ?? '');
      $stats = json_decode((string)($row['base_stats_json'] ?? ''), true);
      $this->assertIsArray($stats, "Expected valid base stats JSON for {$slug}.");
      $this->assertArrayHasKey('precision', $stats, "Expected precision for {$slug}.");
      $this->assertArrayHasKey('resolve', $stats, "Expected resolve for {$slug}.");
      $this->assertIsNumeric($stats['precision'], "Expected numeric precision for {$slug}.");
      $this->assertIsNumeric($stats['resolve'], "Expected numeric resolve for {$slug}.");
      $bySlug[$slug] = $stats;
    }

    $this->assertSame(5, (int)($bySlug['frontline_bruiser_t1']['precision'] ?? -1));
    $this->assertSame(5, (int)($bySlug['frontline_bruiser_t1']['resolve'] ?? -1));
    $this->assertSame(8, (int)($bySlug['backline_marksman_t3']['precision'] ?? -1));
    $this->assertSame(8, (int)($bySlug['frontline_guardian_t3']['resolve'] ?? -1));
  }

  public function testSeededEnemyTemplatesExposeAuthoredPrecisionAndResolve(): void
  {
    $stmt = $this->pdo?->query('
      SELECT `slug`, `base_stats_json`
      FROM `enemy_templates`
      ORDER BY `slug` ASC
    ');
    $rows = $stmt?->fetchAll() ?: [];
    $this->assertNotSame([], $rows, 'Expected seeded enemy templates.');

    $bySlug = [];
    foreach ($rows as $row) {
      $slug = (string)($row['slug'] ?? '');
      $stats = json_decode((string)($row['base_stats_json'] ?? ''), true);
      $this->assertIsArray($stats, "Expected valid base stats JSON for {$slug}.");
      $this->assertArrayHasKey('precision', $stats, "Expected precision for {$slug}.");
      $this->assertArrayHasKey('resolve', $stats, "Expected resolve for {$slug}.");
      $this->assertIsNumeric($stats['precision'], "Expected numeric precision for {$slug}.");
      $this->assertIsNumeric($stats['resolve'], "Expected numeric resolve for {$slug}.");
      $bySlug[$slug] = $stats;
    }

    $this->assertSame(7, (int)($bySlug['kobold_sharpshooter']['precision'] ?? -1));
    $this->assertSame(6, (int)($bySlug['kobold_shieldbearer']['resolve'] ?? -1));
    $this->assertSame(8, (int)($bySlug['frogman_bog_tyrant']['resolve'] ?? -1));
  }
}
