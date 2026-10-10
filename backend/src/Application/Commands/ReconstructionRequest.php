<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Support\ClientSafeInteger;

final class ReconstructionRequest
{
  private function __construct(
    public readonly string $recipeId,
    public readonly string $expectedMode,
    public readonly int $expectedAmount,
    public readonly array $expectedIngredients,
    public readonly ?string $unitTypeId,
  ) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    $mode = $request['expected_mode'] ?? null;
    $repeat = $mode === 'repeat_reconstruction';
    $expectedKeys = $repeat
      ? ['recipe_id', 'expected_mode', 'expected_price', 'expected_ingredients', 'unit_type_id']
      : ['recipe_id', 'expected_mode', 'expected_price', 'expected_ingredients'];
    $price = $request['expected_price'] ?? null;
    $ingredients = $request['expected_ingredients'] ?? null;
    if (!self::exact($request, $expectedKeys)
      || !is_string($request['recipe_id'] ?? null)
      || preg_match('/^reconstruction_recipe\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $request['recipe_id']) !== 1
      || !in_array($mode, ['first_restoration', 'repeat_reconstruction'], true)
      || !is_array($price) || array_is_list($price) || !self::exact($price, ['currency_id', 'amount'])
      || ($price['currency_id'] ?? null) !== 'raw_chaos'
      || !self::positive($price['amount'] ?? null)
      || !is_array($ingredients) || !array_is_list($ingredients) || $ingredients === []
      || ($repeat && (!is_string($request['unit_type_id'] ?? null)
        || preg_match('/^unit_type\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $request['unit_type_id']) !== 1))) {
      throw new ReconstructionException('invalid_reconstruction', 'Reconstruction request is invalid.', 422);
    }
    $normalized = []; $seen = [];
    foreach ($ingredients as $ingredient) {
      if (!is_array($ingredient) || array_is_list($ingredient) || !self::exact($ingredient, ['item_id', 'quantity'])
        || !is_string($ingredient['item_id'] ?? null)
        || preg_match('/^item\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $ingredient['item_id']) !== 1
        || !self::positive($ingredient['quantity'] ?? null) || isset($seen[$ingredient['item_id']])) {
        throw new ReconstructionException('invalid_reconstruction', 'Reconstruction request is invalid.', 422);
      }
      $seen[$ingredient['item_id']] = true;
      $normalized[] = ['item_id' => $ingredient['item_id'], 'quantity' => $ingredient['quantity']];
    }
    usort($normalized, static fn(array $a, array $b): int => strcmp($a['item_id'], $b['item_id']));
    return new self($request['recipe_id'], $mode, $price['amount'], $normalized, $repeat ? $request['unit_type_id'] : null);
  }

  /** @return array<string,mixed> */
  public function canonicalRequest(): array
  {
    $request = ['recipe_id' => $this->recipeId, 'expected_mode' => $this->expectedMode,
      'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $this->expectedAmount],
      'expected_ingredients' => $this->expectedIngredients];
    if ($this->unitTypeId !== null) $request['unit_type_id'] = $this->unitTypeId;
    return $request;
  }

  private static function positive(mixed $value): bool
  { return is_int($value) && $value > 0 && $value <= ClientSafeInteger::MAXIMUM; }
  /** @param array<string,mixed> $value @param list<string> $keys */
  private static function exact(array $value, array $keys): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys; }
}
