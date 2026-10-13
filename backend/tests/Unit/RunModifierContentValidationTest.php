<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\Tests\Support\RunModifierContentFixture;
use PHPUnit\Framework\TestCase;

final class RunModifierContentValidationTest extends TestCase
{
  public function testAuthoredModifierAndSourceValidateWithoutClientCatalogLeak(): void
  {
    $registry = RunModifierContentFixture::registry(true);
    $this->assertSame(2, $registry->runModifier(RunModifierContentFixture::MODIFIER_ID)['attack_bonus']);
    $projection = json_encode((new ClientContentProjector())->project($registry), JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString(RunModifierContentFixture::MODIFIER_ID, $projection);
    $this->assertStringNotContainsString('create_run_modifier', $projection);
    $this->assertStringNotContainsString('attack_bonus', $projection);
  }

  public function testMalformedDefinitionSourceAndReferenceFailValidation(): void
  {
    foreach (['id', 'type', 'kind', 'attack_zero', 'attack_over', 'duration', 'missing_reference',
      'wrong_reference_type', 'wrong_family', 'reward_effect'] as $case) {
      $definitions = RunModifierContentFixture::definitions();
      foreach ($definitions as &$definition) {
        if ($definition['id'] === RunModifierContentFixture::MODIFIER_ID) {
          if ($case === 'id') $definition['id'] = 'bad_modifier';
          if ($case === 'type') $definition['type'] = 'reward_definition';
          if ($case === 'kind') $definition['kind'] = 'arbitrary_effect';
          if ($case === 'attack_zero') $definition['attack_bonus'] = 0;
          if ($case === 'attack_over') $definition['attack_bonus'] = 11;
          if ($case === 'duration') $definition['remaining_combats'] = 2;
        }
        if ($definition['id'] === RunModifierContentFixture::ENCOUNTER_ID) {
          if ($case === 'missing_reference') $definition['actions'][0]['effect']['modifier_id'] = 'modifier.missing';
          if ($case === 'wrong_reference_type') $definition['actions'][0]['effect']['modifier_id'] = 'modifier.test_battle_focus';
          if ($case === 'wrong_family') $definition['family'] = 'chaos';
          if ($case === 'reward_effect') $definition['actions'][0]['effect'] = ['type' => 'grant_currency', 'amount' => 5];
        }
        if ($case === 'wrong_reference_type' && $definition['id'] === RunModifierContentFixture::MODIFIER_ID) {
          $definition['type'] = 'item';
        }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'modifier-test.json', 'document' => ['definitions' => $definitions]]]);
        $this->fail("Malformed modifier case {$case} was accepted.");
      } catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }
}
