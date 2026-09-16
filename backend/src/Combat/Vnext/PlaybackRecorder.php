<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use InvalidArgumentException;

final class PlaybackRecorder
{
  private const FACTS = [
    'battle_started' => ['combatant_keys'],
    'round_started' => [],
    'action_started' => ['actor_key', 'ability_id', 'target_key', 'target_reason'],
    'action_skipped' => ['actor_key', 'ability_id', 'reason'],
    'dice_rolled' => ['actor_key', 'ability_id', 'slot', 'die_key', 'sides', 'initial_roll', 'extra_roll', 'roll_total'],
    'hit_resolved' => ['actor_key', 'target_key', 'result', 'chance_percent', 'check_roll'],
    'damage_dealt' => ['actor_key', 'target_key', 'amount', 'hp_before', 'hp_after', 'attack_component', 'roll_total', 'target_defense', 'conditional_multiplier', 'position_multiplier'],
    'status_applied' => ['target_key', 'status_id', 'source_key', 'expires_round', 'params', 'forced_target_key'],
    'status_resisted' => ['target_key', 'status_id', 'chance_percent', 'check_roll'],
    'status_removed' => ['target_key', 'status_id', 'reason'],
    'death' => ['combatant_key'],
    'battle_ended' => ['outcome'],
  ];

  /** @var list<array<string,mixed>> */
  private array $events = [];

  /** @param array<string,mixed> $facts */
  public function add(string $type, int $round, int $tick, array $facts): void
  {
    if (!isset(self::FACTS[$type])) throw new InvalidArgumentException('Unsupported playback event type.');
    CombatInput::keys($facts, self::FACTS[$type]);
    CombatInput::integer($round, 0, 200, 'event round');
    CombatInput::integer($tick, 0, 4000, 'event tick');
    foreach ($facts as $key => $value) {
      if (in_array($key, ['actor_key', 'ability_id', 'target_key', 'target_reason', 'die_key', 'result',
        'status_id', 'source_key', 'reason', 'combatant_key', 'outcome'], true)
        && (!is_string($value) || $value === '')) throw new InvalidArgumentException("Playback {$key} must be a string.");
      if (in_array($key, ['slot', 'sides', 'initial_roll', 'roll_total', 'amount', 'hp_before', 'hp_after',
        'attack_component', 'target_defense', 'expires_round', 'chance_percent'], true)
        && !is_int($value)) throw new InvalidArgumentException("Playback {$key} must be an integer.");
      if (in_array($key, ['check_roll', 'extra_roll'], true) && $value !== null && !is_int($value)) {
        throw new InvalidArgumentException("Playback {$key} must be an integer or null.");
      }
      if (in_array($key, ['conditional_multiplier', 'position_multiplier'], true)
        && ((!is_float($value) && !is_int($value)) || !is_finite((float)$value))) {
        throw new InvalidArgumentException("Playback {$key} must be finite numeric.");
      }
    }
    if (isset($facts['params']) && !is_array($facts['params'])) throw new InvalidArgumentException('Playback status params must be an object.');
    if (isset($facts['combatant_keys']) && (!is_array($facts['combatant_keys']) || !array_is_list($facts['combatant_keys']))) {
      throw new InvalidArgumentException('Playback combatant keys must be an ordered list.');
    }
    foreach ($facts['combatant_keys'] ?? [] as $key) {
      if (!is_string($key) || $key === '') throw new InvalidArgumentException('Playback combatant keys must be strings.');
    }
    if (array_key_exists('forced_target_key', $facts)
      && $facts['forced_target_key'] !== null && (!is_string($facts['forced_target_key']) || $facts['forced_target_key'] === '')) {
      throw new InvalidArgumentException('Playback forced target must be a key or null.');
    }
    if (isset($facts['outcome']) && !in_array($facts['outcome'], ['victory', 'defeat', 'stalemate'], true)) {
      throw new InvalidArgumentException('Playback outcome is invalid.');
    }
    if (isset($facts['result']) && !in_array($facts['result'], ['hit', 'miss', 'critical'], true)) {
      throw new InvalidArgumentException('Playback hit result is invalid.');
    }
    $this->events[] = ['sequence' => count($this->events), 'type' => $type, 'round' => $round, 'tick' => $tick, 'facts' => $facts];
  }

  /** @return list<array<string,mixed>> */
  public function events(): array { return $this->events; }
}
