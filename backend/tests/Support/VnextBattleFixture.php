<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Combat\Vnext\CombatEngine;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Domain\Battles\FinalizedBattle;

final class VnextBattleFixture
{
  /** @return array<string,mixed> */
  public static function input(): array
  {
    return ['seed' => 'persistence-fixture-1', 'combatants' => [
      self::combatant('player_bruiser', 'player', 1, 1, 20, 100, 2),
      self::combatant('mudwrestler', 'enemy', 2, 1, 1, 0, 0),
    ]];
  }

  /** @return list<array<string,mixed>> */
  public static function manifest(int $unitId = 42): array
  {
    return [
      ['combatant_key' => 'player_bruiser', 'side' => 'player', 'unit_id' => $unitId,
        'unit_type_id' => 'unit_type.bruiser', 'enemy_unit_type_id' => null,
        'display_name' => 'Bash', 'art_key' => 'unit.bruiser'],
      ['combatant_key' => 'mudwrestler', 'side' => 'enemy', 'unit_id' => null,
        'unit_type_id' => null, 'enemy_unit_type_id' => 'enemy_unit_type.mudwrestler',
        'display_name' => 'Mudwrestler', 'art_key' => 'enemy.mudwrestler'],
    ];
  }

  /** @return array<string,mixed> */
  public static function result(?array $input = null): array
  {
    return (new CombatEngine())->resolve(new CombatInput($input ?? self::input()))->toArray();
  }

  public static function battle(int $unitId = 42): FinalizedBattle
  {
    $input = self::input();
    return new FinalizedBattle(1, 1, $input, self::manifest($unitId), self::result($input));
  }

  /** @return array<string,mixed> */
  private static function combatant(string $key, string $side, int $x, int $y, int $hp, int $attack, int $defense): array
  {
    return [
      'key' => $key, 'side' => $side, 'position' => ['x' => $x, 'y' => $y],
      'stats' => ['hp' => $hp, 'attack' => $attack, 'defense' => $defense, 'precision' => 5, 'resolve' => 5],
      'max_hp' => $hp, 'current_hp' => $hp,
      'active_abilities' => [[
        'id' => 'ability.basic_attack_melee', 'handler_id' => 'basic_attack_melee',
        'target_rule' => 'enemy_front_prefer', 'action_delay' => $side === 'player' ? 1 : 4,
        'resolution_priority' => 10, 'dice_slot_count' => 1, 'config' => ['power_ratio' => 1.0],
        'dice' => [['key' => $key . '_d6', 'sides' => 6, 'profile_id' => 'dice_profile.cardboard_plain', 'effects' => []]],
      ]],
      'passive_abilities' => [], 'statuses' => [],
    ];
  }
}
