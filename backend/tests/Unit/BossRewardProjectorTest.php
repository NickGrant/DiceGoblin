<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\RunNodes\BossRewardProjector;
use DiceGoblins\Domain\Rewards\FinalizedRewardResult;
use PHPUnit\Framework\TestCase;

final class BossRewardProjectorTest extends TestCase
{
  public function testProjectsAuthoredXpWithoutRequiringAnUnlock(): void
  {
    $result = $this->result([
      $this->xpEntry(0, 'victory_xp', 37, [
        ['unit_id' => '2', 'level_before' => 2, 'xp_before' => 80, 'level_after' => 2, 'xp_after' => 117],
        ['unit_id' => '11', 'level_before' => 1, 'xp_before' => 0, 'level_after' => 1, 'xp_after' => 37],
      ]),
    ]);

    $this->assertSame([
      'unit_xp' => [
        ['unit_id' => '2', 'amount' => 37, 'level_before' => 2, 'xp_before' => 80, 'level_after' => 2, 'xp_after' => 117],
        ['unit_id' => '11', 'amount' => 37, 'level_before' => 1, 'xp_before' => 0, 'level_after' => 1, 'xp_after' => 37],
      ],
      'unlocks' => [],
    ], (new BossRewardProjector())->project($result, [11, 2]));
  }

  public function testProjectsUnlocksByStableIdentityInDeterministicOrder(): void
  {
    $result = $this->result([
      $this->xpEntry(0, 'xp', 1, [['unit_id' => '2', 'level_before' => 1, 'xp_before' => 0, 'level_after' => 1, 'xp_after' => 1]]),
      $this->unlockEntry(1, 'later', 'unlock.region.zzz', 'already_owned'),
      $this->unlockEntry(2, 'earlier', 'unlock.region.aaa', 'granted'),
    ]);

    $projected = (new BossRewardProjector())->project($result, [2]);
    $this->assertSame([
      ['unlock_id' => 'unlock.region.aaa', 'outcome' => 'granted'],
      ['unlock_id' => 'unlock.region.zzz', 'outcome' => 'already_owned'],
    ], $projected['unlocks']);
  }

  public function testRejectsUnsupportedRewardTypeAndNonParticipantXp(): void
  {
    $currency = $this->result([[
      'entry_index' => 0, 'key' => 'money', 'reward_type' => 'currency', 'probability_basis_points' => 10000,
      'roll' => 1, 'outcome' => 'granted',
      'grant' => ['currency_id' => 'teeth', 'amount' => 1, 'balance_before' => 0, 'balance_after' => 1],
    ]]);
    $this->expectException(RunNodeResolutionIntegrityException::class);
    (new BossRewardProjector())->project($currency, [2]);
  }

  public function testRejectsXpWhoseTargetsDoNotMatchParticipants(): void
  {
    $result = $this->result([$this->xpEntry(0, 'xp', 1,
      [['unit_id' => '2', 'level_before' => 1, 'xp_before' => 0, 'level_after' => 1, 'xp_after' => 1]])]);
    $this->expectException(RunNodeResolutionIntegrityException::class);
    (new BossRewardProjector())->project($result, [2, 11]);
  }

  /** @param list<array<string,mixed>> $entries */
  private function result(array $entries): FinalizedRewardResult
  {
    return FinalizedRewardResult::fromArray([
      'version' => 1, 'event_id' => 'event.test_boss_completed',
      'reward_definition_id' => 'reward_definition.test_boss', 'source_type' => 'run_node',
      'source_id' => 'run_node:1', 'entries' => $entries,
    ]);
  }

  /** @param list<array<string,int|string>> $units @return array<string,mixed> */
  private function xpEntry(int $index, string $key, int $amount, array $units): array
  {
    return [
      'entry_index' => $index, 'key' => $key, 'reward_type' => 'unit_xp', 'probability_basis_points' => 10000,
      'roll' => 1, 'outcome' => 'granted',
      'grant' => ['target_scope' => 'participating_units', 'amount_per_unit' => $amount, 'units' => $units],
    ];
  }

  /** @return array<string,mixed> */
  private function unlockEntry(int $index, string $key, string $unlockId, string $outcome): array
  {
    return [
      'entry_index' => $index, 'key' => $key, 'reward_type' => 'unlock', 'probability_basis_points' => 10000,
      'roll' => 1, 'outcome' => $outcome, 'grant' => ['unlock_id' => $unlockId],
    ];
  }
}
