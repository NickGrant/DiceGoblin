<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Rewards;

use DiceGoblins\Repositories\UserItemRepository;

final class VictoryItemGrantService
{
  public function __construct(
    private readonly VictoryItemDropPolicy $drops,
    private readonly UserItemRepository $items,
  ) {}

  /** Caller owns the node-resolution transaction.
   *  @return list<array{item_id:string,quantity:int,owned_after:int}>
   */
  public function grant(int $userId, string $encounterId): array
  {
    $granted = [];
    foreach ($this->drops->grants($encounterId) as $drop) {
      $granted[] = [
        'item_id' => $drop['item_id'],
        'quantity' => $drop['quantity'],
        'owned_after' => $this->items->increment($userId, $drop['item_id'], $drop['quantity']),
      ];
    }
    return $granted;
  }
}
