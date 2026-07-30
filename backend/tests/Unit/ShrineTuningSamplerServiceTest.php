<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\ShrineTuningSamplerService;
use PHPUnit\Framework\TestCase;

final class ShrineTuningSamplerServiceTest extends TestCase
{
  public function testSamplesShrineDistributionDeterministically(): void
  {
    $sampler = new ShrineTuningSamplerService();

    $first = $sampler->sample(['mountains'], 25, true, 'qa-shrine-sample');
    $second = $sampler->sample(['mountains'], 25, true, 'qa-shrine-sample');

    $this->assertSame($first, $second);
    $this->assertSame(25, $first['samples_per_quality']);
    $this->assertTrue($first['allow_declineable']);
    $this->assertArrayHasKey('mountains', $first['regions']);
    $this->assertArrayHasKey('poor', $first['regions']['mountains']);
    $this->assertArrayHasKey('good', $first['regions']['mountains']);
    $this->assertArrayHasKey('great', $first['regions']['mountains']);
    $this->assertArrayHasKey('primitive_percentages', $first['regions']['mountains']['good']);
    $this->assertGreaterThan(0, array_sum($first['regions']['mountains']['good']['primitive_percentages']));
  }

  public function testDeclineableShrinesAreExcludedUnlessRequested(): void
  {
    $sampler = new ShrineTuningSamplerService();

    $withoutDeclineable = $sampler->sample(['mountains'], 50, false, 'qa-shrine-sample');
    $withDeclineable = $sampler->sample(['mountains'], 50, true, 'qa-shrine-sample');

    $this->assertSame(
      0,
      (int)($withoutDeclineable['regions']['mountains']['good']['declineable_count'] ?? -1)
    );
    $this->assertGreaterThan(
      0,
      (int)($withDeclineable['regions']['mountains']['good']['declineable_count'] ?? 0)
    );
  }
}
