<?php
declare(strict_types=1);

namespace DiceGoblins\Support;

final class RunPatternGenerationTrace
{
  /** @var list<array<string,mixed>> */
  private array $events = [];

  /** @var array<string,int> */
  private array $counters = [
    'placements' => 0,
    'candidate_rejections' => 0,
    'backtracks' => 0,
    'validation_failures' => 0,
  ];

  public function __construct(
    private readonly int $maxEvents = 200,
    private readonly int $startedAtMs = 0
  ) {
  }

  /**
   * @param array<string,mixed> $context
   */
  public function placement(string $phase, string $patternKey, array $context = []): void
  {
    $this->counters['placements']++;
    $this->record('placement', [
      'phase' => $phase,
      'pattern_key' => $patternKey,
      'context' => $context,
    ]);
  }

  /**
   * @param array<string,mixed> $context
   */
  public function candidateRejected(string $phase, string $patternKey, string $reason, array $context = []): void
  {
    $this->counters['candidate_rejections']++;
    $this->record('candidate_rejected', [
      'phase' => $phase,
      'pattern_key' => $patternKey,
      'reason' => $reason,
      'context' => $context,
    ]);
  }

  /**
   * @param array<string,mixed> $context
   */
  public function backtrack(string $reason, array $context = []): void
  {
    $this->counters['backtracks']++;
    $this->record('backtrack', [
      'reason' => $reason,
      'context' => $context,
    ]);
  }

  /**
   * @param list<string> $errors
   */
  public function validationFailure(array $errors): void
  {
    $this->counters['validation_failures']++;
    $this->record('validation_failure', ['errors' => $errors]);
  }

  /**
   * @return array<string,mixed>
   */
  public function summary(int $endedAtMs = 0): array
  {
    $duration = $this->startedAtMs > 0 && $endedAtMs >= $this->startedAtMs
      ? $endedAtMs - $this->startedAtMs
      : null;

    return [
      'counters' => $this->counters,
      'event_count' => count($this->events),
      'truncated' => $this->maxEvents >= 0 && count($this->events) >= $this->maxEvents,
      'duration_ms' => $duration,
      'events' => $this->events,
    ];
  }

  /**
   * @param array<string,mixed> $payload
   */
  private function record(string $type, array $payload): void
  {
    if ($this->maxEvents >= 0 && count($this->events) >= $this->maxEvents) {
      return;
    }

    $this->events[] = [
      'type' => $type,
      ...$payload,
    ];
  }
}
