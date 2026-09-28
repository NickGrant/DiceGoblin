<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\Queries\UnitNotFoundException;
use DiceGoblins\Application\UnitPromotionPolicy;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Support\ClientSafeInteger;
use PDO;
use Throwable;

/** One player intention, one transaction owner, and one finalized promotion receipt. */
final class PromoteUnitCommand
{
  private const OPERATION = 'promote_unit';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly WarbandUnitRepository $units,
    private readonly UnitDetailQuery $unitDetails,
    private readonly UnitPromotionPolicy $promotions,
    private readonly ActiveRunConfigurationPolicy $activeRuns,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, int $unitId, array $request, ?string $providedKey): array
  {
    $promotionRequest = UnitPromotionRequest::fromRequest($unitId, $request);
    $key = IdempotencyKey::validate($providedKey);
    $hash = hash('sha256', json_encode($promotionRequest->canonicalRequest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new WarbandIntegrityException('Required player state is unavailable.');
      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireMatchingReceipt($prior, $hash);
        UnitPromotionReceipt::validate($prior['result'], $promotionRequest);
        $this->pdo->commit();
        return $prior['result'];
      }

      $balance = $state['raw_chaos']; $revision = $state['player_revision'];
      if (!$this->safe($balance) || !$this->safe($revision) || $revision === ClientSafeInteger::MAXIMUM)
        throw new WarbandIntegrityException('Player promotion wallet or revision is incoherent.');

      $unit = $this->units->getActiveForUser($userId, $unitId, true);
      if ($unit === null) throw new UnitNotFoundException('Unit is unavailable.');
      $fromTypeId = (string)$unit['unit_type_id'];
      try { $outgoing = $this->promotions->outgoing($fromTypeId); }
      catch (ContentValidationException $e) { throw new WarbandIntegrityException('Unit type is unavailable.', 0, $e); }
      if ($outgoing === []) throw new UnitNotFoundException('Unit is unavailable.');

      $before = $this->unitDetails->execute($userId, $unitId, true);
      $this->promotions->validateHistory($fromTypeId, $this->units->listPromotions($unitId, true));
      $this->promotions->xpThreshold((int)$unit['level'], (int)$unit['xp']);
      try { $promotion = $this->promotions->exact($promotionRequest->promotionId); }
      catch (ContentValidationException) {
        throw new UnitPromotionException('unit_promotion_not_found', 'Unit promotion is unavailable.', 404);
      }
      if ($promotion['from_unit_type_id'] !== $fromTypeId)
        throw new UnitPromotionException('unit_promotion_unavailable', 'Unit promotion is unavailable.', 409);
      $this->promotions->targetTier($promotionRequest->promotionId);
      if (!$this->promotions->levelMet($promotionRequest->promotionId, (int)$unit['level']))
        throw new UnitPromotionException('unit_promotion_level_required', 'Unit level is too low.', 409);
      if ($this->activeRuns->isUnitConfigurationLocked($userId, $unitId))
        throw new ActiveRunConfigurationLockedException();
      $price = $promotion['price']['amount'];
      if (($promotion['price']['currency_id'] ?? null) !== 'raw_chaos' || !is_int($price)
        || $price < 1 || $price > ClientSafeInteger::MAXIMUM)
        throw new WarbandIntegrityException('Authored unit promotion price is invalid.');
      if ($price !== $promotionRequest->expectedAmount)
        throw new UnitPromotionException('unit_promotion_changed', 'Unit promotion price changed.', 409);
      if ($balance < $price)
        throw new UnitPromotionException('insufficient_raw_chaos', 'Not enough Raw Chaos.', 409);

      $owned = $before['owned_ability_ids'];
      $delta = $this->promotions->targetAbilityDelta($promotionRequest->promotionId, $owned);
      $after = $balance - $price;
      $this->players->applyCurrencyDebitTransition($userId, 'raw_chaos', $balance, $after);
      $this->units->updatePromotedType($userId, $unitId, $fromTypeId, $promotion['to_unit_type_id']);
      $this->units->appendPromotion($unitId, $fromTypeId, $promotion['to_unit_type_id']);
      $this->units->insertOwnedAbilities($unitId, $delta);

      $current = $this->unitDetails->execute($userId, $unitId, true);
      $this->promotions->validateHistory($current['unit_type_id'], $this->units->listPromotions($unitId, true));
      $actualDelta = array_values(array_diff($current['owned_ability_ids'], $owned));
      $expectedDelta = $delta;
      sort($actualDelta, SORT_STRING); sort($expectedDelta, SORT_STRING);
      if ($current['id'] !== $before['id'] || $current['display_name'] !== $before['display_name']
        || $current['kin_id'] !== $before['kin_id'] || $current['level'] !== $before['level']
        || $current['xp'] !== $before['xp'] || $current['xp_to_next_level'] !== $before['xp_to_next_level']
        || $current['lifecycle_status'] !== 'active' || $current['unit_type_id'] !== $promotion['to_unit_type_id']
        || $current['ability_loadout'] !== $before['ability_loadout']
        || $current['dice_bindings'] !== $before['dice_bindings']
        || count($current['promotion_history']) !== count($before['promotion_history']) + 1
        || array_slice($current['promotion_history'], 0, -1) !== $before['promotion_history']
        || $actualDelta !== $expectedDelta
        || count($current['owned_ability_ids']) !== count($owned) + count($delta)) {
        throw new WarbandIntegrityException('Unit promotion result is incoherent.');
      }

      $nextRevision = $this->players->incrementRevision($userId);
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM)
        throw new WarbandIntegrityException('Unit promotion revision is incoherent.');
      $result = ['promotion' => ['promotion_id' => $promotionRequest->promotionId,
        'from_unit_type_id' => $fromTypeId, 'to_unit_type_id' => $promotion['to_unit_type_id'],
        'granted_ability_ids' => $delta],
        'spend' => ['currency_id' => 'raw_chaos', 'amount' => $price,
          'balance_before' => $balance, 'balance_after' => $after],
        'unit' => $current, 'player_revision' => $nextRevision];
      UnitPromotionReceipt::validate($result, $promotionRequest);
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null) throw new WarbandIntegrityException('Finalized unit promotion receipt is unavailable.');
      $this->requireMatchingReceipt($stored, $hash);
      UnitPromotionReceipt::validate($stored['result'], $promotionRequest);
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $stored['result'];
    } catch (UnitPromotionException|UnitNotFoundException|ActiveRunConfigurationLockedException|IdempotencyConflictException|WarbandIntegrityException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new WarbandIntegrityException('Unit promotion failed safely.', 0, $e);
    }
  }

  /** @param array{operation_type:string,request_hash:string,result:array<string,mixed>} $receipt */
  private function requireMatchingReceipt(array $receipt, string $hash): void
  {
    if ($receipt['operation_type'] !== self::OPERATION || !hash_equals($receipt['request_hash'], $hash))
      throw new IdempotencyConflictException('Idempotency key was already used for another request.');
  }

  private function safe(mixed $value): bool
  { return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM; }
}
