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
