<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use RuntimeException;

final class WrongMachineQuery
{
  public function __construct(
    private readonly PlayerStateRepository $players,
    private readonly UserUnlockRepository $unlocks,
    private readonly UserItemRepository $items,
    private readonly UnitTypeAvailabilityPolicy $unitTypes,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{raw_chaos:int,player_revision:int,recipes:list<array<string,mixed>>} */
  public function execute(int $userId): array
  {
    $state = $this->players->getPlayerState($userId);
    if ($state === null || $state['raw_chaos'] < 0 || $state['raw_chaos'] > ClientSafeInteger::MAXIMUM
      || $state['player_revision'] < 0 || $state['player_revision'] > ClientSafeInteger::MAXIMUM)
      throw new RuntimeException('Required Wrong Machine player state is unavailable.');

    $ownedUnlocks = $this->unlocks->listIdsForUser($userId);
    $owned = array_fill_keys($ownedUnlocks, true);
    $quantities = array_column($this->items->listPositiveForUser($userId), 'quantity', 'item_id');
    $eligibleUnitTypes = $this->unitTypes->availableUnitTypeIds($ownedUnlocks);
    $recipes = [];
    foreach ($this->content->definitionsOfType('reconstruction_recipe') as $id => $recipe) {
      $restored = isset($owned[$recipe['kin_unlock_id']]);
      $mode = $restored ? 'repeat_reconstruction' : 'first_restoration';
      $requirements = $recipe[$mode];
      $prerequisites = array_map(static fn(string $unlockId): array => [
        'unlock_id' => $unlockId, 'owned' => isset($owned[$unlockId]),
      ], $recipe['prerequisite_unlock_ids']);
      $ingredients = array_map(static fn(array $ingredient): array => [
        'item_id' => $ingredient['item_id'], 'quantity' => $ingredient['quantity'],
        'owned' => $quantities[$ingredient['item_id']] ?? 0,
      ], $requirements['ingredients']);
      $prerequisitesMet = !in_array(false, array_column($prerequisites, 'owned'), true);
      $ingredientsMet = !array_filter($ingredients, static fn(array $ingredient): bool => $ingredient['owned'] < $ingredient['quantity']);
      $recipes[] = [
        'recipe_id' => $id,
        'display_name' => $recipe['display_name'],
        'description' => $recipe['description'],
        'kin_id' => $recipe['kin_id'],
        'kin_restored' => $restored,
        'mode' => $mode,
        'unit_type_selection' => $requirements['unit_type_selection'],
        'eligible_unit_type_ids' => $eligibleUnitTypes,
        'prerequisites' => $prerequisites,
        'prerequisites_met' => $prerequisitesMet,
        'price' => $requirements['price'],
        'ingredients' => $ingredients,
        'reconstructable' => $prerequisitesMet && $eligibleUnitTypes !== []
          && $state['raw_chaos'] >= $requirements['price']['amount'] && $ingredientsMet,
      ];
    }
    return ['raw_chaos' => $state['raw_chaos'], 'player_revision' => $state['player_revision'], 'recipes' => $recipes];
  }
}
