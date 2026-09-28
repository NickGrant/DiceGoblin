<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Application\UnitPromotionPolicy;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class UnitPromotionPolicyTest extends TestCase
{
  private function root(): string { return dirname(__DIR__, 2) . '/content'; }

  public function testCanonicalGraphCadenceAndProjection(): void
  {
    $content = ContentRegistry::load($this->root());
    $policy = new UnitPromotionPolicy($content);
    $this->assertCount(20, $content->definitionsOfType('unit_promotion'));
    foreach (['bruiser', 'guardian', 'marksman', 'bannerbearer', 'saboteur'] as $base) {
      $edges = $policy->outgoing('unit_type.' . $base);
      $this->assertCount(2, $edges);
      foreach ($edges as $edge) {
        $this->assertSame(3, $edge['required_level']);
        $this->assertSame(5, $edge['price']['amount']);
        $this->assertSame(2, $policy->targetTier($edge['id']));
        $this->assertFalse($policy->levelMet($edge['id'], 2));
        $this->assertTrue($policy->levelMet($edge['id'], 3));
        $next = $policy->outgoing($edge['to_unit_type_id']);
        $this->assertCount(1, $next);
        $this->assertSame(6, $next[0]['required_level']);
        $this->assertSame(10, $next[0]['price']['amount']);
        $this->assertSame(3, $policy->targetTier($next[0]['id']));
        $this->assertFalse($policy->levelMet($next[0]['id'], 5));
        $this->assertTrue($policy->levelMet($next[0]['id'], 6));
        $this->assertSame([], $policy->outgoing($next[0]['to_unit_type_id']));
      }
    }
    $this->assertSame(300, $policy->xpThreshold(3, 44));
    $delta = $policy->targetAbilityDelta('unit_promotion.enforcer.juggernaut', ['ability.menacing_follow_through']);
    $this->assertSame(array_values(array_diff($content->unitType('unit_type.juggernaut')['ability_ids'], ['ability.menacing_follow_through'])), $delta);
    $this->assertSame([], $policy->targetAbilityDelta('unit_promotion.enforcer.juggernaut', $content->unitType('unit_type.juggernaut')['ability_ids']));
    $projected = (new ClientContentProjector())->project($content)['content']['unit_promotions'];
    $this->assertCount(20, $projected);
    foreach ($projected as $edge) $this->assertSame(['id', 'from_unit_type_id', 'to_unit_type_id'], array_keys($edge));
  }

  public function testPromotionHistoryFollowsAuthoredPaths(): void
  {
    $policy = new UnitPromotionPolicy(ContentRegistry::load($this->root()));
    $policy->validateHistory('unit_type.bruiser', []);
    foreach (['enforcer', 'pit_fighter'] as $branch) {
      $policy->validateHistory('unit_type.juggernaut', [
        ['from_unit_type_id' => 'unit_type.bruiser', 'to_unit_type_id' => 'unit_type.' . $branch, 'promoted_at' => '2026-01-01'],
        ['from_unit_type_id' => 'unit_type.' . $branch, 'to_unit_type_id' => 'unit_type.juggernaut', 'promoted_at' => '2026-01-02'],
      ]);
    }
    $policy->validateHistory('unit_type.ironwall', [
      ['from_unit_type_id' => 'unit_type.guardian', 'to_unit_type_id' => 'unit_type.bulwark', 'promoted_at' => '2026-01-01'],
      ['from_unit_type_id' => 'unit_type.bulwark', 'to_unit_type_id' => 'unit_type.ironwall', 'promoted_at' => '2026-01-02'],
    ]);
    foreach ([
      ['unit_type.enforcer', []],
      ['unit_type.juggernaut', [['from_unit_type_id' => 'unit_type.bruiser', 'to_unit_type_id' => 'unit_type.juggernaut', 'promoted_at' => '2026-01-01']]],
      ['unit_type.pit_fighter', [['from_unit_type_id' => 'unit_type.guardian', 'to_unit_type_id' => 'unit_type.enforcer', 'promoted_at' => '2026-01-01']]],
      ['unit_type.bruiser', [['from_unit_type_id' => 'unit_type.bruiser', 'to_unit_type_id' => 'unit_type.enforcer', 'promoted_at' => '2026-01-01']]],
    ] as [$type, $history]) {
      try { $policy->validateHistory($type, $history); $this->fail('Invalid history was accepted.'); }
      catch (WarbandIntegrityException) { $this->addToAssertionCount(1); }
    }
  }

  /** @dataProvider invalidEdgeProvider */
  public function testInvalidAuthoredEdgesAreRejected(callable $change): void
  {
    $documents = [];
    $paths = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root(), \FilesystemIterator::SKIP_DOTS));
    foreach ($paths as $file) {
      if (!$file->isFile() || $file->getExtension() !== 'json') continue;
      $document = json_decode((string)file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
      if (str_contains($file->getPathname(), 'unit_promotions')) $change($document['definitions']);
      $documents[] = ['path' => $file->getPathname(), 'document' => $document];
    }
    $this->expectException(ContentValidationException::class);
    (new ContentValidator())->validate($documents);
  }

  public static function invalidEdgeProvider(): array
  {
    return [
      'missing reference' => [static function (array &$edges): void { $edges[0]['to_unit_type_id'] = 'unit_type.missing'; }],
      'wrong reference type' => [static function (array &$edges): void { $edges[0]['to_unit_type_id'] = 'ability.basic_attack_melee'; }],
      'same type' => [static function (array &$edges): void { $edges[0]['to_unit_type_id'] = $edges[0]['from_unit_type_id']; }],
      'tier jump' => [static function (array &$edges): void { $edges[0]['to_unit_type_id'] = 'unit_type.juggernaut'; }],
      'cycle' => [static function (array &$edges): void { $edges[2]['to_unit_type_id'] = 'unit_type.bruiser'; }],
      'duplicate pair' => [static function (array &$edges): void { $edges[1]['to_unit_type_id'] = $edges[0]['to_unit_type_id']; }],
      'wrong currency' => [static function (array &$edges): void { $edges[0]['price']['currency_id'] = 'teeth'; }],
      'zero price' => [static function (array &$edges): void { $edges[0]['price']['amount'] = 0; }],
      'unsafe price' => [static function (array &$edges): void { $edges[0]['price']['amount'] = 9007199254740992; }],
      'invalid level' => [static function (array &$edges): void { $edges[0]['required_level'] = 0; }],
    ];
  }
}
