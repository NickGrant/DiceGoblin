<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit\Combat;

use DiceGoblins\Combat\Vnext\CombatEngine;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatRules;
use DiceGoblins\Combat\Vnext\DamageCalculator;
use DiceGoblins\Combat\Vnext\TargetResolver;
use DiceGoblins\Content\CombatSnapshotNormalizer;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Support\DeterministicRandom;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VnextCombatEngineTest extends TestCase
{
  public function testRealFarmSnapshotIsRepeatableVersionedAndEnvironmentFree(): void
  {
    $registry = $this->content();
    $normalizer = new CombatSnapshotNormalizer($registry);
    $bruiser = $registry->unitType('unit_type.bruiser');
    $plain = $normalizer->normalizeDie('bruiser_basic', 6, 'dice_profile.cardboard_plain');
    $heavy = $normalizer->normalizeDie('bruiser_heavy', 10, 'dice_profile.cardboard_striking');
    $player = $this->unit('bruiser', 'player', 2, 1, $bruiser['base_stats'], [
      $normalizer->ability('ability.basic_attack_melee', [$plain]),
      $normalizer->ability('ability.heavy_strike', [$heavy]),
    ], [$normalizer->passive('ability.thick_hide')]);
    $snapshot = $normalizer->forEncounter('farm-golden-1', [$player], 'encounter.the_farm_mud_combat_1');
    $engine = new CombatEngine();
    $first = $engine->resolve($snapshot)->toArray();
    $second = $engine->resolve($snapshot)->toArray();
    $this->assertSame($first, $second);
    $this->assertSame(1, $first['engine_version']);
    $this->assertSame(1, $first['playback_version']);
    $this->assertContains($first['outcome'], ['victory', 'defeat', 'stalemate']);
    $this->assertSame(['victory', 2, 28, 3], [$first['outcome'], $first['ending_round'],
      $first['ending_tick'], $first['combatants'][0]['current_hp']]);
    $firstDamage = $this->events($first, 'damage_dealt')[0]['facts'];
    $this->assertSame([5, 1, 2, 1.21, 4], [$firstDamage['attack_component'], $firstDamage['roll_total'],
      $firstDamage['target_defense'], $firstDamage['position_multiplier'], $firstDamage['amount']]);
    $this->assertSame(['bruiser', 'mudslinger', 'mudwrestler'], array_column($first['combatants'], 'key'));
    $this->assertSame('battle_started', $first['events'][0]['type']);
    $this->assertSame('battle_ended', $first['events'][array_key_last($first['events'])]['type']);
    $this->assertSame(['mudslinger', 'mudwrestler', 'bruiser'],
      array_column(array_column(array_values(array_filter($first['events'],
        static fn(array $event): bool => $event['type'] === 'action_started' && $event['tick'] === 12)), 'facts'), 'actor_key'));
    $this->assertSame('wrestled_forced', array_values(array_filter($first['events'],
      static fn(array $event): bool => $event['type'] === 'action_started' && $event['tick'] === 12))[2]['facts']['target_reason']);
    $this->assertSame('dead', array_values(array_filter($first['events'],
      static fn(array $event): bool => $event['type'] === 'action_skipped'))[0]['facts']['reason']);
    $encoded = json_encode($first, JSON_THROW_ON_ERROR);
    foreach (['createdAt', 'timestamp', 'reward', 'teeth', 'run_id', 'node_id', 'message'] as $forbidden) {
      $this->assertStringNotContainsString($forbidden, $encoded);
    }
    $this->assertNotSame($first['events'], $engine->resolve($normalizer->forEncounter('farm-golden-2', [$player], 'encounter.the_farm_mud_combat_1'))->events);
  }

  public function testCurrentFiveRaiderLoadoutsResolveAgainstAuthoredFarmFight(): void
  {
    $content = $this->content();
    $normalizer = new CombatSnapshotNormalizer($content);
    $resolver = new BaseLevelStatResolver();
    $specs = [
      ['bruiser', 'unit_type.bruiser', 3, 2, 0,
        [['ability.basic_attack_melee', [['bruiser_basic', 6, 'dice_profile.cardboard_plain']]],
          ['ability.heavy_strike', [['bruiser_heavy', 10, 'dice_profile.cardboard_striking']]]], ['ability.thick_hide']],
      ['guardian', 'unit_type.guardian', 2, 2, 1,
        [['ability.shield_up', [['guardian_shield', 8, 'dice_profile.cardboard_guarding']]],
          ['ability.basic_attack_melee', [['guardian_basic', 6, 'dice_profile.wood_plain']]]], ['ability.thick_hide']],
      ['marksman', 'unit_type.marksman', 4, 1, 0,
        [['ability.basic_attack_ranged', [['marksman_basic', 8, 'dice_profile.wood_precise']]],
          ['ability.aimed_shot', [['marksman_aimed', 12, 'dice_profile.bone_executioner']]]], ['ability.sharpshooter']],
      ['bannerbearer', 'unit_type.bannerbearer', 2, 1, 1,
        [['ability.bolster_ally', [['banner_bolster', 10, 'dice_profile.wood_bulwark']]],
          ['ability.basic_attack_melee', [['banner_basic', 6, 'dice_profile.cardboard_plain']]]], []],
      ['saboteur', 'unit_type.saboteur', 5, 0, 0,
        [['ability.sleep_dart', [['sleep_a', 4, 'dice_profile.cardboard_plain'], ['sleep_b', 20, 'dice_profile.bone_explosive']]],
          ['ability.basic_attack_ranged', [['saboteur_basic', 8, 'dice_profile.wood_precise']]]], []],
    ];
    $players = [];
    foreach ($specs as [$key, $typeId, $level, $x, $y, $loadout, $passiveIds]) {
      $type = $content->unitType($typeId);
      $stats = $resolver->resolve($type['base_stats'], $type['growth_per_level'], $level)->toArray();
      $abilities = [];
      foreach ($loadout as [$abilityId, $bound]) {
        $dice = [];
        foreach ($bound as [$dieKey, $sides, $profile]) $dice[] = $normalizer->normalizeDie($dieKey, $sides, $profile);
        $abilities[] = $normalizer->ability($abilityId, $dice);
      }
      $passives = [];
      foreach ($passiveIds as $id) $passives[] = $normalizer->passive($id);
      $players[] = $this->unit($key, 'player', $x, $y, $stats, $abilities, $passives);
    }
    $input = $normalizer->forEncounter('raiders-farm-golden', $players, 'encounter.the_farm_mud_combat_1');
    $result = (new CombatEngine())->resolve($input)->toArray();
    $this->assertSame($result, (new CombatEngine())->resolve($input)->toArray());
    $this->assertSame('victory', $result['outcome']);
    $this->assertCount(7, $result['combatants']);
    $abilitiesUsed = array_unique(array_column(array_column($this->events($result, 'action_started'), 'facts'), 'ability_id'));
    $this->assertContains('ability.basic_attack_melee', $abilitiesUsed);
    $this->assertContains('ability.basic_attack_ranged', $abilitiesUsed);
  }

  public function testFirstActionNextAbilityDelayPriorityAndKeyOrder(): void
  {
    $player = $this->unit('player_a', 'player', 1, 1, $this->stats(200, 1, 200), [
      $this->ability('basic_attack_ranged', 4, 10),
      $this->ability('aimed_shot', 8, 20),
    ]);
    $enemyA = $this->unit('enemy_a', 'enemy', 1, 0, $this->stats(200, 1, 200), [$this->ability('basic_attack_ranged', 4, 5)]);
    $enemyB = $this->unit('enemy_b', 'enemy', 0, 1, $this->stats(200, 1, 200), [$this->ability('basic_attack_ranged', 4, 5)]);
    $result = $this->resolve('schedule-1', [$enemyB, $player, $enemyA]);
    $actions = $this->events($result, 'action_started');
    $atFour = array_values(array_filter($actions, static fn(array $event): bool => $event['tick'] === 4));
    $this->assertSame(['enemy_a', 'enemy_b', 'player_a'], array_column(array_column($atFour, 'facts'), 'actor_key'));
    $playerActions = array_values(array_filter($actions, static fn(array $event): bool => $event['facts']['actor_key'] === 'player_a'));
    $this->assertSame([4, 12, 16], array_slice(array_column($playerActions, 'tick'), 0, 3));
    $this->assertSame(['ability.basic_attack_ranged', 'ability.aimed_shot', 'ability.basic_attack_ranged'],
      array_slice(array_column(array_column($playerActions, 'facts'), 'ability_id'), 0, 3));
  }

  public function testPowerRatioDiceAndRoundingHaveNoFreeDamageVariance(): void
  {
    $ability = $this->ability('basic_attack_ranged', 1, 10, [$this->die('plain', 6)], ['power_ratio' => 1.6]);
    $player = $this->unit('player_a', 'player', 1, 1, $this->stats(20, 10, 0), [$ability]);
    $enemy = $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(1, 0, 4), [$this->ability('basic_attack_ranged', 1000)]);
    $first = $this->resolve('damage-golden-1', [$player, $enemy]);
    $second = $this->resolve('damage-golden-2', [$player, $enemy]);
    $die1 = $this->events($first, 'dice_rolled')[0]['facts']['initial_roll'];
    $die2 = $this->events($second, 'dice_rolled')[0]['facts']['initial_roll'];
    $this->assertSame((new DeterministicRandom('damage-golden-1'))->nextInt(1, 6), $die1);
    $this->assertSame(16 + $die1 - 4, $this->events($first, 'damage_dealt')[0]['facts']['amount']);
    $this->assertSame(16 + $die2 - 4, $this->events($second, 'damage_dealt')[0]['facts']['amount']);
    $this->assertSame('victory', $first['outcome']);
  }

  public function testPositionAndConditionalDamageStagesFloorInOrder(): void
  {
    $actor = $this->unit('actor', 'player', 2, 1, $this->stats(30, 10, 0), [$this->ability('heavy_strike', 8, 20, [$this->die('d', 6)], ['power_ratio' => 1.6])]);
    $front = $this->unit('front', 'enemy', 2, 1, $this->stats(30, 0, 4), [$this->ability('basic_attack_melee')]);
    $back = $this->unit('back', 'enemy', 0, 1, $this->stats(30, 0, 4), [$this->ability('basic_attack_melee')]);
    $calculator = new DamageCalculator();
    $this->assertSame((int)floor((16 + 3 - 4) * 1.10 * 1.10),
      $calculator->calculate($actor, $front, $actor['active_abilities'][0], 3, false)['amount']);
    $this->assertSame((int)floor((16 + 3 - 4) * 1.10 * 0.90),
      $calculator->calculate($actor, $back, $actor['active_abilities'][0], 3, false)['amount']);
    $this->assertSame((int)floor((int)floor((16 + 3 - 4) * 1.10 * 1.10) * 1.5),
      $calculator->calculate($actor, $front, $actor['active_abilities'][0], 3, true)['amount']);
    $ignored = $actor['active_abilities'][0];
    $ignored['config']['ignore_defense_flat'] = 2;
    $this->assertSame(2, $calculator->calculate($actor, $front, $ignored, 3, false)['target_defense']);
  }

  public function testPrecisionMissCritAndNeutralRngConsumption(): void
  {
    $lowSeed = $this->seedWithFirstRollAtMost(24);
    $highSeed = $this->seedWithFirstRollAtMost(30);
    $enemy = $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(50, 0, 0), [$this->ability('basic_attack_ranged', 1000)]);
    $low = $this->unit('player_a', 'player', 1, 1, $this->stats(20, 5, 0, 2), [$this->ability('basic_attack_ranged', 1)]);
    $high = $this->unit('player_a', 'player', 1, 1, $this->stats(20, 5, 0, 11), [$this->ability('basic_attack_ranged', 1)]);
    $neutral = $this->unit('player_a', 'player', 1, 1, $this->stats(20, 5, 0, 5), [$this->ability('basic_attack_ranged', 1)]);
    $miss = $this->resolve($lowSeed, [$low, $enemy]);
    $crit = $this->resolve($highSeed, [$high, $enemy]);
    $plain = $this->resolve('neutral-precision', [$neutral, $enemy]);
    $this->assertSame('miss', $this->events($miss, 'hit_resolved')[0]['facts']['result']);
    $this->assertSame([], array_values(array_filter($this->events($miss, 'damage_dealt'),
      static fn(array $event): bool => $event['tick'] === 1)));
    $this->assertSame('critical', $this->events($crit, 'hit_resolved')[0]['facts']['result']);
    $this->assertSame(30, $this->events($crit, 'hit_resolved')[0]['facts']['chance_percent']);
    $this->assertNull($this->events($plain, 'hit_resolved')[0]['facts']['check_roll']);
    $this->assertSame((new DeterministicRandom('neutral-precision'))->nextInt(1, 6),
      $this->events($plain, 'dice_rolled')[0]['facts']['initial_roll']);
  }

  public function testFrontBackLowestHpAndWrestledForcedTargetAreDeterministic(): void
  {
    $rng = new DeterministicRandom('targets-1');
    $actor = $this->unit('actor', 'player', 1, 1, $this->stats(20, 5, 0), [$this->ability('basic_attack_melee')]);
    $frontA = $this->unit('front_a', 'enemy', 2, 0, $this->stats(10, 0, 0), [$this->ability('basic_attack_melee')]);
    $frontB = $this->unit('front_b', 'enemy', 2, 1, $this->stats(10, 0, 0), [$this->ability('basic_attack_melee')]);
    $back = $this->unit('back', 'enemy', 0, 1, $this->stats(10, 0, 0), [$this->ability('basic_attack_ranged')]);
    $units = ['actor' => $actor, 'front_a' => $frontA, 'front_b' => $frontB, 'back' => $back];
    $resolver = new TargetResolver();
    $this->assertSame('front_preference_tie', $resolver->choose($units, 'actor', 'enemy_front_prefer', true, $rng)['reason']);
    $expectedIndex = (new DeterministicRandom('targets-1'))->nextInt(0, 1);
    $this->assertSame(['front_a', 'front_b'][$expectedIndex],
      $resolver->choose($units, 'actor', 'enemy_front_prefer', true, new DeterministicRandom('targets-1'))['key']);
    $differentSeed = $this->seedWithDifferentFirstChoice('targets-1', 2);
    $this->assertNotSame(['front_a', 'front_b'][$expectedIndex],
      $resolver->choose($units, 'actor', 'enemy_front_prefer', true, new DeterministicRandom($differentSeed))['key']);
    $this->assertSame(['key' => 'back', 'reason' => 'back_preference'],
      $resolver->choose($units, 'actor', 'enemy_back_prefer', true, $rng));
    $actor['statuses'][] = $this->status('wrestled', 'back', 3, [], 'back');
    $units['actor'] = $actor;
    $this->assertSame(['key' => 'back', 'reason' => 'wrestled_forced'],
      $resolver->choose($units, 'actor', 'enemy_front_prefer', true, $rng));
    $ally = $this->unit('ally', 'player', 0, 1, $this->stats(40, 0, 0), [$this->ability('shield_up')]);
    $ally['current_hp'] = 10;
    $actor['current_hp'] = 10;
    $units['actor'] = $actor; $units['ally'] = $ally;
    $this->assertSame('ally', $resolver->choose($units, 'actor', 'ally_lowest_hp_pct', false, $rng)['key']);
    $tiedAlly = $this->unit('ally_tie', 'player', 0, 2, $this->stats(20, 0, 0), [$this->ability('shield_up')]);
    $tiedAlly['current_hp'] = 5;
    $units['ally_tie'] = $tiedAlly;
    $this->assertSame('lowest_hp_pct_tie',
      $resolver->choose($units, 'actor', 'ally_lowest_hp_pct', false, new DeterministicRandom('targets-1'))['reason']);
    $units['back']['current_hp'] = 0;
    $this->assertNotSame('wrestled_forced',
      $resolver->choose($units, 'actor', 'enemy_front_prefer', true, new DeterministicRandom('targets-1'))['reason']);
  }

  public function testDiceAspectsPassiveAndMultipleSlotsComposeFromSnapshotFacts(): void
  {
    $registry = $this->content();
    $normalizer = new CombatSnapshotNormalizer($registry);
    $guard = $normalizer->normalizeDie('guard', 6, 'dice_profile.cardboard_guarding');
    $bulwark = $normalizer->normalizeDie('bulwark', 6, 'dice_profile.wood_bulwark');
    $bulwarkTwo = $normalizer->normalizeDie('bulwark_two', 6, 'dice_profile.wood_bulwark');
    $precise = $normalizer->normalizeDie('precise', 6, 'dice_profile.wood_precise');
    $striking = $normalizer->normalizeDie('striking', 6, 'dice_profile.cardboard_striking');
    $executioner = $normalizer->normalizeDie('executioner', 6, 'dice_profile.bone_executioner');
    $actor = $this->unit('actor', 'player', 1, 1, $this->stats(30, 10, 10), [
      $this->ability('basic_attack_ranged', 1, 10, [$striking], ['power_ratio' => 1]),
      $this->ability('sleep_dart', 12, 25, [$precise, $executioner], ['status_id' => 'sleep', 'duration_rounds' => 2]),
      $this->ability('shield_up', 10, 5, [$guard], ['status_id' => 'bolstered', 'bolster_defense_pct' => 0.25, 'duration_rounds' => 2]),
      $this->ability('bolster_ally', 10, 5, [$bulwark], ['status_id' => 'bolstered', 'bolster_defense_pct' => 0.25, 'duration_rounds' => 2]),
      $this->ability('basic_attack_melee', 4, 10, [$bulwarkTwo]),
    ], [$normalizer->passive('ability.thick_hide'), $normalizer->passive('ability.sharpshooter')]);
    $target = $this->unit('target', 'enemy', 1, 1, $this->stats(30, 0, 0), [$this->ability('basic_attack_ranged')]);
    $target['current_hp'] = 14;
    $math = new \DiceGoblins\Combat\Vnext\CombatStatMath();
    $this->assertSame(11, $math->attack($actor));
    $this->assertSame(15, $math->defense($actor));
    $damage = (new DamageCalculator())->calculate($actor, $target, $actor['active_abilities'][0], 3, false);
    $this->assertSame((int)floor((11 + 3 + 1) * 1.15), $damage['amount']);
    $action = $this->ability('sleep_dart', 1, 25, [$precise, $executioner], ['status_id' => 'sleep', 'duration_rounds' => 2]);
    $plainActor = $this->unit('player_a', 'player', 1, 1, $this->stats(20, 1, 0), [$action]);
    $result = $this->resolve('two-slot-1', [$plainActor, $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(20, 0, 0), [$this->ability('basic_attack_ranged', 1000)])]);
    $rolls = $this->events($result, 'dice_rolled');
    $this->assertSame([0, 1], array_slice(array_column(array_column($rolls, 'facts'), 'slot'), 0, 2));
  }

  public function testExecutionerAndExplosiveAreConditionalAndOneLevelOnly(): void
  {
    $normalizer = new CombatSnapshotNormalizer($this->content());
    $executioner = $normalizer->normalizeDie('executioner', 6, 'dice_profile.bone_executioner');
    $explosive = $normalizer->normalizeDie('explosive', 6, 'dice_profile.bone_explosive');
    $actor = $this->unit('actor', 'player', 1, 1, $this->stats(20, 10, 0), [$this->ability('basic_attack_ranged', 1, 10, [$executioner])]);
    $target = $this->unit('target', 'enemy', 1, 1, $this->stats(20, 0, 0), [$this->ability('basic_attack_ranged', 1000)]);
    $calculator = new DamageCalculator();
    $this->assertSame(1.0, $calculator->calculate($actor, $target, $actor['active_abilities'][0], 1, false)['conditional_multiplier']);
    $target['current_hp'] = 9;
    $this->assertSame(1.15, $calculator->calculate($actor, $target, $actor['active_abilities'][0], 1, false)['conditional_multiplier']);
    $seed = $this->seedWithFirstDieMaximum(6);
    $actor['active_abilities'] = [$this->ability('basic_attack_ranged', 1, 10, [$explosive])];
    $battle = $this->resolve($seed, [$actor, $target]);
    $roll = $this->events($battle, 'dice_rolled')[0]['facts'];
    $this->assertSame(6, $roll['initial_roll']);
    $this->assertNotNull($roll['extra_roll']);
    $this->assertSame(6 + $roll['extra_roll'], $roll['roll_total']);
  }

  public function testResolveResistanceAndNeutralThresholdConsumeRngOnlyWhenRequired(): void
  {
    $seed = $this->seedWithSecondRollAtMost(40);
    $wrestler = $this->unit('player_a', 'player', 2, 1, $this->stats(100, 1, 100),
      [$this->ability('wrestle', 1, 17)]);
    $resistant = $this->unit('enemy_a', 'enemy', 2, 1, $this->stats(100, 0, 100, 5, 10),
      [$this->ability('basic_attack_melee', 4000)]);
    $resisted = $this->resolve($seed, [$wrestler, $resistant]);
    $firstResist = $this->events($resisted, 'status_resisted')[0];
    $this->assertSame(1, $firstResist['tick']);
    $this->assertSame(40, $firstResist['facts']['chance_percent']);
    $this->assertSame([], array_values(array_filter($this->events($resisted, 'status_applied'),
      static fn(array $event): bool => $event['tick'] === 1)));
    $neutral = $resistant; $neutral['stats']['resolve'] = 5;
    $applied = $this->resolve($seed, [$wrestler, $neutral]);
    $this->assertSame(1, $this->events($applied, 'status_applied')[0]['tick']);
    $this->assertSame([], $this->events($applied, 'status_resisted'));
    $this->assertSame((new DeterministicRandom($seed))->nextInt(1, 6),
      $this->events($applied, 'dice_rolled')[0]['facts']['initial_roll']);
  }

  public function testPrecisionMissStillRollsDiceButCannotApplyMudStatus(): void
  {
    $seed = $this->seedWithFirstRollAtMost(24);
    $slinger = $this->unit('player_a', 'player', 0, 1, $this->stats(100, 4, 0, 2),
      [$this->ability('mud_sling', 1, 17)]);
    $target = $this->unit('enemy_a', 'enemy', 0, 1, $this->stats(100, 0, 100),
      [$this->ability('basic_attack_ranged', 4000)]);
    $result = $this->resolve($seed, [$slinger, $target]);
    $this->assertSame('miss', $this->events($result, 'hit_resolved')[0]['facts']['result']);
    $this->assertSame(1, $this->events($result, 'dice_rolled')[0]['tick']);
    $this->assertSame([], array_values(array_filter($this->events($result, 'damage_dealt'),
      static fn(array $event): bool => $event['tick'] === 1)));
    $this->assertSame([], array_values(array_filter($this->events($result, 'status_applied'),
      static fn(array $event): bool => $event['tick'] === 1)));
  }

  public function testWrestledExpiresNormallyWhenNoEligibleAttackConsumesIt(): void
  {
    $player = $this->unit('player_a', 'player', 1, 1, $this->stats(100000, 1, 100000),
      [$this->ability('basic_attack_ranged', 4000)], [], [$this->status('wrestled', 'enemy_a', 2, [], 'enemy_a')]);
    $enemy = $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(100000, 1, 100000),
      [$this->ability('basic_attack_ranged', 4000)]);
    $result = $this->resolve('wrestled-expiry', [$player, $enemy]);
    $removed = $this->events($result, 'status_removed')[0];
    $this->assertSame([2, 21, 'wrestled', 'expired'], [$removed['round'], $removed['tick'],
      $removed['facts']['status_id'], $removed['facts']['reason']]);
  }

  public function testBolsteredAndCrackedArmorUseAuthoredValuesAndExpireAtRoundBoundary(): void
  {
    $shield = $this->unit('player_a', 'player', 1, 1, $this->stats(100000, 1, 8), [
      $this->ability('shield_up', 1, 5),
      $this->ability('basic_attack_ranged', 4000),
    ]);
    $enemy = $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(100000, 1, 100000),
      [$this->ability('basic_attack_ranged', 4000)]);
    $battle = $this->resolve('bolster-duration', [$shield, $enemy]);
    $applied = $this->events($battle, 'status_applied')[0];
    $this->assertSame(['bolstered', 3, ['defense_pct' => 0.25]],
      [$applied['facts']['status_id'], $applied['facts']['expires_round'], $applied['facts']['params']]);
    $expired = array_values(array_filter($this->events($battle, 'status_removed'),
      static fn(array $event): bool => $event['facts']['status_id'] === 'bolstered' && $event['facts']['reason'] === 'expired'));
    $this->assertSame([3, 41], [$expired[0]['round'], $expired[0]['tick']]);
    $math = new \DiceGoblins\Combat\Vnext\CombatStatMath();
    $shield['statuses'][] = $this->status('bolstered', 'player_a', 3, ['defense_pct' => 0.25]);
    $this->assertSame(10, $math->defense($shield));

    $slinger = $this->unit('player_a', 'player', 0, 1, $this->stats(100000, 1, 100000), [
      $this->ability('mud_sling', 1, 17),
      $this->ability('basic_attack_ranged', 4000),
    ]);
    $armored = $this->unit('enemy_a', 'enemy', 0, 1, $this->stats(100000, 0, 100000),
      [$this->ability('basic_attack_ranged', 4000)]);
    $mud = $this->resolve('armor-duration', [$slinger, $armored]);
    $cracked = $this->events($mud, 'status_applied')[0];
    $this->assertSame(['cracked_armor', 3, ['defense_reduction_flat' => 2]],
      [$cracked['facts']['status_id'], $cracked['facts']['expires_round'], $cracked['facts']['params']]);
    $armored['statuses'][] = $this->status('cracked_armor', 'player_a', 3, ['defense_reduction_flat' => 2]);
    $this->assertSame(99998, $math->defense($armored));
    $removed = array_values(array_filter($this->events($mud, 'status_removed'),
      static fn(array $event): bool => $event['facts']['status_id'] === 'cracked_armor' && $event['facts']['reason'] === 'expired'));
    $this->assertSame([3, 41], [$removed[0]['round'], $removed[0]['tick']]);
  }

  public function testBolsterAllyTargetsLowestHpPercentageAndDoesNotRollForPrecisionOrResolve(): void
  {
    $support = $this->unit('player_a', 'player', 1, 1, $this->stats(30, 1, 0, 0),
      [$this->ability('bolster_ally', 1, 5), $this->ability('basic_attack_ranged', 4000)]);
    $ally = $this->unit('player_b', 'player', 0, 1, $this->stats(40, 1, 0, 5, 20),
      [$this->ability('basic_attack_ranged', 4000)]);
    $ally['current_hp'] = 10;
    $enemy = $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(100, 1, 100),
      [$this->ability('basic_attack_ranged', 4000)]);
    $result = $this->resolve('ally-buff', [$enemy, $ally, $support]);
    $action = $this->events($result, 'action_started')[0]['facts'];
    $this->assertSame(['player_b', 'lowest_hp_pct'], [$action['target_key'], $action['target_reason']]);
    $this->assertSame('player_b', $this->events($result, 'status_applied')[0]['facts']['target_key']);
    $this->assertSame([], array_values(array_filter($this->events($result, 'hit_resolved'),
      static fn(array $event): bool => $event['tick'] === 1)));
    $this->assertSame([], $this->events($result, 'status_resisted'));
    $this->assertSame((new DeterministicRandom('ally-buff'))->nextInt(1, 6),
      $this->events($result, 'dice_rolled')[0]['facts']['initial_roll']);
  }

  public function testSleepSkipsActionsBreaksOnDamageAndBlocksWakeTick(): void
  {
    $sleeper = $this->unit('player_a', 'player', 1, 1, $this->stats(100, 100, 0), [
      $this->ability('sleep_dart', 1, 1, [$this->die('dart_a', 6), $this->die('dart_b', 6)]),
      $this->ability('basic_attack_ranged', 2, 1),
    ]);
    $enemy = $this->unit('enemy_a', 'enemy', 0, 1, $this->stats(100, 1, 0),
      [$this->ability('basic_attack_ranged', 2, 10)]);
    $battle = $this->resolve('sleep-break', [$sleeper, $enemy]);
    $this->assertSame('sleep', $this->events($battle, 'status_applied')[0]['facts']['status_id']);
    $skipped = $this->events($battle, 'action_skipped')[0];
    $this->assertSame([2, 'sleep'], [$skipped['tick'], $skipped['facts']['reason']]);
    $removed = $this->events($battle, 'status_removed')[0];
    $this->assertSame([3, 'damaged'], [$removed['tick'], $removed['facts']['reason']]);

    $wake = $enemy;
    $wake['statuses'] = [$this->status('sleep', 'player_a', 3)];
    $wake['active_abilities'][0]['action_delay'] = 4;
    $attacker = $this->unit('player_a', 'player', 1, 1, $this->stats(100, 1, 0),
      [$this->ability('basic_attack_ranged', 4, 1)]);
    $sameTick = $this->resolve('wake-tick', [$wake, $attacker]);
    $this->assertSame([4, 'sleep'], [$this->events($sameTick, 'action_skipped')[0]['tick'],
      $this->events($sameTick, 'action_skipped')[0]['facts']['reason']]);
  }

  public function testStatusOnlySleepDoesNotSpendAnUnusableCriticalRoll(): void
  {
    $sleeper = $this->unit('player_a', 'player', 1, 1, $this->stats(100, 1, 0, 6),
      [$this->ability('sleep_dart', 1, 25, [$this->die('dart_a', 6), $this->die('dart_b', 6)])]);
    $enemy = $this->unit('enemy_a', 'enemy', 0, 1, $this->stats(100, 1, 100),
      [$this->ability('basic_attack_ranged', 4000)]);
    $result = $this->resolve('sleep-no-crit', [$sleeper, $enemy]);
    $this->assertSame(['hit', null], [$this->events($result, 'hit_resolved')[0]['facts']['result'],
      $this->events($result, 'hit_resolved')[0]['facts']['check_roll']]);
    $this->assertSame((new DeterministicRandom('sleep-no-crit'))->nextInt(1, 6),
      $this->events($result, 'dice_rolled')[0]['facts']['initial_roll']);
  }

  public function testVictoryDefeatDeathCancellationAndRoundCap(): void
  {
    $strong = $this->unit('player_a', 'player', 1, 1, $this->stats(100, 100, 0), [$this->ability('basic_attack_ranged', 1, 1)]);
    $weak = $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(1, 0, 0), [$this->ability('basic_attack_ranged', 1, 10)]);
    $this->assertSame('victory', $this->resolve('victory-1', [$strong, $weak])['outcome']);
    $this->assertSame('defeat', $this->resolve('defeat-1', [
      $this->unit('player_a', 'player', 1, 1, $this->stats(1, 0, 0), [$this->ability('basic_attack_ranged', 2)]),
      $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(100, 100, 0), [$this->ability('basic_attack_ranged', 1)]),
    ])['outcome']);
    $stalemate = $this->resolve('stalemate-1', [
      $this->unit('player_a', 'player', 1, 1, $this->stats(1000000, 0, 1000000), [$this->ability('basic_attack_ranged', 4000)]),
      $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(1000000, 0, 1000000), [$this->ability('basic_attack_ranged', 4000)]),
    ]);
    $this->assertSame(['outcome' => 'stalemate', 'round' => 200, 'tick' => 4000],
      ['outcome' => $stalemate['outcome'], 'round' => $stalemate['ending_round'], 'tick' => $stalemate['ending_tick']]);
  }

  /** @dataProvider invalidSnapshotProvider */
  public function testMalformedSnapshotFailsBeforeSimulation(callable $change): void
  {
    $snapshot = ['seed' => 'bad-1', 'combatants' => [
      $this->unit('player_a', 'player', 1, 1, $this->stats(20, 1, 1), [$this->ability('basic_attack_ranged')]),
      $this->unit('enemy_a', 'enemy', 1, 1, $this->stats(20, 1, 1), [$this->ability('basic_attack_ranged')]),
    ]];
    $change($snapshot);
    $this->expectException(InvalidArgumentException::class);
    new CombatInput($snapshot);
  }

  public function invalidSnapshotProvider(): array
  {
    return [
      'missing stat' => [static function (array &$s): void { unset($s['combatants'][0]['stats']['resolve']); }],
      'speed stat' => [static fn(array &$s) => $s['combatants'][0]['stats']['speed'] = 1],
      'duplicate key' => [static fn(array &$s) => $s['combatants'][1]['key'] = 'player_a'],
      'bad level-free HP' => [static fn(array &$s) => $s['combatants'][0]['max_hp'] = 0],
      'unfilled die slots' => [static fn(array &$s) => $s['combatants'][0]['active_abilities'][0]['dice_slot_count'] = 2],
      'unsupported handler' => [static fn(array &$s) => $s['combatants'][0]['active_abilities'][0]['handler_id'] = 'poison_arrow'],
    ];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 3) . '/content'); }

  /** @return array{hp:int,attack:int,defense:int,precision:int,resolve:int} */
  private function stats(int $hp, int $attack, int $defense, int $precision = 5, int $resolve = 5): array
  {
    return compact('hp', 'attack', 'defense', 'precision', 'resolve');
  }

  /** @param array<string,int> $stats @param list<array<string,mixed>> $abilities @param list<array<string,mixed>> $passives
   *  @param list<array<string,mixed>> $statuses @return array<string,mixed>
   */
  private function unit(string $key, string $side, int $x, int $y, array $stats, array $abilities,
    array $passives = [], array $statuses = []): array
  {
    return ['key' => $key, 'side' => $side, 'position' => ['x' => $x, 'y' => $y], 'stats' => $stats,
      'max_hp' => $stats['hp'], 'current_hp' => $stats['hp'], 'active_abilities' => $abilities,
      'passive_abilities' => $passives, 'statuses' => $statuses];
  }

  /** @param list<array<string,mixed>>|null $dice @param array<string,mixed>|null $config @return array<string,mixed> */
  private function ability(string $handler, int $delay = 4, int $priority = 10, ?array $dice = null, ?array $config = null): array
  {
    $target = match ($handler) {
      'shield_up' => 'self', 'bolster_ally' => 'ally_lowest_hp_pct',
      'basic_attack_ranged', 'aimed_shot', 'sleep_dart', 'mud_sling' => 'enemy_back_prefer',
      default => 'enemy_front_prefer',
    };
    $config ??= match ($handler) {
      'shield_up', 'bolster_ally' => ['status_id' => 'bolstered', 'bolster_defense_pct' => 0.25, 'duration_rounds' => 2],
      'sleep_dart' => ['status_id' => 'sleep', 'duration_rounds' => 2],
      'wrestle' => ['power_ratio' => 1.05, 'status_id' => 'wrestled', 'duration_rounds' => 2],
      'mud_sling' => ['power_ratio' => 0.9, 'status_id' => 'cracked_armor', 'defense_reduction_flat' => 2, 'duration_rounds' => 2],
      default => ['power_ratio' => $handler === 'heavy_strike' || $handler === 'aimed_shot' ? 1.6 : 1.0],
    };
    $dice ??= [$this->die('plain_' . $handler, 6)];
    return ['id' => 'ability.' . $handler, 'handler_id' => $handler, 'target_rule' => $target,
      'action_delay' => $delay, 'resolution_priority' => $priority, 'dice_slot_count' => count($dice),
      'config' => $config, 'dice' => $dice];
  }

  /** @return array<string,mixed> */
  private function die(string $key, int $sides): array
  {
    return ['key' => $key, 'sides' => $sides, 'profile_id' => 'dice_profile.cardboard_plain', 'effects' => []];
  }

  /** @param array<string,mixed> $params @return array<string,mixed> */
  private function status(string $id, string $source, int $expiresRound, array $params = [], ?string $forced = null): array
  {
    return ['id' => $id, 'source_key' => $source, 'expires_round' => $expiresRound,
      'params' => $params, 'forced_target_key' => $forced];
  }

  /** @param list<array<string,mixed>> $units @return array<string,mixed> */
  private function resolve(string $seed, array $units): array
  {
    return (new CombatEngine())->resolve(new CombatInput(['seed' => $seed, 'combatants' => $units]))->toArray();
  }

  /** @param array<string,mixed> $result @return list<array<string,mixed>> */
  private function events(array $result, string $type): array
  {
    return array_values(array_filter($result['events'], static fn(array $event): bool => $event['type'] === $type));
  }

  private function seedWithFirstRollAtMost(int $limit): string
  {
    for ($index = 0; $index < 10000; $index++) {
      $seed = 'roll-seed-' . $index;
      if ((new DeterministicRandom($seed))->nextInt(1, 100) <= $limit) return $seed;
    }
    throw new \RuntimeException('No deterministic seed found.');
  }

  private function seedWithFirstDieMaximum(int $sides): string
  {
    for ($index = 0; $index < 10000; $index++) {
      $seed = 'die-seed-' . $index;
      if ((new DeterministicRandom($seed))->nextInt(1, $sides) === $sides) return $seed;
    }
    throw new \RuntimeException('No deterministic seed found.');
  }

  private function seedWithSecondRollAtMost(int $limit): string
  {
    for ($index = 0; $index < 10000; $index++) {
      $seed = 'resist-seed-' . $index;
      $rng = new DeterministicRandom($seed);
      $rng->nextInt(1, 6);
      if ($rng->nextInt(1, 100) <= $limit) return $seed;
    }
    throw new \RuntimeException('No deterministic resistance seed found.');
  }

  private function seedWithDifferentFirstChoice(string $reference, int $count): string
  {
    $choice = (new DeterministicRandom($reference))->nextInt(0, $count - 1);
    for ($index = 0; $index < 10000; $index++) {
      $seed = 'target-alt-' . $index;
      if ((new DeterministicRandom($seed))->nextInt(0, $count - 1) !== $choice) return $seed;
    }
    throw new \RuntimeException('No alternate target seed found.');
  }
}
