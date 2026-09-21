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

  public function testCanonicalKoboldRosterHasExactStatsRolesAndAbilityIdentities(): void
  {
    $content = $this->content();
    $expected = [
      'enemy_unit_type.kobold_skirmisher' => ['Kobold Skirmisher', 'backline', [18, 6, 2, 6, 4],
        ['ability.bomb_toss', 'ability.basic_attack_ranged'], ['ability.sharpshooter'], 'kobold_skirmisher'],
      'enemy_unit_type.kobold_shieldbearer' => ['Kobold Shieldbearer', 'frontline', [28, 3, 6, 4, 6],
        ['ability.basic_attack_melee', 'ability.taunting_guard'], ['ability.shield_set', 'ability.wall_of_scrap', 'ability.unmoving'], 'kobold_shieldbearer'],
      'enemy_unit_type.kobold_sharpshooter' => ['Kobold Sharpshooter', 'backline', [22, 9, 3, 7, 4],
        ['ability.basic_attack_ranged', 'ability.disarming_shot', 'ability.aimed_shot'], ['ability.sharpshooter', 'ability.clean_shot'], 'kobold_sharpshooter'],
      'enemy_unit_type.kobold_warchief' => ['Kobold Chief Engineer', 'backline', [42, 11, 4, 7, 5],
        ['ability.bomb_toss', 'ability.basic_attack_ranged', 'ability.aimed_shot'], ['ability.sharpshooter', 'ability.patient_aim', 'ability.dumb_luck'], 'kobold_warchief'],
    ];
    foreach ($expected as $id => [$name, $role, $stats, $actives, $passives, $art]) {
      $enemy = $content->enemyUnitType($id);
      $this->assertSame([$name, $role, $art], [$enemy['display_name'], $enemy['role'], $enemy['art_key']]);
      $this->assertSame(array_combine(['hp', 'attack', 'defense', 'precision', 'resolve'], $stats), $enemy['stats']);
      $this->assertSame($actives, $enemy['active_ability_ids']);
      $this->assertSame($passives, $enemy['passive_ability_ids']);
    }
  }

  public function testMountainsEncountersHaveExactAuthoredFormationsAndBossClassification(): void
  {
    $content = $this->content();
    $expected = [
      self::ENCOUNTERS[0] => ['combat', [['kobold_shieldbearer', 0, 1], ['kobold_skirmisher', 2, 0], ['kobold_skirmisher', 2, 2]]],
      self::ENCOUNTERS[1] => ['combat', [['kobold_shieldbearer', 0, 1], ['kobold_skirmisher', 2, 0], ['kobold_sharpshooter', 2, 2]]],
      self::ENCOUNTERS[2] => ['combat', [['kobold_shieldbearer', 0, 0], ['kobold_shieldbearer', 0, 2], ['kobold_skirmisher', 2, 0], ['kobold_sharpshooter', 2, 2]]],
      self::ENCOUNTERS[3] => ['boss', [['kobold_shieldbearer', 0, 1], ['kobold_sharpshooter', 1, 0], ['kobold_skirmisher', 2, 2], ['kobold_warchief', 2, 1]]],
    ];
    foreach ($expected as $id => [$kind, $formation]) {
      $encounter = $content->encounter($id);
      $this->assertSame(['region.mountains', $kind], [$encounter['region_id'], $encounter['kind']]);
      $actual = array_map(static fn(array $slot): array => [
        substr($slot['enemy_unit_type_id'], strlen('enemy_unit_type.')), $slot['position']['x'], $slot['position']['y'],
      ], $encounter['combatants']);
      $this->assertSame($formation, $actual);
    }
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

  public function testSnapshotManifestCarriesStableEnemyAndExistingArtIdentities(): void
  {
    $manifest = (new CombatSnapshotNormalizer($this->content()))->enemyManifest(self::ENCOUNTERS[3]);
    $this->assertSame(
      ['enemy_unit_type.kobold_shieldbearer', 'enemy_unit_type.kobold_sharpshooter',
        'enemy_unit_type.kobold_skirmisher', 'enemy_unit_type.kobold_warchief'],
      array_column($manifest, 'enemy_unit_type_id'),
    );
    $this->assertSame(['kobold_shieldbearer', 'kobold_sharpshooter', 'kobold_skirmisher', 'kobold_warchief'],
      array_column($manifest, 'art_key'));
    $this->assertSame('Kobold Chief Engineer', $manifest[3]['display_name']);
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
    $projection = (new ClientContentProjector())->project($this->content());
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    foreach (['kobold_skirmisher', 'kobold_shieldbearer', 'kobold_sharpshooter', 'kobold_warchief',
      'bomb_toss', 'wall_of_scrap', 'unmoving', 'clean_shot', 'patient_aim', 'dumb_luck',
      'guard_stack_cap', 'attack_reduction_pct'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
    $this->assertSame(['id' => 'region.mountains', 'display_name' => 'Mountains', 'art_key' => 'mountains'],
      $projection['content']['regions']['region.mountains']);
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
