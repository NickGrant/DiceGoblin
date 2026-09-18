<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Infrastructure\CryptoRewardRollSource;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RewardFinalizerTest extends TestCase
{
  public function testOneRollPerOrderedEntryAndProjectedStateChaining(): void
  {
    $rolls = new ScriptedRewardRollSource([1, 10000, 2500, 10000, 1, 1]);
    $result = (new RewardFinalizer($rolls))->finalize(
      $this->event(), $this->definition(), 'run_node', 'run-node:41', $this->context(),
    )->toArray();

    $this->assertSame(6, $rolls->consumed());
    $this->assertSame(['first_teeth', 'second_teeth', 'first_xp', 'second_xp', 'first_unlock', 'second_unlock'], array_column($result['entries'], 'key'));
    $this->assertSame([10, 15], [
      $result['entries'][0]['grant']['balance_after'],
      $result['entries'][1]['grant']['balance_after'],
    ]);
    $this->assertSame(['2', '12'], array_column($result['entries'][2]['grant']['units'], 'unit_id'));
    $this->assertSame(['level_before' => 1, 'xp_before' => 90, 'level_after' => 2, 'xp_after' => 40], array_diff_key($result['entries'][2]['grant']['units'][0], ['unit_id' => true]));
    $this->assertSame(['level_before' => 2, 'xp_before' => 40, 'level_after' => 2, 'xp_after' => 50], array_diff_key($result['entries'][3]['grant']['units'][0], ['unit_id' => true]));
    $this->assertSame('granted', $result['entries'][4]['outcome']);
    $this->assertSame('already_owned', $result['entries'][5]['outcome']);
  }

  public function testProbabilityBoundariesAndFixedRollsAreDeterministic(): void
  {
    $definition = ['id' => 'reward_definition.boundaries', 'type' => 'reward_definition', 'entries' => [
      ['key' => 'one', 'probability_basis_points' => 1, 'reward_type' => 'currency', 'config' => ['currency_id' => 'teeth', 'amount' => 1]],
      ['key' => 'always', 'probability_basis_points' => 10000, 'reward_type' => 'currency', 'config' => ['currency_id' => 'raw_chaos', 'amount' => 1]],
      ['key' => 'miss', 'probability_basis_points' => 1, 'reward_type' => 'currency', 'config' => ['currency_id' => 'teeth', 'amount' => 1]],
    ]];
    $first = (new RewardFinalizer(new ScriptedRewardRollSource([1, 10000, 2])))->finalize(
      ['id' => 'event.boundaries', 'type' => 'event', 'reward_definition_id' => 'reward_definition.boundaries'],
      $definition, 'test', 'boundary:1', $this->context(),
    )->toArray();
    $second = (new RewardFinalizer(new ScriptedRewardRollSource([1, 10000, 2])))->finalize(
      ['id' => 'event.boundaries', 'type' => 'event', 'reward_definition_id' => 'reward_definition.boundaries'],
      $definition, 'test', 'boundary:1', $this->context(),
    )->toArray();

    $this->assertSame($first, $second);
    $this->assertSame(['granted', 'granted', 'not_rolled'], array_column($first['entries'], 'outcome'));
  }

  public function testAlreadyOwnedUnlockProducesNoDurableGrantOutcome(): void
  {
    $definition = ['id' => 'reward_definition.unlock', 'type' => 'reward_definition', 'entries' => [[
      'key' => 'mountains', 'probability_basis_points' => 10000, 'reward_type' => 'unlock',
      'config' => ['unlock_id' => 'unlock.region.mountains'],
    ]]];
    $context = new RewardContext(0, 0, [], ['unlock.region.mountains']);
    $entry = (new RewardFinalizer(new ScriptedRewardRollSource([1])))->finalize(
      ['id' => 'event.unlock', 'reward_definition_id' => 'reward_definition.unlock'], $definition, 'test', 'unlock:1', $context,
    )->entries()[0];
    $this->assertSame('already_owned', $entry['outcome']);
    $this->assertSame(['unlock_id' => 'unlock.region.mountains'], $entry['grant']);
  }

  public function testProductionRollSourceStaysInsideBasisPointRange(): void
  {
    $source = new CryptoRewardRollSource();
    for ($i = 0; $i < 50; $i++) $this->assertGreaterThanOrEqual(1, $source->nextBasisPointRoll());
    for ($i = 0; $i < 50; $i++) $this->assertLessThanOrEqual(10000, $source->nextBasisPointRoll());
  }

  public function testContextRejectsUnnormalizedXpBeforeAnyRollCanOccur(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new RewardContext(0, 0, [['unit_id' => 1, 'level' => 1, 'xp' => 100]], []);
  }

  /** @return array<string,mixed> */
  private function event(): array
  {
    return ['id' => 'event.test_completed', 'type' => 'event', 'reward_definition_id' => 'reward_definition.test_completion'];
  }

  /** @return array<string,mixed> */
  private function definition(): array
  {
    return ['id' => 'reward_definition.test_completion', 'type' => 'reward_definition', 'entries' => [
      ['key' => 'first_teeth', 'probability_basis_points' => 1, 'reward_type' => 'currency', 'config' => ['currency_id' => 'teeth', 'amount' => 10]],
      ['key' => 'second_teeth', 'probability_basis_points' => 10000, 'reward_type' => 'currency', 'config' => ['currency_id' => 'teeth', 'amount' => 5]],
      ['key' => 'first_xp', 'probability_basis_points' => 2500, 'reward_type' => 'unit_xp', 'config' => ['target_scope' => 'participating_units', 'amount' => 50]],
      ['key' => 'second_xp', 'probability_basis_points' => 10000, 'reward_type' => 'unit_xp', 'config' => ['target_scope' => 'participating_units', 'amount' => 10]],
      ['key' => 'first_unlock', 'probability_basis_points' => 10000, 'reward_type' => 'unlock', 'config' => ['unlock_id' => 'unlock.region.mountains']],
      ['key' => 'second_unlock', 'probability_basis_points' => 10000, 'reward_type' => 'unlock', 'config' => ['unlock_id' => 'unlock.region.mountains']],
    ]];
  }

  private function context(): RewardContext
  {
    return new RewardContext(0, 3, [
      ['unit_id' => 12, 'level' => 2, 'xp' => 150],
      ['unit_id' => 2, 'level' => 1, 'xp' => 90],
    ], []);
  }
}
