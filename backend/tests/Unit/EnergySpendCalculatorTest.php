<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Domain\Energy\EnergySpendCalculator;
use DiceGoblins\Domain\Energy\InsufficientEnergyException;
use PHPUnit\Framework\TestCase;

final class EnergySpendCalculatorTest extends TestCase
{
  private EnergySpendCalculator $calculator;
  private DateTimeZone $utc;

  protected function setUp(): void
  {
    $this->calculator = new EnergySpendCalculator();
    $this->utc = new DateTimeZone('UTC');
  }

  public function testBelowCapSpendMaterializesWholeTicksAndPreservesFractionalProgress(): void
  {
    $result = $this->spend(40, '2026-09-13 12:00:00', '2026-09-13 12:27:00', 10);

    $this->assertSame(35, $result->persistedCurrent);
    $this->assertSame('2026-09-13T12:25:00Z', $result->view->toArray()['last_regeneration_at']);
    $this->assertSame('2026-09-13T12:30:00Z', $result->view->toArray()['next_regeneration_at']);
  }

  public function testRegenerationThatReachedCapRestartsAnchorAtSpendTime(): void
  {
    $result = $this->spend(48, '2026-09-13 12:00:00', '2026-09-13 13:00:00', 10);

    $this->assertSame(40, $result->persistedCurrent);
    $this->assertSame('2026-09-13T13:00:00Z', $result->view->toArray()['last_regeneration_at']);
    $this->assertSame('2026-09-13T13:05:00Z', $result->view->toArray()['next_regeneration_at']);
  }

  public function testFullAndOverCapBalancesCannotBankCappedElapsedTime(): void
  {
    $full = $this->spend(50, '2026-09-01 12:00:00', '2026-09-13 13:00:00', 10);
    $overCapBelow = $this->spend(57, '2026-09-01 12:00:00', '2026-09-13 13:00:00', 10);
    $overCapStill = $this->spend(57, '2026-09-01 12:00:00', '2026-09-13 13:00:00', 5);

    $this->assertSame(40, $full->persistedCurrent);
    $this->assertSame(47, $overCapBelow->persistedCurrent);
    $this->assertSame(52, $overCapStill->persistedCurrent);
    $this->assertSame('2026-09-13T13:00:00Z', $full->view->toArray()['last_regeneration_at']);
    $this->assertSame('2026-09-13T13:00:00Z', $overCapBelow->view->toArray()['last_regeneration_at']);
    $this->assertSame('2026-09-13T13:00:00Z', $overCapStill->view->toArray()['last_regeneration_at']);
    $this->assertSame('2026-09-13T13:05:00Z', $overCapBelow->view->toArray()['next_regeneration_at']);
    $this->assertNull($overCapStill->view->nextRegenerationAt);
  }

  public function testInsufficientEffectiveEnergyIsRejected(): void
  {
    $this->expectException(InsufficientEnergyException::class);
    $this->spend(8, '2026-09-13 12:00:00', '2026-09-13 12:09:59', 10);
  }

  private function spend(int $current, string $anchor, string $now, int $cost): \DiceGoblins\Domain\Energy\EnergySpendResult
  {
    return $this->calculator->spend(
      $current,
      new DateTimeImmutable($anchor, $this->utc),
      50,
      12.0,
      $cost,
      new DateTimeImmutable($now, $this->utc),
    );
  }
}
