<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;

/** Resolves permanent entitlements solely from authored capability unlocks. */
final class PermanentCapabilityPolicy
{
  private const STANDARD_DIE_SIZES = [4, 6, 8, 10, 12, 20];

  public function __construct(private readonly ContentRegistry $content) {}

  /** @param list<string> $ownedUnlockIds */
  public function energyNormalMaximum(array $ownedUnlockIds): int
  {
    return max($this->content->energyNormalMaximum(), $this->maximumValue('energy_normal_max', $ownedUnlockIds));
  }

  /** @param list<string> $ownedUnlockIds */
  public function maxAcquirableDieSize(array $ownedUnlockIds): int
  {
    return max(8, $this->maximumValue('max_acquirable_die_size', $ownedUnlockIds));
  }

  /** @param list<string> $ownedUnlockIds @param array<string,mixed> $profile */
  public function canAcquireDie(int $size, array $profile, array $ownedUnlockIds): bool
  {
    return in_array($size, self::STANDARD_DIE_SIZES, true)
      && $size <= $this->maxAcquirableDieSize($ownedUnlockIds)
      && in_array($size, $profile['allowed_sizes'] ?? [], true);
  }

  /** @param list<string> $ownedUnlockIds */
  private function maximumValue(string $kind, array $ownedUnlockIds): int
  {
    $owned = array_fill_keys($ownedUnlockIds, true);
    $maximum = 0;
    foreach ($this->content->definitionsOfType('unlock') as $id => $unlock) {
      if (!isset($owned[$id]) || $unlock['target_type'] !== 'capability') continue;
      $capability = $this->content->definition($unlock['target_id']);
      if ($capability['kind'] === $kind) $maximum = max($maximum, $capability['value']);
    }
    return $maximum;
  }
}
