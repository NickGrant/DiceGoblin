<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit\Combat;

use DiceGoblins\Combat\Engine\DeterministicRunNodeResolver;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DeterministicRunNodeResolverPrimitivesTest extends TestCase
{
  private DeterministicRunNodeResolver $resolver;
  private ReflectionClass $reflection;

  protected function setUp(): void
  {
    $this->resolver = new DeterministicRunNodeResolver(new PDO('sqlite::memory:'));
    $this->reflection = new ReflectionClass($this->resolver);
  }

  public function testHalfDieValueRoundsUp(): void
  {
    $this->assertSame(3, $this->invokePrivate('halfDieValue', [5]));
    $this->assertSame(2, $this->invokePrivate('halfDieValue', [4]));
    $this->assertSame(0, $this->invokePrivate('halfDieValue', [0]));
  }

  public function testOneAttackDefenseStacksAccumulateAndClearAfterConsumption(): void
  {
    $statusMap = ['unit-1' => []];

    $this->invokePrivate('applyOneAttackDefenseStacks', [
      &$statusMap,
      'unit-1',
      'guard_stacks',
      2,
      3,
      5,
      8,
    ]);
    $this->invokePrivate('applyOneAttackDefenseStacks', [
      &$statusMap,
      'unit-1',
      'guard_stacks',
      2,
      3,
      5,
      9,
    ]);

    $this->assertSame(4, (int)($statusMap['unit-1']['guard_stacks']['params']['stack_count'] ?? 0));

    $consumed = $this->invokePrivate('consumeOneAttackDefenseStacks', [&$statusMap, 'unit-1']);
    $this->assertSame(12, (int)($consumed['damage_reduction'] ?? 0));
    $this->assertStringContainsString('guard_stacks x4', (string)($consumed['outcome'] ?? ''));
    $this->assertArrayNotHasKey('guard_stacks', $statusMap['unit-1']);
  }

  public function testDistinctDebuffCountingIgnoresBuffsAndIncludesBonusTypes(): void
  {
    $statuses = [
      'marked' => ['params' => ['is_debuff' => true]],
      'poison' => ['params' => ['is_debuff' => true, 'counts_as_extra_debuff_type' => 1]],
      'bolstered' => ['params' => ['is_debuff' => false]],
    ];

    $count = $this->invokePrivate('countDistinctDebuffTypes', [$statuses]);
    $this->assertSame(3, $count);
  }

  public function testSpitefulReflexReflectsDebuffsOncePerRoundWithoutRecursing(): void
  {
    $defenderStatuses = [
      'defender-1' => [
        'spiteful_reflex' => [
          'duration_rounds' => 99,
          'params' => ['is_debuff' => false, 'last_trigger_round' => 0],
          'applied_tick' => 0,
          'applied_round' => 0,
        ],
      ],
    ];
    $attackerStatuses = ['attacker-1' => []];
    $outcome = [
      'status_applied' => 'poison',
      'status_duration_rounds' => 3,
      'status_params' => ['is_debuff' => true, 'poison_damage_ratio' => 0.2],
    ];

    $reflection = $this->invokePrivate('reflectDebuffToSourceIfNeeded', [
      &$defenderStatuses,
      &$attackerStatuses,
      'defender-1',
      'attacker-1',
      $outcome,
      2,
      7,
    ]);

    $this->assertStringContainsString('reflected poison', (string)$reflection);
    $this->assertTrue((bool)($attackerStatuses['attacker-1']['poison']['params']['reflected'] ?? false));

    $sameRound = $this->invokePrivate('reflectDebuffToSourceIfNeeded', [
      &$defenderStatuses,
      &$attackerStatuses,
      'defender-1',
      'attacker-1',
      $outcome,
      2,
      8,
    ]);
    $this->assertSame('spiteful_reflex ready but already triggered this round', $sameRound);

    $recursiveOutcome = [
      'status_applied' => 'poison',
      'status_duration_rounds' => 3,
      'status_params' => ['is_debuff' => true, 'reflected' => true],
    ];
    $recursive = $this->invokePrivate('reflectDebuffToSourceIfNeeded', [
      &$defenderStatuses,
      &$attackerStatuses,
      'defender-1',
      'attacker-1',
      $recursiveOutcome,
      3,
      9,
    ]);
    $this->assertNull($recursive);
  }

  public function testChooseTargetSelectionPrefersWeightedMarkedWoundedBacklinePreviousTarget(): void
  {
    $state = str_repeat('a', 64);
    $unitsById = [
      'enemy-a' => ['max_hp' => 20, 'current_hp' => 20, 'attack' => 5, 'defense' => 2, 'pos' => ['x' => 2, 'y' => 0], 'formation' => ['w' => 1, 'h' => 1]],
      'enemy-b' => ['max_hp' => 20, 'current_hp' => 5, 'attack' => 8, 'defense' => 1, 'pos' => ['x' => 0, 'y' => 1], 'formation' => ['w' => 1, 'h' => 1]],
      'enemy-c' => ['max_hp' => 20, 'current_hp' => 12, 'attack' => 6, 'defense' => 3, 'pos' => ['x' => 1, 'y' => 2], 'formation' => ['w' => 1, 'h' => 1]],
    ];
    $statusMap = [
      'enemy-a' => [],
      'enemy-b' => ['marked' => ['params' => ['is_debuff' => true]]],
      'enemy-c' => [],
    ];

    $selection = $this->invokePrivate('chooseTargetSelection', [
      &$state,
      ['enemy-a', 'enemy-b', 'enemy-c'],
      $unitsById,
      'enemy_back_prefer_marked_wounded_preferred_previous_target',
      'actor-1',
      ['enemy-a' => 20, 'enemy-b' => 5, 'enemy-c' => 12],
      $statusMap,
      'enemy-b',
    ]);

    $this->assertSame('enemy-b', $selection['id']);
    $this->assertStringContainsString('backline', (string)$selection['reason']);
    $this->assertStringContainsString('marked', (string)$selection['reason']);
    $this->assertStringContainsString('wounded', (string)$selection['reason']);
    $this->assertStringContainsString('preferred_previous_target', (string)$selection['reason']);
  }

  public function testApplyPassiveAbilityAffixesToUnitAggregatesUnlockedPassiveCombatBonuses(): void
  {
    $unit = [
      'attack' => 10,
      'defense' => 4,
      'max_hp' => 20,
      'current_hp' => 20,
      'passive_abilities' => ['thick_hide', 'finisher', 'armor_gap', 'exposed_weaknesses'],
      'combat_affixes' => ['damage_flat' => 0, 'below_half_bonus' => 0.0],
    ];

    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();
    $this->invokePrivate('applyPassiveAbilityAffixesToUnit', [&$unit, $registry]);

    $this->assertSame(6, (int)$unit['defense']);
    $this->assertSame(0.2, (float)($unit['combat_affixes']['wounded_damage_pct'] ?? 0.0));
    $this->assertSame(2, (int)($unit['combat_affixes']['ignore_defense_flat'] ?? 0));
    $this->assertSame(1, (int)($unit['combat_affixes']['bonus_damage_per_debuff_type'] ?? 0));
    $this->assertSame(3, (int)($unit['combat_affixes']['debuff_type_cap'] ?? 0));
  }

  public function testApplyGlobalD4ExplosionUnlockAddsExplodeAffixToD4Only(): void
  {
    $d4 = [
      'kind' => 'unit',
      'dice_instance_id' => '4',
      'sides' => 4,
      'affixes' => [],
    ];
    $d6 = [
      'kind' => 'unit',
      'dice_instance_id' => '6',
      'sides' => 6,
      'affixes' => [],
    ];

    $this->invokePrivate('applyGlobalD4ExplosionUnlockToDie', [&$d4]);
    $this->invokePrivate('applyGlobalD4ExplosionUnlockToDie', [&$d6]);

    $this->assertSame('explode_once', (string)($d4['affixes'][0]['slug'] ?? ''));
    $this->assertSame([], $d6['affixes']);
  }

  public function testInitializePassiveStatusesForCombatSeedsSpitefulReflex(): void
  {
    $statusMap = ['unit-1' => []];

    $this->invokePrivate('initializePassiveStatusesForCombat', [
      &$statusMap,
      'unit-1',
      ['spiteful_reflex'],
    ]);

    $this->assertArrayHasKey('spiteful_reflex', $statusMap['unit-1']);
    $this->assertSame(99, (int)($statusMap['unit-1']['spiteful_reflex']['duration_rounds'] ?? 0));
    $this->assertSame(false, (bool)($statusMap['unit-1']['spiteful_reflex']['params']['is_debuff'] ?? true));
  }

  public function testInitializePassiveStatusesForCombatSeedsCounterpunchReadiness(): void
  {
    $statusMap = ['unit-1' => []];

    $this->invokePrivate('initializePassiveStatusesForCombat', [
      &$statusMap,
      'unit-1',
      ['counterpunch'],
    ]);

    $this->assertArrayHasKey('counterpunch_ready', $statusMap['unit-1']);
    $this->assertSame(0, (int)($statusMap['unit-1']['counterpunch_ready']['params']['last_trigger_round'] ?? -1));
  }

  public function testResolveCounterpunchRetaliationOnlyTriggersOncePerRound(): void
  {
    $state = str_repeat('e', 64);
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();
    $events = [];
    $playerHp = ['defender-1' => 18];
    $enemyHp = ['attacker-1' => 20];
    $playerStatuses = [
      'defender-1' => [
        'counterpunch_ready' => [
          'duration_rounds' => 99,
          'params' => ['last_trigger_round' => 0, 'is_debuff' => false],
          'applied_tick' => 0,
          'applied_round' => 0,
        ],
      ],
    ];
    $enemyStatuses = ['attacker-1' => []];
    $enemyActor = [
      'defense' => 2,
      'max_hp' => 20,
      'pos' => ['x' => 2, 'y' => 0],
      'formation' => ['w' => 1, 'h' => 1],
    ];
    $defenderUnit = [
      'attack' => 8,
      'pos' => ['x' => 0, 'y' => 0],
      'formation' => ['w' => 1, 'h' => 1],
      'passive_abilities' => ['counterpunch'],
      'combat_affixes' => [],
    ];

    $first = $this->invokePrivate('resolveCounterpunchRetaliation', [
      &$events,
      &$state,
      $registry,
      $enemyActor,
      $defenderUnit,
      'attacker-1',
      'defender-1',
      'basic_attack_melee',
      &$playerHp,
      &$enemyHp,
      &$playerStatuses,
      &$enemyStatuses,
      2,
      10,
    ]);

    $this->assertStringContainsString('counterpunch', (string)$first);
    $this->assertLessThan(20, $enemyHp['attacker-1']);
    $this->assertSame(2, (int)($playerStatuses['defender-1']['counterpunch_ready']['params']['last_trigger_round'] ?? 0));

    $second = $this->invokePrivate('resolveCounterpunchRetaliation', [
      &$events,
      &$state,
      $registry,
      $enemyActor,
      $defenderUnit,
      'attacker-1',
      'defender-1',
      'basic_attack_melee',
      &$playerHp,
      &$enemyHp,
      &$playerStatuses,
      &$enemyStatuses,
      2,
      11,
    ]);

    $this->assertSame('counterpunch already used this round', $second);
  }

  public function testInitializePassiveStatusesForCombatSeedsDumbLuckReadiness(): void
  {
    $statusMap = ['unit-1' => []];

    $this->invokePrivate('initializePassiveStatusesForCombat', [
      &$statusMap,
      'unit-1',
      ['dumb_luck'],
    ]);

    $this->assertArrayHasKey('dumb_luck_ready', $statusMap['unit-1']);
    $this->assertFalse((bool)($statusMap['unit-1']['dumb_luck_ready']['params']['used'] ?? true));
  }

  public function testDeriveActionOutcomeUsesPassiveIgnoreDefenseAndWoundedBonus(): void
  {
    $state = str_repeat('b', 64);
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $outcome = $this->invokePrivate('deriveActionOutcome', [
      &$state,
      12,
      8,
      5,
      20,
      'basic_attack_melee',
      0,
      [
        'damage_flat' => 0,
        'below_half_bonus' => 0.0,
        'ignore_defense_flat' => 2,
        'wounded_damage_pct' => 0.2,
      ],
      [
        'dice_used' => [],
        'dice_rolls' => [],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      [],
      ['x' => 2, 'y' => 1],
      ['x' => 2, 'y' => 1],
      ['w' => 1, 'h' => 1],
      ['w' => 1, 'h' => 1],
      $registry,
      12,
      0,
    ]);

    $this->assertGreaterThan(0, (int)($outcome['damage'] ?? 0));
    $this->assertStringContainsString('ignored 2 defense', (string)($outcome['affix_outcome'] ?? ''));
    $this->assertStringContainsString('passive damage', (string)($outcome['affix_outcome'] ?? ''));
  }

  public function testDeriveActionOutcomeNeutralPrecisionAndResolvePreservesBaselineHit(): void
  {
    $state = str_repeat('f', 64);
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $outcome = $this->invokePrivate('deriveActionOutcome', [
      &$state,
      12,
      4,
      20,
      20,
      'poison_stab',
      0,
      ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      [
        'dice_used' => [],
        'dice_rolls' => [],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      [],
      ['x' => 2, 'y' => 1],
      ['x' => 2, 'y' => 1],
      ['w' => 1, 'h' => 1],
      ['w' => 1, 'h' => 1],
      $registry,
      12,
      0,
      5,
      5,
    ]);

    $this->assertSame('hit', (string)($outcome['hit_outcome'] ?? ''));
    $this->assertSame(0, (int)($outcome['precision_target'] ?? -1));
    $this->assertNull($outcome['precision_roll'] ?? null);
    $this->assertSame(0, (int)($outcome['crit_target'] ?? -1));
    $this->assertNull($outcome['crit_roll'] ?? null);
    $this->assertFalse((bool)($outcome['status_resisted'] ?? true));
    $this->assertSame('poison', (string)($outcome['status_applied'] ?? ''));
  }

  public function testDeriveActionOutcomeCanMissWithLowPrecision(): void
  {
    $state = '129f756a2dacf6886dcf83035b27f9f20d6b148e89e7804ddb3bb72d424c7d98';
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $outcome = $this->invokePrivate('deriveActionOutcome', [
      &$state,
      12,
      4,
      20,
      20,
      'basic_attack_melee',
      0,
      ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      [
        'dice_used' => [],
        'dice_rolls' => [],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      [],
      ['x' => 2, 'y' => 1],
      ['x' => 2, 'y' => 1],
      ['w' => 1, 'h' => 1],
      ['w' => 1, 'h' => 1],
      $registry,
      12,
      0,
      1,
      5,
    ]);

    $this->assertSame('missed', (string)($outcome['outcome'] ?? ''));
    $this->assertSame('miss', (string)($outcome['hit_outcome'] ?? ''));
    $this->assertSame(0, (int)($outcome['damage'] ?? -1));
    $this->assertSame(20, (int)($outcome['target_hp_after'] ?? -1));
    $this->assertSame(32, (int)($outcome['precision_target'] ?? -1));
    $this->assertSame(32, (int)($outcome['precision_roll'] ?? -1));
    $this->assertStringContainsString('attack missed', (string)($outcome['ability_outcome'] ?? ''));
  }

  public function testDeriveActionOutcomeCanCritWithHighPrecision(): void
  {
    $state = '8119a37d4bd9c02207afd7d47abc0d37922b4d372ea44ff1a6fb877fb6eb86e1';
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $outcome = $this->invokePrivate('deriveActionOutcome', [
      &$state,
      12,
      4,
      20,
      20,
      'basic_attack_melee',
      0,
      ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      [
        'dice_used' => [],
        'dice_rolls' => [],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      [],
      ['x' => 2, 'y' => 1],
      ['x' => 2, 'y' => 1],
      ['w' => 1, 'h' => 1],
      ['w' => 1, 'h' => 1],
      $registry,
      12,
      0,
      11,
      5,
    ]);

    $this->assertSame('critical', (string)($outcome['hit_outcome'] ?? ''));
    $this->assertSame(30, (int)($outcome['crit_target'] ?? -1));
    $this->assertSame(19, (int)($outcome['crit_roll'] ?? -1));
    $this->assertStringContainsString('critical hit', (string)($outcome['ability_outcome'] ?? ''));
    $this->assertStringContainsString('critical hit x1.5', (string)($outcome['affix_outcome'] ?? ''));
  }

  public function testDeriveActionOutcomeCanResistDebuffWithHighResolve(): void
  {
    $state = 'f3c5bc4e044d3f88740fd8eb7e281322dc62ddbcb700c46887799490f5c86a1e';
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $outcome = $this->invokePrivate('deriveActionOutcome', [
      &$state,
      12,
      4,
      20,
      20,
      'poison_stab',
      0,
      ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      [
        'dice_used' => [],
        'dice_rolls' => [],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      [],
      ['x' => 2, 'y' => 1],
      ['x' => 2, 'y' => 1],
      ['w' => 1, 'h' => 1],
      ['w' => 1, 'h' => 1],
      $registry,
      12,
      0,
      5,
      10,
    ]);

    $this->assertTrue((bool)($outcome['status_resisted'] ?? false));
    $this->assertNull($outcome['status_applied'] ?? null);
    $this->assertSame(40, (int)($outcome['status_resist_target'] ?? -1));
    $this->assertSame(8, (int)($outcome['status_resist_roll'] ?? -1));
    $this->assertStringContainsString('status resisted by Resolve 10', (string)($outcome['ability_outcome'] ?? ''));
  }

  public function testDeriveStatusApplicationHonorsAuthoredStatusParams(): void
  {
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $application = $this->invokePrivate('deriveStatusApplication', [
      $registry,
      'skullcrack',
      'cracked_skull',
      ['dice_used' => [], 'dice_rolls' => []],
      10,
    ]);

    $this->assertSame(2, (int)($application['duration_rounds'] ?? 0));
    $this->assertSame(0.15, (float)($application['params']['attack_reduction_pct'] ?? 0.0));
    $this->assertSame(true, (bool)($application['params']['is_debuff'] ?? false));
  }

  public function testEffectiveAttackWithStatusesAppliesBuffsAndDebuffs(): void
  {
    $attack = $this->invokePrivate('effectiveAttackWithStatuses', [
      10,
      [
        'warcry' => ['params' => ['attack_pct' => 0.2]],
        'cracked_skull' => ['params' => ['attack_reduction_pct' => 0.1]],
        'lucky' => ['params' => ['lucky_bonus_flat' => 2]],
      ],
    ]);

    $this->assertSame(12, $attack);
  }

  public function testApplyPassiveAbilityTargetingPreferencesUpdatesAimedShotTargeting(): void
  {
    $schedule = [[
      'ability_id' => 'aimed_shot',
      'speed' => 8,
      'target' => 'enemy_back_prefer',
      'trigger_tick' => 8,
      'equip_order' => 0,
    ]];

    $updated = $this->invokePrivate('applyPassiveAbilityTargetingPreferencesToSchedule', [
      $schedule,
      ['patient_aim'],
    ]);

    $this->assertSame(
      'enemy_back_prefer_marked_wounded_preferred_previous_target',
      (string)$updated[0]['target']
    );
  }

  public function testBuildActiveAbilityScheduleFillsRemainingTicksWithRepeatableAttacks(): void
  {
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $schedule = $this->invokePrivate('buildActiveAbilitySchedule', [
      ['basic_attack_melee', 'shield_up'],
      $registry,
    ]);

    $this->assertSame([4, 14, 18], array_column($schedule, 'trigger_tick'));
    $this->assertSame(
      ['basic_attack_melee', 'shield_up', 'basic_attack_melee'],
      array_column($schedule, 'ability_id')
    );
  }

  public function testBuildActiveAbilityScheduleDoesNotRepeatSupportAbilitiesAsFiller(): void
  {
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $schedule = $this->invokePrivate('buildActiveAbilitySchedule', [
      ['basic_attack_melee', 'bolster_ally'],
      $registry,
    ]);

    $this->assertSame([4, 14, 18], array_column($schedule, 'trigger_tick'));
    $this->assertSame(
      ['basic_attack_melee', 'bolster_ally', 'basic_attack_melee'],
      array_column($schedule, 'ability_id')
    );
  }

  public function testDeriveSupportOutcomeUsesHalfDieGuardStacksForTauntingGuard(): void
  {
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $outcome = $this->invokePrivate('deriveSupportOutcome', [
      $registry,
      'taunting_guard',
      'unit-1',
      ['unit-1' => 20],
      ['max_hp' => 20],
      [
        'dice_used' => [],
        'dice_rolls' => [['sides' => 6, 'roll' => 5]],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
    ]);

    $this->assertSame('guard_stacks', (string)($outcome['status_applied'] ?? ''));
    $this->assertSame(3, (int)($outcome['status_params']['stack_count'] ?? 0));
    $this->assertStringContainsString('half-die scaling', (string)($outcome['affix_outcome'] ?? ''));
  }

  public function testDeriveStatusApplicationScalesWarcryAndLuckyFromDieRolls(): void
  {
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();

    $warcryLow = $this->invokePrivate('deriveStatusApplication', [
      $registry,
      'warcry',
      'warcry',
      ['dice_rolls' => [['sides' => 6, 'roll' => 1]]],
      0,
    ]);
    $warcryHigh = $this->invokePrivate('deriveStatusApplication', [
      $registry,
      'warcry',
      'warcry',
      ['dice_rolls' => [['sides' => 6, 'roll' => 6]]],
      0,
    ]);
    $this->assertGreaterThan(
      (float)($warcryLow['params']['attack_pct'] ?? 0.0),
      (float)($warcryHigh['params']['attack_pct'] ?? 0.0)
    );
    $this->assertGreaterThan(0.18, (float)($warcryHigh['params']['attack_pct'] ?? 0.0));

    $luckyLow = $this->invokePrivate('deriveStatusApplication', [
      $registry,
      'lucky_chant',
      'lucky',
      ['dice_rolls' => [['sides' => 6, 'roll' => 1]]],
      0,
    ]);
    $luckyHigh = $this->invokePrivate('deriveStatusApplication', [
      $registry,
      'lucky_chant',
      'lucky',
      ['dice_rolls' => [['sides' => 6, 'roll' => 6]]],
      0,
    ]);
    $this->assertGreaterThan(
      (int)($luckyLow['params']['lucky_bonus_flat'] ?? 0),
      (int)($luckyHigh['params']['lucky_bonus_flat'] ?? 0)
    );
    $this->assertGreaterThanOrEqual(2, (int)($luckyLow['params']['lucky_bonus_flat'] ?? 0));
  }

  public function testApplyLastGoblinStandingIfNeededLeavesUnitAtOneHpOnce(): void
  {
    $hpByUnitId = ['unit-1' => 0];
    $statusMap = ['unit-1' => []];
    $unitsById = [
      'unit-1' => ['passive_abilities' => ['last_goblin_standing']],
    ];

    $first = $this->invokePrivate('applyLastGoblinStandingIfNeeded', [
      &$hpByUnitId,
      &$statusMap,
      $unitsById,
      'unit-1',
      1,
      10,
    ]);

    $this->assertSame(1, $hpByUnitId['unit-1']);
    $this->assertStringContainsString('1 HP', (string)$first);

    $hpByUnitId['unit-1'] = 0;
    $second = $this->invokePrivate('applyLastGoblinStandingIfNeeded', [
      &$hpByUnitId,
      &$statusMap,
      $unitsById,
      'unit-1',
      2,
      20,
    ]);

    $this->assertNull($second);
    $this->assertSame(0, $hpByUnitId['unit-1']);
  }

  public function testApplyTriggeredDefenderPassivesAfterHitBuildsReactiveStacks(): void
  {
    $statusMap = ['unit-1' => []];
    $unitsById = [
      'unit-1' => ['passive_abilities' => ['brawl_hardened', 'shield_set', 'crowd_favorite']],
    ];

    $outcome = $this->invokePrivate('applyTriggeredDefenderPassivesAfterHit', [
      &$statusMap,
      $unitsById,
      'unit-1',
      3,
      1,
      12,
    ]);

    $this->assertStringContainsString('brawl_hardened', (string)$outcome);
    $this->assertStringContainsString('shield_set', (string)$outcome);
    $this->assertStringContainsString('crowd_favorite', (string)$outcome);
    $this->assertSame(1, (int)($statusMap['unit-1']['brawl_hardened_stacks']['params']['stack_count'] ?? 0));
    $this->assertSame(1, (int)($statusMap['unit-1']['shield_set']['params']['stack_count'] ?? 0));
    $this->assertSame(1, (int)($statusMap['unit-1']['crowd_favorite']['params']['stack_count'] ?? 0));
  }

  public function testEffectiveDefenseWithStatusesIncludesShieldSetStacks(): void
  {
    $defense = $this->invokePrivate('effectiveDefenseWithStatuses', [
      5,
      [
        'shield_set' => [
          'params' => [
            'stack_count' => 2,
            'defense_flat_per_stack' => 1,
          ],
        ],
      ],
    ]);

    $this->assertSame(7, $defense);
  }

  public function testApplyAttackerPassiveStatusAugmentsCanAddBarbedMarkAndStrongerDebuffs(): void
  {
    $statusMap = ['enemy-1' => []];
    $attackerUnit = [
      'passive_abilities' => ['barbed_mark', 'shatter_plate'],
    ];

    $marked = $this->invokePrivate('applyAttackerPassiveStatusAugments', [
      &$statusMap,
      $attackerUnit,
      'enemy-1',
      [
        'status_applied' => 'marked',
        'status_duration_rounds' => 3,
        'status_params' => ['damage_taken_pct' => 0.15, 'is_debuff' => true],
      ],
      1,
      10,
    ]);
    $this->assertStringContainsString('barbed_mark', (string)$marked);
    $this->assertArrayHasKey('snared', $statusMap['enemy-1']);

    $crackedArmor = $this->invokePrivate('applyAttackerPassiveStatusAugments', [
      &$statusMap,
      $attackerUnit,
      'enemy-1',
      [
        'status_applied' => 'cracked_armor',
        'status_duration_rounds' => 2,
        'status_params' => ['defense_reduction_flat' => 2, 'is_debuff' => true],
      ],
      1,
      11,
    ]);
    $this->assertStringContainsString('shatter_plate', (string)$crackedArmor);
    $this->assertSame(3, (int)($statusMap['enemy-1']['cracked_armor']['params']['defense_reduction_flat'] ?? 0));
  }

  public function testApplyAttackerPassiveStatusAugmentsSupportsToxicToolsDisablingHitAndSicklyWeakness(): void
  {
    $statusMap = ['enemy-1' => []];
    $attackerUnit = [
      'passive_abilities' => ['toxic_tools', 'disabling_hit', 'sickly_weakness'],
      'combat_affixes' => ['status_potency_pct' => 0.0],
    ];

    $disarm = $this->invokePrivate('applyAttackerPassiveStatusAugments', [
      &$statusMap,
      $attackerUnit,
      'enemy-1',
      [
        'status_applied' => 'disarmed',
        'status_duration_rounds' => 2,
        'status_params' => ['attack_reduction_pct' => 0.15, 'is_debuff' => true],
      ],
      1,
      10,
    ]);
    $this->assertStringContainsString('toxic_tools', (string)$disarm);
    $this->assertStringContainsString('disabling_hit', (string)$disarm);
    $this->assertGreaterThan(0.23, (float)($statusMap['enemy-1']['disarmed']['params']['attack_reduction_pct'] ?? 0.0));

    $poison = $this->invokePrivate('applyAttackerPassiveStatusAugments', [
      &$statusMap,
      $attackerUnit,
      'enemy-1',
      [
        'status_applied' => 'poison',
        'status_duration_rounds' => 3,
        'status_params' => ['poison_damage_ratio' => 0.2, 'is_debuff' => true],
      ],
      1,
      11,
    ]);
    $this->assertStringContainsString('sickly_weakness', (string)$poison);
    $this->assertSame(1, (int)($statusMap['enemy-1']['poison']['params']['counts_as_extra_debuff_type'] ?? 0));
  }

  public function testApplySupportOutcomeActorPassivesAugmentsBolsterWarcryAndPatchJob(): void
  {
    $bolster = $this->invokePrivate('applySupportOutcomeActorPassives', [
      [
        'target_hp_after' => 4,
        'status_applied' => 'bolstered',
        'status_params' => ['defense_pct' => 0.25, 'is_debuff' => false],
        'affix_outcome' => null,
      ],
      ['passive_abilities' => ['rally_rhythm', 'patch_job']],
      ['max_hp' => 20],
      4,
    ]);
    $this->assertSame(0.10, (float)($bolster['status_params']['attack_pct'] ?? 0.0));
    $this->assertSame(6, (int)($bolster['target_hp_after'] ?? 0));
    $this->assertStringContainsString('patch_job', (string)($bolster['affix_outcome'] ?? ''));

    $warcry = $this->invokePrivate('applySupportOutcomeActorPassives', [
      [
        'target_hp_after' => 10,
        'status_applied' => 'warcry',
        'status_params' => ['attack_pct' => 0.18, 'is_debuff' => false],
        'affix_outcome' => null,
      ],
      ['passive_abilities' => ['chant_of_violence']],
      ['max_hp' => 20],
      10,
    ]);
    $this->assertSame(0.26, round((float)($warcry['status_params']['attack_pct'] ?? 0.0), 2));
  }

  public function testApplySupportEchoPassiveCopiesReducedBuffToAnotherAlly(): void
  {
    $state = str_repeat('d', 64);
    $statusMap = ['mascot' => [], 'ally-1' => [], 'ally-2' => []];
    $unitsById = [
      'mascot' => ['passive_abilities' => ['attention_hog']],
      'ally-1' => ['passive_abilities' => []],
      'ally-2' => ['passive_abilities' => []],
    ];
    $hpByUnitId = ['mascot' => 10, 'ally-1' => 10, 'ally-2' => 10];

    $summary = $this->invokePrivate('applySupportEchoPassive', [
      &$state,
      &$statusMap,
      $unitsById,
      $hpByUnitId,
      'mascot',
      [
        'status_applied' => 'lucky',
        'status_duration_rounds' => 2,
        'status_params' => ['lucky_bonus_flat' => 4, 'is_debuff' => false],
      ],
      1,
      8,
    ]);

    $this->assertStringContainsString('attention_hog echoed lucky', (string)$summary);
    $echoed = (int)($statusMap['ally-1']['lucky']['params']['lucky_bonus_flat'] ?? $statusMap['ally-2']['lucky']['params']['lucky_bonus_flat'] ?? 0);
    $this->assertSame(2, $echoed);
  }

  public function testApplyAllyProtectionPassivesSupportsBodyguardAndUnmoving(): void
  {
    $outcome = $this->invokePrivate('applyAllyProtectionPassives', [
      [
        'damage' => 10,
        'target_hp_after' => 0,
        'outcome' => 'defeated',
        'affix_outcome' => null,
      ],
      'guard',
      [
        'guard' => ['max_hp' => 20, 'passive_abilities' => ['unmoving']],
        'protector' => ['max_hp' => 25, 'passive_abilities' => ['bodyguard']],
      ],
      ['guard' => 8, 'protector' => 20],
      ['guard' => []],
      true,
    ]);

    $this->assertSame(6, (int)$outcome['damage']);
    $this->assertSame(2, (int)$outcome['target_hp_after']);
    $this->assertStringContainsString('bodyguard', (string)($outcome['affix_outcome'] ?? ''));
    $this->assertStringContainsString('unmoving', (string)($outcome['affix_outcome'] ?? ''));
  }

  public function testApplyTeamDamagePassivesAddsMobMentalityBonusForDamagedTargets(): void
  {
    $affixes = $this->invokePrivate('applyTeamDamagePassives', [
      ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      [
        'warcaller' => ['passive_abilities' => ['mob_mentality']],
      ],
      ['warcaller' => 10],
      'enemy-1',
      ['enemy-1' => true],
    ]);

    $this->assertSame(0.12, (float)($affixes['damaged_enemy_bonus_pct'] ?? 0.0));
  }

  public function testApplyTeamDamagePassivesSupportsBreakOpenForCrackedArmorTargets(): void
  {
    $affixes = $this->invokePrivate('applyTeamDamagePassives', [
      ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      [
        'breaker' => ['passive_abilities' => ['break_open']],
      ],
      ['breaker' => 10],
      'enemy-1',
      [],
      ['cracked_armor' => ['params' => ['is_debuff' => true]]],
    ]);

    $this->assertSame('cracked_armor', (string)($affixes['status_bonus_target'] ?? ''));
    $this->assertSame(0.12, (float)($affixes['status_bonus_pct'] ?? 0.0));
  }

  public function testApplyTeamLuckPassivesConsumesDumbLuckOnLowRoll(): void
  {
    $statusMap = [
      'mascot' => [
        'dumb_luck_ready' => [
          'params' => ['used' => false, 'is_debuff' => false],
        ],
      ],
    ];
    $result = $this->invokePrivate('applyTeamLuckPassives', [
      &$statusMap,
      [
        'mascot' => ['passive_abilities' => ['dumb_luck']],
      ],
      ['mascot' => 10],
      [
        'dice_used' => [],
        'dice_rolls' => [['sides' => 6, 'roll' => 2]],
        'dice_outcome' => 'rolled low',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      1,
      8,
    ]);

    $this->assertSame(2, (int)($result['dice']['dice_modifier'] ?? 0));
    $this->assertStringContainsString('dumb_luck', (string)($result['outcome'] ?? ''));
    $this->assertTrue((bool)($statusMap['mascot']['dumb_luck_ready']['params']['used'] ?? false));
  }

  public function testResolveAdditionalAbilityTargetsSplashesPoisonCloud(): void
  {
    $state = str_repeat('c', 64);
    $registryClass = new ReflectionClass('DiceGoblins\\Combat\\Abilities\\AbilityRegistry');
    $registry = $registryClass->newInstance();
    $events = [];
    $targetUnitsById = [
      'enemy-a' => ['defense' => 1, 'max_hp' => 10, 'pos' => ['x' => 1, 'y' => 0], 'formation' => ['w' => 1, 'h' => 1]],
      'enemy-b' => ['defense' => 1, 'max_hp' => 10, 'pos' => ['x' => 1, 'y' => 1], 'formation' => ['w' => 1, 'h' => 1]],
    ];
    $targetHpById = ['enemy-a' => 10, 'enemy-b' => 10];
    $targetStatuses = ['enemy-a' => [], 'enemy-b' => []];
    $actorStatuses = ['unit-1' => []];

    $summary = $this->invokePrivate('resolveAdditionalAbilityTargets', [
      &$events,
      &$state,
      $registry,
      'player',
      [
        'attack' => 8,
        'combat_affixes' => ['damage_flat' => 0, 'below_half_bonus' => 0.0],
        'pos' => ['x' => 0, 'y' => 1],
        'formation' => ['w' => 1, 'h' => 1],
        'passive_abilities' => [],
      ],
      'unit-1',
      [
        'ability_id' => 'poison_cloud',
        'target' => 'enemy_back_prefer',
        'equip_order' => 0,
      ],
      'enemy-a',
      $targetUnitsById,
      &$targetHpById,
      &$targetStatuses,
      &$actorStatuses,
      [
        'dice_used' => [],
        'dice_rolls' => [],
        'dice_outcome' => 'none',
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ],
      1,
      8,
    ]);

    $this->assertStringContainsString('enemy-b', (string)$summary);
    $this->assertCount(1, $events);
    $this->assertSame('action_splash', (string)$events[0]['type']);
    $this->assertArrayHasKey('poison', $targetStatuses['enemy-b']);
    $this->assertLessThan(10, $targetHpById['enemy-b']);
  }

  /**
   * @param array<int,mixed> $args
   */
  private function invokePrivate(string $methodName, array $args = []): mixed
  {
    $method = $this->reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($this->resolver, $args);
  }
}
