<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Rewards;

use DiceGoblins\Domain\Progression\UnitXpResolver;
use OverflowException;

final class RewardFinalizer
{
  public function __construct(
    private readonly RewardRollSource $rolls,
    private readonly UnitXpResolver $xp = new UnitXpResolver(),
  ) {}

  /** @param array<string,mixed> $event @param array<string,mixed> $definition */
  public function finalize(array $event, array $definition, string $sourceType, string $sourceId, RewardContext $context): FinalizedRewardResult
  {
    $balances = ['teeth' => $context->teeth, 'raw_chaos' => $context->rawChaos];
    $units = $context->units();
    uksort($units, [FinalizedRewardResult::class, 'compareIds']);
    $unlocks = $context->unlocks();
    $entries = [];
    foreach ($definition['entries'] as $index => $authored) {
      $roll = $this->rolls->nextBasisPointRoll();
      if ($roll < 1 || $roll > 10000) throw new RewardResultException('Reward roll source returned an out-of-range value.');
      $probability = (int)$authored['probability_basis_points'];
      $type = (string)$authored['reward_type'];
      $entry = [
        'entry_index' => $index,
        'key' => (string)$authored['key'],
        'reward_type' => $type,
        'probability_basis_points' => $probability,
        'roll' => $roll,
        'outcome' => 'not_rolled',
        'grant' => null,
      ];
      if ($roll <= $probability) {
        $config = $authored['config'];
        if ($type === 'currency') {
          $currency = (string)$config['currency_id'];
          $amount = (int)$config['amount'];
          $before = $balances[$currency];
          if ($amount > PHP_INT_MAX - $before) throw new OverflowException('Currency reward overflowed.');
          $after = $before + $amount;
          $balances[$currency] = $after;
          $entry['outcome'] = 'granted';
          $entry['grant'] = ['currency_id' => $currency, 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after];
        } elseif ($type === 'unit_xp') {
          $amount = (int)$config['amount'];
          $transitions = [];
          foreach ($units as $id => $unit) {
            $id = (string)$id;
            $after = $this->xp->apply($unit['level'], $unit['xp'], $amount);
            $transitions[] = ['unit_id' => $id, 'level_before' => $unit['level'], 'xp_before' => $unit['xp'], 'level_after' => $after['level'], 'xp_after' => $after['xp']];
            $units[$id] = ['unit_id' => $id, 'level' => $after['level'], 'xp' => $after['xp']];
          }
          $entry['outcome'] = 'granted';
          $entry['grant'] = ['target_scope' => 'participating_units', 'amount_per_unit' => $amount, 'units' => $transitions];
        } else {
          $unlockId = (string)$config['unlock_id'];
          $entry['outcome'] = isset($unlocks[$unlockId]) ? 'already_owned' : 'granted';
          $entry['grant'] = ['unlock_id' => $unlockId];
          $unlocks[$unlockId] = true;
        }
      }
      $entries[] = $entry;
    }
    return FinalizedRewardResult::fromArray([
      'version' => FinalizedRewardResult::VERSION,
      'event_id' => (string)$event['id'],
      'reward_definition_id' => (string)$definition['id'],
      'source_type' => $sourceType,
      'source_id' => $sourceId,
      'entries' => $entries,
    ]);
  }
}
