<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Application\PermanentCapabilityPolicy;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Domain\Energy\EnergyRestoreCalculator;
use DiceGoblins\Domain\Energy\EnergyRestoreUnavailableException;
use DiceGoblins\Domain\Inventory\InsufficientInventoryException;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use JsonException;
use PDO;
use Throwable;

final class RestoreEnergyCommand
{
  private const OPERATION = 'restore_energy';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly UserItemRepository $items,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly UserUnlockRepository $unlocks,
    private readonly ContentRegistry $content,
    private readonly EnergyRestoreCalculator $energy,
    private readonly Clock $clock,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    $use = ConsumableUseRequest::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    $hash = $this->requestHash($use->canonicalRequest());
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new ConsumableUseIntegrityException('Required player state is unavailable.');
      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireMatchingReceipt($prior, $hash);
        $this->validateResult($prior['result'], $use->itemId);
        $this->pdo->commit();
        return $prior['result'];
      }
      $revision = $state['player_revision'];
      if ($revision < 0 || $revision >= ClientSafeInteger::MAXIMUM) {
        throw new ConsumableUseIntegrityException('Player revision is incoherent.');
      }
      $item = $this->eligibleItem($use->itemId, 'energy_restore');
      $now = $this->clock->now();
      try {
        $anchor = new DateTimeImmutable($state['energy_last_regen_at'], new DateTimeZone('UTC'));
        $restored = $this->energy->restore(
          $state['energy_current'], $anchor,
          (new PermanentCapabilityPolicy($this->content))->energyNormalMaximum($this->unlocks->listIdsForUser($userId, true)),
          $this->content->energyRegenerationPerHour(), $item['effect']['amount'], $now,
        );
      } catch (EnergyRestoreUnavailableException) {
        throw new ConsumableUseException('energy_restore_unavailable', 'Energy restore is unavailable.', 409);
      } catch (ConsumableUseException $e) {
        throw $e;
      } catch (Throwable $e) {
        throw new ConsumableUseIntegrityException('Energy state is incoherent.', 0, $e);
      }
      try { $remaining = $this->items->decrement($userId, $use->itemId, 1); }
      catch (InsufficientInventoryException) {
        throw new ConsumableUseException('consumable_unavailable', 'Consumable is unavailable.', 409);
      }
      $nextRevision = $this->players->persistEnergyAndIncrementRevision(
        $userId, $restored->persistedCurrent, $restored->persistedLastRegenerationAt,
      );
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM) {
        throw new ConsumableUseIntegrityException('Player revision transition is incoherent.');
      }
      $result = ['item_id' => $use->itemId, 'quantity_consumed' => 1,
        'owned_quantity_after' => $remaining, 'energy' => $restored->view->toArray(),
        'player_revision' => $nextRevision];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null) throw new ConsumableUseIntegrityException('Finalized consumable receipt is unavailable.');
      $this->requireMatchingReceipt($stored, $hash); $this->validateResult($stored['result'], $use->itemId);
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $stored['result'];
    } catch (ConsumableUseException|ConsumableUseIntegrityException|IdempotencyConflictException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new ConsumableUseIntegrityException('Energy restore failed safely.', 0, $e);
    }
  }

  /** @return array<string,mixed> */
  private function eligibleItem(string $itemId, string $effectType): array
  {
    try { $item = $this->content->item($itemId); }
    catch (ContentValidationException) {
      throw new ConsumableUseException('invalid_consumable', 'Item is invalid for this command.', 422);
    }
    if (($item['category'] ?? null) !== 'consumable' || ($item['stackable'] ?? null) !== true
      || ($item['effect']['type'] ?? null) !== $effectType || !is_int($item['effect']['amount'] ?? null)) {
      throw new ConsumableUseException('invalid_consumable', 'Item is invalid for this command.', 422);
    }
    return $item;
  }

  /** @param array<string,mixed> $canonical */
  private function requestHash(array $canonical): string
  {
    try { return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); }
    catch (JsonException $e) { throw new ConsumableUseIntegrityException('Consumable request could not be normalized.', 0, $e); }
  }

  /** @param array{operation_type:string,request_hash:string,result:array<string,mixed>} $receipt */
  private function requireMatchingReceipt(array $receipt, string $hash): void
  {
    if ($receipt['operation_type'] !== self::OPERATION || !hash_equals($receipt['request_hash'], $hash)) {
      throw new IdempotencyConflictException('Idempotency key was already used for another request.');
    }
  }

  /** @param array<string,mixed> $result */
  private function validateResult(array $result, string $itemId): void
  {
    if (!$this->exact($result, ['item_id', 'quantity_consumed', 'owned_quantity_after', 'energy', 'player_revision'])
      || ($result['item_id'] ?? null) !== $itemId || ($result['quantity_consumed'] ?? null) !== 1
      || !$this->safeNonNegative($result['owned_quantity_after'] ?? null)
      || !$this->safeNonNegative($result['player_revision'] ?? null)
      || !is_array($result['energy'] ?? null) || array_is_list($result['energy'])) {
      throw new ConsumableUseIntegrityException('Persisted Energy restore receipt is invalid.');
    }
    $energy = $result['energy'];
    if (!$this->exact($energy, ['current', 'normal_max', 'regeneration_per_hour', 'regeneration_interval_seconds',
        'last_regeneration_at', 'next_regeneration_at', 'fully_regenerated_at'])
      || !$this->safeNonNegative($energy['current'] ?? null) || !$this->safePositive($energy['normal_max'] ?? null)
      || !$this->positiveNumber($energy['regeneration_per_hour'] ?? null)
      || !$this->positiveNumber($energy['regeneration_interval_seconds'] ?? null)
      || !$this->timestamp($energy['last_regeneration_at'] ?? null)) {
      throw new ConsumableUseIntegrityException('Persisted Energy restore receipt is invalid.');
    }
    $capped = $energy['current'] >= $energy['normal_max'];
    foreach (['next_regeneration_at', 'fully_regenerated_at'] as $field) {
      if (($capped && $energy[$field] !== null) || (!$capped && !$this->timestamp($energy[$field] ?? null))) {
        throw new ConsumableUseIntegrityException('Persisted Energy timing is invalid.');
      }
    }
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private function exact(array $value, array $keys): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys; }
  private function safeNonNegative(mixed $value): bool
  { return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM; }
  private function safePositive(mixed $value): bool { return $this->safeNonNegative($value) && $value > 0; }
  private function positiveNumber(mixed $value): bool
  { return (is_int($value) || is_float($value)) && is_finite((float)$value) && $value > 0; }
  private function timestamp(mixed $value): bool
  { return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) === 1; }
}
