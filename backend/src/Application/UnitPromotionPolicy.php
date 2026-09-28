<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Progression\UnitXpResolver;
use DiceGoblins\Support\ClientSafeInteger;
use Throwable;

/** Authored paths and persistent progression facts for one surviving unit. */
final class UnitPromotionPolicy
{
  public function __construct(private readonly ContentRegistry $content, private readonly UnitXpResolver $xp = new UnitXpResolver()) {}

  /** @return list<array<string,mixed>> */
  public function outgoing(string $unitTypeId): array
  {
    $this->content->unitType($unitTypeId);
    $options = [];
    foreach ($this->content->definitionsOfType('unit_promotion') as $promotion) {
      if ($promotion['from_unit_type_id'] === $unitTypeId) $options[] = $promotion;
    }
    usort($options, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
    return $options;
  }

  /** @return array<string,mixed> */
  public function exact(string $promotionId): array { return $this->content->unitPromotion($promotionId); }

  public function targetTier(string $promotionId): int
  {
    $promotion = $this->exact($promotionId);
    $from = $this->content->unitType($promotion['from_unit_type_id']);
    $to = $this->content->unitType($promotion['to_unit_type_id']);
    if ($to['tier'] !== $from['tier'] + 1) throw new WarbandIntegrityException('Authored promotion tier is invalid.');
    return $to['tier'];
  }

  public function levelMet(string $promotionId, int $level): bool
  { return $level >= $this->exact($promotionId)['required_level']; }

  /** @param list<string> $ownedAbilityIds @return list<string> */
  public function targetAbilityDelta(string $promotionId, array $ownedAbilityIds): array
  {
    $promotion = $this->exact($promotionId);
    $target = $this->content->unitType($promotion['to_unit_type_id']);
    if (count($ownedAbilityIds) !== count(array_unique($ownedAbilityIds))) throw new WarbandIntegrityException('Unit ability ownership is duplicated.');
    foreach ($ownedAbilityIds as $abilityId) {
      try { $this->content->ability($abilityId); }
      catch (Throwable $e) { throw new WarbandIntegrityException('Unit ability ownership references unavailable content.', 0, $e); }
    }
    $owned = array_fill_keys($ownedAbilityIds, true);
    return array_values(array_filter($target['ability_ids'], static fn(string $id): bool => !isset($owned[$id])));
  }

  public function xpThreshold(int $level, int $xp): int
  {
    try { $threshold = $this->xp->threshold($level); }
    catch (Throwable $e) { throw new WarbandIntegrityException('Unit progression is invalid.', 0, $e); }
    if ($threshold > ClientSafeInteger::MAXIMUM || $xp < 0 || $xp >= $threshold) throw new WarbandIntegrityException('Unit XP is not normalized.');
    return $threshold;
  }

  /** @param list<array<string,mixed>> $history */
  public function validateHistory(string $currentUnitTypeId, array $history): void
  {
    try { $current = $this->content->unitType($currentUnitTypeId); }
    catch (Throwable $e) { throw new WarbandIntegrityException('Unit type is unavailable.', 0, $e); }
    if ($history === []) {
      if ($current['tier'] !== 1) throw new WarbandIntegrityException('Promoted unit has no authored history.');
      return;
    }
    $priorType = null; $priorTime = null;
    foreach ($history as $row) {
      $from = $row['from_unit_type_id'] ?? null; $to = $row['to_unit_type_id'] ?? null;
      $time = $row['promoted_at'] ?? null;
      if (!is_string($from) || !is_string($to) || !is_string($time)
        || ($priorTime !== null && $time < $priorTime) || ($priorType !== null && $from !== $priorType)) {
        throw new WarbandIntegrityException('Unit promotion history is invalid.');
      }
      try { $fromType = $this->content->unitType($from); }
      catch (Throwable $e) { throw new WarbandIntegrityException('Unit promotion history references unavailable content.', 0, $e); }
      if ($priorType === null && $fromType['tier'] !== 1) {
        throw new WarbandIntegrityException('Unit promotion history has no base type.');
      }
      $valid = false;
      foreach ($this->outgoing($from) as $promotion) {
        if ($promotion['to_unit_type_id'] === $to) { $valid = true; break; }
      }
      if (!$valid) throw new WarbandIntegrityException('Unit promotion history has an unauthored path.');
      $priorType = $to; $priorTime = $time;
    }
    if ($priorType !== $currentUnitTypeId) throw new WarbandIntegrityException('Unit promotion history disagrees with current type.');
  }
}
