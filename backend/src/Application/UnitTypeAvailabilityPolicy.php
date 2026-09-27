<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;

final class UnitTypeAvailabilityPolicy
{
  public function __construct(private readonly ContentRegistry $content) {}

  /** @param list<string> $ownedUnlockIds @return list<string> */
  public function availableUnitTypeIds(array $ownedUnlockIds): array
  {
    $available = [];
    foreach ($ownedUnlockIds as $unlockId) {
      try { $unlock = $this->content->unlock($unlockId); }
      catch (ContentValidationException) { continue; }
      if (($unlock['target_type'] ?? null) !== 'unit_type') continue;
      $unitTypeId = $unlock['target_id'] ?? null;
      if (!is_string($unitTypeId)) continue;
      try { $this->content->unitType($unitTypeId); }
      catch (ContentValidationException) { continue; }
      $available[$unitTypeId] = true;
    }
    $ids = array_keys($available); sort($ids, SORT_STRING); return $ids;
  }
}
