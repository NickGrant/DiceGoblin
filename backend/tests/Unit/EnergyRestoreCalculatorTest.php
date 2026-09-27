<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DateTimeImmutable;
use DiceGoblins\Domain\Energy\EnergyRestoreCalculator;
use DiceGoblins\Domain\Energy\EnergyRestoreUnavailableException;
use DiceGoblins\Support\ClientSafeInteger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnergyRestoreCalculatorTest extends TestCase
{
  public function testMaterializesWholeTicksAndPreservesFractionalProgressBelowCap(): void
  {
    $result = (new EnergyRestoreCalculator())->restore(
      5, new DateTimeImmutable('2026-09-26T12:00:00Z'), 50, 12, 4,
      new DateTimeImmutable('2026-09-26T12:12:30Z'),
    );
    $this->assertSame(11, $result->persistedCurrent);
    $this->assertSame('2026-09-26T12:10:00Z', $result->view->toArray()['last_regeneration_at']);
    $this->assertSame('2026-09-26T12:15:00Z', $result->view->toArray()['next_regeneration_at']);
  }

  public function testMayOverchargeAndResetsAnchorAtCap(): void
  {
    $now = new DateTimeImmutable('2026-09-26T13:00:00Z');
    $result = (new EnergyRestoreCalculator())->restore(48, new DateTimeImmutable('2026-09-26T12:59:00Z'), 50, 12, 5, $now);
    $this->assertSame(53, $result->persistedCurrent);
    $this->assertSame('2026-09-26T13:00:00Z', $result->view->toArray()['last_regeneration_at']);
    $this->assertNull($result->view->nextRegenerationAt);
  }

  /** @dataProvider unavailableProvider */
  public function testAtOrAboveEffectiveMaximumIsUnavailable(int $current, string $anchor): void
  {
    $this->expectException(EnergyRestoreUnavailableException::class);
    (new EnergyRestoreCalculator())->restore($current, new DateTimeImmutable($anchor), 50, 12, 5,
      new DateTimeImmutable('2026-09-26T13:00:00Z'));
  }

  public function unavailableProvider(): array
  {
    return [[50, '2026-09-26T13:00:00Z'], [55, '2026-09-26T13:00:00Z'], [49, '2026-09-26T12:55:00Z']];
  }

  public function testClientSafeOverflowRejects(): void
  {
    $this->expectException(InvalidArgumentException::class);
    (new EnergyRestoreCalculator())->restore(ClientSafeInteger::MAXIMUM - 1,
      new DateTimeImmutable('2026-09-26T13:00:00Z'), ClientSafeInteger::MAXIMUM, 12, 5,
      new DateTimeImmutable('2026-09-26T13:00:00Z'));
  }
}
