<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use InvalidArgumentException;

final class CombatRules
{
  public const ACTIVE_HANDLERS = [
    'basic_attack_melee', 'basic_attack_ranged', 'heavy_strike', 'aimed_shot',
    'shield_up', 'bolster_ally', 'sleep_dart', 'wrestle', 'mud_sling', 'mud_slam',
    'bomb_toss', 'taunting_guard', 'disarming_shot',
  ];
  public const PASSIVE_HANDLERS = [
    'thick_hide', 'sharpshooter', 'shield_set', 'wall_of_scrap', 'unmoving', 'clean_shot', 'patient_aim', 'dumb_luck',
  ];
  public const TARGET_RULES = ['self', 'enemy_front_prefer', 'enemy_back_prefer', 'ally_lowest_hp_pct'];
  private const DAMAGING = ['basic_attack_melee', 'basic_attack_ranged', 'heavy_strike', 'aimed_shot', 'wrestle', 'mud_sling', 'mud_slam',
    'bomb_toss', 'disarming_shot'];
  private const MELEE = ['basic_attack_melee', 'heavy_strike', 'wrestle', 'mud_slam'];
  private const EFFECTS = [
    'flat_damage_bonus', 'flat_defense_bonus', 'percent_defense_bonus', 'percent_attack_bonus',
    'wounded_target_damage_bonus', 'explode_on_maximum',
  ];

  public static function isDamaging(string $handler): bool { return in_array($handler, self::DAMAGING, true); }
  public static function isMelee(string $handler): bool { return in_array($handler, self::MELEE, true); }
  public static function isRanged(string $handler): bool { return self::isDamaging($handler) && !self::isMelee($handler); }

  public static function validateTargetRule(string $handler, string $rule): void
  {
    $expected = match ($handler) {
      'shield_up' => 'self',
      'taunting_guard' => 'self',
      'bolster_ally' => 'ally_lowest_hp_pct',
      'basic_attack_melee', 'heavy_strike', 'wrestle', 'mud_slam' => 'enemy_front_prefer',
      'basic_attack_ranged', 'aimed_shot', 'sleep_dart', 'mud_sling', 'bomb_toss', 'disarming_shot' => 'enemy_back_prefer',
      default => throw new InvalidArgumentException('Unsupported active handler.'),
    };
    if ($rule !== $expected) throw new InvalidArgumentException('Active target rule is incompatible with its handler.');
  }

  /** @param array<string,mixed> $config */
  public static function validateAbilityConfig(string $handler, array $config): void
  {
    $required = match ($handler) {
      'basic_attack_melee', 'basic_attack_ranged', 'heavy_strike', 'aimed_shot' => ['power_ratio'],
      'shield_up', 'bolster_ally' => ['status_id', 'bolster_defense_pct', 'duration_rounds'],
      'sleep_dart' => ['status_id', 'duration_rounds'],
      'wrestle' => ['power_ratio', 'status_id', 'duration_rounds'],
      'mud_sling', 'mud_slam' => ['power_ratio', 'status_id', 'defense_reduction_flat', 'duration_rounds'],
      'bomb_toss' => ['power_ratio', 'status_id', 'bomb_damage_ratio', 'duration_rounds'],
      'taunting_guard' => ['status_id', 'guard_stack_cap', 'guard_reduction_per_stack', 'duration_rounds'],
      'disarming_shot' => ['power_ratio', 'status_id', 'attack_reduction_pct', 'duration_rounds'],
      default => throw new InvalidArgumentException('Unsupported ability handler.'),
    };
    if (self::isDamaging($handler) && array_key_exists('ignore_defense_flat', $config)) $required[] = 'ignore_defense_flat';
    CombatInput::keys($config, $required);
    if (isset($config['power_ratio'])) self::number($config['power_ratio'], 0.01, 10, 'power_ratio');
    if (isset($config['bolster_defense_pct'])) self::number($config['bolster_defense_pct'], 0, 1, 'bolster_defense_pct');
    if (isset($config['attack_reduction_pct'])) self::number($config['attack_reduction_pct'], 0, 1, 'attack_reduction_pct');
    if (isset($config['bomb_damage_ratio'])) self::number($config['bomb_damage_ratio'], 0.01, 10, 'bomb_damage_ratio');
    if (isset($config['guard_stack_cap'])) CombatInput::integer($config['guard_stack_cap'], 1, 100, 'guard_stack_cap');
    if (isset($config['guard_reduction_per_stack'])) CombatInput::integer($config['guard_reduction_per_stack'], 0, 1000000, 'guard_reduction_per_stack');
    if (isset($config['defense_reduction_flat'])) CombatInput::integer($config['defense_reduction_flat'], 0, 1000000, 'defense_reduction_flat');
    if (isset($config['ignore_defense_flat'])) CombatInput::integer($config['ignore_defense_flat'], 0, 1000000, 'ignore_defense_flat');
    if (isset($config['duration_rounds'])) CombatInput::integer($config['duration_rounds'], 1, 200, 'duration_rounds');
    if (isset($config['status_id'])) {
      $expected = match ($handler) {
        'shield_up', 'bolster_ally' => 'bolstered',
        'sleep_dart' => 'sleep',
        'wrestle' => 'wrestled',
        'mud_sling', 'mud_slam' => 'cracked_armor',
        'taunting_guard' => 'taunting_guard',
        'disarming_shot' => 'disarmed',
        'bomb_toss' => 'fuse_lit',
        default => '',
      };
      if ($config['status_id'] !== $expected) throw new InvalidArgumentException('Unsupported ability status.');
    }
  }

  /** @param array<string,mixed> $config */
  public static function validatePassive(string $handler, array $config): void
  {
    $required = match ($handler) {
      'thick_hide' => ['defense_flat'],
      'sharpshooter' => ['ranged_damage_pct'],
      'shield_set' => ['stack_cap', 'defense_flat_per_stack'],
      'wall_of_scrap' => ['stack_cap_bonus'],
      'unmoving' => ['taunt_damage_reduction_flat'],
      'clean_shot' => ['status_bonus_target', 'status_bonus_pct'],
      'patient_aim' => ['aimed_shot_bonus_pct'],
      'dumb_luck' => ['low_roll_threshold', 'bonus_flat'],
      default => throw new InvalidArgumentException('Unsupported passive handler.'),
    };
    CombatInput::keys($config, $required);
    if ($handler === 'thick_hide') CombatInput::integer($config['defense_flat'], 0, 1000000, 'defense_flat');
    elseif ($handler === 'shield_set') {
      CombatInput::integer($config['stack_cap'], 1, 100, 'stack_cap');
      CombatInput::integer($config['defense_flat_per_stack'], 0, 1000000, 'defense_flat_per_stack');
    }
    elseif ($handler === 'sharpshooter') self::number($config['ranged_damage_pct'], 0, 1, 'ranged_damage_pct');
    elseif ($handler === 'wall_of_scrap') CombatInput::integer($config['stack_cap_bonus'], 0, 100, 'stack_cap_bonus');
    elseif ($handler === 'unmoving') CombatInput::integer($config['taunt_damage_reduction_flat'], 0, 1000000, 'taunt_damage_reduction_flat');
    elseif ($handler === 'clean_shot') {
      if ($config['status_bonus_target'] !== 'disarmed') throw new InvalidArgumentException('Clean Shot requires disarmed status.');
      self::number($config['status_bonus_pct'], 0, 1, 'status_bonus_pct');
    } elseif ($handler === 'patient_aim') self::number($config['aimed_shot_bonus_pct'], 0, 1, 'aimed_shot_bonus_pct');
    else {
      CombatInput::integer($config['low_roll_threshold'], 1, 1000000, 'low_roll_threshold');
      CombatInput::integer($config['bonus_flat'], 0, 1000000, 'bonus_flat');
    }
  }

  /** @param array<string,mixed> $config */
  public static function validateDieEffect(mixed $effect, mixed $config): void
  {
    if (!is_string($effect) || !in_array($effect, self::EFFECTS, true) || !is_array($config) || array_is_list($config)) {
      throw new InvalidArgumentException('Unsupported die aspect effect.');
    }
    $required = match ($effect) {
      'flat_damage_bonus', 'flat_defense_bonus', 'percent_defense_bonus', 'percent_attack_bonus' => ['amount'],
      'wounded_target_damage_bonus' => ['health_threshold', 'damage_bonus'],
      'explode_on_maximum' => ['maximum_extra_rolls'],
    };
    CombatInput::keys($config, $required);
    if ($effect === 'flat_damage_bonus' || $effect === 'flat_defense_bonus') CombatInput::integer($config['amount'], 0, 1000000, 'aspect amount');
    if ($effect === 'percent_defense_bonus' || $effect === 'percent_attack_bonus') self::number($config['amount'], 0, 1, 'aspect amount');
    if ($effect === 'wounded_target_damage_bonus') {
      self::number($config['health_threshold'], 0, 1, 'health_threshold');
      self::number($config['damage_bonus'], 0, 1, 'damage_bonus');
    }
    if ($effect === 'explode_on_maximum' && $config['maximum_extra_rolls'] !== 1) throw new InvalidArgumentException('Only one explosive reroll is supported.');
  }

  /** @param array<string,mixed> $status */
  public static function validateStatus(array $status): void
  {
    CombatInput::keys($status, ['id', 'source_key', 'expires_round', 'params', 'forced_target_key']);
    if (!in_array($status['id'], ['bolstered', 'sleep', 'cracked_armor', 'wrestled', 'taunting_guard', 'disarmed', 'fuse_lit', 'shield_set', 'marked'], true)) throw new InvalidArgumentException('Unsupported status.');
    if (!is_string($status['source_key']) || $status['source_key'] === '') throw new InvalidArgumentException('Status source is required.');
    CombatInput::integer($status['expires_round'], 1, 401, 'expires_round');
    if (!is_array($status['params']) || ($status['params'] !== [] && array_is_list($status['params']))) throw new InvalidArgumentException('Status params must be an object.');
    $required = match ($status['id']) {
      'bolstered' => ['defense_pct'],
      'cracked_armor' => ['defense_reduction_flat'],
      'taunting_guard' => ['stack_count', 'per_stack_damage_reduction'],
      'disarmed' => ['attack_reduction_pct'],
      'fuse_lit' => ['bomb_damage'],
      'shield_set' => ['stacks', 'defense_flat_per_stack'],
      default => [],
    };
    CombatInput::keys($status['params'], $required);
    if ($status['id'] === 'bolstered') self::number($status['params']['defense_pct'], 0, 1, 'defense_pct');
    if ($status['id'] === 'cracked_armor') CombatInput::integer($status['params']['defense_reduction_flat'], 0, 1000000, 'defense_reduction_flat');
    if ($status['id'] === 'taunting_guard') {
      CombatInput::integer($status['params']['stack_count'], 1, 100, 'stack_count');
      CombatInput::integer($status['params']['per_stack_damage_reduction'], 0, 1000000, 'per_stack_damage_reduction');
    }
    if ($status['id'] === 'disarmed') self::number($status['params']['attack_reduction_pct'], 0, 1, 'attack_reduction_pct');
    if ($status['id'] === 'fuse_lit') CombatInput::integer($status['params']['bomb_damage'], 1, 1000000, 'bomb_damage');
    if ($status['id'] === 'shield_set') {
      CombatInput::integer($status['params']['stacks'], 1, 100, 'stacks');
      CombatInput::integer($status['params']['defense_flat_per_stack'], 0, 1000000, 'defense_flat_per_stack');
    }
    if ($status['id'] === 'wrestled' && (!is_string($status['forced_target_key']) || $status['forced_target_key'] === '')) throw new InvalidArgumentException('Wrestled needs a forced target.');
    if ($status['id'] !== 'wrestled' && $status['forced_target_key'] !== null) throw new InvalidArgumentException('Only Wrestled has a forced target.');
  }

  public static function number(mixed $value, float $min, float $max, string $name): void
  {
    if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < $min || $value > $max) {
      throw new InvalidArgumentException("{$name} must be a finite number from {$min} to {$max}.");
    }
  }
}
