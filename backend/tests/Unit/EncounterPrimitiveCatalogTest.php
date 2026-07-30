<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\EncounterPrimitiveCatalog;
use PHPUnit\Framework\TestCase;

final class EncounterPrimitiveCatalogTest extends TestCase
{
  public function testVocabularyCoversRoadmapHazardAndShrinePrimitives(): void
  {
    $vocabulary = (new EncounterPrimitiveCatalog())->vocabulary();

    $this->assertSame(
      ['hp_attrition', 'temporary_modifier', 'currency_pressure', 'item_pressure', 'route_pressure', 'kin_mitigation'],
      $vocabulary['hazard']
    );
    $this->assertSame(
      ['grant_teeth', 'heal_random_unit', 'drain_highest_life_heal_rest', 'squad_damage_next_combat', 'double_run_teeth', 'clear_random_combat_node'],
      $vocabulary['shrine']
    );
  }

  public function testShrineResolutionIsDeterministicForProvidedRolls(): void
  {
    $rolls = [4];
    $effect = (new EncounterPrimitiveCatalog())->resolveNodeEffect('shrine', static function () use (&$rolls): int {
      return array_shift($rolls) ?? 0;
    }, 'shrine_rust_blessing');

    $this->assertSame('shrine', $effect['family']);
    $this->assertSame('shrine_rust_blessing', $effect['slug']);
    $this->assertSame('grant_teeth', $effect['primitive']);
    $this->assertSame(8, $effect['currency_soft']);
    $this->assertSame('rust_blessing', $effect['result']['favor']);
    $this->assertSame(8, $effect['result']['currency_soft']);
    $this->assertSame('grant_teeth', $effect['result']['effect']['type']);
  }

  public function testGeneratedShrinesRespectRegionAndQualityWeights(): void
  {
    $effect = (new EncounterPrimitiveCatalog())->resolveNodeEffect('shrine', static fn(): int => 0, null, [
      'region_slug' => 'swamps',
      'quality' => 'great',
    ]);

    $this->assertSame('shrine', $effect['family']);
    $this->assertStringStartsWith('shrine_', $effect['slug']);
    $this->assertSame('great', $effect['result']['quality']);
    $this->assertContains($effect['primitive'], (new EncounterPrimitiveCatalog())->vocabulary()['shrine']);
    $this->assertIsArray($effect['result']['effect']);
  }

  public function testHazardResolutionUsesRoutePressurePrimitive(): void
  {
    $effect = (new EncounterPrimitiveCatalog())->resolveNodeEffect('hazard', static fn(): int => 0);

    $this->assertSame('hazard', $effect['family']);
    $this->assertSame('hazard_cautious_footing', $effect['slug']);
    $this->assertSame('route_pressure', $effect['primitive']);
    $this->assertSame(0, $effect['currency_soft']);
  }

  public function testHazardRulesRespectRegionAndDepth(): void
  {
    $catalog = new EncounterPrimitiveCatalog();

    $this->assertSame([], $catalog->hazardEffectsForRegion('mountains', 2));

    $mountainSlugs = array_map(
      static fn(array $effect): string => (string)$effect['slug'],
      $catalog->hazardEffectsForRegion('mountains', 4)
    );
    $swampSlugs = array_map(
      static fn(array $effect): string => (string)$effect['slug'],
      $catalog->hazardEffectsForRegion('swamps', 4)
    );

    $this->assertContains('hazard_cautious_footing', $mountainSlugs);
    $this->assertContains('hazard_loose_scree', $mountainSlugs);
    $this->assertContains('hazard_bog_mire', $swampSlugs);
    $this->assertNotContains('hazard_loose_scree', $swampSlugs);
  }

  public function testHazardCatalogMeetsInitialContentPackTarget(): void
  {
    $catalog = new EncounterPrimitiveCatalog();
    $hazards = $catalog->hazardCatalog();
    $vocabulary = $catalog->vocabulary()['hazard'];
    $slugs = [];

    $this->assertGreaterThanOrEqual(10, count($hazards));
    foreach ($hazards as $hazard) {
      $slug = (string)$hazard['slug'];
      $this->assertStringStartsWith('hazard_', $slug);
      $this->assertNotContains($slug, $slugs);
      $this->assertContains((string)$hazard['primitive'], $vocabulary);
      $this->assertNotSame([], $hazard['regions']);
      $this->assertGreaterThan(0, (int)$hazard['weight']);
      $slugs[] = $slug;
    }
  }

  public function testShrineCatalogMeetsInitialContentPackTarget(): void
  {
    $catalog = new EncounterPrimitiveCatalog();
    $shrines = $catalog->shrineCatalog();
    $vocabulary = $catalog->vocabulary()['shrine'];
    $slugs = [];

    $this->assertGreaterThanOrEqual(7, count($shrines));
    foreach ($shrines as $shrine) {
      $slug = (string)$shrine['slug'];
      $this->assertStringStartsWith('shrine_', $slug);
      $this->assertNotContains($slug, $slugs);
      $this->assertContains((string)$shrine['primitive'], $vocabulary);
      $this->assertNotSame([], $shrine['regions']);
      $this->assertNotSame([], $shrine['qualities']);
      $this->assertNotSame([], $shrine['weights']);
      $this->assertGreaterThan(0, array_sum(array_map('intval', $shrine['weights'])));
      $this->assertNotSame('', (string)$shrine['title']);
      $this->assertNotSame('', (string)$shrine['result_copy']);
      $this->assertIsArray($shrine['effect']);
      $this->assertNotSame('', (string)($shrine['effect']['type'] ?? ''));
      $slugs[] = $slug;
    }
  }
}
