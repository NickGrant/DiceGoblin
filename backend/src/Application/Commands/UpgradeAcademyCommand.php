<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\PermanentCapabilityPolicy;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Domain\Energy\EnergyCapacityTransition;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use PDO;
use Throwable;

final class UpgradeAcademyCommand
{
  private const OPERATION = 'upgrade_academy';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly UserUnlockRepository $unlocks,
    private readonly RewardApplicationService $rewards,
    private readonly ContentRegistry $content,
    private readonly EnergyCapacityTransition $energy,
    private readonly Clock $clock,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    $upgradeRequest = AcademyUpgradeRequest::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    $hash = hash('sha256', json_encode($upgradeRequest->canonicalRequest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new AcademyUpgradeIntegrityException('Required player state is unavailable.');
      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireMatchingReceipt($prior, $hash);
        $this->validateResult($prior['result'], $upgradeRequest);
        $this->pdo->commit();
        return $prior['result'];
      }

      $balance = $state['raw_chaos']; $revision = $state['player_revision'];
      if (!$this->safeNonNegative($balance) || !$this->safeNonNegative($state['teeth'])
        || !$this->safeNonNegative($revision) || $revision === ClientSafeInteger::MAXIMUM) {
        throw new AcademyUpgradeIntegrityException('Academy wallet or revision is incoherent.');
      }
      try { $upgrade = $this->content->academyUpgrade($upgradeRequest->upgradeId); }
      catch (ContentValidationException) { throw new AcademyUpgradeException('academy_upgrade_not_found', 'Academy upgrade is unavailable.', 404); }
      $event = $this->content->event($upgrade['event_id']);
      $reward = $this->content->rewardDefinition($event['reward_definition_id']);
      $grantId = $upgrade['grant_unlock_id'];
      if (count($reward['entries']) !== 1 || $reward['entries'][0]['probability_basis_points'] !== 10000
        || $reward['entries'][0]['reward_type'] !== 'unlock'
        || ($reward['entries'][0]['config']['unlock_id'] ?? null) !== $grantId) {
        throw new AcademyUpgradeIntegrityException('Academy reward definition is incoherent.');
      }
      $owned = $this->unlocks->listIdsForUser($userId, true);
      if (in_array($grantId, $owned, true)) throw new AcademyUpgradeException('academy_upgrade_owned', 'Academy upgrade is already owned.', 409);
      foreach ($upgrade['prerequisite_unlock_ids'] as $prerequisite) {
        if (!in_array($prerequisite, $owned, true)) throw new AcademyUpgradeException('academy_upgrade_unavailable', 'Academy upgrade is unavailable.', 403);
      }
      $price = $upgrade['price']['amount'];
      if (($upgrade['price']['currency_id'] ?? null) !== 'raw_chaos' || !is_int($price) || $price < 1 || $price > ClientSafeInteger::MAXIMUM) {
        throw new AcademyUpgradeIntegrityException('Academy price is incoherent.');
      }
      if ($price !== $upgradeRequest->expectedAmount) throw new AcademyUpgradeException('academy_upgrade_changed', 'Academy upgrade price changed.', 409);
      if ($balance < $price) throw new AcademyUpgradeException('insufficient_raw_chaos', 'Not enough Raw Chaos.', 409);

      $after = $balance - $price;
      $this->players->applyCurrencyDebitTransition($userId, 'raw_chaos', $balance, $after);
      $energyView = null;
      $unlock = $this->content->unlock($grantId);
      if ($unlock['target_type'] === 'capability') {
        $capability = $this->content->definition($unlock['target_id']);
        if ($capability['kind'] === 'energy_normal_max') {
          $oldMax = (new PermanentCapabilityPolicy($this->content))->energyNormalMaximum($owned);
          if ($capability['value'] > $oldMax) {
            $now = $this->clock->now();
            $anchor = new DateTimeImmutable($state['energy_last_regen_at'], new DateTimeZone('UTC'));
            $transition = $this->energy->raise($state['energy_current'], $anchor, $oldMax,
              $capability['value'], $this->content->energyRegenerationPerHour(), $now);
            $this->players->persistEnergyWithoutRevision($userId, $transition['current'], $transition['anchor']);
            $energyView = $transition['view']->toArray();
          }
        }
      }
      $result = $this->rewards->apply($userId, $upgrade['event_id'], 'academy_upgrade', $upgradeRequest->upgradeId,
        new RewardContext($state['teeth'], $after, [], $owned));
      $entries = $result->entries();
      if (count($entries) !== 1 || ($entries[0]['outcome'] ?? null) !== 'granted'
        || ($entries[0]['grant']['unlock_id'] ?? null) !== $grantId || !$this->unlocks->owns($userId, $grantId)) {
        throw new AcademyUpgradeIntegrityException('Academy reward did not grant the declared unlock.');
      }
      $nextRevision = $this->players->incrementRevision($userId);
      if ($nextRevision !== $revision + 1 || $nextRevision > ClientSafeInteger::MAXIMUM) {
        throw new AcademyUpgradeIntegrityException('Academy revision transition is incoherent.');
      }
      $receipt = ['upgrade_id' => $upgradeRequest->upgradeId,
        'spend' => ['currency_id' => 'raw_chaos', 'amount' => $price, 'balance_before' => $balance, 'balance_after' => $after],
        'grant' => ['unlock_id' => $grantId], 'energy' => $energyView, 'player_revision' => $nextRevision];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $receipt);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null) throw new AcademyUpgradeIntegrityException('Finalized Academy receipt is unavailable.');
      $this->requireMatchingReceipt($stored, $hash);
      $this->validateResult($stored['result'], $upgradeRequest);
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $stored['result'];
    } catch (AcademyUpgradeException|AcademyUpgradeIntegrityException|IdempotencyConflictException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new AcademyUpgradeIntegrityException('Academy upgrade failed safely.', 0, $e);
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
  private function validateResult(array $result, AcademyUpgradeRequest $request): void
  {
    $spend = $result['spend'] ?? null; $grant = $result['grant'] ?? null; $energy = $result['energy'] ?? null;
    if (!$this->exact($result, ['upgrade_id', 'spend', 'grant', 'energy', 'player_revision'])
      || ($result['upgrade_id'] ?? null) !== $request->upgradeId
      || !is_array($spend) || array_is_list($spend) || !$this->exact($spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
      || $spend['currency_id'] !== 'raw_chaos' || $spend['amount'] !== $request->expectedAmount
      || !$this->safeNonNegative($spend['balance_before']) || !$this->safeNonNegative($spend['balance_after'])
      || $spend['balance_before'] - $spend['amount'] !== $spend['balance_after']
      || !is_array($grant) || array_is_list($grant) || !$this->exact($grant, ['unlock_id'])
      || !is_string($grant['unlock_id'] ?? null)
      || preg_match('/^unlock\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $grant['unlock_id']) !== 1
      || !$this->safeNonNegative($result['player_revision'] ?? null)) {
      throw new AcademyUpgradeIntegrityException('Persisted Academy receipt is invalid.');
    }
    if ($energy !== null) {
      if (!is_array($energy) || array_is_list($energy)
        || !$this->exact($energy, ['current', 'normal_max', 'regeneration_per_hour', 'regeneration_interval_seconds',
          'last_regeneration_at', 'next_regeneration_at', 'fully_regenerated_at'])
        || !$this->safeNonNegative($energy['current'] ?? null) || !$this->safePositive($energy['normal_max'] ?? null)
        || !$this->positiveNumber($energy['regeneration_per_hour'] ?? null)
        || !$this->positiveNumber($energy['regeneration_interval_seconds'] ?? null)
        || abs($energy['regeneration_interval_seconds'] - 3600 / $energy['regeneration_per_hour']) > 0.000001
        || !$this->timestamp($energy['last_regeneration_at'] ?? null)
        || ($energy['current'] >= $energy['normal_max']
          ? ($energy['next_regeneration_at'] !== null || $energy['fully_regenerated_at'] !== null)
          : (!$this->timestamp($energy['next_regeneration_at'] ?? null)
            || !$this->timestamp($energy['fully_regenerated_at'] ?? null)
            || $energy['next_regeneration_at'] <= $energy['last_regeneration_at']
            || $energy['fully_regenerated_at'] < $energy['next_regeneration_at']))) {
        throw new AcademyUpgradeIntegrityException('Persisted Academy Energy receipt is invalid.');
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
  { return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) === 1
      && (new DateTimeImmutable($value))->format('Y-m-d\TH:i:s\Z') === $value; }
}
