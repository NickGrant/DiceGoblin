<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Support\RunPatternGenerationTrace;
use PHPUnit\Framework\TestCase;

final class RunPatternGenerationTraceTest extends TestCase
{
  public function testRecordsBoundedGenerationEventsAndCounters(): void
  {
    $trace = new RunPatternGenerationTrace(maxEvents: 2, startedAtMs: 100);

    $trace->placement('start', 'shared_start_single@1', ['x' => 0, 'y' => 0]);
    $trace->candidateRejected('spine', 'shared_hazard_rest@1', 'depth_out_of_range');
    $trace->backtrack('terminal_missing');
    $trace->validationFailure(['exit_bypasses_boss']);

    $summary = $trace->summary(135);

    $this->assertSame(1, $summary['counters']['placements']);
    $this->assertSame(1, $summary['counters']['candidate_rejections']);
    $this->assertSame(1, $summary['counters']['backtracks']);
    $this->assertSame(1, $summary['counters']['validation_failures']);
    $this->assertSame(2, $summary['event_count']);
    $this->assertTrue($summary['truncated']);
    $this->assertSame(35, $summary['duration_ms']);
    $this->assertSame(['placement', 'candidate_rejected'], array_column($summary['events'], 'type'));
  }
}
