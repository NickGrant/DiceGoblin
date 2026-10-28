<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\UserObjectiveRepository;
use DiceGoblins\Support\ClientSafeInteger;
use RuntimeException;

final class ObjectiveQuery
{
  public function __construct(
    private readonly UserObjectiveRepository $progress,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{objectives:list<array<string,mixed>>} */
  public function execute(int $userId): array
  {
    $states = [];
    foreach ($this->progress->listStatesForUser($userId) as $row) {
      $this->knownObjective($row['objective_id']);
      if (isset($states[$row['objective_id']])) throw new RuntimeException('Duplicate persisted objective identity.');
      $states[$row['objective_id']] = ['completed_at' => $row['completed_at'], 'counts' => []];
    }
    foreach ($this->progress->listCountsForUser($userId) as $row) {
      $objectiveId = $row['objective_id'];
      if (!isset($states[$objectiveId])) throw new RuntimeException('Orphaned objective requirement count.');
      $definition = $this->knownObjective($objectiveId);
      $requirements = array_column($definition['requirements'], null, 'id');
      if (!isset($requirements[$row['requirement_id']])) {
        throw new RuntimeException('Persisted objective requirement identity is unknown.');
      }
      $raw = $row['current_count'];
      $maximum = (string)ClientSafeInteger::MAXIMUM;
      if (preg_match('/^[1-9][0-9]*$/D', $raw) !== 1 || strlen($raw) > strlen($maximum)
        || (strlen($raw) === strlen($maximum) && strcmp($raw, $maximum) > 0)) {
        throw new RuntimeException('Persisted objective count is incoherent.');
      }
      $count = (int)$raw;
      if ($count > $requirements[$row['requirement_id']]['needed']) {
        throw new RuntimeException('Persisted objective count exceeds its authored target.');
      }
      if (isset($states[$objectiveId]['counts'][$row['requirement_id']])) {
        throw new RuntimeException('Duplicate persisted objective requirement count.');
      }
      $states[$objectiveId]['counts'][$row['requirement_id']] = $count;
    }
    $objectives = [];
    foreach ($this->content->definitionsOfType('objective') as $id => $definition) {
      $state = $states[$id] ?? null;
      $requirements = [];
      $allMet = true;
      foreach ($definition['requirements'] as $requirement) {
        $current = $state['counts'][$requirement['id']] ?? 0;
        if ($current !== $requirement['needed']) $allMet = false;
        $requirements[] = ['id' => $requirement['id'], 'label' => $requirement['label'],
          'current' => $current, 'needed' => $requirement['needed']];
      }
      if ($state !== null && (($state['completed_at'] !== null) !== $allMet)) {
        throw new RuntimeException('Persisted objective completion is incoherent.');
      }
      $status = $state === null ? 'unstarted' : ($state['completed_at'] === null ? 'active' : 'completed');
      $objectives[] = ['id' => $id, 'title' => $definition['title'],
        'description' => $definition['description'], 'category' => $definition['category'],
        'status' => $status, 'requirements' => $requirements];
    }
    return ['objectives' => $objectives];
  }

  /** @return array<string,mixed> */
  private function knownObjective(string $id): array
  {
    try { return $this->content->objective($id); }
    catch (ContentValidationException $e) { throw new RuntimeException('Persisted objective identity is unknown.', 0, $e); }
  }
}
