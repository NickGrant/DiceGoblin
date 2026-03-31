<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\DiceValuationService;
use PHPUnit\Framework\TestCase;

final class DiceValuationServiceTest extends TestCase
{
  public function testCommonDiceBaseValuesMatchShopPricing(): void
  {
    $this->assertSame(12, DiceValuationService::calculateValue(4, 'common'));
    $this->assertSame(18, DiceValuationService::calculateValue(6, 'common'));
    $this->assertSame(28, DiceValuationService::calculateValue(8, 'common'));
    $this->assertSame(34, DiceValuationService::calculateValue(10, 'common'));
  }

  public function testDailyDealShapePricesToTwoTimesBaseForUncommonDieWithUncommonAffix(): void
  {
    $value = DiceValuationService::calculateValue(6, 'uncommon', [
      ['rarity' => 'uncommon'],
    ]);

    $this->assertSame(36, $value);
  }

  public function testSellValueReturnsHalfRoundedDown(): void
  {
    $sellValue = DiceValuationService::calculateSellValue(8, 'rare', [
      ['rarity' => 'common'],
      ['rarity' => 'rare'],
    ]);

    $this->assertSame(
      (int)floor(DiceValuationService::calculateValue(8, 'rare', [
        ['rarity' => 'common'],
        ['rarity' => 'rare'],
      ]) / 2),
      $sellValue
    );
  }
}
