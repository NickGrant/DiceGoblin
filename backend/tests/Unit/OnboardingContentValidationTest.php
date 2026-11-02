<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\Tests\Support\CanonicalContentFixture;
use PHPUnit\Framework\TestCase;

final class OnboardingContentValidationTest extends TestCase
{
  public function testFlowReferencesValidatedDialogueKnowledgeAndBasicGoblinWithoutGlobalLeak(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $flow = $content->onboardingFlow();
    $this->assertSame('kin.goblin', $flow['starting_kin_id']);
    $this->assertSame(['unit_type.bruiser', 'unit_type.bruiser', 'unit_type.marksman', 'unit_type.marksman'],
      array_column($flow['starting_warband']['units'], 'unit_type_id'));
    $this->assertSame([2, 5, 0, 3], array_column($flow['starting_warband']['units'], 'position'));
    $this->assertSame('region.mystic_cave', $flow['region_id']);
    $this->assertSame([['encounter_id' => 'encounter.mystic_cave.whim_arrival',
      'knowledge_id' => 'codex.mystic_cave.whim_arrival']], $flow['steps']);
    $projected = (new ClientContentProjector())->project($content);
    $encoded = json_encode($projected, JSON_THROW_ON_ERROR);
    foreach (['onboarding.mystic_cave', 'region.mystic_cave', 'dialogue.mystic_cave',
      'codex.mystic_cave', 'reward_definition.mystic_cave', 'capability.wrong_machine_access', 'starting_warband'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
  }

  /** @dataProvider invalidFlow */
  public function testInvalidFlowReferencesFailClosed(string $field, string $value): void
  {
    $definitions = CanonicalContentFixture::definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] !== 'onboarding.mystic_cave') continue;
      if ($field === 'encounter_id' || $field === 'knowledge_id') $definition['steps'][0][$field] = $value;
      else $definition[$field] = $value;
      break;
    }
    unset($definition);
    $this->expectException(ContentValidationException::class);
    (new ContentValidator())->validate([['path' => 'test.json', 'document' => ['definitions' => $definitions]]]);
  }

  public function testOnboardingDialogueCannotGrantMachineAccessInsteadOfKnowledge(): void
  {
    $definitions = CanonicalContentFixture::definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] !== 'reward_definition.mystic_cave.whim_arrival') continue;
      $definition['entries'][0]['reward_type'] = 'unlock';
      $definition['entries'][0]['config'] = ['unlock_id' => 'unlock.capability.wrong_machine_access'];
      break;
    }
    unset($definition);

    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('dialogue reward must begin with one guaranteed Codex entry');
    (new ContentValidator())->validate([['path' => 'test.json', 'document' => ['definitions' => $definitions]]]);
  }

  public function testStartingWarbandRequiresAnEquippableAuthoredAbility(): void
  {
    $definitions = CanonicalContentFixture::definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] !== 'onboarding.mystic_cave') continue;
      $definition['starting_warband']['units'][0]['ability_id'] = 'ability.thick_hide';
      break;
    }
    unset($definition);
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('starting_warband.units[0] configuration is invalid');
    (new ContentValidator())->validate([['path' => 'test.json', 'document' => ['definitions' => $definitions]]]);
  }

  public function testStartingWarbandRejectsDuplicateAndWrongColumnPositions(): void
  {
    foreach ([2, 1] as $badPosition) {
      $definitions = CanonicalContentFixture::definitions();
      foreach ($definitions as &$definition) {
        if ($definition['id'] !== 'onboarding.mystic_cave') continue;
        $definition['starting_warband']['units'][2]['position'] = $badPosition;
        break;
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'test.json', 'document' => ['definitions' => $definitions]]]);
        $this->fail('Invalid starting position was accepted.');
      } catch (ContentValidationException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  public function invalidFlow(): array
  {
    return [
      ['region_id', 'region.missing'],
      ['starting_kin_id', 'kin.pig'],
      ['encounter_id', 'encounter.missing'],
      ['knowledge_id', 'codex.missing'],
    ];
  }
}
