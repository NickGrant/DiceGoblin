<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class UnitProgressionService
{
  /**
   * @return array<string,mixed>
   */
  public function decodeBaseStats(mixed $baseStatsRaw): array
  {
    if (is_array($baseStatsRaw)) {
      return $baseStatsRaw;
    }

    if (is_string($baseStatsRaw)) {
      $decoded = json_decode($baseStatsRaw, true);
      return is_array($decoded) ? $decoded : [];
    }

    return [];
  }

  public function totalAttackForLevel(mixed $baseStatsRaw, int $level, int $attackPerLevel): int
  {
    $baseStats = $this->decodeBaseStats($baseStatsRaw);
    $levelScale = max(0, $level - 1);
    $baseAttack = max(1, (int)($baseStats['attack'] ?? 1));

    return max(1, $baseAttack + (max(0, $attackPerLevel) * $levelScale));
  }

  public function totalDefenseForLevel(mixed $baseStatsRaw, int $level, int $defensePerLevel): int
  {
    $baseStats = $this->decodeBaseStats($baseStatsRaw);
    $levelScale = max(0, $level - 1);
    $baseDefense = max(0, (int)($baseStats['defense'] ?? 0));

    return max(0, $baseDefense + (max(0, $defensePerLevel) * $levelScale));
  }

  public function maxHpForLevel(mixed $baseStatsRaw, int $level, int $maxHpPerLevel): int
  {
    $baseStats = $this->decodeBaseStats($baseStatsRaw);
    $levelScale = max(0, $level - 1);
    $baseMaxHp = max(1, (int)($baseStats['max_hp'] ?? 1));

    return max(1, $baseMaxHp + (max(0, $maxHpPerLevel) * $levelScale));
  }

  public function precision(mixed $baseStatsRaw): int
  {
    $baseStats = $this->decodeBaseStats($baseStatsRaw);
    return max(0, (int)($baseStats['precision'] ?? 5));
  }

  public function resolve(mixed $baseStatsRaw): int
  {
    $baseStats = $this->decodeBaseStats($baseStatsRaw);
    return max(0, (int)($baseStats['resolve'] ?? 5));
  }

  public function totalPrecisionForLevel(mixed $baseStatsRaw, int $level, int $precisionPerLevel): int
  {
    $levelScale = max(0, $level - 1);
    return max(0, $this->precision($baseStatsRaw) + (max(0, $precisionPerLevel) * $levelScale));
  }

  public function totalResolveForLevel(mixed $baseStatsRaw, int $level, int $resolvePerLevel): int
  {
    $levelScale = max(0, $level - 1);
    return max(0, $this->resolve($baseStatsRaw) + (max(0, $resolvePerLevel) * $levelScale));
  }

  public function xpToNextLevel(int $tier, int $level, int $maxLevel, int $xp): int
  {
    if ($level >= $maxLevel) {
      return 0;
    }

    return max(0, ($tier * ($level + 1) * 50) - max(0, $xp));
  }

  /**
   * @return array{level:int,xp:int}
   */
  public function resolveAutoLevel(int $tier, int $level, int $xp, int $maxLevel): array
  {
    $resolvedLevel = max(1, $level);
    $resolvedXp = max(0, $xp);
    $resolvedMaxLevel = max(1, $maxLevel);
    $resolvedTier = max(1, $tier);

    while ($resolvedLevel < $resolvedMaxLevel) {
      $xpToNext = $resolvedTier * ($resolvedLevel + 1) * 50;
      if ($resolvedXp < $xpToNext) {
        break;
      }

      $resolvedXp -= $xpToNext;
      $resolvedLevel++;
    }

    return [
      'level' => $resolvedLevel,
      'xp' => $resolvedXp,
    ];
  }
}
