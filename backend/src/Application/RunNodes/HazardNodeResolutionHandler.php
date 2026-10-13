<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use Throwable;

final class HazardNodeResolutionHandler implements ActionRunNodeResolutionHandler
{
  public function __construct(
    private readonly RunNodeResolutionRepository $nodes,
    private readonly WarbandUnitRepository $units,
    private readonly ContentRegistry $content,
    private readonly BaseLevelStatResolver $stats = new BaseLevelStatResolver(),
    private readonly ?\Closure $chooseIndex = null,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.hazard'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    throw new RunNodeResolutionException('invalid_node_request', 'Hazard requires an authorized encounter action.', 400);
  }

  public function resolveAction(int $userId, array $playerState, array $run, array $node,
    string $encounterId, string $actionId): RunNodeResolutionOutcome
  {
    if (($node['encounter_id'] ?? null) === null) {
      throw new RunNodeResolutionIntegrityException('Persisted Hazard encounter is missing.');
    }
    if (($node['encounter_id'] ?? null) !== $encounterId) {
      throw new RunNodeResolutionException('invalid_node_request', 'Encounter does not match this node.', 400);
    }
    try {
      $encounter = $this->content->encounter($encounterId);
    } catch (Throwable) {
      throw new RunNodeResolutionIntegrityException('Persisted Hazard encounter is unavailable.');
    }
    if (($encounter['kind'] ?? null) !== 'non_combat' || ($encounter['family'] ?? null) !== 'hazard'
      || ($encounter['region_id'] ?? null) !== ($run['region_id'] ?? null)) {
      throw new RunNodeResolutionIntegrityException('Persisted Hazard encounter does not match this run.');
    }
    $stepId = $node['encounter_step_id'] ?? null;
    if ($stepId !== null && (!isset($encounter['steps'][0]) || $encounter['steps'][0]['id'] !== $stepId)) {
      throw new RunNodeResolutionIntegrityException('Persisted Hazard step is invalid.');
    }
    $stage = $stepId === null ? $encounter : $encounter['steps'][0];
    $action = null;
    foreach ($stage['actions'] as $candidate) {
      if ($candidate['id'] === $actionId) { $action = $candidate; break; }
    }
    if ($action === null) throw new RunNodeResolutionException('invalid_node_request', 'Encounter action is unavailable.', 400);
    $effect = $action['effect'];
    if (!in_array($effect['type'] ?? null, ['damage_random_participating_unit', 'damage_all_participating_units'], true)
      || !is_int($effect['amount'] ?? null) || $effect['amount'] < 1 || $effect['amount'] > 20) {
      throw new RunNodeResolutionIntegrityException('Hazard effect is unsupported.');
    }
    try {
      $runId = (int)$run['id'];
      $participants = $this->nodes->listParticipatingUnitsForUpdate($runId);
      if ($participants === []) throw new RunNodeResolutionIntegrityException('Run participation is empty.');
      $hpById = [];
      foreach ($participants as $row) {
        $unitId = (int)($row['unit_id'] ?? 0);
        $hp = $row['current_hp'] ?? null;
        if ((int)($row['run_id'] ?? 0) !== $runId || $unitId < 1 || isset($hpById[$unitId]) || !is_int($hp) || $hp < 0) {
          throw new RunNodeResolutionIntegrityException('Run participation HP is invalid.');
        }
        $hpById[$unitId] = $hp;
      }
      $owned = $this->units->listActiveByIdsForUser($userId, array_keys($hpById), true);
      if (count($owned) !== count($hpById)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
      $targetId = null;
      if ($effect['type'] === 'damage_random_participating_unit') {
        $index = $this->chooseIndex !== null ? ($this->chooseIndex)(count($owned)) : random_int(0, count($owned) - 1);
        if (!is_int($index) || $index < 0 || $index >= count($owned)) {
          throw new RunNodeResolutionIntegrityException('Hazard target selection is invalid.');
        }
        $targetId = (int)$owned[$index]['id'];
      }
      $damage = [];
      foreach ($owned as $unit) {
        $unitId = (int)$unit['id'];
        if (!array_key_exists($unitId, $hpById)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
        $type = $this->content->unitType((string)$unit['unit_type_id']);
        $maximum = $this->stats->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
        $before = $hpById[$unitId];
        if ($before > $maximum) throw new RunNodeResolutionIntegrityException('Persisted run HP exceeds current maximum HP.');
        if ($targetId !== null && $unitId !== $targetId) continue;
        $after = max(0, $before - $effect['amount']);
        $this->nodes->persistUnitHp($runId, $unitId, $after);
        $damage[] = ['unit_id' => (string)$unitId, 'hp_before' => $before, 'hp_after' => $after, 'max_hp' => $maximum];
      }
      return new RunNodeResolutionOutcome('hazard', [
        'encounter_id' => $encounterId, 'action_id' => $actionId, 'damage' => $damage,
      ]);
    } catch (RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Hazard resolution failed.', 0, $e);
    }
  }
}
