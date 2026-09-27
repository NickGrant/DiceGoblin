<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class ConsumableUseRequest
{
  private function __construct(public readonly string $itemId) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    $keys = array_keys($request); sort($keys, SORT_STRING);
    $itemId = $request['item_id'] ?? null;
    if ($keys !== ['item_id'] || !is_string($itemId)
      || !preg_match('/^item\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $itemId)) {
      throw new ConsumableUseException('invalid_consumable_request', 'Consumable request is invalid.', 422);
    }
    return new self($itemId);
  }

  /** @return array{item_id:string} */
  public function canonicalRequest(): array { return ['item_id' => $this->itemId]; }
}
