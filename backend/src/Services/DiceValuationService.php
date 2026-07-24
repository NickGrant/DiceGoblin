<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class DiceValuationService
{
  /** @var array<int,int> */
  private const BASE_VALUES_BY_SIDES = [
    4 => 12,
    6 => 18,
    8 => 28,
    10 => 34,
    12 => 42,
    20 => 60,
  ];

  /** @var array<string,float> */
  private const DIE_RARITY_BONUS = [
    'common' => 0.0,
    'uncommon' => 0.15,
    'rare' => 0.35,
    'epic' => 0.65,
    'legendary' => 0.9,
  ];

  /** @var array<string,float> */
  private const AFFIX_RARITY_PREMIUM = [
    'common' => 0.7,
    'uncommon' => 0.85,
    'rare' => 1.0,
    'epic' => 1.25,
    'legendary' => 1.5,
  ];

  public static function baseValueForSides(int $sides): int
  {
    if (isset(self::BASE_VALUES_BY_SIDES[$sides])) {
      return self::BASE_VALUES_BY_SIDES[$sides];
    }

    $largestKnownSides = 20;
    $largestKnownValue = self::BASE_VALUES_BY_SIDES[$largestKnownSides];
    $normalizedSides = max(2, $sides);

    return (int)max(1, round($largestKnownValue * ($normalizedSides / $largestKnownSides)));
  }

  /**
   * @param array<int,array{rarity?:string}> $affixes
   */
  public static function calculateValue(int $sides, string $diceRarity, array $affixes = []): int
  {
    $baseValue = self::baseValueForSides($sides);
    $multiplier = 1 + (self::DIE_RARITY_BONUS[self::normalizeRarity($diceRarity)] ?? 0.0);

    foreach ($affixes as $affix) {
      $multiplier += self::AFFIX_RARITY_PREMIUM[self::normalizeRarity((string)($affix['rarity'] ?? 'common'))] ?? 0.0;
    }

    return (int)max(1, round($baseValue * $multiplier));
  }

  /**
   * @param array<int,array{rarity?:string}> $affixes
   */
  public static function calculateSellValue(int $sides, string $diceRarity, array $affixes = []): int
  {
    return max(1, (int)floor(self::calculateValue($sides, $diceRarity, $affixes) / 2));
  }

  /**
   * @param array<int,array{rarity?:string}> $affixes
   */
  public static function calculateRawChaosSalvageValue(int $sides, string $diceRarity, array $affixes = []): int
  {
    $base = max(1, (int)floor(self::baseValueForSides($sides) / 6));
    $rarityBonus = match (self::normalizeRarity($diceRarity)) {
      'uncommon' => 1,
      'rare' => 3,
      'epic' => 6,
      'legendary' => 10,
      default => 0,
    };

    $affixBonus = 0;
    foreach ($affixes as $affix) {
      $affixBonus += match (self::normalizeRarity((string)($affix['rarity'] ?? 'common'))) {
        'uncommon' => 1,
        'rare' => 2,
        'epic' => 3,
        'legendary' => 5,
        default => 1,
      };
    }

    return max(1, $base + $rarityBonus + $affixBonus);
  }

  private static function normalizeRarity(string $rarity): string
  {
    return strtolower(trim($rarity));
  }
}
