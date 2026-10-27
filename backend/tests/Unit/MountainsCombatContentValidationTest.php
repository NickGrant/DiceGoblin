<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Combat\Vnext\CombatEngine;
use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\CombatSnapshotNormalizer;
use DiceGoblins\Content\ContentRegistry;
use PHPUnit\Framework\TestCase;

final class MountainsCombatContentValidationTest extends TestCase
{
  private const ENCOUNTERS = [
    'encounter.mountains_kobold_combat_1',
    'encounter.mountains_kobold_combat_2',
    'encounter.mountains_kobold_combat_3',
    'encounter.mountains_kobold_boss_1',
  ];

  public function testAuthoredMountainsEncountersNormalizeWithoutCopyingRosterBalance(): void
  {
    $content = $this->content();
    $normalizer = new CombatSnapshotNormalizer($content);
    foreach (self::ENCOUNTERS as $encounterId) {
      $encounter = $content->encounter($encounterId);
      $this->assertSame('region.mountains', $encounter['region_id']);
      $this->assertContains($encounter['kind'], ['combat', 'boss']);
      $combatants = $normalizer->enemyCombatants($encounterId);
      $manifest = $normalizer->enemyManifest($encounterId);
      $this->assertCount(count($encounter['combatants']), $combatants);
      foreach ($encounter['combatants'] as $index => $slot) {
        $enemy = $content->enemyUnitType($slot['enemy_unit_type_id']);
        $actual = $combatants[$index];
        $this->assertSame($slot['key'], $actual['key']);
        $this->assertSame($slot['position'], $actual['position']);
        $this->assertSame($enemy['stats'], $actual['stats']);
        $this->assertSame($enemy['active_ability_ids'], array_column($actual['active_abilities'], 'id'));
        $this->assertSame($enemy['passive_ability_ids'], array_column($actual['passive_abilities'], 'id'));
        $this->assertSame($slot['enemy_unit_type_id'], $manifest[$index]['enemy_unit_type_id']);
        $this->assertSame($enemy['display_name'], $manifest[$index]['display_name']);
        $this->assertSame($enemy['art_key'], $manifest[$index]['art_key']);
      }
    }
    $this->assertSame('boss', $content->encounter(self::ENCOUNTERS[3])['kind']);
  }

  public function testEveryMountainsEncounterNormalizesAndResolvesDeterministicallyThroughVnextEngine(): void
  {
    $normalizer = new CombatSnapshotNormalizer($this->content());
    foreach (self::ENCOUNTERS as $encounterId) {
      $inputA = $normalizer->forEncounter('mountains-deterministic-' . $encounterId, [$this->player($normalizer)], $encounterId);
      $inputB = $normalizer->forEncounter('mountains-deterministic-' . $encounterId, [$this->player($normalizer)], $encounterId);
      $resultA = (new CombatEngine())->resolve($inputA)->toArray();
      $resultB = (new CombatEngine())->resolve($inputB)->toArray();
      $this->assertSame($resultA, $resultB);
      $this->assertSame('victory', $resultA['outcome']);
    }
  }

  public function testKoboldHandlersProduceAuthoredStatusesThroughTheSharedPlaybackModel(): void
  {
    $normalizer = new CombatSnapshotNormalizer($this->content());
    $player = $this->player($normalizer);
    $player['stats'] = ['hp' => 800, 'attack' => 4, 'defense' => 12, 'precision' => 5, 'resolve' => 5];
    $player['max_hp'] = $player['current_hp'] = 800;
    $input = $normalizer->forEncounter('mountains-handler-playback', [$player], self::ENCOUNTERS[3]);
    $result = (new CombatEngine())->resolve($input)->toArray();
    $statusIds = array_column(array_column(array_values(array_filter($result['events'],
      static fn(array $event): bool => $event['type'] === 'status_applied')), 'facts'), 'status_id');
    foreach (['taunting_guard', 'shield_set', 'disarmed', 'fuse_lit'] as $statusId) {
      $this->assertContains($statusId, $statusIds);
    }
    $this->assertNotEmpty(array_filter($result['events'], static fn(array $event): bool =>
      $event['type'] === 'damage_dealt' && $event['facts']['roll_total'] === 0));
  }

  public function testKoboldDefinitionsAndPrivateMechanicsRemainServerOnly(): void
  {
    $content = $this->content();
    $projection = (new ClientContentProjector())->project($content);
    $this->assertArrayNotHasKey('enemy_unit_types', $projection['content']);
    $this->assertArrayNotHasKey('encounters', $projection['content']);
    $this->assertArrayHasKey('region.mountains', $projection['content']['regions']);
    foreach ($content->definitionsOfType('ability') as $ability) {
      if ($ability['server_only'] ?? false) $this->assertArrayNotHasKey($ability['id'], $projection['content']['abilities']);
    }
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    foreach (['virtual_ability_dice', 'handler_config', 'resolution_priority'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
  }

  private function content(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/content');
  }

  /** @return array<string,mixed> */
  private function player(CombatSnapshotNormalizer $normalizer): array
  {
    $die = $normalizer->normalizeDie('mountains_test_die', 6, 'dice_profile.cardboard_plain');
    return [
      'key' => 'player_test', 'side' => 'player', 'position' => ['x' => 2, 'y' => 1],
      'stats' => ['hp' => 500, 'attack' => 40, 'defense' => 12, 'precision' => 8, 'resolve' => 8],
      'max_hp' => 500, 'current_hp' => 500,
      'active_abilities' => [$normalizer->ability('ability.basic_attack_ranged', [$die])],
      'passive_abilities' => [], 'statuses' => [],
    ];
  }
}
