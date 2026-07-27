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
      ['small_reward', 'cleansing', 'bargain', 'reroute', 'controlled_risk'],
      $vocabulary['shrine']
    );
  }

  public function testShrineResolutionIsDeterministicForProvidedRolls(): void
  {
    $rolls = [1, 4];
    $effect = (new EncounterPrimitiveCatalog())->resolveNodeEffect('shrine', static function () use (&$rolls): int {
      return array_shift($rolls) ?? 0;
    });

    $this->assertSame('shrine', $effect['family']);
    $this->assertSame('shrine_rust_blessing', $effect['slug']);
    $this->assertSame('small_reward', $effect['primitive']);
    $this->assertSame(8, $effect['currency_soft']);
    $this->assertSame('rust_blessing', $effect['result']['favor']);
    $this->assertSame(8, $effect['result']['currency_soft']);
  }

  public function testHazardResolutionUsesRoutePressurePrimitive(): void
  {
    $effect = (new EncounterPrimitiveCatalog())->resolveNodeEffect('hazard', static fn(): int => 0);

    $this->assertSame('hazard', $effect['family']);
    $this->assertSame('hazard_cautious_footing', $effect['slug']);
    $this->assertSame('route_pressure', $effect['primitive']);
    $this->assertSame(0, $effect['currency_soft']);
  }
}
