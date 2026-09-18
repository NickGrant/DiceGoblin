<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Domain\Progression\UnitXpResolver;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

final class UnitXpResolverTest extends TestCase
{
  /** @dataProvider examples */
  public function testCanonicalExamples(int $level, int $xp, int $amount, int $afterLevel, int $afterXp): void
  {
    $this->assertSame(['level' => $afterLevel, 'xp' => $afterXp], (new UnitXpResolver())->apply($level, $xp, $amount));
  }

  public function examples(): array
  {
    return [
      'below threshold' => [1, 0, 99, 1, 99],
      'exact threshold' => [1, 0, 100, 2, 0],
      'level two boundary' => [2, 150, 50, 3, 0],
      'multiple levels with remainder' => [1, 90, 250, 3, 40],
    ];
  }

  public function testResolverHasNoTierInputAndCarriesAcrossManyLevels(): void
  {
    $result = (new UnitXpResolver())->apply(3, 25, 1000);
    $this->assertSame(['level' => 5, 'xp' => 325], $result);
    $this->assertSame(['level', 'xp'], array_keys($result));
  }

  public function testRejectsUnnormalizedAndOverflowingInputs(): void
  {
    $resolver = new UnitXpResolver();
    try {
      $resolver->apply(1, 100, 1);
      $this->fail('Expected unnormalized XP to fail.');
    } catch (InvalidArgumentException) {}

    $this->expectException(OverflowException::class);
    $resolver->apply(1, 1, PHP_INT_MAX);
  }
}
