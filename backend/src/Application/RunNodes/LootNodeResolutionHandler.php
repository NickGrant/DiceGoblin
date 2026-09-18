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

final class LootNodeResolutionHandler implements RunNodeResolutionHandler
{
  public function __construct(
    private readonly RunNodeResolutionRepository $nodes,
    private readonly WarbandUnitRepository $units,
    private readonly UserUnlockRepository $unlocks,
    private readonly RewardApplicationService $rewards,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.loot'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    try {
      $eventId = $node['event_id'] ?? null;
      if (!is_string($eventId) || preg_match('/^event\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $eventId) !== 1) {
        throw new RunNodeResolutionIntegrityException('Persisted Loot event identity is invalid.');
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
      $ownedUnits = $this->units->listActiveByIdsForUser($userId, array_keys($ids), true);
      if (count($ownedUnits) !== count($ids)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
      $contextUnits = [];
      foreach ($ownedUnits as $unit) {
        $unitId = (int)$unit['id'];
        if (!isset($ids[$unitId])) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
        $contextUnits[] = ['unit_id' => $unitId, 'level' => (int)$unit['level'], 'xp' => (int)$unit['xp']];
      }
      $context = new RewardContext((int)$playerState['teeth'], (int)$playerState['raw_chaos'],
        $contextUnits, $this->unlocks->listIdsForUser($userId, true));
      $result = $this->rewards->apply($userId, $eventId, 'run_node', 'run_node:' . $nodeId, $context);

      $wallet = [];
      $granted = [];
      foreach ($result->entries() as $entry) {
        if ($entry['outcome'] !== 'granted') continue;
        if ($entry['reward_type'] !== 'currency') {
          throw new RunNodeResolutionIntegrityException('Loot reward type is unsupported by this response projection.');
        }
        $grant = $entry['grant'];
        $wallet[$grant['currency_id']] = $grant['balance_after'];
        $granted[] = ['reward_type' => 'currency', 'currency_id' => $grant['currency_id'], 'amount' => $grant['amount']];
      }
      if ($wallet === [] || $granted === []) throw new RunNodeResolutionIntegrityException('Loot produced no player-visible grant.');
      ksort($wallet, SORT_STRING);
      return new RunNodeResolutionOutcome('loot', ['wallet' => $wallet, 'granted_rewards' => $granted]);
    } catch (RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Loot reward resolution failed.', 0, $e);
    }
  }
}
