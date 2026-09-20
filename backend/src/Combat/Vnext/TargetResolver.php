<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use DiceGoblins\Support\DeterministicRandom;

final class TargetResolver
{
  /** @param array<string,array<string,mixed>> $combatants @return array{key:string,reason:string}|null */
  public function choose(array $combatants, string $actorKey, string $rule, bool $damaging, DeterministicRandom $rng,
    ?array $ability = null, ?string $previousTargetKey = null): ?array
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

    if (($ability['handler_id'] ?? null) === 'aimed_shot' && $this->hasPassive($actor, 'patient_aim')) {
      $bestScore = null;
      $ties = [];
      $reasons = [];
      foreach ($candidates as $key => $candidate) {
        $score = $candidate['position']['x'] === $this->backmostX($candidates) ? 300 : 0;
        $candidateReasons = $score > 0 ? ['backline'] : [];
        if ($this->isWounded($candidate)) { $score += 250; $candidateReasons[] = 'wounded'; }
        if ($this->hasStatus($candidate, 'marked')) { $score += 260; $candidateReasons[] = 'marked'; }
        if ($key === $previousTargetKey) { $score += 290; $candidateReasons[] = 'preferred_previous_target'; }
        if ($bestScore === null || $score > $bestScore) {
          $bestScore = $score; $ties = [$key]; $reasons = [$key => $candidateReasons];
        } elseif ($score === $bestScore) {
          $ties[] = $key; $reasons[$key] = $candidateReasons;
        }
      }
      sort($ties, SORT_STRING);
      $key = $ties[count($ties) === 1 ? 0 : $rng->nextInt(0, count($ties) - 1)];
      return ['key' => $key, 'reason' => 'patient_aim:' . implode(',', $reasons[$key])];
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

  /** @param array<string,mixed> $unit */
  private function hasPassive(array $unit, string $handler): bool
  {
    foreach ($unit['passive_abilities'] as $passive) if ($passive['handler_id'] === $handler) return true;
    return false;
  }

  /** @param array<string,mixed> $unit */
  private function hasStatus(array $unit, string $id): bool
  {
    foreach ($unit['statuses'] as $status) if ($status['id'] === $id) return true;
    return false;
  }

  /** @param array<string,mixed> $unit */
  private function isWounded(array $unit): bool
  {
    return $unit['current_hp'] <= intdiv($unit['max_hp'] * 3, 10);
  }

  /** @param array<string,array<string,mixed>> $candidates */
  private function backmostX(array $candidates): int
  {
    return min(array_column(array_column($candidates, 'position'), 'x'));
  }
}
