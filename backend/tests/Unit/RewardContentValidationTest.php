<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class RewardContentValidationTest extends TestCase
{
  public function testCanonicalMountainsUnlockReferencesAuthoredRegionWithGeneration(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $this->assertSame([
      'id' => 'unlock.region.mountains',
      'type' => 'unlock',
      'target_type' => 'region',
      'target_id' => 'region.mountains',
    ], $registry->unlock('unlock.region.mountains'));
    $this->assertSame('run_generation.mountains', $registry->runGenerationForRegion('region.mountains')['id']);
  }

  public function testValidEventAndRewardDefinitionRetainEntryOrderAndTypedAccess(): void
  {
    $registry = $this->registryWithRewardFixture();
    $reward = $registry->rewardDefinition('reward_definition.test_completion');

    $this->assertSame('reward_definition.test_completion', $registry->event('event.test_completed')['reward_definition_id']);
    $this->assertSame(['teeth', 'xp', 'mountains'], array_column($reward['entries'], 'key'));
    $this->assertSame([10000, 2500, 10000], array_column($reward['entries'], 'probability_basis_points'));
    $this->assertSame('participating_units', $reward['entries'][1]['config']['target_scope']);
  }

  public function testCanonicalFarmLootIsExactlyEightTeeth(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $this->assertSame('reward_definition.farm_loot_completed',
      $registry->event('event.farm_loot_completed')['reward_definition_id']);
    $this->assertSame([[
      'key' => 'teeth', 'probability_basis_points' => 10000, 'reward_type' => 'currency',
      'config' => ['currency_id' => 'teeth', 'amount' => 8],
    ]], $registry->rewardDefinition('reward_definition.farm_loot_completed')['entries']);
  }

  public function testCanonicalFarmBossRewardIsExactlyParticipatingXpAndMountains(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $this->assertSame('reward_definition.farm_boss_completed',
      $registry->event('event.farm_boss_completed')['reward_definition_id']);
    $this->assertSame([
      ['key' => 'xp', 'probability_basis_points' => 10000, 'reward_type' => 'unit_xp',
        'config' => ['target_scope' => 'participating_units', 'amount' => 16]],
      ['key' => 'mountains', 'probability_basis_points' => 10000, 'reward_type' => 'unlock',
        'config' => ['unlock_id' => 'unlock.region.mountains']],
    ], $registry->rewardDefinition('reward_definition.farm_boss_completed')['entries']);
  }

  public function testCanonicalMountainsRewardsAreEightTeethAndXpOnly(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $this->assertSame('reward_definition.mountains_loot_completed',
      $registry->event('event.mountains_loot_completed')['reward_definition_id']);
    $this->assertSame([[
      'key' => 'teeth', 'probability_basis_points' => 10000, 'reward_type' => 'currency',
      'config' => ['currency_id' => 'teeth', 'amount' => 8],
    ]], $registry->rewardDefinition('reward_definition.mountains_loot_completed')['entries']);
    $this->assertSame('reward_definition.mountains_boss_completed',
      $registry->event('event.mountains_boss_completed')['reward_definition_id']);
    $this->assertSame([[
      'key' => 'xp', 'probability_basis_points' => 10000, 'reward_type' => 'unit_xp',
      'config' => ['target_scope' => 'participating_units', 'amount' => 16],
    ]], $registry->rewardDefinition('reward_definition.mountains_boss_completed')['entries']);
  }

  public function testEventRewardAndUnlockDefinitionsRemainOutsideClientProjection(): void
  {
    $projection = (new ClientContentProjector())->project($this->registryWithRewardFixture());
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);

    $this->assertSame(['id' => 'region.mountains', 'display_name' => 'Mountains', 'art_key' => 'mountains'],
      $projection['content']['regions']['region.mountains']);
    foreach (['unlock.region.mountains', 'event.test_completed', 'event.mountains_loot_completed',
      'reward_definition.test_completion', 'run_generation.mountains', 'probability_basis_points', 'participating_units'] as $privateValue) {
      $this->assertStringNotContainsString($privateValue, $encoded);
    }
  }

  /** @dataProvider invalidRewardContentProvider */
  public function testMalformedRewardContentIsRejected(callable $mutator, string $message): void
  {
    $definitions = $this->definitionsWithRewardFixture();
    $mutator($definitions);

    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage($message);
    (new ContentValidator())->validate([['path' => 'reward-test.json', 'document' => ['definitions' => $definitions]]]);
  }

  public function invalidRewardContentProvider(): array
  {
    return [
      'zero probability' => [$this->entryMutation('teeth', fn(array &$entry) => $entry['probability_basis_points'] = 0), 'probability_basis_points'],
      'probability over 100 percent' => [$this->entryMutation('teeth', fn(array &$entry) => $entry['probability_basis_points'] = 10001), 'probability_basis_points'],
      'fractional probability' => [$this->entryMutation('teeth', fn(array &$entry) => $entry['probability_basis_points'] = 12.5), 'probability_basis_points'],
      'duplicate entry key' => [$this->entryMutation('xp', fn(array &$entry) => $entry['key'] = 'teeth'), 'duplicate reward entry key'],
      'unsupported reward type' => [$this->entryMutation('teeth', fn(array &$entry) => $entry['reward_type'] = 'item'), 'reward_type'],
      'unsupported currency' => [$this->entryMutation('teeth', fn(array &$entry) => $entry['config']['currency_id'] = 'gold'), 'currency_id'],
      'non-positive currency amount' => [$this->entryMutation('teeth', fn(array &$entry) => $entry['config']['amount'] = 0), 'amount'],
      'unsupported XP scope' => [$this->entryMutation('xp', fn(array &$entry) => $entry['config']['target_scope'] = 'all_units'), 'target_scope'],
      'extra reward config' => [$this->entryMutation('xp', fn(array &$entry) => $entry['config']['curve'] = 'fast'), 'invalid field set'],
      'missing unlock reference' => [$this->entryMutation('mountains', fn(array &$entry) => $entry['config']['unlock_id'] = 'unlock.region.missing'), 'references missing unlock'],
      'missing event reward reference' => [fn(array &$definitions) => $this->mutate($definitions, 'event.test_completed', fn(array &$definition) => $definition['reward_definition_id'] = 'reward_definition.missing'), 'references missing reward_definition'],
      'missing unlock region reference' => [fn(array &$definitions) => $this->mutate($definitions, 'unlock.region.mountains', fn(array &$definition) => $definition['target_id'] = 'region.missing'), 'references missing region'],
      'extra event field' => [fn(array &$definitions) => $this->mutate($definitions, 'event.test_completed', fn(array &$definition) => $definition['script'] = 'grant_everything'), 'invalid field set'],
    ];
  }

  private function entryMutation(string $key, callable $mutator): callable
  {
    return function(array &$definitions) use ($key, $mutator): void {
      $this->mutate($definitions, 'reward_definition.test_completion', function(array &$definition) use ($key, $mutator): void {
        foreach ($definition['entries'] as &$entry) {
          if ($entry['key'] === $key) {
            $mutator($entry);
            return;
          }
        }
      });
    };
  }

  private function registryWithRewardFixture(): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-rewards-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $path = $root . '/content.json';
    file_put_contents($path, json_encode(['definitions' => $this->definitionsWithRewardFixture()], JSON_THROW_ON_ERROR));
    try {
      return ContentRegistry::load($root);
    } finally {
      unlink($path);
      rmdir($root);
    }
  }

  /** @return list<array<string,mixed>> */
  private function definitionsWithRewardFixture(): array
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $definitions = [];
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability', 'dice_material', 'dice_aspect', 'dice_profile', 'run_node_type', 'run_generation', 'unlock', 'event', 'reward_definition'] as $type) {
      foreach ($registry->definitionsOfType($type) as $definition) $definitions[] = $definition;
    }
    $definitions[] = [
      'id' => 'reward_definition.test_completion',
      'type' => 'reward_definition',
      'entries' => [
        ['key' => 'teeth', 'probability_basis_points' => 10000, 'reward_type' => 'currency', 'config' => ['currency_id' => 'teeth', 'amount' => 1]],
        ['key' => 'xp', 'probability_basis_points' => 2500, 'reward_type' => 'unit_xp', 'config' => ['target_scope' => 'participating_units', 'amount' => 1]],
        ['key' => 'mountains', 'probability_basis_points' => 10000, 'reward_type' => 'unlock', 'config' => ['unlock_id' => 'unlock.region.mountains']],
      ],
    ];
    $definitions[] = ['id' => 'event.test_completed', 'type' => 'event', 'reward_definition_id' => 'reward_definition.test_completion'];
    return $definitions;
  }

  /** @param list<array<string,mixed>> $definitions */
  private function mutate(array &$definitions, string $id, callable $mutator): void
  {
    foreach ($definitions as &$definition) {
      if (($definition['id'] ?? null) !== $id) continue;
      $mutator($definition);
      return;
    }
    $this->fail("Definition {$id} was not found.");
  }

  private function canonicalRoot(): string
  {
    return dirname(__DIR__, 2) . '/content';
  }
}
