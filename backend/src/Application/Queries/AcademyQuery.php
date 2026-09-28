<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use RuntimeException;

final class AcademyQuery
{
  public function __construct(
    private readonly PlayerStateRepository $players,
    private readonly UserUnlockRepository $unlocks,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{raw_chaos:int,player_revision:int,upgrades:list<array<string,mixed>>} */
  public function execute(int $userId): array
  {
    $state = $this->players->getPlayerState($userId);
    if ($state === null || $state['raw_chaos'] < 0 || $state['raw_chaos'] > ClientSafeInteger::MAXIMUM
      || $state['player_revision'] < 0 || $state['player_revision'] > ClientSafeInteger::MAXIMUM) {
      throw new RuntimeException('Required Academy player state is unavailable.');
    }
    $owned = array_fill_keys($this->unlocks->listIdsForUser($userId), true);
    $upgrades = [];
    foreach ($this->content->definitionsOfType('academy_upgrade') as $id => $upgrade) {
      $isOwned = isset($owned[$upgrade['grant_unlock_id']]);
      $available = !$isOwned;
      foreach ($upgrade['prerequisite_unlock_ids'] as $prerequisite) {
        if (!isset($owned[$prerequisite])) $available = false;
      }
      $upgrades[] = [
        'upgrade_id' => $id,
        'price' => ['currency_id' => 'raw_chaos', 'amount' => $upgrade['price']['amount']],
        'owned' => $isOwned,
        'available' => $available,
      ];
    }
    return ['raw_chaos' => $state['raw_chaos'], 'player_revision' => $state['player_revision'], 'upgrades' => $upgrades];
  }
}
