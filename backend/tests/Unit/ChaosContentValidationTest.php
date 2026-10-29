<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\Tests\Support\ChaosContentFixture;
use PHPUnit\Framework\TestCase;

final class ChaosContentValidationTest extends TestCase
{
  public function testTestOnlyChaosValidatesWithoutProductionRegionOrCatalogLeak(): void
  {
    $canonical = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    foreach (['region.the_farm', 'region.mountains'] as $regionId) {
      $nodes = $canonical->runGenerationForRegion($regionId)['nodes'];
      $this->assertNotContains('run_node_type.chaos', array_column($nodes, 'node_type_id'));
      $this->assertNotContains(ChaosContentFixture::ENCOUNTER_ID, array_column($nodes, 'encounter_id'));
    }
    $content = ChaosContentFixture::registry();
    $encounter = $content->encounter(ChaosContentFixture::ENCOUNTER_ID);
    $this->assertSame('chaos', $encounter['family']);
    $this->assertSame(['mend', 'wound'], array_column($encounter['actions'][0]['effect']['outcomes'], 'id'));
    $projection = json_encode((new ClientContentProjector())->project($content), JSON_THROW_ON_ERROR);
    foreach ([ChaosContentFixture::ENCOUNTER_ID, 'random_participating_hp', '"outcomes"', '"weight"'] as $private) {
      $this->assertStringNotContainsString($private, $projection);
    }
  }

  public function testMalformedChaosOutcomeAndReferencesFailValidation(): void
  {
    foreach (['wrong_family', 'missing_outcome', 'duplicate_outcome', 'bad_weight', 'bad_effect',
      'reward_effect', 'foreign_region', 'wrong_reference', 'missing_reference'] as $case) {
      $definitions = ChaosContentFixture::definitions();
      foreach ($definitions as &$definition) {
        if ($definition['id'] === ChaosContentFixture::ENCOUNTER_ID) {
          $effect =& $definition['actions'][0]['effect'];
          if ($case === 'wrong_family') $definition['family'] = 'rest';
          if ($case === 'missing_outcome') array_pop($effect['outcomes']);
          if ($case === 'duplicate_outcome') $effect['outcomes'][1]['id'] = 'mend';
          if ($case === 'bad_weight') $effect['outcomes'][1]['weight'] = 0;
          if ($case === 'bad_effect') $effect['outcomes'][1]['effect']['amount'] = 0;
          if ($case === 'reward_effect') $effect['outcomes'][1]['effect'] = ['type' => 'grant_currency', 'amount' => 2];
          if ($case === 'foreign_region') $definition['region_id'] = 'region.missing';
          unset($effect);
        }
        if ($definition['id'] === ChaosContentFixture::GENERATION_ID) {
          if ($case === 'wrong_reference') $definition['nodes'][3]['encounter_id'] = 'encounter.the_farm_rest';
          if ($case === 'missing_reference') $definition['nodes'][3]['encounter_id'] = 'encounter.missing';
        }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'chaos-test.json', 'document' => ['definitions' => $definitions]]]);
        $this->fail("Malformed Chaos case {$case} was accepted.");
      } catch (ContentValidationException) {
        $this->addToAssertionCount(1);
      }
    }
  }
}
