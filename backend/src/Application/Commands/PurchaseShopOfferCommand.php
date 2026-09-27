<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Application\NormalUnitCreationService;
use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Support\ClientSafeInteger;
use JsonException;
use PDO;
use Throwable;

final class PurchaseShopOfferCommand
{
  private const OPERATION = 'purchase_shop_offer';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly UserItemRepository $items,
    private readonly WarbandDiceRepository $dice,
    private readonly UserUnlockRepository $unlocks,
    private readonly UnitTypeAvailabilityPolicy $unitTypes,
    private readonly NormalUnitCreationService $unitCreation,
    private readonly ContentRegistry $content,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    $purchase = ShopPurchaseRequest::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    try {
      $hash = hash('sha256', json_encode($purchase->canonicalRequest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    } catch (JsonException $e) {
      throw new ShopPurchaseException('invalid_shop_purchase', 'Shop purchase request is invalid.', 422);
    }

    try {
      $this->pdo->beginTransaction();
      $state = $this->playerState->getPlayerStateForUpdate($userId);
      if ($state === null) throw new ShopPurchaseIntegrityException('Required player state is unavailable.');

      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        if ($prior['operation_type'] !== self::OPERATION || !hash_equals($prior['request_hash'], $hash)) {
          throw new IdempotencyConflictException('Idempotency key was already used for another request.');
        }
        $this->validatePersistedResult($prior['result'], $purchase);
        $this->pdo->commit();
        return $prior['result'];
      }

      $balance = $state['teeth'];
      $revision = $state['player_revision'];
      if ($balance < 0 || $balance > ClientSafeInteger::MAXIMUM || $revision < 0
        || $revision >= ClientSafeInteger::MAXIMUM) {
        throw new ShopPurchaseIntegrityException('Shop wallet or revision is incoherent.');
      }

      try {
        $offer = $this->content->shopOffer($purchase->offerId);
      } catch (ContentValidationException) {
        throw new ShopPurchaseException('shop_offer_not_found', 'Shop offer is unavailable.', 404);
      }
      $price = $offer['price']['amount'] ?? null;
      if (($offer['price']['currency_id'] ?? null) !== 'teeth' || !is_int($price)
        || $price < 1 || $price > ClientSafeInteger::MAXIMUM) {
        throw new ShopPurchaseIntegrityException('Shop price is incoherent.');
      }
      if (($offer['grant']['type'] ?? null) === 'unit') {
        $available = $this->unitTypes->availableUnitTypeIds($this->unlocks->listIdsForUser($userId, true));
        if (!in_array($offer['grant']['unit_type_id'] ?? null, $available, true)) {
          throw new ShopPurchaseException('shop_offer_unavailable', 'Shop offer is unavailable.', 403);
        }
      }
      if ($purchase->expectedAmount !== $price) {
        throw new ShopPurchaseException('shop_offer_changed', 'Shop offer changed; refresh before purchasing.', 409);
      }
      if ($balance < $price) {
        throw new ShopPurchaseException('insufficient_teeth', 'Not enough Teeth.', 409);
      }
      $after = $balance - $price;
      $this->playerState->applyCurrencyDebitTransition($userId, 'teeth', $balance, $after);
      $output = $this->grant($userId, $offer['grant'] ?? null);
      $nextRevision = $this->playerState->incrementRevision($userId);
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM) {
        throw new ShopPurchaseIntegrityException('Player revision transition is incoherent.');
      }
      $result = [
        'offer_id' => $purchase->offerId,
        'spend' => ['currency_id' => 'teeth', 'amount' => $price, 'balance_before' => $balance, 'balance_after' => $after],
        'player_revision' => $nextRevision,
        'output' => $output,
      ];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null || $stored['operation_type'] !== self::OPERATION || !hash_equals($stored['request_hash'], $hash)) {
        throw new ShopPurchaseIntegrityException('Finalized Shop receipt is unavailable.');
      }
      $this->validatePersistedResult($stored['result'], $purchase);
      $result = $stored['result'];
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $result;
    } catch (ShopPurchaseException|ShopPurchaseIntegrityException|IdempotencyConflictException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new ShopPurchaseIntegrityException('Shop purchase data is incoherent.', 0, $e);
    }
  }

  /** @return array<string,mixed> */
  private function grant(int $userId, mixed $grant): array
  {
    if (!is_array($grant) || array_is_list($grant)) throw new ShopPurchaseIntegrityException('Shop grant is incoherent.');
    if (($grant['type'] ?? null) === 'item') {
      $itemId = $grant['item_id'] ?? null; $quantity = $grant['quantity'] ?? null;
      if (!is_string($itemId) || !is_int($quantity) || $quantity < 1 || $quantity > ClientSafeInteger::MAXIMUM) {
        throw new ShopPurchaseIntegrityException('Shop item grant is incoherent.');
      }
      $item = $this->content->item($itemId);
      if (($item['stackable'] ?? null) !== true) throw new ShopPurchaseIntegrityException('Shop item is not stackable.');
      return ['type' => 'item', 'item_id' => $itemId, 'quantity_granted' => $quantity,
        'owned_quantity_after' => $this->items->increment($userId, $itemId, $quantity)];
    }
    if (($grant['type'] ?? null) === 'die') {
      $profileId = $grant['dice_profile_id'] ?? null; $size = $grant['size'] ?? null;
      if (!is_string($profileId) || !is_int($size) || !in_array($size, [4, 6, 8], true)) {
        throw new ShopPurchaseIntegrityException('Shop die grant is incoherent.');
      }
      $profile = $this->content->diceProfile($profileId);
      if (!in_array($size, $profile['allowed_sizes'] ?? [], true)) {
        throw new ShopPurchaseIntegrityException('Shop die profile is incompatible.');
      }
      return ['type' => 'die', 'die' => $this->dice->createActive($userId, $size, $profileId)];
    }
    if (($grant['type'] ?? null) === 'unit') {
      $unitTypeId = $grant['unit_type_id'] ?? null; $kinId = $grant['kin_id'] ?? null;
      if (!is_string($unitTypeId) || $kinId !== 'kin.goblin') {
        throw new ShopPurchaseIntegrityException('Shop unit grant is incoherent.');
      }
      $unitType = $this->content->unitType($unitTypeId);
      if (($unitType['tier'] ?? null) !== 1) throw new ShopPurchaseIntegrityException('Shop unit tier is incoherent.');
      return ['type' => 'unit', 'unit' => $this->unitCreation->create($userId, $unitTypeId, $kinId)];
    }
    throw new ShopPurchaseIntegrityException('Shop grant type is incoherent.');
  }

  /** @param array<string,mixed> $result */
  private function validatePersistedResult(array $result, ShopPurchaseRequest $purchase): void
  {
    if (!$this->exact($result, ['offer_id', 'spend', 'player_revision', 'output'])
      || ($result['offer_id'] ?? null) !== $purchase->offerId
      || !is_array($result['spend'] ?? null) || array_is_list($result['spend'])
      || !$this->exact($result['spend'], ['currency_id', 'amount', 'balance_before', 'balance_after'])
      || ($result['spend']['currency_id'] ?? null) !== 'teeth'
      || ($result['spend']['amount'] ?? null) !== $purchase->expectedAmount
      || !$this->safeNonNegative($result['spend']['balance_before'] ?? null)
      || !$this->safeNonNegative($result['spend']['balance_after'] ?? null)
      || $result['spend']['balance_before'] - $result['spend']['amount'] !== $result['spend']['balance_after']
      || !$this->safeNonNegative($result['player_revision'] ?? null)
      || !is_array($result['output'] ?? null) || array_is_list($result['output'])) {
      throw new ShopPurchaseIntegrityException('Persisted Shop receipt is invalid.');
    }
    $output = $result['output'];
    $itemValid = ($output['type'] ?? null) === 'item'
      && $this->exact($output, ['type', 'item_id', 'quantity_granted', 'owned_quantity_after'])
      && is_string($output['item_id'] ?? null) && $this->safePositive($output['quantity_granted'] ?? null)
      && $this->safeNonNegative($output['owned_quantity_after'] ?? null)
      && $output['owned_quantity_after'] >= $output['quantity_granted'];
    $die = $output['die'] ?? null;
    $dieValid = ($output['type'] ?? null) === 'die' && $this->exact($output, ['type', 'die'])
      && is_array($die) && !array_is_list($die) && $this->exact($die, ['id', 'size', 'profile_id', 'lifecycle_status'])
      && is_string($die['id'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $die['id'])
      && in_array($die['size'] ?? null, [4, 6, 8], true) && is_string($die['profile_id'] ?? null)
      && ($die['lifecycle_status'] ?? null) === 'active';
    $unit = $output['unit'] ?? null;
    $unitValid = ($output['type'] ?? null) === 'unit' && $this->exact($output, ['type', 'unit'])
      && is_array($unit) && !array_is_list($unit)
      && $this->exact($unit, ['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status'])
      && is_string($unit['id'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $unit['id'])
      && is_string($unit['display_name'] ?? null) && trim($unit['display_name']) !== '' && strlen(trim($unit['display_name'])) <= 128
      && is_string($unit['unit_type_id'] ?? null) && ($unit['kin_id'] ?? null) === 'kin.goblin'
      && ($unit['level'] ?? null) === 1 && ($unit['xp'] ?? null) === 0 && ($unit['lifecycle_status'] ?? null) === 'active';
    if (!$itemValid && !$dieValid && !$unitValid) throw new ShopPurchaseIntegrityException('Persisted Shop receipt output is invalid.');
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private function exact(array $value, array $keys): bool
  {
    $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys;
  }
  private function safeNonNegative(mixed $value): bool
  {
    return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM;
  }
  private function safePositive(mixed $value): bool
  {
    return $this->safeNonNegative($value) && $value > 0;
  }
}
