<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class CodexContentValidationTest extends TestCase
{
  public function testCanonicalEntryHasMeaningfulPresentationAndIsAbsentFromGlobalClientCatalog(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $entry = $content->codexEntry('codex.farm.mudking');
    $this->assertNotSame('', trim($entry['title']));
    $projected = (new \DiceGoblins\Content\ClientContentProjector())->project($content);
    $this->assertArrayNotHasKey('codex_entries', $projected['content']);
    $this->assertStringNotContainsString($entry['body'], json_encode($projected, JSON_THROW_ON_ERROR));
  }

  public function testValidTestAuthoredCodexRewardReferencesEntry(): void
  {
    $definitions = $this->definitions();
    $definitions[] = $this->reward();
    $definitions[] = ['id' => 'event.codex_test', 'type' => 'event', 'reward_definition_id' => 'reward_definition.codex_test'];
    $validated = (new ContentValidator())->validate([['path' => 'fixture.json', 'document' => ['definitions' => $definitions]]]);
    $this->assertSame('codex.farm.mudking', $validated['reward_definition.codex_test']['entries'][0]['config']['codex_id']);
  }

  public function testMalformedCodexDefinitionsAndRewardReferencesFail(): void
  {
    foreach (['bad_id', 'extra', 'empty_body', 'long_title', 'duplicate', 'missing_reference', 'bad_config'] as $case) {
      $definitions = $this->definitions();
      $reward = $this->reward();
      foreach ($definitions as &$definition) {
        if ($definition['id'] !== 'codex.farm.mudking') continue;
        if ($case === 'bad_id') $definition['id'] = 'entry.farm.mudking';
        if ($case === 'extra') $definition['secret'] = 'server-only';
        if ($case === 'empty_body') $definition['body'] = '';
        if ($case === 'long_title') $definition['title'] = str_repeat('a', 121);
      }
      unset($definition);
      if ($case === 'duplicate') $definitions[] = $this->codex();
      if ($case === 'missing_reference') $reward['entries'][0]['config']['codex_id'] = 'codex.missing';
      if ($case === 'bad_config') $reward['entries'][0]['config']['private_roll'] = 1;
      $definitions[] = $reward;
      try {
        (new ContentValidator())->validate([['path' => 'fixture.json', 'document' => ['definitions' => $definitions]]]);
        $this->fail("Expected {$case} to fail validation.");
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

  /** @return array<string,mixed> */
  private function codex(): array
  {
    return ['id' => 'codex.farm.mudking', 'type' => 'codex_entry', 'title' => 'The Mudking', 'body' => 'Knowledge.'];
  }

  /** @return array<string,mixed> */
  private function reward(): array
  {
    return ['id' => 'reward_definition.codex_test', 'type' => 'reward_definition', 'entries' => [[
      'key' => 'discovery', 'probability_basis_points' => 10000, 'reward_type' => 'codex',
      'config' => ['codex_id' => 'codex.farm.mudking'],
    ]]];
  }
}
