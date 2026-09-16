<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use InvalidArgumentException;

/** A fully resolved snapshot. No catalog or persistence lookup occurs after construction. */
final class CombatInput
{
  /** @var array<string,array<string,mixed>> */
  public readonly array $combatants;

  /** @param array<string,mixed> $snapshot */
  public function __construct(public readonly array $snapshot)
  {
    self::keys($snapshot, ['seed', 'combatants']);
    if (!is_string($snapshot['seed']) || $snapshot['seed'] === '') throw new InvalidArgumentException('Combat seed must be non-empty.');
    if (!is_array($snapshot['combatants']) || !array_is_list($snapshot['combatants']) || $snapshot['combatants'] === []) {
      throw new InvalidArgumentException('Combatants must be a non-empty list.');
    }
    $byKey = $cells = $sides = [];
    foreach ($snapshot['combatants'] as $combatant) {
      if (!is_array($combatant)) throw new InvalidArgumentException('Combatant must be an object.');
      self::keys($combatant, ['key', 'side', 'position', 'stats', 'max_hp', 'current_hp', 'active_abilities', 'passive_abilities', 'statuses']);
      $key = $combatant['key'];
      $side = $combatant['side'];
      if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1 || isset($byKey[$key])) throw new InvalidArgumentException('Combatant keys must be unique stable keys.');
      if (!in_array($side, ['player', 'enemy'], true)) throw new InvalidArgumentException('Combatant side is invalid.');
      self::keys($combatant['position'], ['x', 'y']);
      foreach (['x', 'y'] as $axis) self::integer($combatant['position'][$axis], 0, 2, "position.{$axis}");
      $cell = $side . ':' . $combatant['position']['x'] . ':' . $combatant['position']['y'];
      if (isset($cells[$cell])) throw new InvalidArgumentException('Combatant position is occupied.');
      $cells[$cell] = true;
      self::keys($combatant['stats'], ['hp', 'attack', 'defense', 'precision', 'resolve']);
      foreach ($combatant['stats'] as $stat => $value) self::integer($value, $stat === 'hp' ? 1 : 0, 1000000000, "stats.{$stat}");
      self::integer($combatant['max_hp'], 1, 1000000000, 'max_hp');
      self::integer($combatant['current_hp'], 0, $combatant['max_hp'], 'current_hp');
      if (!is_array($combatant['active_abilities']) || !array_is_list($combatant['active_abilities']) || $combatant['active_abilities'] === []) {
        throw new InvalidArgumentException('Combatant needs an ordered active loadout.');
      }
      $activeIds = [];
      $boundDice = [];
      foreach ($combatant['active_abilities'] as $ability) {
        self::keys($ability, ['id', 'handler_id', 'target_rule', 'action_delay', 'resolution_priority', 'dice_slot_count', 'config', 'dice']);
        if (!is_string($ability['id']) || isset($activeIds[$ability['id']])) throw new InvalidArgumentException('Active ability IDs must be unique.');
        $activeIds[$ability['id']] = true;
        if (!in_array($ability['handler_id'], CombatRules::ACTIVE_HANDLERS, true)) throw new InvalidArgumentException('Unsupported active handler.');
        if (!in_array($ability['target_rule'], CombatRules::TARGET_RULES, true)) throw new InvalidArgumentException('Unsupported target rule.');
        CombatRules::validateTargetRule($ability['handler_id'], $ability['target_rule']);
        self::integer($ability['action_delay'], 1, 4000, 'action_delay');
        self::integer($ability['resolution_priority'], 0, 10000, 'resolution_priority');
        self::integer($ability['dice_slot_count'], 1, 8, 'dice_slot_count');
        if (!is_array($ability['config']) || array_is_list($ability['config'])) throw new InvalidArgumentException('Ability config must be an object.');
        CombatRules::validateAbilityConfig($ability['handler_id'], $ability['config']);
        if (!is_array($ability['dice']) || !array_is_list($ability['dice']) || count($ability['dice']) !== $ability['dice_slot_count']) {
          throw new InvalidArgumentException('Ability dice must fill its normalized slots.');
        }
        $abilityDice = [];
        foreach ($ability['dice'] as $die) {
          self::keys($die, ['key', 'sides', 'profile_id', 'effects']);
          if (!is_string($die['key']) || $die['key'] === '' || isset($abilityDice[$die['key']])) throw new InvalidArgumentException('Die keys must be unique within an ability.');
          $abilityDice[$die['key']] = true;
          if (isset($boundDice[$die['key']]) && $boundDice[$die['key']] !== $die) {
            throw new InvalidArgumentException('A bound die key must have one consistent specification.');
          }
          $boundDice[$die['key']] = $die;
          if (!is_string($die['profile_id']) || $die['profile_id'] === '') throw new InvalidArgumentException('Die profile ID is required.');
          if (!in_array($die['sides'], [4, 6, 8, 10, 12, 20], true)) throw new InvalidArgumentException('Die sides are unsupported.');
          if (!is_array($die['effects']) || !array_is_list($die['effects'])) throw new InvalidArgumentException('Die effects must be ordered.');
          $effectIds = [];
          foreach ($die['effects'] as $effect) {
            self::keys($effect, ['id', 'effect_id', 'config']);
            if (!is_string($effect['id']) || isset($effectIds[$effect['id']])) throw new InvalidArgumentException('Die aspect IDs must be unique.');
            $effectIds[$effect['id']] = true;
            CombatRules::validateDieEffect($effect['effect_id'], $effect['config']);
          }
        }
      }
      if (!is_array($combatant['passive_abilities']) || !array_is_list($combatant['passive_abilities'])) throw new InvalidArgumentException('Passive abilities must be a list.');
      $passiveIds = [];
      foreach ($combatant['passive_abilities'] as $passive) {
        self::keys($passive, ['id', 'handler_id', 'config']);
        if (!is_string($passive['id']) || isset($passiveIds[$passive['id']])) throw new InvalidArgumentException('Passive ability IDs must be unique.');
        $passiveIds[$passive['id']] = true;
        CombatRules::validatePassive($passive['handler_id'], $passive['config']);
      }
      if (!is_array($combatant['statuses']) || !array_is_list($combatant['statuses'])) throw new InvalidArgumentException('Statuses must be a list.');
      $statusIds = [];
      foreach ($combatant['statuses'] as $status) {
        CombatRules::validateStatus($status);
        if (isset($statusIds[$status['id']])) throw new InvalidArgumentException('Status IDs must be unique on a combatant.');
        $statusIds[$status['id']] = true;
      }
      $byKey[$key] = $combatant;
      $sides[$side] = true;
    }
    if (!isset($sides['player'], $sides['enemy'])) throw new InvalidArgumentException('Combat requires both sides.');
    foreach ($byKey as $unit) {
      foreach ($unit['statuses'] as $status) {
        if (!isset($byKey[$status['source_key']])) throw new InvalidArgumentException('Status source must be a combatant.');
        if ($status['forced_target_key'] !== null && !isset($byKey[$status['forced_target_key']])) {
          throw new InvalidArgumentException('Forced target must be a combatant.');
        }
        if ($status['id'] === 'wrestled' && ($status['forced_target_key'] !== $status['source_key']
          || $byKey[$status['source_key']]['side'] === $unit['side'])) {
          throw new InvalidArgumentException('Wrestled source must be its opposing forced target.');
        }
      }
    }
    ksort($byKey, SORT_STRING);
    $this->combatants = $byKey;
  }

  /** @param array<string,mixed> $value @param list<string> $expected */
  public static function keys(mixed $value, array $expected): void
  {
    if (!is_array($value)) throw new InvalidArgumentException('Expected an object.');
    $actual = array_keys($value);
    sort($actual); sort($expected);
    if ($actual !== $expected) throw new InvalidArgumentException('Snapshot object has an invalid field set.');
  }

  public static function integer(mixed $value, int $min, int $max, string $name): void
  {
    if (!is_int($value) || $value < $min || $value > $max) throw new InvalidArgumentException("{$name} must be an integer from {$min} to {$max}.");
  }
}
