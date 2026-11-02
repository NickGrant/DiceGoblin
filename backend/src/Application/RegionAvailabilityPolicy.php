<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;

final class RegionAvailabilityPolicy
{
  public function __construct(private readonly ContentRegistry $content) {}

  public function isPlayable(string $regionId): bool
  {
    try {
      $this->content->runGenerationForRegion($regionId);
      return true;
    } catch (ContentValidationException) {
      return false;
    }
  }

  /** @param list<string> $ownedUnlockIds @return list<string> */
  public function availableRegionIds(array $ownedUnlockIds): array
  {
    $available = [];
    foreach ($ownedUnlockIds as $unlockId) {
      try {
        $unlock = $this->content->unlock($unlockId);
      } catch (ContentValidationException) {
        continue;
      }
      if (($unlock['target_type'] ?? null) !== 'region') continue;
      $regionId = (string)($unlock['target_id'] ?? '');
      if ($this->isPlayable($regionId)) $available[$regionId] = true;
    }

    $remaining = array_keys($available);
    sort($remaining, SORT_STRING);
    $startingRegionId = $this->content->startingRegionId();
    if (!isset($available[$startingRegionId])) return $remaining;
    return [$startingRegionId, ...array_values(array_filter($remaining,
      static fn(string $id): bool => $id !== $startingRegionId))];
  }
}
