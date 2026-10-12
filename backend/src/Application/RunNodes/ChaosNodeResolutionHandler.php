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

final class ChaosNodeResolutionHandler implements ActionRunNodeResolutionHandler
{
  public function __construct(
    private readonly RunNodeResolutionRepository $nodes,
    private readonly WarbandUnitRepository $units,
    private readonly ContentRegistry $content,
    private readonly BaseLevelStatResolver $stats = new BaseLevelStatResolver(),
    private readonly ?\Closure $chooseRoll = null,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.chaos'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    throw new RunNodeResolutionException('invalid_node_request', 'Chaos requires an authorized encounter action.', 400);
  }

  public function resolveAction(int $userId, array $playerState, array $run, array $node,
    string $encounterId, string $actionId): RunNodeResolutionOutcome
  {
    if (($node['encounter_id'] ?? null) === null) {
      throw new RunNodeResolutionIntegrityException('Persisted Chaos encounter is missing.');
    }
    if ($node['encounter_id'] !== $encounterId) {
      throw new RunNodeResolutionException('invalid_node_request', 'Encounter does not match this node.', 400);
    }
    try {
      $encounter = $this->content->encounter($encounterId);
    } catch (Throwable) {
      throw new RunNodeResolutionIntegrityException('Persisted Chaos encounter is unavailable.');
    }
    if (($encounter['kind'] ?? null) !== 'non_combat' || ($encounter['family'] ?? null) !== 'chaos'
      || ($encounter['region_id'] ?? null) !== ($run['region_id'] ?? null)) {
      throw new RunNodeResolutionIntegrityException('Persisted Chaos encounter does not match this run.');
    }
    $action = null;
    foreach ($encounter['actions'] as $candidate) {
      if ($candidate['id'] === $actionId) { $action = $candidate; break; }
    }
    if ($action === null) throw new RunNodeResolutionException('invalid_node_request', 'Encounter action is unavailable.', 400);
    $outcomes = $action['effect']['outcomes'] ?? null;
    if (($action['effect']['type'] ?? null) !== 'random_participating_hp' || !is_array($outcomes) || count($outcomes) !== 2) {
      throw new RunNodeResolutionIntegrityException('Chaos outcome table is unsupported.');
    }
    $totalWeight = 0;
    foreach ($outcomes as $outcome) {
      $weight = $outcome['weight'] ?? null;
      $effect = $outcome['effect'] ?? null;
      $type = $effect['type'] ?? null;
      if (!is_int($weight) || $weight < 1 || $weight > 10000
        || !is_array($effect) || !in_array($type, ['restore_participating_units_to_max', 'damage_all_participating_units'], true)
        || ($type === 'restore_participating_units_to_max' && count($effect) !== 1)
        || ($type === 'damage_all_participating_units' && (count($effect) !== 2
          || !is_int($effect['amount'] ?? null) || $effect['amount'] < 1 || $effect['amount'] > 20))) {
        throw new RunNodeResolutionIntegrityException('Chaos outcome is unsupported.');
      }
      $totalWeight += $weight;
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
      $roll = $this->chooseRoll !== null ? ($this->chooseRoll)($totalWeight) : random_int(1, $totalWeight);
      if (!is_int($roll) || $roll < 1 || $roll > $totalWeight) {
        throw new RunNodeResolutionIntegrityException('Chaos outcome selection is invalid.');
      }
      $remaining = $roll;
      $selected = null;
      foreach ($outcomes as $outcome) {
        $remaining -= $outcome['weight'];
        if ($remaining <= 0) { $selected = $outcome; break; }
      }
      if ($selected === null) throw new RunNodeResolutionIntegrityException('Chaos outcome selection failed.');
      $effect = $selected['effect'];
      $hpChanges = [];
      foreach ($owned as $unit) {
        $unitId = (int)$unit['id'];
        if (!array_key_exists($unitId, $hpById)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
        $unitType = $this->content->unitType((string)$unit['unit_type_id']);
        $maximum = $this->stats->resolve($unitType['base_stats'], $unitType['growth_per_level'], (int)$unit['level'])->hp;
        $before = $hpById[$unitId];
        if ($before > $maximum) throw new RunNodeResolutionIntegrityException('Persisted run HP exceeds current maximum HP.');
        $after = $effect['type'] === 'restore_participating_units_to_max'
          ? $maximum : max(0, $before - $effect['amount']);
        $this->nodes->persistUnitHp($runId, $unitId, $after);
        $hpChanges[] = ['unit_id' => (string)$unitId, 'hp_before' => $before, 'hp_after' => $after, 'max_hp' => $maximum];
      }
      return new RunNodeResolutionOutcome('chaos', [
        'encounter_id' => $encounterId, 'action_id' => $actionId,
        'outcome_id' => $selected['id'], 'hp_changes' => $hpChanges,
      ]);
    } catch (RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Chaos resolution failed.', 0, $e);
    }
  }
}
