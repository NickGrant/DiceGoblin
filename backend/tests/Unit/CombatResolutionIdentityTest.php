<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Domain\Battles\CombatFormationPosition;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CombatResolutionIdentityTest extends TestCase
{
  public function testAllSquadPositionsMapRowMajorWithFrontAtXTwo(): void
  {
    $this->assertSame([
      ['x' => 0, 'y' => 0], ['x' => 1, 'y' => 0], ['x' => 2, 'y' => 0],
      ['x' => 0, 'y' => 1], ['x' => 1, 'y' => 1], ['x' => 2, 'y' => 1],
      ['x' => 0, 'y' => 2], ['x' => 1, 'y' => 2], ['x' => 2, 'y' => 2],
    ], array_map(static fn(int $position): array => CombatFormationPosition::fromSquadPosition($position), range(0, 8)));
  }

  public function testFormationRejectsOutOfRangePositions(): void
  {
    $this->expectException(InvalidArgumentException::class);
    CombatFormationPosition::fromSquadPosition(9);
  }

  public function testVersionedSeedIsStableAndUsesDurableIdentity(): void
  {
    $deriver = new CombatSeedDeriver();
    $first = $deriver->derive(10, 20, 'encounter.the_farm_mud_combat_1');
    $this->assertSame($first, $deriver->derive(10, 20, 'encounter.the_farm_mud_combat_1'));
    $this->assertNotSame($first, $deriver->derive(11, 20, 'encounter.the_farm_mud_combat_1'));
    $this->assertNotSame($first, $deriver->derive(10, 21, 'encounter.the_farm_mud_combat_1'));
    $this->assertStringStartsWith('combat-seed-v1:', $first);
  }
}
