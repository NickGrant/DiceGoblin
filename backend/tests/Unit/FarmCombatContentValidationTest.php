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
  public function testStandardFarmEnemiesAbilitiesEncounterAndSnapshotDice(): void
  {
    $content = $this->content();
    $wrestler = $content->enemyUnitType('enemy_unit_type.mudwrestler');
    $slinger = $content->enemyUnitType('enemy_unit_type.mudslinger');
    $encounter = $content->encounter('encounter.the_farm_mud_combat_1');
    $this->assertSame(['hp' => 16, 'attack' => 3, 'defense' => 2, 'precision' => 5, 'resolve' => 5], $wrestler['stats']);
    $this->assertSame(['hp' => 14, 'attack' => 4, 'defense' => 1, 'precision' => 6, 'resolve' => 4], $slinger['stats']);
    $this->assertSame(['ability.basic_attack_melee', 'ability.wrestle'], $wrestler['active_ability_ids']);
    $this->assertSame(['ability.basic_attack_ranged', 'ability.mud_sling'], $slinger['active_ability_ids']);
    $this->assertSame([['x' => 2, 'y' => 1], ['x' => 0, 'y' => 1]], array_column($encounter['combatants'], 'position'));
    $this->assertSame(1, $encounter['difficulty']);
    $this->assertSame('A pair of pigs lurches out of the muck, giving the warband its first real skirmish.', $encounter['description']);
    $this->assertSame(['sides' => 6, 'profile_id' => 'dice_profile.cardboard_plain'], $wrestler['virtual_ability_dice']);
    $this->assertSame(1.05, $content->ability('ability.wrestle')['handler_config']['power_ratio']);
    $this->assertSame(0.9, $content->ability('ability.mud_sling')['handler_config']['power_ratio']);
    $this->assertSame(2, $content->ability('ability.mud_sling')['handler_config']['defense_reduction_flat']);
    $snapshotEnemies = (new CombatSnapshotNormalizer($content))->enemyCombatants($encounter['id']);
    $this->assertSame(['mudwrestler', 'mudslinger'], array_column($snapshotEnemies, 'key'));
    foreach ($snapshotEnemies as $enemy) {
      foreach ($enemy['active_abilities'] as $ability) {
        $this->assertCount($ability['dice_slot_count'], $ability['dice']);
        foreach ($ability['dice'] as $die) {
          $this->assertSame(6, $die['sides']);
          $this->assertSame('dice_profile.cardboard_plain', $die['profile_id']);
          $this->assertSame([], $die['effects']);
        }
      }
    }
  }

  public function testMudkingAbilityEncounterAndNormalizedSnapshotUseExactAuthoredFacts(): void
  {
    $content = $this->content();
    $mudking = $content->enemyUnitType('enemy_unit_type.mudking');
    $slam = $content->ability('ability.mud_slam');
    $encounter = $content->encounter('encounter.the_farm_mud_boss_1');
    $this->assertSame('Mudking', $mudking['display_name']);
    $this->assertSame('frontline', $mudking['role']);
    $this->assertSame('enemy_mudking', $mudking['art_key']);
    $this->assertSame(['hp' => 30, 'attack' => 5, 'defense' => 4, 'precision' => 5, 'resolve' => 7], $mudking['stats']);
    $this->assertSame(['ability.basic_attack_melee', 'ability.wrestle', 'ability.mud_slam'], $mudking['active_ability_ids']);
    $this->assertSame(['ability.thick_hide'], $mudking['passive_ability_ids']);
    $this->assertSame(['sides' => 6, 'profile_id' => 'dice_profile.cardboard_plain'], $mudking['virtual_ability_dice']);
    $this->assertSame(['active', true, 1, 8, 18, 'enemy_front_prefer', 'mud_slam'], [
      $slam['kind'], $slam['server_only'], $slam['dice_slot_count'], $slam['action_delay'],
      $slam['resolution_priority'], $slam['target_rule'], $slam['handler_id'],
    ]);
    $this->assertSame(['power_ratio' => 1.2, 'status_id' => 'cracked_armor',
      'defense_reduction_flat' => 3, 'duration_rounds' => 2], $slam['handler_config']);
    $this->assertSame(['region.the_farm', 2], [$encounter['region_id'], $encounter['difficulty']]);
    $this->assertSame([['key' => 'mudking', 'enemy_unit_type_id' => 'enemy_unit_type.mudking',
      'position' => ['x' => 2, 'y' => 1]]], $encounter['combatants']);

    $normalized = (new CombatSnapshotNormalizer($content))->enemyCombatants($encounter['id']);
    $this->assertCount(1, $normalized);
    $boss = $normalized[0];
    $this->assertSame('mudking', $boss['key']);
    $this->assertSame($mudking['stats'], $boss['stats']);
    $this->assertSame($mudking['stats']['hp'], $boss['max_hp']);
    $this->assertSame($mudking['stats']['hp'], $boss['current_hp']);
    $this->assertSame($mudking['active_ability_ids'], array_column($boss['active_abilities'], 'id'));
    $this->assertSame([['id' => 'ability.thick_hide', 'handler_id' => 'thick_hide',
      'config' => ['defense_flat' => 2]]], $boss['passive_abilities']);
    $dice = array_column($boss['active_abilities'], 'dice');
    $keys = array_map(static fn(array $slots): string => (string)$slots[0]['key'], $dice);
    $this->assertSame(['mudking_basic_attack_melee_0', 'mudking_wrestle_0', 'mudking_mud_slam_0'], $keys);
    $this->assertCount(3, array_unique($keys));
    foreach ($dice as $slots) {
      $this->assertSame([['key' => $slots[0]['key'], 'sides' => 6,
        'profile_id' => 'dice_profile.cardboard_plain', 'effects' => []]], $slots);
    }
  }

  public function testHiddenEnemyMechanicsAndRosterNeverEnterClientProjection(): void
  {
    $projection = (new ClientContentProjector())->project($this->content());
    $this->assertArrayNotHasKey('enemy_unit_types', $projection['content']);
    $this->assertArrayNotHasKey('encounters', $projection['content']);
    $this->assertArrayNotHasKey('ability.wrestle', $projection['content']['abilities']);
    $this->assertArrayNotHasKey('ability.mud_sling', $projection['content']['abilities']);
    $this->assertArrayNotHasKey('ability.mud_slam', $projection['content']['abilities']);
    $this->assertArrayHasKey('ability.basic_attack_melee', $projection['content']['abilities']);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    foreach (['Mudwrestler', 'Mudslinger', 'Mudking', 'enemy_mudking', 'the_farm_mud_combat_1',
      'the_farm_mud_boss_1', 'mud_slam', 'virtual_ability_dice', 'cracked_armor', 'wrestled'] as $secret) {
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
      'missing run encounter' => ['run_generation.the_farm', static function (array &$d): void { unset($d['nodes'][0]['encounter_id']); }, 'first combat node'],
      'wrong run encounter' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][0]['encounter_id'] = 'encounter.missing', 'references missing encounter'],
      'missing boss encounter' => ['run_generation.the_farm', static function (array &$d): void { unset($d['nodes'][3]['encounter_id']); }, 'Boss node'],
      'wrong boss encounter' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][3]['encounter_id'] = 'encounter.the_farm_mud_combat_1', 'Boss node'],
      'loot encounter forbidden' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][1]['encounter_id'] = 'encounter.the_farm_mud_combat_1', 'only combat or boss nodes'],
      'boss completion event forbidden' => ['run_generation.the_farm', static fn(array &$d) => $d['nodes'][3]['event_id'] = 'event.farm_loot_completed', 'must not reference a completion event'],
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
    $content = $this->content();
    $definitions = [];
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability',
      'dice_material', 'dice_aspect', 'dice_profile', 'run_node_type', 'run_generation', 'event', 'reward_definition'] as $type) {
      foreach ($content->definitionsOfType($type) as $definition) $definitions[] = $definition;
    }
    return $definitions;
  }
}
