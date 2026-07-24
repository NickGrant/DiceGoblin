<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\UnitProgressionService;
use PHPUnit\Framework\TestCase;

final class UnitProgressionServiceTest extends TestCase
{
  public function testPrecisionAndResolveDefaultToNeutralValues(): void
  {
    $service = new UnitProgressionService();

    $this->assertSame(5, $service->precision(['attack' => 3]));
    $this->assertSame(5, $service->resolve(['defense' => 2]));
  }

  public function testPrecisionAndResolveReadAuthoredStats(): void
  {
    $service = new UnitProgressionService();
    $stats = ['precision' => 7, 'resolve' => 4];

    $this->assertSame(7, $service->precision($stats));
    $this->assertSame(4, $service->resolve($stats));
  }
}
