<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Inventory\InsufficientInventoryException;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\UserItemRepository;
use Throwable;

final class ContextualEncounterStepAdvancer
{
  public function __construct(
    private readonly ContentRegistry $content,
    private readonly UserItemRepository $items,
    private readonly RunNodeResolutionRepository $nodes,
  ) {}

  /** @param array<string,mixed> $run @param array<string,mixed> $node */
  public function tryAdvance(int $userId, array $run, array $node, string $encounterId,
    string $actionId): ?RunNodeResolutionOutcome
  {
    if (($node['encounter_id'] ?? null) !== $encounterId) return null;
    try {
      $encounter = $this->content->encounter($encounterId);
      $nodeType = $this->content->runNodeType((string)$node['node_type_id']);
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Persisted encounter step content is unavailable.', 0, $e);
    }
    if ($encounter['kind'] !== 'non_combat' || $encounter['region_id'] !== $run['region_id']
      || ($nodeType['encounter_kind'] ?? null) !== 'non_combat'
      || (isset($nodeType['encounter_family']) && ($encounter['family'] ?? null) !== $nodeType['encounter_family'])) {
      throw new RunNodeResolutionIntegrityException('Persisted encounter step does not match this run.');
    }
    $stepId = $node['encounter_step_id'] ?? null;
    $steps = $encounter['steps'] ?? [];
    if ($stepId !== null && ($steps === [] || $steps[0]['id'] !== $stepId)) {
      throw new RunNodeResolutionIntegrityException('Persisted encounter step is invalid.');
    }
    if ($steps === []) return null;
    $stage = $stepId === null ? $encounter : $steps[0];
    $action = null;
    foreach ($stage['actions'] as $candidate) {
      if ($candidate['id'] === $actionId) { $action = $candidate; break; }
    }
    if ($action === null) throw new RunNodeResolutionException('invalid_node_request', 'Encounter action is unavailable.', 400);
    if ($action['effect']['type'] !== 'advance_step') return null;
    if ($stepId !== null) throw new RunNodeResolutionIntegrityException('Encounter step transition is invalid.');
    $effect = $action['effect'];
    try {
      $this->items->decrement($userId, $effect['item_id'], $effect['quantity']);
    } catch (InsufficientInventoryException) {
      throw new RunNodeResolutionException('invalid_node_request', 'Encounter action is unavailable.', 400);
    }
    $this->nodes->advanceEncounterStep((int)$run['id'], (int)$node['id'], null, $effect['step_id']);
    return new RunNodeResolutionOutcome('interaction_step', [
      'encounter_id' => $encounterId, 'action_id' => $actionId, 'step_id' => $effect['step_id'],
    ], nodeCompleted: false);
  }
}
