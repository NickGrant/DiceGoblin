<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\Tests\Support\ContextualEncounterFixture as Fixture;
use PHPUnit\Framework\TestCase;

final class ContextualEncounterContentValidationTest extends TestCase
{
  public function testValidatedStepsAndContextualRulesRemainServerPrivate(): void
  {
    $registry = Fixture::registry();
    $encounter = $registry->encounter(Fixture::ENCOUNTER_ID);
    $this->assertSame('kindled', $encounter['steps'][0]['id']);
    $this->assertSame(Fixture::ITEM_ID, $encounter['actions'][1]['effect']['item_id']);
    $projected = json_encode((new ClientContentProjector())->project($registry), JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString(Fixture::ENCOUNTER_ID, $projected);
    $this->assertStringNotContainsString('advance_step', $projected);
    $this->assertStringNotContainsString('kindled', $projected);
  }

  public function testMalformedStepTransitionItemAndQuantityAreRejected(): void
  {
    foreach (['bad_step_id', 'missing_step', 'wrong_target', 'bad_quantity', 'missing_item',
      'unsupported_effect', 'step_transition', 'duplicate_action', 'non_material_item'] as $case) {
      $definitions = Fixture::definitions();
      foreach ($definitions as &$definition) {
        if ($definition['id'] === Fixture::ENCOUNTER_ID) {
          if ($case === 'bad_step_id') $definition['steps'][0]['id'] = 'Bad Step';
          if ($case === 'missing_step') unset($definition['steps']);
          if ($case === 'wrong_target') $definition['actions'][1]['effect']['step_id'] = 'missing';
          if ($case === 'bad_quantity') $definition['actions'][1]['effect']['quantity'] = 2;
          if ($case === 'missing_item') $definition['actions'][1]['effect']['item_id'] = 'item.missing';
          if ($case === 'unsupported_effect') $definition['steps'][0]['actions'][0]['effect'] = ['type' => 'arbitrary_state'];
          if ($case === 'step_transition') $definition['steps'][0]['actions'][0]['effect'] = $definition['actions'][1]['effect'];
          if ($case === 'duplicate_action') $definition['steps'][0]['actions'][0]['id'] = 'offer_wick';
          if ($case === 'non_material_item') $definition['actions'][1]['effect']['item_id'] = 'item.spark_tonic';
        }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'contextual-test.json',
          'document' => ['definitions' => $definitions]]]);
        $this->fail("Invalid contextual case {$case} was accepted.");
      } catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }
}
