<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

use InvalidArgumentException;

final class CombatRules
{
  public const ACTIVE_HANDLERS = [
    'basic_attack_melee', 'basic_attack_ranged', 'heavy_strike', 'aimed_shot',
    'shield_up', 'bolster_ally', 'sleep_dart', 'wrestle', 'mud_sling',
  ];
  public const TARGET_RULES = ['self', 'enemy_front_prefer', 'enemy_back_prefer', 'ally_lowest_hp_pct'];
  private const DAMAGING = ['basic_attack_melee', 'basic_attack_ranged', 'heavy_strike', 'aimed_shot', 'wrestle', 'mud_sling'];
  private const MELEE = ['basic_attack_melee', 'heavy_strike', 'wrestle'];
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
      'bolster_ally' => 'ally_lowest_hp_pct',
      'basic_attack_melee', 'heavy_strike', 'wrestle' => 'enemy_front_prefer',
      'basic_attack_ranged', 'aimed_shot', 'sleep_dart', 'mud_sling' => 'enemy_back_prefer',
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
      'mud_sling' => ['power_ratio', 'status_id', 'defense_reduction_flat', 'duration_rounds'],
      default => throw new InvalidArgumentException('Unsupported ability handler.'),
    };
    if (self::isDamaging($handler) && array_key_exists('ignore_defense_flat', $config)) $required[] = 'ignore_defense_flat';
    CombatInput::keys($config, $required);
    if (isset($config['power_ratio'])) self::number($config['power_ratio'], 0.01, 10, 'power_ratio');
    if (isset($config['bolster_defense_pct'])) self::number($config['bolster_defense_pct'], 0, 1, 'bolster_defense_pct');
    if (isset($config['defense_reduction_flat'])) CombatInput::integer($config['defense_reduction_flat'], 0, 1000000, 'defense_reduction_flat');
    if (isset($config['ignore_defense_flat'])) CombatInput::integer($config['ignore_defense_flat'], 0, 1000000, 'ignore_defense_flat');
    if (isset($config['duration_rounds'])) CombatInput::integer($config['duration_rounds'], 1, 200, 'duration_rounds');
    if (isset($config['status_id'])) {
      $expected = match ($handler) {
        'shield_up', 'bolster_ally' => 'bolstered',
        'sleep_dart' => 'sleep',
        'wrestle' => 'wrestled',
        'mud_sling' => 'cracked_armor',
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
      default => throw new InvalidArgumentException('Unsupported passive handler.'),
    };
    CombatInput::keys($config, $required);
    if ($handler === 'thick_hide') CombatInput::integer($config['defense_flat'], 0, 1000000, 'defense_flat');
    else self::number($config['ranged_damage_pct'], 0, 1, 'ranged_damage_pct');
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
    if (!in_array($status['id'], ['bolstered', 'sleep', 'cracked_armor', 'wrestled'], true)) throw new InvalidArgumentException('Unsupported status.');
    if (!is_string($status['source_key']) || $status['source_key'] === '') throw new InvalidArgumentException('Status source is required.');
    CombatInput::integer($status['expires_round'], 1, 401, 'expires_round');
    if (!is_array($status['params']) || ($status['params'] !== [] && array_is_list($status['params']))) throw new InvalidArgumentException('Status params must be an object.');
    $required = match ($status['id']) {
      'bolstered' => ['defense_pct'],
      'cracked_armor' => ['defense_reduction_flat'],
      default => [],
    };
    CombatInput::keys($status['params'], $required);
    if ($status['id'] === 'bolstered') self::number($status['params']['defense_pct'], 0, 1, 'defense_pct');
    if ($status['id'] === 'cracked_armor') CombatInput::integer($status['params']['defense_reduction_flat'], 0, 1000000, 'defense_reduction_flat');
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
