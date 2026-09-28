<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Application\PermanentCapabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;

final class ShopCatalogQuery
{
  public function __construct(
    private readonly PlayerStateRepository $players,
    private readonly UserUnlockRepository $unlocks,
    private readonly UnitTypeAvailabilityPolicy $unitTypes,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{teeth:int,player_revision:int,offers:list<array<string,mixed>>} */
  public function execute(int $userId): array
  {
    $state = $this->players->getPlayerState($userId);
    if ($state === null
      || $state['teeth'] < 0
      || $state['teeth'] > ClientSafeInteger::MAXIMUM
      || $state['player_revision'] < 0
      || $state['player_revision'] > ClientSafeInteger::MAXIMUM) {
      throw new ShopIntegrityException('Required Shop player state is unavailable.');
    }
    $unlockIds = $this->unlocks->listIdsForUser($userId);
    $availableUnitTypes = array_flip($this->unitTypes->availableUnitTypeIds($unlockIds));
    $capabilities = new PermanentCapabilityPolicy($this->content);
    $offers = [];
    foreach ($this->content->definitionsOfType('shop_offer') as $id => $definition) {
      $amount = (int)$definition['price']['amount'];
      if ($amount < 1 || $amount > ClientSafeInteger::MAXIMUM) {
        throw new ShopIntegrityException('Authored Shop price is outside the client-safe range.');
      }
      $offers[] = [
        'offer_id' => $id,
        'price' => ['currency_id' => 'teeth', 'amount' => $amount],
        'available' => match ($definition['grant']['type']) {
          'unit' => isset($availableUnitTypes[(string)$definition['grant']['unit_type_id']]),
          'die' => $capabilities->canAcquireDie($definition['grant']['size'],
            $this->content->diceProfile($definition['grant']['dice_profile_id']), $unlockIds),
          default => true,
        },
        'can_afford' => $state['teeth'] >= $amount,
      ];
    }
    return ['teeth' => $state['teeth'], 'player_revision' => $state['player_revision'], 'offers' => $offers];
  }
}
