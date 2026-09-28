<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Support\ClientSafeInteger;

final class UnitPromotionRequest
{
  private function __construct(
    public readonly int $unitId,
    public readonly string $promotionId,
    public readonly int $expectedAmount,
  ) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(int $unitId, array $request): self
  {
    $price = $request['expected_price'] ?? null;
    if ($unitId <= 0 || !self::exact($request, ['promotion_id', 'expected_price'])
      || !is_string($request['promotion_id'] ?? null)
      || preg_match('/^unit_promotion\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $request['promotion_id']) !== 1
      || !is_array($price) || array_is_list($price) || !self::exact($price, ['currency_id', 'amount'])
      || ($price['currency_id'] ?? null) !== 'raw_chaos'
      || !is_int($price['amount'] ?? null) || $price['amount'] < 1
      || $price['amount'] > ClientSafeInteger::MAXIMUM) {
      throw new UnitPromotionException('invalid_unit_promotion', 'Unit promotion request is invalid.', 422);
    }
    return new self($unitId, $request['promotion_id'], $price['amount']);
  }

  /** @return array<string,mixed> */
  public function canonicalRequest(): array
  {
    return ['unit_id' => (string)$this->unitId, 'promotion_id' => $this->promotionId,
      'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $this->expectedAmount]];
  }

  /** @param array<string,mixed> $value @param list<string> $fields */
  private static function exact(array $value, array $fields): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($fields, SORT_STRING); return $actual === $fields; }
}
