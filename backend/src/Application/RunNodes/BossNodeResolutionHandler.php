<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use Closure;
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
    private readonly ?Closure $afterCombat = null,
    private readonly ?Closure $afterRewards = null,
    private readonly ?BossRewardProjector $projector = null,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.boss'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    $combat = $this->combat->resolve($userId, $playerState, $run, $node);
    if ($this->afterCombat !== null) ($this->afterCombat)();
    $facts = $combat->facts;
    $facts['rewards'] = null;
    if ($combat->runFailed) return new RunNodeResolutionOutcome('boss', $facts, true);

    try {
      $eventId = $node['event_id'] ?? null;
      if (!is_string($eventId) || preg_match('/^event\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $eventId) !== 1) {
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

      $facts['rewards'] = ($this->projector ?? new BossRewardProjector())->project($result, array_keys($ids));
      if ($this->afterRewards !== null) ($this->afterRewards)();
      return new RunNodeResolutionOutcome('boss', $facts);
    } catch (RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Boss reward resolution failed.', 0, $e);
    }
  }
}
