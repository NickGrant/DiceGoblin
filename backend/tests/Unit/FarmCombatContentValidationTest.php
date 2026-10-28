<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\CombatSnapshotNormalizer;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class FarmCombatContentValidationTest extends TestCase
{
  public function testAuthoredFarmCombatantsNormalizeWithoutDuplicatingTheirStatsOrLoadouts(): void
  {
    $content = $this->content();
    $normalizer = new CombatSnapshotNormalizer($content);
    $encounters = array_filter($content->definitionsOfType('encounter'),
      static fn(array $encounter): bool => $encounter['region_id'] === 'region.the_farm'
        && in_array($encounter['kind'], ['combat', 'boss'], true));
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

  public function testHiddenEnemyMechanicsAndRosterNeverEnterClientProjection(): void
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

  /** @dataProvider malformedFarmContentProvider */
  public function testMalformedEnemyEncounterAndReferencesFailValidation(string $id, callable $mutate, string $message): void
  {
    $definitions = $this->definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] === $id) { $mutate($definition); break; }
    }
    unset($definition);
    try {
      (new ContentValidator())->validate([['path' => 'farm-fixture.json', 'document' => ['definitions' => $definitions]]]);
      $this->fail('Expected malformed Farm content to fail.');
    } catch (ContentValidationException $e) {
      $this->assertStringContainsString($message, $e->getMessage());
    }
  }

  public function malformedFarmContentProvider(): array
  {
    return [
      'enemy missing stat' => ['enemy_unit_type.mudwrestler', static function (array &$d): void { unset($d['stats']['resolve']); }, 'exactly HP, Attack, Defense, Precision, and Resolve'],
      'enemy zero HP' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['stats']['hp'] = 0, 'stats.hp'],
      'enemy negative Attack' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['stats']['attack'] = -1, 'stats.attack'],
      'enemy Speed' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['stats']['speed'] = 1, 'exactly HP, Attack, Defense, Precision, and Resolve'],
      'missing active ability' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['active_ability_ids'][1] = 'ability.missing', 'references missing ability'],
      'passive in active list' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['active_ability_ids'][1] = 'ability.thick_hide', 'requires active abilities'],
      'wrong virtual sides' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['virtual_ability_dice']['sides'] = 8, 'sides'],
      'missing virtual profile' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['virtual_ability_dice']['profile_id'] = 'dice_profile.missing', 'references missing dice_profile'],
      'aspect virtual profile' => ['enemy_unit_type.mudwrestler', static fn(array &$d) => $d['virtual_ability_dice']['profile_id'] = 'dice_profile.cardboard_striking', 'eligible plain profile'],
      'missing encounter region' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['region_id'] = 'region.missing', 'references missing region'],
      'missing encounter enemy' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['combatants'][0]['enemy_unit_type_id'] = 'enemy_unit_type.missing', 'references missing enemy_unit_type'],
      'empty encounter' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['combatants'] = [], 'non-empty list'],
      'duplicate combatant key' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['combatants'][1]['key'] = 'mudwrestler', 'duplicate combatant key or occupied position'],
      'occupied position' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['combatants'][1]['position'] = ['x' => 2, 'y' => 1], 'duplicate combatant key or occupied position'],
      'bad cell' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['combatants'][0]['position']['x'] = 3, 'position'],
      'bad difficulty' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['difficulty'] = 0, 'difficulty'],
      'empty description' => ['encounter.the_farm_mud_combat_1', static fn(array &$d) => $d['description'] = '', 'description'],
      'missing run encounter' => ['run_generation.the_farm', static function (array &$d): void { unset($d['nodes'][0]['encounter_id']); }, 'node requires an encounter_id'],
      'wrong run encounter' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][0]['encounter_id'] = 'encounter.missing', 'references missing encounter'],
      'missing boss encounter' => ['run_generation.the_farm', static function (array &$d): void { unset($d['nodes'][4]['encounter_id']); }, 'node requires an encounter_id'],
      'wrong boss encounter' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][4]['encounter_id'] = 'encounter.the_farm_mud_combat_1', 'encounter kind does not match'],
      'loot encounter forbidden' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][1]['encounter_id'] = 'encounter.the_farm_mud_combat_1', 'node type cannot reference an encounter'],
      'wrong boss completion event' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][4]['event_id'] = 'event.farm_loot_completed', 'must reference its completion event'],
      'missing boss completion event' => ['run_generation.the_farm', static function(array &$d): void { unset($d['nodes'][4]['event_id']); }, 'must reference its completion event'],
      'server only nonboolean' => ['ability.wrestle', static fn(array &$d) => $d['server_only'] = 'yes', 'server_only'],
      'wrestle missing ratio' => ['ability.wrestle', static function (array &$d): void { unset($d['handler_config']['power_ratio']); }, 'invalid current combat handler configuration'],
      'mud sling invalid reduction' => ['ability.mud_sling', static fn(array &$d) => $d['handler_config']['defense_reduction_flat'] = -1, 'invalid current combat handler configuration'],
      'mud slam wrong target' => ['ability.mud_slam', static fn(array &$d) => $d['target_rule'] = 'enemy_back_prefer', 'incompatible with its handler'],
      'mud slam wrong status' => ['ability.mud_slam', static fn(array &$d) => $d['handler_config']['status_id'] = 'wrestled', 'Unsupported ability status'],
      'mud slam invalid reduction' => ['ability.mud_slam', static fn(array &$d) => $d['handler_config']['defense_reduction_flat'] = -1, 'invalid current combat handler configuration'],
      'mud slam invalid duration' => ['ability.mud_slam', static fn(array &$d) => $d['handler_config']['duration_rounds'] = 0, 'invalid current combat handler configuration'],
      'mudking missing active ability' => ['enemy_unit_type.mudking', static fn(array &$d) => $d['active_ability_ids'][2] = 'ability.missing', 'references missing ability'],
      'mudking passive in active list' => ['enemy_unit_type.mudking', static fn(array &$d) => $d['active_ability_ids'][2] = 'ability.thick_hide', 'requires active abilities'],
      'mudking active in passive list' => ['enemy_unit_type.mudking', static fn(array &$d) => $d['passive_ability_ids'][0] = 'ability.mud_slam', 'requires passive abilities'],
      'boss missing enemy' => ['encounter.the_farm_mud_boss_1', static fn(array &$d) => $d['combatants'][0]['enemy_unit_type_id'] = 'enemy_unit_type.missing', 'references missing enemy_unit_type'],
      'boss invalid position' => ['encounter.the_farm_mud_boss_1', static fn(array &$d) => $d['combatants'][0]['position']['x'] = 3, 'position'],
      'enemy wrong target rule' => ['ability.wrestle', static fn(array &$d) => $d['target_rule'] = 'self', 'incompatible with its handler'],
    ];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }

  /** @return list<array<string,mixed>> */
  private function definitions(): array
  {
    return \DiceGoblins\Tests\Support\CanonicalContentFixture::definitions();
  }
}
