<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Domain\Energy\EnergyCalculator;
use PHPUnit\Framework\TestCase;

final class EnergyCalculatorTest extends TestCase
{
  private EnergyCalculator $calculator;

  protected function setUp(): void
  {
    $this->calculator = new EnergyCalculator();
  }

  public function testNoCompleteIntervalLeavesEnergyUnchanged(): void
  {
    $view = $this->calculate(20, '2026-09-10 12:00:00', '2026-09-10 12:04:59');

    $this->assertSame(20, $view->current);
    $this->assertSame('2026-09-10T12:05:00Z', $view->toArray()['next_regeneration_at']);
  }

  public function testElapsedIntervalsRegeneratePartiallyAndPreserveRemainder(): void
  {
    $view = $this->calculate(20, '2026-09-10 12:00:00', '2026-09-10 12:17:00');

    $this->assertSame(23, $view->current);
    $this->assertSame('2026-09-10T12:20:00Z', $view->toArray()['next_regeneration_at']);
    $this->assertSame('2026-09-10T14:30:00Z', $view->toArray()['fully_regenerated_at']);
  }

  public function testNaturalRegenerationCapsAtNormalMaximum(): void
  {
    $view = $this->calculate(48, '2026-09-10 12:00:00', '2026-09-10 13:00:00');

    $this->assertSame(50, $view->current);
    $this->assertNull($view->nextRegenerationAt);
    $this->assertNull($view->fullyRegeneratedAt);
  }

  public function testExistingOvercapEnergyIsPreserved(): void
  {
    $view = $this->calculate(57, '2026-09-10 12:00:00', '2026-09-10 12:00:00');

    $this->assertSame(57, $view->current);
    $this->assertSame(50, $view->normalMaximum);
  }

  public function testOvercapEnergyDoesNotRegenerateFurther(): void
  {
    $view = $this->calculate(57, '2026-09-01 12:00:00', '2026-09-10 12:00:00');

    $this->assertSame(57, $view->current);
    $this->assertNull($view->nextRegenerationAt);
  }

  private function calculate(int $current, string $last, string $now): \DiceGoblins\Domain\Energy\EnergyView
  {
    $utc = new DateTimeZone('UTC');
    return $this->calculator->calculate(
      $current,
      new DateTimeImmutable($last, $utc),
      50,
      12,
      new DateTimeImmutable($now, $utc),
    );
  }
}
