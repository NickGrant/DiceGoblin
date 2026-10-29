<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\CombatSnapshotNormalizer;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\Tests\Support\CanonicalContentFixture;
use PHPUnit\Framework\TestCase;

final class EnemyCombatContentContractTest extends TestCase
{
  public function testEncounterCombatantsNormalizeFromReferencedEnemyDefinitions(): void
  {
    $content = $this->content();
    $normalizer = new CombatSnapshotNormalizer($content);
    $encounters = array_filter($content->definitionsOfType('encounter'),
      static fn(array $encounter): bool => in_array($encounter['kind'], ['combat', 'boss'], true));
    $this->assertNotEmpty($encounters);
    foreach ($encounters as $encounter) {
      $combatants = $normalizer->enemyCombatants($encounter['id']);
      $manifest = $normalizer->enemyManifest($encounter['id']);
      $this->assertCount(count($encounter['combatants']), $combatants);
      $this->assertCount(count($combatants), $manifest);
      foreach ($encounter['combatants'] as $index => $slot) {
        $enemy = $content->enemyUnitType($slot['enemy_unit_type_id']);
        $actual = $combatants[$index];
        $this->assertSame($slot['key'], $actual['key']);
        $this->assertSame($slot['position'], $actual['position']);
        $this->assertSame($enemy['stats'], $actual['stats']);
        $this->assertSame($enemy['stats']['hp'], $actual['current_hp']);
        $this->assertSame($enemy['active_ability_ids'], array_column($actual['active_abilities'], 'id'));
        $this->assertSame($enemy['passive_ability_ids'], array_column($actual['passive_abilities'], 'id'));
        $this->assertSame($slot['enemy_unit_type_id'], $manifest[$index]['enemy_unit_type_id']);
        $this->assertSame($enemy['display_name'], $manifest[$index]['display_name']);
        $this->assertSame($enemy['art_key'], $manifest[$index]['art_key']);
        foreach ($actual['active_abilities'] as $ability) {
          $definition = $content->ability($ability['id']);
          $this->assertSame($definition['handler_config'], $ability['config']);
          $this->assertCount($definition['dice_slot_count'], $ability['dice']);
          foreach ($ability['dice'] as $die) {
            $this->assertSame($enemy['virtual_ability_dice']['sides'], $die['sides']);
            $this->assertSame($enemy['virtual_ability_dice']['profile_id'], $die['profile_id']);
          }
        }
      }
    }
  }

  public function testServerOnlyEnemyDefinitionsAndMechanicsStayOutOfTheClientProjection(): void
  {
    $content = $this->content();
    $projection = (new ClientContentProjector())->project($content);
    $this->assertArrayNotHasKey('enemy_unit_types', $projection['content']);
    $this->assertArrayNotHasKey('encounters', $projection['content']);
    foreach ($content->definitionsOfType('ability') as $ability) {
      if ($ability['server_only'] ?? false) $this->assertArrayNotHasKey($ability['id'], $projection['content']['abilities']);
    }
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    foreach (['virtual_ability_dice', 'handler_config', 'resolution_priority'] as $secret) {
      $this->assertStringNotContainsString($secret, $encoded);
    }
  }

  public function testGenericEnemyAndEncounterValidationRejectsInvalidDefinitions(): void
  {
    $baseline = CanonicalContentFixture::definitions();
    $enemies = array_values(array_filter($baseline,
      static fn(array $definition): bool => $definition['type'] === 'enemy_unit_type'));
    $encounters = array_values(array_filter($baseline,
      static fn(array $definition): bool => $definition['type'] === 'encounter'
        && in_array($definition['kind'], ['combat', 'boss'], true)));
    $enemy = $enemies[0] ?? null;
    $encounter = $encounters[0] ?? null;
    $this->assertIsArray($enemy);
    $this->assertIsArray($encounter);
    $passives = array_values(array_filter($baseline,
      static fn(array $definition): bool => $definition['type'] === 'ability' && $definition['kind'] === 'passive'));
    $passive = $passives[0] ?? null;
    $this->assertIsArray($passive);
    $cases = [
      [$enemy['id'], static function (array &$value): void { unset($value['stats']['resolve']); }],
      [$enemy['id'], static fn(array &$value) => $value['stats']['hp'] = 0],
      [$enemy['id'], static fn(array &$value) => $value['active_ability_ids'][0] = 'ability.missing'],
      [$enemy['id'], static fn(array &$value) => $value['active_ability_ids'][0] = $passive['id']],
      [$enemy['id'], static fn(array &$value) => $value['virtual_ability_dice']['sides'] = 8],
      [$enemy['id'], static fn(array &$value) => $value['virtual_ability_dice']['profile_id'] = 'dice_profile.missing'],
      [$encounter['id'], static fn(array &$value) => $value['region_id'] = 'region.missing'],
      [$encounter['id'], static fn(array &$value) => $value['combatants'][0]['enemy_unit_type_id'] = 'enemy_unit_type.missing'],
      [$encounter['id'], static fn(array &$value) => $value['combatants'] = []],
      [$encounter['id'], static fn(array &$value) => $value['combatants'][] = $value['combatants'][0]],
      [$encounter['id'], static fn(array &$value) => $value['combatants'][0]['position']['x'] = 3],
    ];
    foreach ($cases as [$id, $mutate]) {
      $definitions = $baseline;
      foreach ($definitions as &$definition) {
        if ($definition['id'] === $id) { $mutate($definition); break; }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'enemy-contract.json',
          'document' => ['definitions' => $definitions]]]);
        $this->fail('Invalid enemy or combat encounter was accepted.');
      } catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }

  private function content(): ContentRegistry
  { return ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content'); }
}
