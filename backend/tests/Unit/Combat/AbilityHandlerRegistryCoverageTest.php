<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Unit\Combat\AbilityHandlerRegistryCoverageTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Unit\Combat;

require_once __DIR__ . '/../../../src/Combat/Abilities/Handlers/HandlerInterface.php';
require_once __DIR__ . '/../../../src/Combat/Engine/placeholders.php';

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use DiceGoblins\Combat\Abilities\AbilityType;
use DiceGoblins\Combat\Abilities\Handlers\Active\AimedShot;
use DiceGoblins\Combat\Abilities\Handlers\Active\BasicAttackMelee;
use DiceGoblins\Combat\Abilities\Handlers\Active\BasicAttackRanged;
use DiceGoblins\Combat\Abilities\Handlers\Active\BolsterAlly;
use DiceGoblins\Combat\Abilities\Handlers\Active\ConfigurableAbility;
use DiceGoblins\Combat\Abilities\Handlers\Active\HeavyStrike;
use DiceGoblins\Combat\Abilities\Handlers\Active\PoisonArrow;
use DiceGoblins\Combat\Abilities\Handlers\Active\PoisonStab;
use DiceGoblins\Combat\Abilities\Handlers\Active\ShieldUp;
use DiceGoblins\Combat\Abilities\Handlers\Active\SleepDart;
use DiceGoblins\Combat\Abilities\Handlers\HandlerRegistry;
use DiceGoblins\Combat\Abilities\Handlers\Passive\ConfigurablePassive;
use DiceGoblins\Combat\Abilities\Handlers\Passive\Sharpshooter;
use DiceGoblins\Combat\Abilities\Handlers\Passive\ThickHide;
use DiceGoblins\Combat\Abilities\Handlers\Passive\ToxicTraining;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AbilityHandlerRegistryCoverageTest extends TestCase
{
  public function testHandlerRegistryCoversAllDefinedAbilityIds(): void
  {
    $registry = $this->buildRegistry();
    $defs = (new AbilityRegistry())->all();

    $expectedActive = [];
    $expectedPassive = [];
    foreach ($defs as $def) {
      if ($def->type === AbilityType::Active) {
        $expectedActive[] = $def->abilityId;
      } else {
        $expectedPassive[] = $def->abilityId;
      }
    }

    $registry->assertCoverage($expectedActive, $expectedPassive);

    // Spot checks keep canonical IDs pinned to expected handler type.
    $this->assertTrue($registry->hasActive('basic_attack_melee'));
    $this->assertTrue($registry->hasActive('sleep_dart'));
    $this->assertTrue($registry->hasPassive('thick_hide'));
    $this->assertTrue($registry->hasPassive('toxic_training'));
  }

  public function testRegistryRejectsDuplicateAbilityIdsAcrossBuckets(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Duplicate handler id 'basic_attack_melee'");

    new HandlerRegistry(
      [new BasicAttackMelee()],
      [new class implements \DiceGoblins\Combat\Abilities\Handlers\PassiveAbilityHandlerInterface {
        public function id(): string { return 'basic_attack_melee'; }
        public function apply(
          \DiceGoblins\Combat\Engine\DerivedStats $stats,
          \DiceGoblins\Combat\Engine\UnitRef $unit,
          array $cfg
        ): \DiceGoblins\Combat\Engine\DerivedStats {
          return $stats;
        }
      }]
    );
  }

  public function testAllActiveAbilitiesConsumeAtLeastOneDie(): void
  {
    $defs = (new AbilityRegistry())->all();

    foreach ($defs as $def) {
      if ($def->type !== AbilityType::Active) {
        continue;
      }

      $this->assertGreaterThanOrEqual(
        1,
        $def->diceCost,
        sprintf('Expected active ability %s to consume at least one die.', $def->abilityId)
      );
    }
  }

  private function buildRegistry(): HandlerRegistry
  {
    return new HandlerRegistry(
      [
        new BasicAttackMelee(),
        new BasicAttackRanged(),
        new HeavyStrike(),
        new AimedShot(),
        new ShieldUp(),
        new BolsterAlly(),
        new PoisonStab(),
        new PoisonArrow(),
        new SleepDart(),
        new ConfigurableAbility('skullcrack', 'melee'),
        new ConfigurableAbility('desperate_swing', 'melee'),
        new ConfigurableAbility('piercing_shot', 'ranged'),
        new ConfigurableAbility('mark_target', 'ranged'),
        new ConfigurableAbility('taunting_guard', 'support'),
        new ConfigurableAbility('crack_armor', 'melee'),
        new ConfigurableAbility('warcry', 'support'),
        new ConfigurableAbility('lucky_chant', 'support'),
        new ConfigurableAbility('disarming_shot', 'ranged'),
        new ConfigurableAbility('poison_cloud', 'ranged'),
      ],
      [
        new ThickHide(),
        new Sharpshooter(),
        new ToxicTraining(),
        new ConfigurablePassive('brawl_hardened'),
        new ConfigurablePassive('finisher'),
        new ConfigurablePassive('menacing_follow_through'),
        new ConfigurablePassive('no_mercy'),
        new ConfigurablePassive('brutal_suppression'),
        new ConfigurablePassive('counterpunch'),
        new ConfigurablePassive('last_goblin_standing'),
        new ConfigurablePassive('crowd_favorite'),
        new ConfigurablePassive('patient_aim'),
        new ConfigurablePassive('pick_your_mark'),
        new ConfigurablePassive('vantage_point'),
        new ConfigurablePassive('kill_lane'),
        new ConfigurablePassive('armor_gap'),
        new ConfigurablePassive('treasure_sense'),
        new ConfigurablePassive('exposed_weaknesses'),
        new ConfigurablePassive('barbed_mark'),
        new ConfigurablePassive('bodyguard'),
        new ConfigurablePassive('hold_the_line'),
        new ConfigurablePassive('shield_set'),
        new ConfigurablePassive('unmoving'),
        new ConfigurablePassive('wall_of_scrap'),
        new ConfigurablePassive('find_the_gap'),
        new ConfigurablePassive('shatter_plate'),
        new ConfigurablePassive('break_open'),
        new ConfigurablePassive('rally_rhythm'),
        new ConfigurablePassive('patch_job'),
        new ConfigurablePassive('battle_tempo'),
        new ConfigurablePassive('chant_of_violence'),
        new ConfigurablePassive('mob_mentality'),
        new ConfigurablePassive('attention_hog'),
        new ConfigurablePassive('dumb_luck'),
        new ConfigurablePassive('morale_goblin'),
        new ConfigurablePassive('toxic_tools'),
        new ConfigurablePassive('spiteful_reflex'),
        new ConfigurablePassive('opportunist'),
        new ConfigurablePassive('disabling_hit'),
        new ConfigurablePassive('clean_shot'),
        new ConfigurablePassive('nerve_toxin'),
        new ConfigurablePassive('lingering_cloud'),
        new ConfigurablePassive('sickly_weakness'),
      ]
    );
  }
}
