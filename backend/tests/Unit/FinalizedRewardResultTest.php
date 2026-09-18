<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Domain\Rewards\FinalizedRewardResult;
use DiceGoblins\Domain\Rewards\RewardResultException;
use PHPUnit\Framework\TestCase;

final class FinalizedRewardResultTest extends TestCase
{
  public function testStrictVersionOneRoundTripContainsOnlyDeterministicFacts(): void
  {
    $result = FinalizedRewardResult::fromArray($this->validResult());
    $decoded = FinalizedRewardResult::fromJson($result->toJson());

    $this->assertSame($result->toArray(), $decoded->toArray());
    $this->assertSame(['version', 'event_id', 'reward_definition_id', 'source_type', 'source_id', 'entries'], array_keys($decoded->toArray()));
    foreach (['resolved_at', 'applied_at', 'display_name', 'random_state'] as $excluded) {
      $this->assertStringNotContainsString($excluded, $result->toJson());
    }
  }

  /** @dataProvider incoherentPayloadProvider */
  public function testIncoherentPayloadIsRejected(callable $mutator, string $message): void
  {
    $payload = $this->validResult();
    $mutator($payload);
    $this->expectException(RewardResultException::class);
    $this->expectExceptionMessage($message);
    FinalizedRewardResult::fromArray($payload);
  }

  public function incoherentPayloadProvider(): array
  {
    return [
      'version' => [fn(array &$p) => $p['version'] = 2, 'version'],
      'event identity' => [fn(array &$p) => $p['event_id'] = 'bad', 'event_id'],
      'source type' => [fn(array &$p) => $p['source_type'] = 'Run Node', 'source_type'],
      'source identity' => [fn(array &$p) => $p['source_id'] = '', 'source_id'],
      'source identity control character' => [fn(array &$p) => $p['source_id'] = "run-node:41\nforged", 'source_id'],
      'missing entry field' => [function(array &$p): void { unset($p['entries'][0]['roll']); }, 'field set'],
      'duplicate key' => [fn(array &$p) => $p['entries'][1]['key'] = 'teeth', 'duplicated'],
      'reordered index' => [fn(array &$p) => $p['entries'][0]['entry_index'] = 1, 'order'],
      'unsupported reward type' => [fn(array &$p) => $p['entries'][0]['reward_type'] = 'item', 'unsupported'],
      'unsupported outcome' => [fn(array &$p) => $p['entries'][0]['outcome'] = 'claimed', 'unsupported'],
      'probability bounds' => [fn(array &$p) => $p['entries'][0]['probability_basis_points'] = 0, 'probability'],
      'roll low' => [fn(array &$p) => $p['entries'][0]['roll'] = 0, 'roll'],
      'roll high' => [fn(array &$p) => $p['entries'][0]['roll'] = 10001, 'roll'],
      'roll outcome mismatch' => [fn(array &$p) => $p['entries'][0]['outcome'] = 'not_rolled', 'inconsistent'],
      'rolled grant missing' => [fn(array &$p) => $p['entries'][0]['grant'] = null, 'grant facts'],
      'not rolled grant present' => [fn(array &$p) => $p['entries'][5]['grant'] = ['currency_id' => 'teeth'], 'must not contain'],
      'already owned currency' => [fn(array &$p) => $p['entries'][0]['outcome'] = 'already_owned', 'Only unlock'],
      'currency arithmetic' => [fn(array &$p) => $p['entries'][0]['grant']['balance_after'] = 12, 'arithmetic'],
      'currency overflow' => [function(array &$p): void {
        $p['entries'][0]['grant']['balance_before'] = PHP_INT_MAX;
        $p['entries'][0]['grant']['balance_after'] = PHP_INT_MAX;
      }, 'arithmetic'],
      'currency chain' => [function(array &$p): void {
        $p['entries'][1]['grant']['balance_before'] = 9;
        $p['entries'][1]['grant']['balance_after'] = 14;
      }, 'chain'],
      'XP transition' => [fn(array &$p) => $p['entries'][2]['grant']['units'][0]['xp_after'] = 39, 'canonical resolver'],
      'duplicate XP unit' => [fn(array &$p) => $p['entries'][2]['grant']['units'][1]['unit_id'] = '2', 'duplicated'],
      'overflowing XP unit ID' => [fn(array &$p) => $p['entries'][2]['grant']['units'][1]['unit_id'] = '9223372036854775808', 'unit ID'],
      'unordered XP units' => [fn(array &$p) => $p['entries'][2]['grant']['units'] = array_reverse($p['entries'][2]['grant']['units']), 'ordered'],
      'XP chain' => [function(array &$p): void {
        $p['entries'][] = ['entry_index' => 6, 'key' => 'more_xp', 'reward_type' => 'unit_xp', 'probability_basis_points' => 10000, 'roll' => 1, 'outcome' => 'granted',
          'grant' => ['target_scope' => 'participating_units', 'amount_per_unit' => 50, 'units' => [
            ['unit_id' => '2', 'level_before' => 2, 'xp_before' => 41, 'level_after' => 2, 'xp_after' => 91],
            ['unit_id' => '12', 'level_before' => 3, 'xp_before' => 0, 'level_after' => 3, 'xp_after' => 50],
          ]]];
      }, 'chain'],
      'XP participant set' => [function(array &$p): void {
        $p['entries'][] = ['entry_index' => 6, 'key' => 'more_xp', 'reward_type' => 'unit_xp', 'probability_basis_points' => 10000, 'roll' => 1, 'outcome' => 'granted',
          'grant' => ['target_scope' => 'participating_units', 'amount_per_unit' => 50, 'units' => [
            ['unit_id' => '2', 'level_before' => 2, 'xp_before' => 40, 'level_after' => 2, 'xp_after' => 90],
          ]]];
      }, 'participant sets'],
      'malformed unlock' => [fn(array &$p) => $p['entries'][3]['grant']['unlock_id'] = 'region.mountains', 'unlock_id'],
      'repeated unlock granted' => [fn(array &$p) => $p['entries'][4]['outcome'] = 'granted', 'Repeated'],
      'negative balance' => [fn(array &$p) => $p['entries'][0]['grant']['balance_before'] = -1, 'balance'],
      'extra presentation field' => [fn(array &$p) => $p['display_name'] = 'Prize', 'field set'],
    ];
  }

  /** @return array<string,mixed> */
  private function validResult(): array
  {
    return [
      'version' => 1,
      'event_id' => 'event.test_completed',
      'reward_definition_id' => 'reward_definition.test_completion',
      'source_type' => 'run_node',
      'source_id' => 'run-node:41',
      'entries' => [
        ['entry_index' => 0, 'key' => 'teeth', 'reward_type' => 'currency', 'probability_basis_points' => 10000, 'roll' => 9999, 'outcome' => 'granted',
          'grant' => ['currency_id' => 'teeth', 'amount' => 10, 'balance_before' => 0, 'balance_after' => 10]],
        ['entry_index' => 1, 'key' => 'more_teeth', 'reward_type' => 'currency', 'probability_basis_points' => 10000, 'roll' => 10000, 'outcome' => 'granted',
          'grant' => ['currency_id' => 'teeth', 'amount' => 5, 'balance_before' => 10, 'balance_after' => 15]],
        ['entry_index' => 2, 'key' => 'xp', 'reward_type' => 'unit_xp', 'probability_basis_points' => 5000, 'roll' => 1, 'outcome' => 'granted',
          'grant' => ['target_scope' => 'participating_units', 'amount_per_unit' => 50, 'units' => [
            ['unit_id' => '2', 'level_before' => 1, 'xp_before' => 90, 'level_after' => 2, 'xp_after' => 40],
            ['unit_id' => '12', 'level_before' => 2, 'xp_before' => 150, 'level_after' => 3, 'xp_after' => 0],
          ]]],
        ['entry_index' => 3, 'key' => 'unlock', 'reward_type' => 'unlock', 'probability_basis_points' => 10000, 'roll' => 2, 'outcome' => 'granted',
          'grant' => ['unlock_id' => 'unlock.region.mountains']],
        ['entry_index' => 4, 'key' => 'unlock_again', 'reward_type' => 'unlock', 'probability_basis_points' => 10000, 'roll' => 3, 'outcome' => 'already_owned',
          'grant' => ['unlock_id' => 'unlock.region.mountains']],
        ['entry_index' => 5, 'key' => 'miss', 'reward_type' => 'currency', 'probability_basis_points' => 1, 'roll' => 2, 'outcome' => 'not_rolled', 'grant' => null],
      ],
    ];
  }
}
