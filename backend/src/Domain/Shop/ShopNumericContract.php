<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Shop;

final class ShopNumericContract
{
  public const MAX_CLIENT_SAFE_INTEGER = 9_007_199_254_740_991;

  private function __construct() {}
}
