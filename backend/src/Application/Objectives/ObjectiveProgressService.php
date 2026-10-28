<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Objectives;

use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserObjectiveRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use PDO;
use RuntimeException;

/** Applies one finalized server fact inside its owning gameplay transaction. */
final class ObjectiveProgressService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly UserObjectiveRepository $progress,
    private readonly ContentRegistry $content,
    private readonly PlayerStateRepository $players,
    private readonly WarbandUnitRepository $units,
    private readonly UserUnlockRepository $unlocks,
    private readonly RewardApplicationService $rewards,
  ) {}

  public function apply(int $userId, string $kind, string $targetId, int $count = 1): bool
  {
    if (!$this->pdo->inTransaction()) throw new RuntimeException('Objective progress requires the owning transaction.');
    if ($userId < 1 || $count < 1 || !in_array($kind,
      ['defeat_enemy_unit_type', 'complete_region_run', 'complete_dialogue'], true)) {
      throw new RuntimeException('Objective gameplay fact is invalid.');
    }
    $definitions = $this->content->definitionsOfType('objective');
    if ($definitions === []) return false;
    $current = (new ObjectiveQuery($this->progress, $this->content))->execute($userId)['objectives'];
    $byId = array_column($current, null, 'id');
    $changed = false;
    foreach ($definitions as $id => $definition) {
      if ($byId[$id]['status'] === 'completed') continue;
      $counts = array_column($byId[$id]['requirements'], null, 'id');
      foreach ($definition['requirements'] as $requirement) {
        if ($requirement['kind'] !== $kind || $requirement['target_id'] !== $targetId) continue;
        $before = $counts[$requirement['id']]['current'];
        $after = min($requirement['needed'], $before + min($count, $requirement['needed']));
        if ($after === $before) continue;
        $this->progress->insertActiveIfAbsent($userId, $id);
        $this->progress->setCount($userId, $id, $requirement['id'], $after);
        $changed = true;
      }
    }
    return $changed;
  }

  /** Complete all satisfied objectives after the owning gameplay action's own rewards. @param list<int> $unitIds */
  public function finalizeSatisfied(int $userId, array $unitIds = []): bool
  {
    if (!$this->pdo->inTransaction() || $userId < 1) {
      throw new RuntimeException('Objective completion requires the owning transaction.');
    }
    $definitions = $this->content->definitionsOfType('objective');
    if ($definitions === []) return false;
    $entries = (new ObjectiveQuery($this->progress, $this->content))->execute($userId)['objectives'];
    $completed = false;
    foreach ($entries as $entry) {
      if ($entry['status'] !== 'active') continue;
      foreach ($entry['requirements'] as $requirement) {
        if ($requirement['current'] !== $requirement['needed']) continue 2;
      }
      $id = $entry['id'];
      if (!$this->progress->markCompleted($userId, $id)) {
        throw new RuntimeException('Satisfied objective could not complete exactly once.');
      }
      if (isset($definitions[$id]['completion_event_id'])) {
        $this->rewards->apply($userId, $definitions[$id]['completion_event_id'], 'objective_completion', $id,
          $this->rewardContext($userId, $unitIds));
      }
      $completed = true;
    }
    return $completed;
  }

  /** @param list<int> $unitIds */
  private function rewardContext(int $userId, array $unitIds): RewardContext
  {
    if (count($unitIds) !== count(array_unique($unitIds)) || array_filter($unitIds, static fn(int $id): bool => $id < 1) !== []) {
      throw new RuntimeException('Objective reward participation is invalid.');
    }
    $state = $this->players->getPlayerStateForUpdate($userId);
    if ($state === null) throw new RuntimeException('Objective reward player state is unavailable.');
    $rows = $this->units->listActiveByIdsForUser($userId, $unitIds, true);
    if (count($rows) !== count($unitIds)) throw new RuntimeException('Objective reward participation is unavailable.');
    $participants = array_map(static fn(array $row): array => [
      'unit_id' => (int)$row['id'], 'level' => (int)$row['level'], 'xp' => (int)$row['xp'],
    ], $rows);
    return new RewardContext($state['teeth'], $state['raw_chaos'], $participants,
      $this->unlocks->listIdsForUser($userId, true));
  }
}
