<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\UserItemRepository;

final class ItemCollectionQuery
{
  public function __construct(
    private readonly UserItemRepository $items,
    private readonly ContentRegistry $content,
  ) {}

  /** @return list<array{item_id:string,quantity:int}> */
  public function execute(int $userId): array
  {
    $result = [];
    foreach ($this->items->listPositiveForUser($userId) as $stack) {
      try {
        $definition = $this->content->item($stack['item_id']);
      } catch (ContentValidationException $error) {
        throw new InventoryIntegrityException('Persisted item identity is unknown.', 0, $error);
      }
      if (($definition['stackable'] ?? null) !== true || $stack['quantity'] <= 0) {
        throw new InventoryIntegrityException('Persisted item stack is invalid.');
      }
      $result[] = $stack;
    }
    return $result;
  }
}
