<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunCombatModifierService;
use PHPUnit\Framework\TestCase;

final class RunCombatModifierServiceTest extends TestCase
{
  public function testAppliesGenericRunStatModifiersToCombatUnit(): void
  {
    $unit = [
      'attack' => 10,
      'defense' => 5,
      'precision' => 4,
      'resolve' => 6,
      'combat_affixes' => ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      'run_status_effects' => [[
        'type' => 'stat_modifier_next_combat',
        'source' => 'shrine',
        'remaining_combats' => 1,
        'stat_multipliers' => [
          'attack' => 1.2,
          'defense' => 1.4,
          'damage' => 1.1,
        ],
        'stat_adders' => [
          'precision' => 2,
          'resolve' => 3,
        ],
      ]],
    ];

    $modified = (new RunCombatModifierService())->applyModifiersToUnit($unit);

    $this->assertSame(12, $modified['attack']);
    $this->assertSame(7, $modified['defense']);
    $this->assertSame(6, $modified['precision']);
    $this->assertSame(9, $modified['resolve']);
    $this->assertSame(1.1, $modified['combat_affixes']['run_damage_multiplier']);
    $this->assertSame('stat_modifier_next_combat', $modified['run_combat_modifiers'][0]['type'] ?? '');
  }

  public function testMapsLegacyShrineDamageModifierIntoGenericDamageMultiplier(): void
  {
    $unit = [
      'attack' => 10,
      'defense' => 5,
      'precision' => 4,
      'resolve' => 6,
      'combat_affixes' => ['damage_flat' => 0, 'below_half_bonus' => 0.0],
      'run_status_effects' => [[
        'type' => 'squad_damage_next_combat',
        'source' => 'shrine',
        'remaining_combats' => 1,
        'damage_multiplier' => 1.1,
      ]],
    ];

    $modified = (new RunCombatModifierService())->applyModifiersToUnit($unit);

    $this->assertSame(10, $modified['attack']);
    $this->assertSame(1.1, $modified['combat_affixes']['run_damage_multiplier']);
    $this->assertSame('squad_damage_next_combat', $modified['run_combat_modifiers'][0]['type'] ?? '');
    $this->assertSame(1.1, $modified['run_combat_modifiers'][0]['stat_multipliers']['damage'] ?? 0.0);
  }
}
