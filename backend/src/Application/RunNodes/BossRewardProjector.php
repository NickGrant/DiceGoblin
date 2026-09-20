<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Domain\Rewards\FinalizedRewardResult;

final class BossRewardProjector
{
  /**
   * @param list<int|string> $participatingUnitIds
   * @return array{unit_xp:list<array{unit_id:string,amount:int,level_before:int,xp_before:int,level_after:int,xp_after:int}>,
   *   unlocks:list<array{unlock_id:string,outcome:string}>}
   */
  public function project(FinalizedRewardResult $result, array $participatingUnitIds): array
  {
    $participantIds = array_map('strval', $participatingUnitIds);
    usort($participantIds, [FinalizedRewardResult::class, 'compareIds']);
    if ($participantIds === [] || count($participantIds) !== count(array_unique($participantIds))) {
      throw new RunNodeResolutionIntegrityException('Boss reward participants are invalid.');
    }

    $xp = [];
    $unlocks = [];
    foreach ($result->entries() as $entry) {
      $type = $entry['reward_type'];
      if (!in_array($type, ['unit_xp', 'unlock'], true)) {
        throw new RunNodeResolutionIntegrityException('Boss reward type is unsupported.');
      }
      if ($entry['outcome'] === 'not_rolled') continue;

      $grant = $entry['grant'];
      if ($type === 'unit_xp') {
        if (($grant['target_scope'] ?? null) !== 'participating_units'
          || array_column($grant['units'], 'unit_id') !== $participantIds) {
          throw new RunNodeResolutionIntegrityException('Boss XP reward participants are invalid.');
        }
        foreach ($grant['units'] as $unit) {
          $unitId = $unit['unit_id'];
          if (!isset($xp[$unitId])) {
            $xp[$unitId] = [
              'unit_id' => $unitId,
              'amount' => $grant['amount_per_unit'],
              'level_before' => $unit['level_before'],
              'xp_before' => $unit['xp_before'],
              'level_after' => $unit['level_after'],
              'xp_after' => $unit['xp_after'],
            ];
            continue;
          }
          if ($xp[$unitId]['level_after'] !== $unit['level_before'] || $xp[$unitId]['xp_after'] !== $unit['xp_before']
            || $grant['amount_per_unit'] > PHP_INT_MAX - $xp[$unitId]['amount']) {
            throw new RunNodeResolutionIntegrityException('Boss XP reward transitions are invalid.');
          }
          $xp[$unitId]['amount'] += $grant['amount_per_unit'];
          $xp[$unitId]['level_after'] = $unit['level_after'];
          $xp[$unitId]['xp_after'] = $unit['xp_after'];
        }
        continue;
      }

      $unlockId = $grant['unlock_id'];
      if (isset($unlocks[$unlockId])) {
        throw new RunNodeResolutionIntegrityException('Boss unlock rewards are duplicated.');
      }
      $unlocks[$unlockId] = ['unlock_id' => $unlockId, 'outcome' => $entry['outcome']];
    }

    if (array_map('strval', array_keys($xp)) !== $participantIds) {
      throw new RunNodeResolutionIntegrityException('Boss XP rewards are incomplete.');
    }
    uksort($unlocks, 'strcmp');
    return ['unit_xp' => array_values($xp), 'unlocks' => array_values($unlocks)];
  }
}
