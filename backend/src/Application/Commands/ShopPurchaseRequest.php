<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Support\ClientSafeInteger;

final class ShopPurchaseRequest
{
  private function __construct(
    public readonly string $offerId,
    public readonly int $expectedAmount,
  ) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    if (!self::hasExactKeys($request, ['offer_id', 'expected_price'])
      || !is_string($request['offer_id'] ?? null)
      || !preg_match('/^shop_offer\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $request['offer_id'])
      || !is_array($request['expected_price'] ?? null)
      || array_is_list($request['expected_price'])
      || !self::hasExactKeys($request['expected_price'], ['currency_id', 'amount'])
      || ($request['expected_price']['currency_id'] ?? null) !== 'teeth'
      || !is_int($request['expected_price']['amount'] ?? null)
      || $request['expected_price']['amount'] < 1
      || $request['expected_price']['amount'] > ClientSafeInteger::MAXIMUM) {
      throw new ShopPurchaseException('invalid_shop_purchase', 'Shop purchase request is invalid.', 422);
    }
    return new self($request['offer_id'], $request['expected_price']['amount']);
  }

  /** @return array{offer_id:string,expected_price:array{currency_id:string,amount:int}} */
  public function canonicalRequest(): array
  {
    return ['offer_id' => $this->offerId, 'expected_price' => [
      'currency_id' => 'teeth', 'amount' => $this->expectedAmount,
    ]];
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private static function hasExactKeys(array $value, array $keys): bool
  {
    $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys;
  }
}
