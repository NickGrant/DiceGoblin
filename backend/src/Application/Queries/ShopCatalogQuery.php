<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;

final class ShopCatalogQuery
{
  public function __construct(
    private readonly PlayerStateRepository $players,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{teeth:int,player_revision:int,offers:list<array<string,mixed>>} */
  public function execute(int $userId): array
  {
    $state = $this->players->getPlayerState($userId);
    if ($state === null || $state['teeth'] < 0 || $state['player_revision'] < 0) {
      throw new ShopIntegrityException('Required Shop player state is unavailable.');
    }
    $offers = [];
    foreach ($this->content->definitionsOfType('shop_offer') as $id => $definition) {
      $amount = (int)$definition['price']['amount'];
      $offers[] = [
        'offer_id' => $id,
        'price' => ['currency_id' => 'teeth', 'amount' => $amount],
        'available' => true,
        'can_afford' => $state['teeth'] >= $amount,
      ];
    }
    return ['teeth' => $state['teeth'], 'player_revision' => $state['player_revision'], 'offers' => $offers];
  }
}
