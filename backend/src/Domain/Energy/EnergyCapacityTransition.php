<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Energy;

use DateTimeImmutable;

/** Materializes only Energy earned under the old cap before new capacity exists. */
final class EnergyCapacityTransition
{
  public function __construct(private readonly EnergyCalculator $energy = new EnergyCalculator()) {}

  /** @return array{current:int,anchor:DateTimeImmutable,view:EnergyView} */
  public function raise(
    int $persistedCurrent, DateTimeImmutable $persistedAnchor, int $oldMaximum,
    int $newMaximum, float $rate, DateTimeImmutable $now,
  ): array {
    if ($newMaximum <= $oldMaximum) throw new \InvalidArgumentException('Energy cap must increase.');
    $old = $this->energy->calculate($persistedCurrent, $persistedAnchor, $oldMaximum, $rate, $now);
    if ($old->current >= $oldMaximum) {
      $anchor = $now;
    } else {
      $elapsed = max(0, $now->getTimestamp() - $persistedAnchor->getTimestamp());
      $earnedTicks = (int)floor(($elapsed * $rate) / 3600.0);
      $earnedSeconds = (int)ceil(($earnedTicks * 3600.0) / $rate);
      $anchor = $persistedAnchor->modify("+{$earnedSeconds} seconds");
    }
    return ['current' => $old->current, 'anchor' => $anchor,
      'view' => $this->energy->calculate($old->current, $anchor, $newMaximum, $rate, $now)];
  }
}
