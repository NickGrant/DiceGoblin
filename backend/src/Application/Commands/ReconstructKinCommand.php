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
use DiceGoblins\Support\ClientSafeInteger;
use PDO;
use Throwable;

/** One reconstruction intention, one transaction owner, and one finalized receipt. */
final class ReconstructKinCommand
{
  private const OPERATION = 'reconstruct_kin';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly UserItemRepository $items,
    private readonly UserUnlockRepository $unlocks,
    private readonly UnitTypeAvailabilityPolicy $unitTypes,
    private readonly NormalUnitCreationService $unitCreation,
    private readonly ContentRegistry $content,
    private readonly ?Closure $chooseIndex = null,
    private readonly ?Closure $checkpoint = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    $intent = ReconstructionRequest::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    $hash = hash('sha256', json_encode($intent->canonicalRequest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new ReconstructionIntegrityException('Required player state is unavailable.');
      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireMatchingReceipt($prior, $hash);
        $this->validateReceipt($prior['result'], $intent);
        $this->pdo->commit();
        return $prior['result'];
      }

      $balance = $state['raw_chaos']; $revision = $state['player_revision'];
      if (!$this->safe($balance) || !$this->safe($state['teeth']) || !$this->safe($revision)
        || $revision === ClientSafeInteger::MAXIMUM) {
        throw new ReconstructionIntegrityException('Player wallet or revision is incoherent.');
      }
      try { $recipe = $this->content->reconstructionRecipe($intent->recipeId); }
      catch (ContentValidationException) {
        throw new ReconstructionException('reconstruction_recipe_not_found', 'Reconstruction recipe is unavailable.', 404);
      }
      $owned = $this->unlocks->listIdsForUser($userId, true);
      $restored = in_array($recipe['kin_unlock_id'], $owned, true);
      $mode = $restored ? 'repeat_reconstruction' : 'first_restoration';
      if ($intent->expectedMode !== $mode) {
        throw new ReconstructionException('reconstruction_changed', 'Reconstruction changed; refresh before trying again.', 409);
      }
      foreach ($recipe['prerequisite_unlock_ids'] as $unlockId) {
        if (!in_array($unlockId, $owned, true)) {
          throw new ReconstructionException('reconstruction_unavailable', 'Reconstruction is unavailable.', 403);
        }
      }
      $requirements = $recipe[$mode];
      $price = $requirements['price']['amount'] ?? null;
      $ingredients = $requirements['ingredients'] ?? null;
      if (($requirements['price']['currency_id'] ?? null) !== 'raw_chaos' || !$this->positive($price)
        || !is_array($ingredients) || !array_is_list($ingredients) || $ingredients === []
        || ($mode === 'first_restoration' && $requirements['unit_type_selection'] !== 'random_unlocked')
        || ($mode === 'repeat_reconstruction' && $requirements['unit_type_selection'] !== 'chosen_unlocked')) {
        throw new ReconstructionIntegrityException('Authored reconstruction requirements are incoherent.');
      }
      $canonicalIngredients = $ingredients;
      usort($canonicalIngredients, static fn(array $a, array $b): int => strcmp($a['item_id'], $b['item_id']));
      if ($price !== $intent->expectedAmount || $canonicalIngredients !== $intent->expectedIngredients) {
        throw new ReconstructionException('reconstruction_changed', 'Reconstruction changed; refresh before trying again.', 409);
      }
      $eligible = $this->unitTypes->availableUnitTypeIds($owned);
      if ($eligible === []) throw new ReconstructionException('reconstruction_unavailable', 'Reconstruction is unavailable.', 403);
      if ($mode === 'repeat_reconstruction') {
        if (!in_array($intent->unitTypeId, $eligible, true)) {
          throw new ReconstructionException('unit_type_unavailable', 'Unit type is unavailable.', 409);
        }
        $unitTypeId = $intent->unitTypeId;
      }
      if ($balance < $price) throw new ReconstructionException('insufficient_raw_chaos', 'Not enough Raw Chaos.', 409);
      $stacks = [];
      foreach ($canonicalIngredients as $ingredient) {
        $stack = $this->items->lockOwnedStack($userId, $ingredient['item_id']);
        $quantity = $stack['quantity'] ?? 0;
        if (!$this->safe($quantity)) throw new ReconstructionIntegrityException('Owned item quantity is incoherent.');
        if ($quantity < $ingredient['quantity']) {
          throw new ReconstructionException('insufficient_ingredients', 'Not enough reconstruction materials.', 409);
        }
        $stacks[$ingredient['item_id']] = $quantity;
      }
      if ($mode === 'first_restoration') {
        $index = $this->chooseIndex !== null ? ($this->chooseIndex)(count($eligible)) : random_int(0, count($eligible) - 1);
        if (!is_int($index) || $index < 0 || $index >= count($eligible)) {
          throw new ReconstructionIntegrityException('First-restoration selection is invalid.');
        }
        $unitTypeId = $eligible[$index];
      }

      $after = $balance - $price;
      $this->players->applyCurrencyDebitTransition($userId, 'raw_chaos', $balance, $after);
      $this->mark('after_debit');
      $consumed = [];
      foreach ($canonicalIngredients as $ingredient) {
        $itemId = $ingredient['item_id']; $quantity = $ingredient['quantity'];
        $remaining = $this->items->decrement($userId, $itemId, $quantity);
        if ($remaining !== $stacks[$itemId] - $quantity) {
          throw new ReconstructionIntegrityException('Item consumption is incoherent.');
        }
        $consumed[] = ['item_id' => $itemId, 'quantity' => $quantity, 'owned_after' => $remaining];
      }
      $grantOutcome = 'already_owned';
      if (!$restored) {
        if (!$this->unlocks->insertIfAbsent($userId, $recipe['kin_unlock_id'])) {
          throw new ReconstructionIntegrityException('Kin restoration did not grant its unlock.');
        }
        $grantOutcome = 'granted';
      }
      $this->mark('after_unlock');
      $unit = $this->unitCreation->create($userId, $unitTypeId, $recipe['kin_id']);
      $this->mark('after_unit_creation');
      $nextRevision = $this->players->incrementRevision($userId);
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM) {
        throw new ReconstructionIntegrityException('Player revision transition is incoherent.');
      }
      $result = ['recipe_id' => $intent->recipeId, 'mode' => $mode, 'unit' => $unit,
        'spend' => ['currency_id' => 'raw_chaos', 'amount' => $price,
          'balance_before' => $balance, 'balance_after' => $after],
        'consumed_items' => $consumed,
        'kin_restoration' => ['kin_id' => $recipe['kin_id'], 'unlock_id' => $recipe['kin_unlock_id'],
          'outcome' => $grantOutcome], 'player_revision' => $nextRevision];
      $this->validateReceipt($result, $intent);
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null) throw new ReconstructionIntegrityException('Finalized reconstruction receipt is unavailable.');
      $this->requireMatchingReceipt($stored, $hash);
      $this->validateReceipt($stored['result'], $intent);
      $this->mark('before_commit');
      $this->pdo->commit();
      return $stored['result'];
    } catch (ReconstructionException|ReconstructionIntegrityException|IdempotencyConflictException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new ReconstructionIntegrityException('Reconstruction failed safely.', 0, $e);
    }
  }

  /** @param array{operation_type:string,request_hash:string,result:array<string,mixed>} $receipt */
  private function requireMatchingReceipt(array $receipt, string $hash): void
  {
    if ($receipt['operation_type'] !== self::OPERATION || !hash_equals($receipt['request_hash'], $hash)) {
      throw new IdempotencyConflictException('Idempotency key was already used for another request.');
    }
  }

  /** @param array<string,mixed> $result */
  private function validateReceipt(array $result, ReconstructionRequest $intent): void
  {
    $spend = $result['spend'] ?? null; $unit = $result['unit'] ?? null;
    $restoration = $result['kin_restoration'] ?? null; $consumed = $result['consumed_items'] ?? null;
    if (!$this->exact($result, ['recipe_id', 'mode', 'unit', 'spend', 'consumed_items', 'kin_restoration', 'player_revision'])
      || $result['recipe_id'] !== $intent->recipeId || $result['mode'] !== $intent->expectedMode
      || !$this->safe($result['player_revision'] ?? null)
      || !is_array($spend) || array_is_list($spend)
      || !$this->exact($spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
      || $spend['currency_id'] !== 'raw_chaos' || $spend['amount'] !== $intent->expectedAmount
      || !$this->safe($spend['balance_before']) || !$this->safe($spend['balance_after'])
      || $spend['balance_before'] - $spend['amount'] !== $spend['balance_after']
      || !is_array($unit) || array_is_list($unit)
      || !$this->exact($unit, ['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status'])
      || !is_string($unit['id'] ?? null) || preg_match('/^[1-9][0-9]*$/D', $unit['id']) !== 1
      || !is_string($unit['display_name'] ?? null) || trim($unit['display_name']) === ''
      || !is_string($unit['unit_type_id'] ?? null) || !is_string($unit['kin_id'] ?? null)
      || $unit['level'] !== 1 || $unit['xp'] !== 0 || $unit['lifecycle_status'] !== 'active'
      || ($intent->unitTypeId !== null && $unit['unit_type_id'] !== $intent->unitTypeId)
      || !is_array($restoration) || array_is_list($restoration)
      || !$this->exact($restoration, ['kin_id', 'unlock_id', 'outcome'])
      || $restoration['kin_id'] !== $unit['kin_id']
      || !is_string($restoration['unlock_id'] ?? null)
      || $restoration['outcome'] !== ($intent->expectedMode === 'first_restoration' ? 'granted' : 'already_owned')
      || !is_array($consumed) || !array_is_list($consumed)
      || count($consumed) !== count($intent->expectedIngredients)) {
      throw new ReconstructionIntegrityException('Persisted reconstruction receipt is invalid.');
    }
    foreach ($consumed as $index => $item) {
      if (!is_array($item) || array_is_list($item)
        || !$this->exact($item, ['item_id', 'quantity', 'owned_after'])
        || $item['item_id'] !== $intent->expectedIngredients[$index]['item_id']
        || $item['quantity'] !== $intent->expectedIngredients[$index]['quantity']
        || !$this->safe($item['owned_after'])) {
        throw new ReconstructionIntegrityException('Persisted reconstruction item receipt is invalid.');
      }
    }
  }

  private function mark(string $name): void { if ($this->checkpoint !== null) ($this->checkpoint)($name); }
  private function safe(mixed $value): bool
  { return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM; }
  private function positive(mixed $value): bool { return $this->safe($value) && $value > 0; }
  /** @param array<string,mixed> $value @param list<string> $keys */
  private function exact(array $value, array $keys): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys; }
}
