<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit\Combat;

require_once __DIR__ . '/../../../src/Combat/Abilities/Handlers/HandlerInterface.php';
require_once __DIR__ . '/../../../src/Combat/Engine/placeholders.php';

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use DiceGoblins\Combat\Abilities\AbilityType;
use DiceGoblins\Combat\Abilities\Handlers\Active\AimedShot;
use DiceGoblins\Combat\Abilities\Handlers\Active\BasicAttackMelee;
use DiceGoblins\Combat\Abilities\Handlers\Active\BasicAttackRanged;
use DiceGoblins\Combat\Abilities\Handlers\Active\BolsterAlly;
use DiceGoblins\Combat\Abilities\Handlers\Active\HeavyStrike;
use DiceGoblins\Combat\Abilities\Handlers\Active\PoisonArrow;
use DiceGoblins\Combat\Abilities\Handlers\Active\PoisonStab;
use DiceGoblins\Combat\Abilities\Handlers\Active\ShieldUp;
use DiceGoblins\Combat\Abilities\Handlers\Active\SleepDart;
use DiceGoblins\Combat\Abilities\Handlers\HandlerRegistry;
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
      ],
      [
        new ThickHide(),
        new Sharpshooter(),
        new ToxicTraining(),
      ]
    );
  }
}
