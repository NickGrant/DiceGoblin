<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Domain\Inventory\InsufficientInventoryException;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Support\ClientSafeInteger;
use JsonException;
use PDO;
use Throwable;

final class HealRunUnitCommand
{
  private const OPERATION = 'heal_run_unit';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly RunPersistenceRepository $runs,
    private readonly RunNodeResolutionRepository $runUnits,
    private readonly UserItemRepository $items,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly ContentRegistry $content,
    private readonly BaseLevelStatResolver $stats = new BaseLevelStatResolver(),
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, int $runId, int $unitId, array $request, ?string $providedKey): array
  {
    if ($runId <= 0 || $unitId <= 0) throw new ConsumableUseException('run_unit_not_found', 'Run unit is unavailable.', 404);
    $use = ConsumableUseRequest::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    $canonical = ['run_id' => (string)$runId, 'unit_id' => (string)$unitId, 'item_id' => $use->itemId];
    $hash = $this->requestHash($canonical);
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new ConsumableUseIntegrityException('Required player state is unavailable.');
      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireMatchingReceipt($prior, $hash);
        $this->validateResult($prior['result'], $runId, $unitId, $use->itemId);
        $this->pdo->commit();
        return $prior['result'];
      }
      $revision = $state['player_revision'];
      if ($revision < 0 || $revision >= ClientSafeInteger::MAXIMUM) {
        throw new ConsumableUseIntegrityException('Player revision is incoherent.');
      }
      $run = $this->runs->findOwnedRunForUpdate($userId, $runId);
      if ($run === null) throw new ConsumableUseException('run_not_found', 'Run is unavailable.', 404);
      if (($run['status'] ?? null) !== 'active') {
        throw new ConsumableUseException('run_heal_unavailable', 'Run healing is unavailable.', 409);
      }
      $unit = $this->runUnits->findOwnedParticipatingUnitForUpdate($userId, $runId, $unitId);
      if ($unit === null) throw new ConsumableUseException('run_unit_not_found', 'Run unit is unavailable.', 404);
      if (($unit['lifecycle_status'] ?? null) !== 'active' || (int)$unit['level'] < 1) {
        throw new ConsumableUseIntegrityException('Participating unit state is incoherent.');
      }
      try {
        $type = $this->content->unitType($unit['unit_type_id']);
        $maximum = $this->stats->resolve($type['base_stats'], $type['growth_per_level'], $unit['level'])->hp;
      } catch (Throwable $e) {
        throw new ConsumableUseIntegrityException('Participating unit authored state is incoherent.', 0, $e);
      }
      $before = $unit['current_hp'];
      if ($maximum < 1 || $maximum > ClientSafeInteger::MAXIMUM || $before < 0 || $before > $maximum) {
        throw new ConsumableUseIntegrityException('Participating unit HP is incoherent.');
      }
      $item = $this->eligibleItem($use->itemId);
      if ($before >= $maximum) {
        throw new ConsumableUseException('run_heal_unavailable', 'Run healing is unavailable.', 409);
      }
      $after = min($maximum, $before + $item['effect']['amount']);
      try { $remaining = $this->items->decrement($userId, $use->itemId, 1); }
      catch (InsufficientInventoryException) {
        throw new ConsumableUseException('consumable_unavailable', 'Consumable is unavailable.', 409);
      }
      $this->runUnits->persistParticipatingUnitHp($runId, $unitId, $before, $after);
      $nextRevision = $this->players->incrementRevision($userId);
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM) {
        throw new ConsumableUseIntegrityException('Player revision transition is incoherent.');
      }
      $result = ['item_id' => $use->itemId, 'quantity_consumed' => 1,
        'owned_quantity_after' => $remaining, 'run_id' => (string)$runId,
        'unit' => ['unit_id' => (string)$unitId, 'hp_before' => $before, 'hp_after' => $after, 'max_hp' => $maximum],
        'player_revision' => $nextRevision];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null) throw new ConsumableUseIntegrityException('Finalized consumable receipt is unavailable.');
      $this->requireMatchingReceipt($stored, $hash);
      $this->validateResult($stored['result'], $runId, $unitId, $use->itemId);
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $stored['result'];
    } catch (ConsumableUseException|ConsumableUseIntegrityException|IdempotencyConflictException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new ConsumableUseIntegrityException('Run-unit healing failed safely.', 0, $e);
    }
  }

  /** @return array<string,mixed> */
  private function eligibleItem(string $itemId): array
  {
    try { $item = $this->content->item($itemId); }
    catch (ContentValidationException) {
      throw new ConsumableUseException('invalid_consumable', 'Item is invalid for this command.', 422);
    }
    if (($item['category'] ?? null) !== 'consumable' || ($item['stackable'] ?? null) !== true
      || ($item['effect']['type'] ?? null) !== 'unit_heal' || !is_int($item['effect']['amount'] ?? null)) {
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
  private function validateResult(array $result, int $runId, int $unitId, string $itemId): void
  {
    if (!$this->exact($result, ['item_id', 'quantity_consumed', 'owned_quantity_after', 'run_id', 'unit', 'player_revision'])
      || ($result['item_id'] ?? null) !== $itemId || ($result['quantity_consumed'] ?? null) !== 1
      || ($result['run_id'] ?? null) !== (string)$runId
      || !$this->safeNonNegative($result['owned_quantity_after'] ?? null)
      || !$this->safeNonNegative($result['player_revision'] ?? null)
      || !is_array($result['unit'] ?? null) || array_is_list($result['unit'])) {
      throw new ConsumableUseIntegrityException('Persisted run healing receipt is invalid.');
    }
    $unit = $result['unit'];
    if (!$this->exact($unit, ['unit_id', 'hp_before', 'hp_after', 'max_hp'])
      || ($unit['unit_id'] ?? null) !== (string)$unitId
      || !$this->safeNonNegative($unit['hp_before'] ?? null)
      || !$this->safePositive($unit['hp_after'] ?? null)
      || !$this->safePositive($unit['max_hp'] ?? null)
      || $unit['hp_before'] >= $unit['hp_after'] || $unit['hp_after'] > $unit['max_hp']) {
      throw new ConsumableUseIntegrityException('Persisted run healing receipt is invalid.');
    }
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private function exact(array $value, array $keys): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys; }
  private function safeNonNegative(mixed $value): bool
  { return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM; }
  private function safePositive(mixed $value): bool { return $this->safeNonNegative($value) && $value > 0; }
}
