<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Energy;

use DateTimeImmutable;
use DateTimeZone;

final class EnergyView
{
  public function __construct(
    public readonly int $current,
    public readonly int $normalMaximum,
    public readonly int $regenerationPerHour,
    public readonly int $regenerationIntervalSeconds,
    public readonly DateTimeImmutable $persistedLastRegenerationAt,
    public readonly ?DateTimeImmutable $nextRegenerationAt,
    public readonly ?DateTimeImmutable $fullyRegeneratedAt,
  ) {}

  /** @return array{current:int,normal_max:int,regeneration_per_hour:int,regeneration_interval_seconds:int,last_regeneration_at:string,next_regeneration_at:?string,fully_regenerated_at:?string} */
  public function toArray(): array
  {
    return [
      'current' => $this->current,
      'normal_max' => $this->normalMaximum,
      'regeneration_per_hour' => $this->regenerationPerHour,
      'regeneration_interval_seconds' => $this->regenerationIntervalSeconds,
      'last_regeneration_at' => $this->toIsoUtc($this->persistedLastRegenerationAt),
      'next_regeneration_at' => $this->nextRegenerationAt !== null ? $this->toIsoUtc($this->nextRegenerationAt) : null,
      'fully_regenerated_at' => $this->fullyRegeneratedAt !== null ? $this->toIsoUtc($this->fullyRegeneratedAt) : null,
    ];
  }

  private function toIsoUtc(DateTimeImmutable $value): string
  {
    return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
  }
}
