<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Support\ClientSafeInteger;

final class AcademyUpgradeRequest
{
  private function __construct(public readonly string $upgradeId, public readonly int $expectedAmount) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    if (!self::exact($request, ['upgrade_id', 'expected_price'])
      || !is_string($request['upgrade_id'] ?? null)
      || preg_match('/^academy_upgrade\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $request['upgrade_id']) !== 1
      || !is_array($request['expected_price'] ?? null) || array_is_list($request['expected_price'])
      || !self::exact($request['expected_price'], ['currency_id', 'amount'])
      || ($request['expected_price']['currency_id'] ?? null) !== 'raw_chaos'
      || !is_int($request['expected_price']['amount'] ?? null)
      || $request['expected_price']['amount'] < 1 || $request['expected_price']['amount'] > ClientSafeInteger::MAXIMUM) {
      throw new AcademyUpgradeException('invalid_academy_upgrade', 'Academy upgrade request is invalid.', 422);
    }
    return new self($request['upgrade_id'], $request['expected_price']['amount']);
  }

  /** @return array<string,mixed> */
  public function canonicalRequest(): array
  {
    return ['upgrade_id' => $this->upgradeId, 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $this->expectedAmount]];
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private static function exact(array $value, array $keys): bool
  {
    $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys;
  }
}
