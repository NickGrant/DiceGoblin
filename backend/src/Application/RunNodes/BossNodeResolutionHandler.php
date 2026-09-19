<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use Throwable;

final class BossNodeResolutionHandler implements RunNodeResolutionHandler
{
  public function __construct(
    private readonly CombatNodeResolutionHandler $combat,
    private readonly RunNodeResolutionRepository $nodes,
    private readonly WarbandUnitRepository $units,
    private readonly UserUnlockRepository $unlocks,
    private readonly RewardApplicationService $rewards,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.boss'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    $combat = $this->combat->resolve($userId, $playerState, $run, $node);
    $facts = $combat->facts;
    $facts['rewards'] = null;
    if ($combat->runFailed) return new RunNodeResolutionOutcome('boss', $facts, true);

    try {
      $eventId = $node['event_id'] ?? null;
      if ($eventId !== 'event.farm_boss_completed') {
        throw new RunNodeResolutionIntegrityException('Persisted Boss event identity is invalid.');
      }
      $runId = (int)$run['id'];
      $nodeId = (int)$node['id'];
      $participants = $this->nodes->listParticipatingUnitsForUpdate($runId);
      if ($participants === []) throw new RunNodeResolutionIntegrityException('Run participation is empty.');
      $ids = [];
      foreach ($participants as $row) {
        $unitId = (int)($row['unit_id'] ?? 0);
        if ((int)($row['run_id'] ?? 0) !== $runId || $unitId < 1 || isset($ids[$unitId])) {
          throw new RunNodeResolutionIntegrityException('Run participation is invalid.');
        }
        $ids[$unitId] = true;
      }
      $owned = $this->units->listActiveByIdsForUser($userId, array_keys($ids), true);
      if (count($owned) !== count($ids)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
      $contextUnits = [];
      foreach ($owned as $unit) {
        $unitId = (int)$unit['id'];
        if (!isset($ids[$unitId])) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
        $contextUnits[] = ['unit_id' => $unitId, 'level' => (int)$unit['level'], 'xp' => (int)$unit['xp']];
      }
      $context = new RewardContext((int)$playerState['teeth'], (int)$playerState['raw_chaos'],
        $contextUnits, $this->unlocks->listIdsForUser($userId, true));
      $result = $this->rewards->apply($userId, $eventId, 'run_node', 'run_node:' . $nodeId, $context);

      $xp = null;
      $mountains = null;
      foreach ($result->entries() as $entry) {
        if ($entry['key'] === 'xp' && $entry['reward_type'] === 'unit_xp' && $entry['outcome'] === 'granted') {
          $grant = $entry['grant'];
          if ($grant['amount_per_unit'] !== 16 || $grant['target_scope'] !== 'participating_units') {
            throw new RunNodeResolutionIntegrityException('Boss XP reward is invalid.');
          }
          $xp = array_map(static fn(array $unit): array => [
            'unit_id' => $unit['unit_id'], 'amount' => 16,
            'level_before' => $unit['level_before'], 'xp_before' => $unit['xp_before'],
            'level_after' => $unit['level_after'], 'xp_after' => $unit['xp_after'],
          ], $grant['units']);
        } elseif ($entry['key'] === 'mountains' && $entry['reward_type'] === 'unlock'
          && in_array($entry['outcome'], ['granted', 'already_owned'], true)) {
          if (($entry['grant']['unlock_id'] ?? null) !== 'unlock.region.mountains') {
            throw new RunNodeResolutionIntegrityException('Boss unlock reward is invalid.');
          }
          $mountains = ['region_id' => 'region.mountains', 'outcome' => $entry['outcome']];
        } else {
          throw new RunNodeResolutionIntegrityException('Boss reward result is invalid.');
        }
      }
      if ($xp === null || $mountains === null) throw new RunNodeResolutionIntegrityException('Boss rewards are incomplete.');
      $facts['rewards'] = ['unit_xp' => $xp, 'mountains' => $mountains];
      return new RunNodeResolutionOutcome('boss', $facts);
    } catch (RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Boss reward resolution failed.', 0, $e);
    }
  }
}
