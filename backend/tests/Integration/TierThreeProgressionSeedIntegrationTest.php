<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\PromotionService;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

final class TierThreeProgressionSeedIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run tier three progression seed integration tests.';
  }

  public function testEveryStarterFamilyHasTierThreeDestination(): void
  {
    $stmt = $this->pdo?->query("
      SELECT `slug`, `name`, `promotion_level`, `base_stats_json`, `capstone_choices_json`
      FROM `unit_types`
      WHERE `slug` REGEXP '_t[123]$'
      ORDER BY `slug` ASC
    ");
    $rows = $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $this->assertNotSame([], $rows, 'Expected seeded unit types.');

    $bySlug = [];
    foreach ($rows as $row) {
      $bySlug[(string)$row['slug']] = $row;
    }

    $starterFamilies = [
      'frontline_bruiser' => 'Juggernaut',
      'frontline_guardian' => 'Ironwall',
      'backline_marksman' => 'Sharpshot',
      'support_banner' => 'Warchanter',
      'control_saboteur' => 'Venomwright',
    ];

    foreach ($starterFamilies as $family => $tierThreeName) {
      $this->assertArrayHasKey("{$family}_t1", $bySlug, "Expected Tier I for {$family}.");
      $this->assertArrayHasKey("{$family}_t2", $bySlug, "Expected Tier II for {$family}.");
      $this->assertArrayHasKey("{$family}_t3", $bySlug, "Expected Tier III for {$family}.");
      $this->assertSame($tierThreeName, (string)$bySlug["{$family}_t3"]['name']);

      $stats = json_decode((string)$bySlug["{$family}_t3"]['base_stats_json'], true);
      $this->assertIsArray($stats);
      $this->assertArrayHasKey('precision', $stats);
      $this->assertArrayHasKey('resolve', $stats);

      $capstones = json_decode((string)$bySlug["{$family}_t3"]['capstone_choices_json'], true);
      $choices = is_array($capstones['choices'] ?? null) ? $capstones['choices'] : [];
      $this->assertCount(2, $choices, "Expected terminal capstone choices for {$family}.");
      $this->assertNull($bySlug["{$family}_t3"]['promotion_level'], "Tier III should be terminal for {$family}.");
    }
  }

  public function testTierTwoPromotionRequiresMasteryAndTargetsTierThreeChain(): void
  {
    $userId = $this->insertUser('tier_three_mastery', 'Tier Three Mastery User');
    [$warcallerTypeId] = $this->loadUnitType('support_banner_t2');
    $unitId = $this->insertUnit($userId, $warcallerTypeId, 9);

    $service = new PromotionService($this->pdo);
    $unit = $service->getPromotionUnitSnapshot($userId, $unitId);
    $this->assertIsArray($unit);
    $this->assertSame(10, (int)($unit['promotion_level'] ?? 0));
    $this->assertSame([], $service->listPromotionOptions($userId, $unit));

    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 10 WHERE `id` = ?')->execute([$unitId]);
    $mastered = $service->getPromotionUnitSnapshot($userId, $unitId);
    $this->assertIsArray($mastered);
    $options = $service->listPromotionOptions($userId, $mastered);
    $this->assertGreaterThanOrEqual(1, count($options));
    $this->assertSame('chain', (string)($options[0]['mode'] ?? ''));
    $this->assertSame('support_banner_t3', (string)($options[0]['target_unit_type_slug'] ?? ''));
    $this->assertSame('Warchanter', (string)($options[0]['target_unit_type_name'] ?? ''));
    $this->assertSame(3, (int)($options[0]['target_tier'] ?? 0));
  }

  /** @return array{0:int,1:string} */
  private function loadUnitType(string $slug): array
  {
    $stmt = $this->pdo?->prepare('SELECT `id`, `name` FROM `unit_types` WHERE `slug` = ? LIMIT 1');
    $stmt?->execute([$slug]);
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    $this->assertIsArray($row, "Expected unit type {$slug}.");
    return [(int)$row['id'], (string)$row['name']];
  }

  private function insertUnit(int $userId, int $unitTypeId, int $level): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 2, ?, 0, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId, $level]);
    return (int)$this->pdo?->lastInsertId();
  }
}
