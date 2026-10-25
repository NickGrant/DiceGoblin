<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Rewards;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Rewards\FinalizedRewardResult;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use PDO;
use Throwable;

final class RewardApplicationService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly ContentRegistry $content,
    private readonly RewardFinalizer $finalizer,
    private readonly ResolvedEventRepository $events,
    private readonly PlayerStateRepository $playerState,
    private readonly WarbandUnitRepository $units,
    private readonly UserUnlockRepository $unlocks,
    private readonly UserCodexRepository $codex,
  ) {}

  public function apply(
    int $userId,
    string $eventId,
    string $sourceType,
    string $sourceId,
    ?RewardContext $context,
  ): FinalizedRewardResult {
    if (!$this->pdo->inTransaction()) throw new RewardApplicationIntegrityException('Reward application requires a caller-owned transaction.');

    $existing = $this->events->find($userId, $eventId, $sourceType, $sourceId);
    if ($existing !== null) {
      if ($existing->isApplied()) {
        foreach ($existing->result->entries() as $entry) {
          if ($entry['reward_type'] !== 'codex' || $entry['outcome'] === 'not_rolled') continue;
          $id = $entry['grant']['codex_id'];
          try { $this->content->codexEntry($id); }
          catch (Throwable $e) { throw new RewardApplicationIntegrityException('Finalized Codex identity is unknown.', 0, $e); }
          if (!$this->codex->owns($userId, $id)) {
            throw new RewardApplicationIntegrityException('Finalized Codex ownership is missing.');
          }
        }
        return $existing->result;
      }
      throw new RewardApplicationIntegrityException('A finalized reward event was not applied atomically.');
    }
    if ($context === null) throw new RewardApplicationIntegrityException('Authoritative reward context is required for a new event.');

    try {
      $event = $this->content->event($eventId);
      $reward = $this->content->rewardDefinition((string)$event['reward_definition_id']);
      foreach ($reward['entries'] as $entry) {
        if ($entry['reward_type'] === 'unlock') $this->content->unlock((string)$entry['config']['unlock_id']);
        if ($entry['reward_type'] === 'codex') $this->content->codexEntry((string)$entry['config']['codex_id']);
      }
      $this->validateContext($userId, $context);
      $ownedCodex = $this->codex->listIdsForUser($userId, true);
      foreach ($ownedCodex as $codexId) $this->content->codexEntry($codexId);
      $context = $context->withCodexIds($ownedCodex);
      $result = $this->finalizer->finalize($event, $reward, $sourceType, $sourceId, $context);
      $resolvedEventId = $this->events->insertFinalized($userId, $result);
      $persisted = $this->events->find($userId, $eventId, $sourceType, $sourceId);
      if ($persisted === null || $persisted->id !== $resolvedEventId || $persisted->isApplied()) {
        throw new RewardApplicationIntegrityException('Finalized reward event could not be read back consistently.');
      }
      $result = $persisted->result;
      $this->applyGrants($userId, $result);
      $this->events->markApplied($resolvedEventId, $userId, $result);
      return $result;
    } catch (RewardApplicationIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RewardApplicationIntegrityException('Reward finalization or application failed.', 0, $e);
    }
  }

  private function validateContext(int $userId, RewardContext $context): void
  {
    $state = $this->playerState->getPlayerStateForUpdate($userId);
    if ($state === null || $state['teeth'] !== $context->teeth || $state['raw_chaos'] !== $context->rawChaos) {
      throw new RewardApplicationIntegrityException('Authoritative reward wallet context is stale.');
    }

    $contextUnits = $context->units();
    uksort($contextUnits, [FinalizedRewardResult::class, 'compareIds']);
    $unitIds = array_map('intval', array_keys($contextUnits));
    $persistedUnits = $this->units->listActiveByIdsForUser($userId, $unitIds, true);
    if (count($persistedUnits) !== count($contextUnits)) throw new RewardApplicationIntegrityException('Authoritative reward unit context is invalid.');
    foreach ($persistedUnits as $row) {
      $id = (string)$row['id'];
      $expected = $contextUnits[$id] ?? null;
      if ($expected === null || (int)$row['level'] !== $expected['level'] || (int)$row['xp'] !== $expected['xp']) {
        throw new RewardApplicationIntegrityException('Authoritative reward unit context is stale.');
      }
    }

    $owned = $this->unlocks->listIdsForUser($userId, true);
    $contextUnlocks = array_keys($context->unlocks());
    sort($contextUnlocks, SORT_STRING);
    if ($owned !== $contextUnlocks) throw new RewardApplicationIntegrityException('Authoritative reward unlock context is stale.');
    foreach ($owned as $unlockId) $this->content->unlock($unlockId);
  }

  private function applyGrants(int $userId, FinalizedRewardResult $result): void
  {
    foreach ($result->entries() as $entry) {
      if ($entry['outcome'] !== 'granted') continue;
      $grant = $entry['grant'];
      if ($entry['reward_type'] === 'currency') {
        $this->playerState->applyCurrencyTransition(
          $userId, $grant['currency_id'], $grant['balance_before'], $grant['balance_after'],
        );
      } elseif ($entry['reward_type'] === 'unit_xp') {
        foreach ($grant['units'] as $unit) {
          $this->units->applyProgressionTransition(
            $userId, (int)$unit['unit_id'], $unit['level_before'], $unit['xp_before'], $unit['level_after'], $unit['xp_after'],
          );
        }
      } elseif ($entry['reward_type'] === 'codex') {
        $this->content->codexEntry($grant['codex_id']);
        if (!$this->codex->insertIfAbsent($userId, $grant['codex_id'])) {
          throw new RewardApplicationIntegrityException('Finalized Codex grant unexpectedly already exists.');
        }
      } elseif (!$this->unlocks->insertIfAbsent($userId, $grant['unlock_id'])) {
        throw new RewardApplicationIntegrityException('Finalized unlock grant unexpectedly already exists.');
      }
    }
  }
}
