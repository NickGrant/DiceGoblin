<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Objectives;

use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\UserObjectiveRepository;
use PDO;
use RuntimeException;

/** Applies one finalized server fact inside its owning gameplay transaction. */
final class ObjectiveProgressService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly UserObjectiveRepository $progress,
    private readonly ContentRegistry $content,
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
}
