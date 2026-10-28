<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\Tests\Support\CanonicalContentFixture;
use DiceGoblins\Tests\Support\ObjectiveContentFixture;
use PHPUnit\Framework\TestCase;

final class ObjectiveContentValidationTest extends TestCase
{
  public function testObjectiveRequirementsAndPrivateRoutingValidateWithoutEnteringStaticProjection(): void
  {
    $content = ObjectiveContentFixture::registry();
    $objective = $content->objective(ObjectiveContentFixture::ID);
    $this->assertSame(['defeat_enemy_unit_type', 'complete_region_run', 'complete_dialogue'],
      array_column($objective['requirements'], 'kind'));
    $projection = (new ClientContentProjector())->project($content);
    $this->assertArrayNotHasKey('objectives', $projection['content']);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    foreach (['objective.test.mountains_scout', 'completion_event_id', 'requirements.target_id'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
  }

  public function testMalformedObjectiveFieldsRequirementsAndReferencesFailValidation(): void
  {
    foreach (['bad_id', 'extra', 'empty_requirements', 'duplicate_requirement', 'bad_kind',
      'wrong_namespace', 'missing_enemy', 'missing_region', 'missing_dialogue',
      'zero_needed', 'unsafe_needed', 'unknown_event', 'wrong_category'] as $case) {
      $objective = ObjectiveContentFixture::definition();
      if ($case === 'bad_id') $objective['id'] = 'story.test.mountains_scout';
      if ($case === 'extra') $objective['story_flag'] = true;
      if ($case === 'empty_requirements') $objective['requirements'] = [];
      if ($case === 'duplicate_requirement') $objective['requirements'][] = $objective['requirements'][0];
      if ($case === 'bad_kind') $objective['requirements'][0]['kind'] = 'script';
      if ($case === 'wrong_namespace') $objective['requirements'][0]['target_id'] = 'region.mountains';
      if ($case === 'missing_enemy') $objective['requirements'][0]['target_id'] = 'enemy_unit_type.missing';
      if ($case === 'missing_region') $objective['requirements'][1]['target_id'] = 'region.missing';
      if ($case === 'missing_dialogue') $objective['requirements'][2]['target_id'] = 'dialogue.missing';
      if ($case === 'zero_needed') $objective['requirements'][0]['needed'] = 0;
      if ($case === 'unsafe_needed') $objective['requirements'][0]['needed'] = \DiceGoblins\Support\ClientSafeInteger::MAXIMUM + 1;
      if ($case === 'unknown_event') $objective['completion_event_id'] = 'event.missing';
      if ($case === 'wrong_category') $objective['category'] = 'story';
      $definitions = CanonicalContentFixture::definitions();
      $definitions[] = $objective;
      try {
        (new ContentValidator())->validate([['path' => 'objectives.json', 'document' => ['definitions' => $definitions]]]);
        $this->fail("Expected {$case} to fail.");
      } catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }
}
