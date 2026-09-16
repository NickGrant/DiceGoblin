<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

final class CombatStatMath
{
  /** @param array<string,mixed> $unit */
  public function attack(array $unit): int
  {
    [$flat, $pct] = $this->boundModifiers($unit, 'attack');
    return max(0, (int)floor(($unit['stats']['attack'] + $flat) * (1 + $pct)));
  }

  /** @param array<string,mixed> $unit */
  public function defense(array $unit): int
  {
    [$flat, $pct] = $this->boundModifiers($unit, 'defense');
    foreach ($unit['passive_abilities'] as $passive) {
      if ($passive['handler_id'] === 'thick_hide') $flat += $passive['config']['defense_flat'];
    }
    $base = max(0, (int)floor(($unit['stats']['defense'] + $flat) * (1 + $pct)));
    $statusFlat = 0;
    $statusPct = 0.0;
    foreach ($unit['statuses'] as $status) {
      if ($status['id'] === 'cracked_armor') $statusFlat -= $status['params']['defense_reduction_flat'];
      if ($status['id'] === 'bolstered') $statusPct += $status['params']['defense_pct'];
    }
    return max(0, (int)floor(max(0, $base + $statusFlat) * (1 + $statusPct)));
  }

  /** @param array<string,mixed> $unit @return array{0:int,1:float} */
  private function boundModifiers(array $unit, string $stat): array
  {
    $flat = 0;
    $pct = 0.0;
    $seen = [];
    foreach ($unit['active_abilities'] as $ability) {
      foreach ($ability['dice'] as $die) {
        if (isset($seen[$die['key']])) continue;
        $seen[$die['key']] = true;
        foreach ($die['effects'] as $effect) {
          if ($stat === 'attack' && $effect['effect_id'] === 'percent_attack_bonus') $pct += $effect['config']['amount'];
          if ($stat === 'defense' && $effect['effect_id'] === 'flat_defense_bonus') $flat += $effect['config']['amount'];
          if ($stat === 'defense' && $effect['effect_id'] === 'percent_defense_bonus') $pct += $effect['config']['amount'];
        }
      }
    }
    return [$flat, $pct];
  }
}
