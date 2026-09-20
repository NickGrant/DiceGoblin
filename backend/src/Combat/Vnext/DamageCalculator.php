<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

final class DamageCalculator
{
  public function __construct(private readonly CombatStatMath $stats = new CombatStatMath()) {}

  /** @param array<string,mixed> $actor @param array<string,mixed> $target @param array<string,mixed> $ability
   *  @return array{amount:int,attack_component:int,target_defense:int,conditional_multiplier:float,position_multiplier:float}
   */
  public function calculate(array $actor, array $target, array $ability, int $rollTotal, bool $critical, bool $tauntRedirected = false): array
  {
    $attackComponent = (int)floor($this->stats->attack($actor) * $ability['config']['power_ratio']);
    $defense = max(0, $this->stats->defense($target) - ($ability['config']['ignore_defense_flat'] ?? 0));
    $damage = max(0, $attackComponent + $rollTotal - $defense);
    $conditionalMultiplier = 1.0;
    foreach ($ability['dice'] as $die) {
      foreach ($die['effects'] as $effect) {
        if ($effect['effect_id'] === 'flat_damage_bonus') $damage += $effect['config']['amount'];
        if ($effect['effect_id'] === 'wounded_target_damage_bonus'
          && $target['current_hp'] < $target['max_hp'] * $effect['config']['health_threshold']) {
          $conditionalMultiplier *= 1 + $effect['config']['damage_bonus'];
        }
      }
    }
    if (CombatRules::isRanged($ability['handler_id'])) {
      foreach ($actor['passive_abilities'] as $passive) {
        if ($passive['handler_id'] === 'sharpshooter') $conditionalMultiplier *= 1 + $passive['config']['ranged_damage_pct'];
      }
    }
    foreach ($actor['passive_abilities'] as $passive) {
      if ($passive['handler_id'] === 'patient_aim' && $ability['handler_id'] === 'aimed_shot') {
        $conditionalMultiplier *= 1 + $passive['config']['aimed_shot_bonus_pct'];
      }
      if ($passive['handler_id'] === 'clean_shot' && $this->hasStatus($target, $passive['config']['status_bonus_target'])) {
        $conditionalMultiplier *= 1 + $passive['config']['status_bonus_pct'];
      }
    }
    $positionMultiplier = 1.0;
    if (CombatRules::isMelee($ability['handler_id']) && $actor['position']['x'] === 2) $positionMultiplier *= 1.10;
    if ($target['position']['x'] === 2) $positionMultiplier *= 1.10;
    if (CombatRules::isMelee($ability['handler_id']) && $target['position']['x'] === 0) $positionMultiplier *= 0.90;
    $damage = (int)floor($damage * $conditionalMultiplier * $positionMultiplier);
    if ($critical) $damage = (int)floor($damage * 1.5);
    foreach ($target['passive_abilities'] as $passive) {
      if ($tauntRedirected && $passive['handler_id'] === 'unmoving') $damage -= $passive['config']['taunt_damage_reduction_flat'];
    }
    if ($tauntRedirected) {
      foreach ($target['statuses'] as $status) if ($status['id'] === 'taunting_guard') {
        $damage -= $status['params']['stack_count'] * $status['params']['per_stack_damage_reduction'];
      }
    }
    return ['amount' => max(1, $damage), 'attack_component' => $attackComponent, 'target_defense' => $defense,
      'conditional_multiplier' => round($conditionalMultiplier, 6), 'position_multiplier' => round($positionMultiplier, 6)];
  }

  /** @param array<string,mixed> $unit */
  private function hasStatus(array $unit, string $id): bool
  {
    foreach ($unit['statuses'] as $status) if ($status['id'] === $id) return true;
    return false;
  }
}
