<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Vnext;

final class CombatResult
{
  public const ENGINE_VERSION = 1;
  public const PLAYBACK_VERSION = 1;

  /** @param list<array<string,mixed>> $combatants @param list<array<string,mixed>> $events */
  public function __construct(
    public readonly string $outcome,
    public readonly int $endingRound,
    public readonly int $endingTick,
    public readonly array $combatants,
    public readonly array $events,
  ) {}

  /** @return array<string,mixed> */
  public function toArray(): array
  {
    return [
      'engine_version' => self::ENGINE_VERSION,
      'playback_version' => self::PLAYBACK_VERSION,
      'outcome' => $this->outcome,
      'ending_round' => $this->endingRound,
      'ending_tick' => $this->endingTick,
      'combatants' => $this->combatants,
      'events' => $this->events,
    ];
  }
}
