<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class DialogueContentValidationTest extends TestCase
{
  public function testCanonicalDialogueAndReferencesAreValidButAbsentFromStaticClientProjection(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $dialogue = $content->dialogue('dialogue.camp.farm_scout');
    $this->assertSame('event.dialogue_farm_scout_paths', $dialogue['choices'][0]['event_id']);
    $this->assertSame(['codex.farm.old_paths'], $dialogue['choices'][1]['required_codex_ids']);
    $projection = (new ClientContentProjector())->project($content);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    $this->assertArrayNotHasKey('dialogues', $projection['content']);
    $this->assertStringNotContainsString($dialogue['id'], $encoded);
    $this->assertStringNotContainsString('required_codex_ids', $encoded);
    $this->assertStringNotContainsString('event.dialogue_farm_scout_paths', $encoded);
  }

  public function testMalformedDialogueAndReferencesFailValidation(): void
  {
    foreach (['bad_id', 'extra', 'duplicate_choice', 'bad_choice', 'empty_label', 'unknown_codex',
      'unknown_event', 'wrong_reward', 'unknown_reward_codex', 'null_event'] as $case) {
      $definitions = $this->definitions();
      foreach ($definitions as &$definition) {
        if ($definition['id'] === 'dialogue.camp.farm_scout') {
          if ($case === 'bad_id') $definition['id'] = 'story.camp.farm_scout';
          if ($case === 'extra') $definition['hidden_flag'] = true;
          if ($case === 'duplicate_choice') $definition['choices'][] = $definition['choices'][0];
          if ($case === 'bad_choice') $definition['choices'][0]['id'] = 'Bad Id';
          if ($case === 'empty_label') $definition['choices'][0]['label'] = '';
          if ($case === 'unknown_codex') $definition['choices'][1]['required_codex_ids'] = ['codex.unknown'];
          if ($case === 'unknown_event') $definition['choices'][0]['event_id'] = 'event.unknown';
          if ($case === 'null_event') $definition['choices'][0]['event_id'] = null;
        }
        if ($definition['id'] === 'reward_definition.dialogue_farm_scout_paths') {
          if ($case === 'wrong_reward') {
            $definition['entries'][0]['reward_type'] = 'currency';
            $definition['entries'][0]['config'] = ['currency_id' => 'teeth', 'amount' => 1];
          }
          if ($case === 'unknown_reward_codex') $definition['entries'][0]['config']['codex_id'] = 'codex.unknown';
        }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'test.json', 'document' => ['definitions' => $definitions]]]);
        $this->fail("Expected {$case} to fail.");
      } catch (ContentValidationException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /** @return list<array<string,mixed>> */
  private function definitions(): array
  {
    return \DiceGoblins\Tests\Support\CanonicalContentFixture::definitions();
  }
}
