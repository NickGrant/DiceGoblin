<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use DiceGoblins\Support\DeterministicRandom;

/** Deterministic vNext combat kernel over one already normalized snapshot. */
final class CombatEngine
{
  public const TICKS_PER_ROUND = 20;
  public const MAX_TICKS = 4000;

  public function __construct(
    private readonly TargetResolver $targets = new TargetResolver(),
    private readonly DamageCalculator $damage = new DamageCalculator(),
  ) {}

  public function resolve(CombatInput $input): CombatResult
  {
    $rng = new DeterministicRandom($input->snapshot['seed']);
    $units = $input->combatants;
    $next = [];
    $indices = [];
    $sleepBlockedUntil = [];
    $events = new PlaybackRecorder();
    $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($units)]);
    foreach ($units as $key => $unit) {
      if ($unit['current_hp'] <= 0) continue;
      $indices[$key] = 0;
      $next[$key] = $unit['active_abilities'][0]['action_delay'];
    }

    $outcome = $this->outcome($units);
    if ($outcome !== null) return $this->finish($units, $events, $outcome, 0, 0);

    for ($tick = 1; $tick <= self::MAX_TICKS; $tick++) {
      $round = intdiv($tick - 1, self::TICKS_PER_ROUND) + 1;
      if (($tick - 1) % self::TICKS_PER_ROUND === 0) {
        $events->add('round_started', $round, $tick, []);
        $this->expireStatuses($units, $round, $tick, $events, $sleepBlockedUntil);
      }
      $due = [];
      foreach ($next as $key => $at) {
        if ($at !== $tick) continue;
        $ability = $units[$key]['active_abilities'][$indices[$key]];
        $due[] = ['key' => $key, 'priority' => $ability['resolution_priority']];
      }
      usort($due, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority'] ?: strcmp($a['key'], $b['key']));

      foreach ($due as $scheduled) {
        $key = $scheduled['key'];
        $ability = $units[$key]['active_abilities'][$indices[$key]];
        if ($units[$key]['current_hp'] <= 0) {
          $events->add('action_skipped', $round, $tick,
            ['actor_key' => $key, 'ability_id' => $ability['id'], 'reason' => 'dead']);
          unset($next[$key]);
          continue;
        }
        if ($this->hasStatus($units[$key], 'sleep') || ($sleepBlockedUntil[$key] ?? 0) >= $tick) {
          $events->add('action_skipped', $round, $tick,
            ['actor_key' => $key, 'ability_id' => $ability['id'], 'reason' => 'sleep']);
          $this->advance($units[$key], $key, $tick, $indices, $next);
          continue;
        }
        $chosen = $this->targets->choose($units, $key, $ability['target_rule'], CombatRules::isDamaging($ability['handler_id']), $rng);
        if ($chosen === null) {
          $events->add('action_skipped', $round, $tick,
            ['actor_key' => $key, 'ability_id' => $ability['id'], 'reason' => 'no_target']);
          $this->advance($units[$key], $key, $tick, $indices, $next);
          continue;
        }
        $targetKey = $chosen['key'];
        $events->add('action_started', $round, $tick,
          ['actor_key' => $key, 'ability_id' => $ability['id'], 'target_key' => $targetKey, 'target_reason' => $chosen['reason']]);
        if ($chosen['reason'] === 'wrestled_forced') $this->removeStatus($units, $key, 'wrestled', 'consumed', $round, $tick, $events, $sleepBlockedUntil);

        $needsHit = CombatRules::isDamaging($ability['handler_id']) || $ability['handler_id'] === 'sleep_dart';
        $hit = $needsHit ? $this->hitCheck($units[$key]['stats']['precision'], $rng,
          CombatRules::isDamaging($ability['handler_id']))
          : ['result' => 'hit', 'chance' => 0, 'roll' => null];
        if ($needsHit) {
          $events->add('hit_resolved', $round, $tick,
            ['actor_key' => $key, 'target_key' => $targetKey, 'result' => $hit['result'],
              'chance_percent' => $hit['chance'], 'check_roll' => $hit['roll']]);
        }
        $rollTotal = $this->rollDice($ability, $key, $round, $tick, $rng, $events);
        if ($hit['result'] !== 'miss') {
          if (CombatRules::isDamaging($ability['handler_id'])) {
            $facts = $this->damage->calculate($units[$key], $units[$targetKey], $ability, $rollTotal, $hit['result'] === 'critical');
            $before = $units[$targetKey]['current_hp'];
            $units[$targetKey]['current_hp'] = max(0, $before - $facts['amount']);
            $events->add('damage_dealt', $round, $tick,
              ['actor_key' => $key, 'target_key' => $targetKey, 'amount' => $facts['amount'], 'hp_before' => $before,
                'hp_after' => $units[$targetKey]['current_hp'], 'attack_component' => $facts['attack_component'],
                'roll_total' => $rollTotal, 'target_defense' => $facts['target_defense'],
                'conditional_multiplier' => $facts['conditional_multiplier'], 'position_multiplier' => $facts['position_multiplier']]);
            if ($this->hasStatus($units[$targetKey], 'sleep')) {
              $this->removeStatus($units, $targetKey, 'sleep', 'damaged', $round, $tick, $events, $sleepBlockedUntil);
            }
            if ($units[$targetKey]['current_hp'] === 0) {
              $events->add('death', $round, $tick, ['combatant_key' => $targetKey]);
            }
          }
          if (isset($ability['config']['status_id']) && $units[$targetKey]['current_hp'] > 0) {
            $this->applyStatus($units, $key, $targetKey, $ability, $round, $tick, $rng, $events, $sleepBlockedUntil);
          }
        }
        $this->advance($units[$key], $key, $tick, $indices, $next);
        $outcome = $this->outcome($units);
        if ($outcome !== null) return $this->finish($units, $events, $outcome, $round, $tick);
      }
    }
    return $this->finish($units, $events, 'stalemate', 200, self::MAX_TICKS);
  }

  /** @param array<string,mixed> $unit @param array<string,int> $indices @param array<string,int> $next */
  private function advance(array $unit, string $key, int $tick, array &$indices, array &$next): void
  {
    if ($unit['current_hp'] <= 0) { unset($next[$key]); return; }
    $indices[$key] = ($indices[$key] + 1) % count($unit['active_abilities']);
    $next[$key] = $tick + $unit['active_abilities'][$indices[$key]]['action_delay'];
  }

  /** @param array<string,array<string,mixed>> $units */
  private function outcome(array $units): ?string
  {
    $alive = ['player' => false, 'enemy' => false];
    foreach ($units as $unit) if ($unit['current_hp'] > 0) $alive[$unit['side']] = true;
    if (!$alive['enemy']) return 'victory';
    if (!$alive['player']) return 'defeat';
    return null;
  }

  /** @param array<string,array<string,mixed>> $units */
  private function finish(array $units, PlaybackRecorder $events, string $outcome, int $round, int $tick): CombatResult
  {
    $terminal = [];
    foreach ($units as $key => $unit) {
      usort($unit['statuses'], static fn(array $a, array $b): int => strcmp($a['id'], $b['id']) ?: strcmp($a['source_key'], $b['source_key']));
      $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $unit['current_hp'],
        'max_hp' => $unit['max_hp'], 'is_defeated' => $unit['current_hp'] === 0, 'statuses' => $unit['statuses']];
    }
    $events->add('battle_ended', $round, $tick, ['outcome' => $outcome]);
    return new CombatResult($outcome, $round, $tick, $terminal, $events->events());
  }

  /** @param array<string,mixed> $unit */
  private function hasStatus(array $unit, string $id): bool
  {
    foreach ($unit['statuses'] as $status) if ($status['id'] === $id) return true;
    return false;
  }

  /** @param array<string,array<string,mixed>> $units @param array<string,int> $sleepBlockedUntil */
  private function expireStatuses(array &$units, int $round, int $tick, PlaybackRecorder $events, array &$sleepBlockedUntil): void
  {
    foreach ($units as $key => $unit) {
      foreach ($unit['statuses'] as $status) {
        if ($status['expires_round'] <= $round) $this->removeStatus($units, $key, $status['id'], 'expired', $round, $tick, $events, $sleepBlockedUntil);
      }
    }
  }

  /** @param array<string,array<string,mixed>> $units @param array<string,int> $sleepBlockedUntil */
  private function removeStatus(array &$units, string $target, string $id, string $reason, int $round, int $tick,
    PlaybackRecorder $events, array &$sleepBlockedUntil): void
  {
    $remaining = [];
    $removed = false;
    foreach ($units[$target]['statuses'] as $status) {
      if ($status['id'] === $id) { $removed = true; continue; }
      $remaining[] = $status;
    }
    if (!$removed) return;
    $units[$target]['statuses'] = $remaining;
    if ($id === 'sleep') $sleepBlockedUntil[$target] = $tick;
    $events->add('status_removed', $round, $tick, ['target_key' => $target, 'status_id' => $id, 'reason' => $reason]);
  }

  /** @return array{result:string,chance:int,roll:?int} */
  private function hitCheck(int $precision, DeterministicRandom $rng, bool $canCritical): array
  {
    if ($precision === 5) return ['result' => 'hit', 'chance' => 0, 'roll' => null];
    if ($precision < 5) {
      $chance = min(40, (5 - $precision) * 8);
      $roll = $rng->nextInt(1, 100);
      return ['result' => $roll <= $chance ? 'miss' : 'hit', 'chance' => $chance, 'roll' => $roll];
    }
    if (!$canCritical) return ['result' => 'hit', 'chance' => 0, 'roll' => null];
    $chance = min(30, ($precision - 5) * 5);
    $roll = $rng->nextInt(1, 100);
    return ['result' => $roll <= $chance ? 'critical' : 'hit', 'chance' => $chance, 'roll' => $roll];
  }

  /** @param array<string,mixed> $ability */
  private function rollDice(array $ability, string $actor, int $round, int $tick, DeterministicRandom $rng, PlaybackRecorder $events): int
  {
    $total = 0;
    foreach ($ability['dice'] as $slot => $die) {
      $initial = $rng->nextInt(1, $die['sides']);
      $extra = null;
      foreach ($die['effects'] as $effect) {
        if ($effect['effect_id'] === 'explode_on_maximum' && $initial === $die['sides']) {
          $extra = $rng->nextInt(1, $die['sides']);
          break;
        }
      }
      $value = $initial + ($extra ?? 0);
      $total += $value;
      $events->add('dice_rolled', $round, $tick,
        ['actor_key' => $actor, 'ability_id' => $ability['id'], 'slot' => $slot, 'die_key' => $die['key'],
          'sides' => $die['sides'], 'initial_roll' => $initial, 'extra_roll' => $extra, 'roll_total' => $value]);
    }
    return $total;
  }

  /** @param array<string,array<string,mixed>> $units @param array<string,mixed> $ability @param array<string,int> $sleepBlockedUntil */
  private function applyStatus(array &$units, string $source, string $target, array $ability, int $round, int $tick,
    DeterministicRandom $rng, PlaybackRecorder $events, array &$sleepBlockedUntil): void
  {
    $config = $ability['config'];
    $id = $config['status_id'];
    $harmful = in_array($id, ['sleep', 'cracked_armor', 'wrestled'], true);
    if ($harmful && $units[$target]['stats']['resolve'] > $units[$source]['stats']['precision']) {
      $chance = min(45, ($units[$target]['stats']['resolve'] - $units[$source]['stats']['precision']) * 8);
      $roll = $rng->nextInt(1, 100);
      if ($roll <= $chance) {
        $events->add('status_resisted', $round, $tick,
          ['target_key' => $target, 'status_id' => $id, 'chance_percent' => $chance, 'check_roll' => $roll]);
        return;
      }
    }
    $params = match ($id) {
      'bolstered' => ['defense_pct' => $config['bolster_defense_pct']],
      'cracked_armor' => ['defense_reduction_flat' => $config['defense_reduction_flat']],
      default => [],
    };
    $forced = $id === 'wrestled' ? $source : null;
    $this->removeStatus($units, $target, $id, 'replaced', $round, $tick, $events, $sleepBlockedUntil);
    $status = ['id' => $id, 'source_key' => $source, 'expires_round' => $round + $config['duration_rounds'],
      'params' => $params, 'forced_target_key' => $forced];
    $units[$target]['statuses'][] = $status;
    $events->add('status_applied', $round, $tick,
      ['target_key' => $target, 'status_id' => $id, 'source_key' => $source,
        'expires_round' => $status['expires_round'], 'params' => $params, 'forced_target_key' => $forced]);
  }
}
