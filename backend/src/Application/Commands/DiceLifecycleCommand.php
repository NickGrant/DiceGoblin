<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Services\DiceValuationService;
use DiceGoblins\Support\ClientSafeInteger;
use JsonException;
use PDO;
use Throwable;

final class DiceLifecycleCommand
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly WarbandDiceRepository $dice,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly ActiveRunConfigurationPolicy $activeRuns,
    private readonly ContentRegistry $content,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @return array<string,mixed> */
  public function sell(int $userId, int $diceId, ?string $providedKey): array
  {
    return $this->transition($userId, $diceId, 'sell_die', 'sold', 'teeth', $providedKey);
  }

  /** @return array<string,mixed> */
  public function salvage(int $userId, int $diceId, ?string $providedKey): array
  {
    return $this->transition($userId, $diceId, 'salvage_die', 'salvaged', 'raw_chaos', $providedKey);
  }

  /** @return array<string,mixed> */
  private function transition(
    int $userId,
    int $diceId,
    string $operation,
    string $terminalStatus,
    string $currency,
    ?string $providedKey,
  ): array {
    if ($diceId <= 0) throw new DiceLifecycleException('die_not_found', 'Die is unavailable.', 404);
    $key = IdempotencyKey::validate($providedKey);
    $hash = $this->requestHash(['dice_id' => (string)$diceId]);

    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new DiceLifecycleIntegrityException('Required player state is unavailable.');

      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireMatchingReceipt($prior, $operation, $hash);
        $this->validateResult($prior['result'], $diceId, $terminalStatus, $currency);
        $this->pdo->commit();
        return $prior['result'];
      }

      $balance = $state[$currency] ?? null;
      $revision = $state['player_revision'] ?? null;
      if (!$this->safeNonNegative($balance) || !$this->safeNonNegative($revision)
        || $revision >= ClientSafeInteger::MAXIMUM) {
        throw new DiceLifecycleIntegrityException('Player wallet or revision is incoherent.');
      }

      $die = $this->dice->findActiveOwnedForUpdate($userId, $diceId);
      if ($die === null) throw new DiceLifecycleException('die_not_found', 'Die is unavailable.', 404);
      try {
        $profile = $this->content->diceProfile((string)$die['profile_id']);
      } catch (ContentValidationException $e) {
        throw new DiceLifecycleIntegrityException('Owned die profile is unavailable.', 0, $e);
      }
      $size = (int)$die['size'];
      $rarity = $profile['rarity'] ?? null;
      if (!is_string($rarity) || !in_array($size, $profile['allowed_sizes'] ?? [], true)) {
        throw new DiceLifecycleIntegrityException('Owned die profile is incoherent.');
      }

      $bindings = $this->dice->listLifecycleBindingsForUpdate($diceId);
      $unitIds = [];
      foreach ($bindings as $binding) {
        if ((int)$binding['dice_instance_id'] !== $diceId || (int)$binding['die_user_id'] !== $userId
          || (string)$binding['die_lifecycle_status'] !== 'active'
          || (int)$binding['unit_user_id'] !== $userId || (string)$binding['unit_lifecycle_status'] !== 'active') {
          throw new DiceLifecycleIntegrityException('Owned die binding is incoherent.');
        }
        $unitIds[] = (int)$binding['unit_id'];
      }
      $this->activeRuns->assertDiceLifecycleAllowed($userId, $unitIds);
      if ($bindings !== []) throw new DiceLifecycleException('die_equipped', 'Equipped dice cannot be transitioned.', 409);

      $award = $currency === 'teeth'
        ? DiceValuationService::calculateSellValue($size, $rarity, [])
        : DiceValuationService::calculateRawChaosSalvageValue($size, $rarity, []);
      if ($award < 1 || $award > ClientSafeInteger::MAXIMUM || $balance > ClientSafeInteger::MAXIMUM - $award) {
        throw new DiceLifecycleException('currency_overflow', 'Currency award cannot be applied.', 409);
      }
      $after = $balance + $award;
      $this->dice->transitionActiveLifecycle($userId, $diceId, $terminalStatus);
      $this->players->applyCurrencyTransition($userId, $currency, $balance, $after);
      $nextRevision = $this->players->incrementRevision($userId);
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM) {
        throw new DiceLifecycleIntegrityException('Player revision transition is incoherent.');
      }

      $awardField = $currency . '_awarded';
      $result = ['dice_id' => (string)$diceId, 'lifecycle_status' => $terminalStatus,
        $awardField => $award, $currency => $after, 'player_revision' => $nextRevision];
      $this->idempotency->insertFinalized($userId, $key, $operation, $hash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null) throw new DiceLifecycleIntegrityException('Finalized die lifecycle receipt is unavailable.');
      $this->requireMatchingReceipt($stored, $operation, $hash);
      $this->validateResult($stored['result'], $diceId, $terminalStatus, $currency);
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $stored['result'];
    } catch (DiceLifecycleException|DiceLifecycleIntegrityException|IdempotencyConflictException
      |ActiveRunConfigurationLockedException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (WarbandIntegrityException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new DiceLifecycleIntegrityException('Active run configuration is incoherent.', 0, $e);
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new DiceLifecycleIntegrityException('Die lifecycle transition failed safely.', 0, $e);
    }
  }

  /** @param array<string,string> $request */
  private function requestHash(array $request): string
  {
    try { return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); }
    catch (JsonException $e) { throw new DiceLifecycleIntegrityException('Die request could not be normalized.', 0, $e); }
  }

  /** @param array{operation_type:string,request_hash:string,result:array<string,mixed>} $receipt */
  private function requireMatchingReceipt(array $receipt, string $operation, string $hash): void
  {
    if ($receipt['operation_type'] !== $operation || !hash_equals($receipt['request_hash'], $hash)) {
      throw new IdempotencyConflictException('Idempotency key was already used for another request.');
    }
  }

  /** @param array<string,mixed> $result */
  private function validateResult(array $result, int $diceId, string $status, string $currency): void
  {
    $awardField = $currency . '_awarded';
    if (!$this->exact($result, ['dice_id', 'lifecycle_status', $awardField, $currency, 'player_revision'])
      || ($result['dice_id'] ?? null) !== (string)$diceId || ($result['lifecycle_status'] ?? null) !== $status
      || !$this->safePositive($result[$awardField] ?? null) || !$this->safeNonNegative($result[$currency] ?? null)
      || $result[$currency] < $result[$awardField] || !$this->safeNonNegative($result['player_revision'] ?? null)) {
      throw new DiceLifecycleIntegrityException('Persisted die lifecycle receipt is invalid.');
    }
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private function exact(array $value, array $keys): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING); return $actual === $keys; }
  private function safeNonNegative(mixed $value): bool
  { return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM; }
  private function safePositive(mixed $value): bool { return $this->safeNonNegative($value) && $value > 0; }
}
