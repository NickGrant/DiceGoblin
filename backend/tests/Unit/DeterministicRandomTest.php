<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Support\DeterministicRandom;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeterministicRandomTest extends TestCase
{
  public function testSameSeedProducesSameSequence(): void
  {
    $first = new DeterministicRandom('mountains:run-17');
    $second = new DeterministicRandom('mountains:run-17');

    $this->assertSame(
      [$first->nextInt(1, 10), $first->nextInt(1, 10), $first->nextInt(1, 10)],
      [$second->nextInt(1, 10), $second->nextInt(1, 10), $second->nextInt(1, 10)]
    );
  }

  public function testForksAreStableAndIndependent(): void
  {
    $rng = new DeterministicRandom('swamps:run-4');

    $this->assertSame(
      (new DeterministicRandom('swamps:run-4:branches'))->nextInt(1, 1000),
      $rng->fork('branches')->nextInt(1, 1000)
    );
    $this->assertNotSame($rng->fork('spine')->nextInt(1, 1000), $rng->fork('branches')->nextInt(1, 1000));
  }

  public function testWeightedChoiceIgnoresZeroWeights(): void
  {
    $rng = new DeterministicRandom('weights');

    $choice = $rng->weightedChoice(
      [
        ['slug' => 'never', 'weight' => 0],
        ['slug' => 'always', 'weight' => 10],
      ],
      static fn(array $item): int => (int)$item['weight']
    );

    $this->assertSame('always', $choice['slug']);
  }

  public function testRejectsInvalidBounds(): void
  {
    $this->expectException(InvalidArgumentException::class);

    (new DeterministicRandom('bad'))->nextInt(5, 4);
  }
}
