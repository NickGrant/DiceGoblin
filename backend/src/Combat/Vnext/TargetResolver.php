<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use DiceGoblins\Support\DeterministicRandom;

final class TargetResolver
{
  /** @param array<string,array<string,mixed>> $combatants @return array{key:string,reason:string}|null */
  public function choose(array $combatants, string $actorKey, string $rule, bool $damaging, DeterministicRandom $rng): ?array
  {
    $actor = $combatants[$actorKey];
    if ($rule === 'self') return ['key' => $actorKey, 'reason' => 'self'];

    if ($damaging && str_starts_with($rule, 'enemy_')) {
      foreach ($actor['statuses'] as $status) {
        if ($status['id'] !== 'wrestled') continue;
        $forced = $status['forced_target_key'];
        if (isset($combatants[$forced]) && $combatants[$forced]['current_hp'] > 0 && $combatants[$forced]['side'] !== $actor['side']) {
          return ['key' => $forced, 'reason' => 'wrestled_forced'];
        }
      }
      $guards = [];
      foreach ($combatants as $key => $candidate) {
        if ($candidate['current_hp'] <= 0 || $candidate['side'] === $actor['side']) continue;
        foreach ($candidate['statuses'] as $status) if ($status['id'] === 'taunting_guard') $guards[] = $key;
      }
      if ($guards !== []) {
        sort($guards, SORT_STRING);
        return ['key' => $guards[0], 'reason' => 'taunting_guard'];
      }
    }

    $candidates = [];
    foreach ($combatants as $key => $candidate) {
      if ($candidate['current_hp'] <= 0) continue;
      if ($rule === 'ally_lowest_hp_pct' && $candidate['side'] === $actor['side']) $candidates[$key] = $candidate;
      if (str_starts_with($rule, 'enemy_') && $candidate['side'] !== $actor['side']) $candidates[$key] = $candidate;
    }
    if ($candidates === []) return null;

    if ($rule === 'ally_lowest_hp_pct') {
      $best = null;
      $ties = [];
      foreach ($candidates as $key => $candidate) {
        if ($best === null) { $best = $candidate; $ties = [$key]; continue; }
        $left = $candidate['current_hp'] * $best['max_hp'];
        $right = $best['current_hp'] * $candidate['max_hp'];
        if ($left < $right) { $best = $candidate; $ties = [$key]; }
        elseif ($left === $right) $ties[] = $key;
      }
      sort($ties, SORT_STRING);
      return ['key' => $ties[count($ties) === 1 ? 0 : $rng->nextInt(0, count($ties) - 1)],
        'reason' => count($ties) === 1 ? 'lowest_hp_pct' : 'lowest_hp_pct_tie'];
    }

    $preferred = $rule === 'enemy_front_prefer' ? 2 : 0;
    $bestRank = 3;
    $ties = [];
    foreach ($candidates as $key => $candidate) {
      $x = $candidate['position']['x'];
      $rank = $preferred === 2 ? 2 - $x : $x;
      if ($rank < $bestRank) { $bestRank = $rank; $ties = [$key]; }
      elseif ($rank === $bestRank) $ties[] = $key;
    }
    sort($ties, SORT_STRING);
    $reason = $preferred === 2 ? 'front_preference' : 'back_preference';
    return ['key' => $ties[count($ties) === 1 ? 0 : $rng->nextInt(0, count($ties) - 1)],
      'reason' => count($ties) === 1 ? $reason : $reason . '_tie'];
  }
}
