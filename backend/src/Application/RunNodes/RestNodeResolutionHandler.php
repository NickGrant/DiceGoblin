<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use Throwable;

final class RestNodeResolutionHandler implements ActionRunNodeResolutionHandler
{
  public function __construct(
    private readonly RunNodeResolutionRepository $nodes,
    private readonly WarbandUnitRepository $units,
    private readonly ContentRegistry $content,
    private readonly BaseLevelStatResolver $stats = new BaseLevelStatResolver(),
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.rest'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    throw new RunNodeResolutionException('invalid_node_request', 'Rest requires an authorized encounter action.', 400);
  }

  public function resolveAction(int $userId, array $playerState, array $run, array $node,
    string $encounterId, string $actionId): RunNodeResolutionOutcome
  {
    if (($node['encounter_id'] ?? null) !== $encounterId) {
      throw new RunNodeResolutionException('invalid_node_request', 'Encounter does not match this node.', 400);
    }
    try {
      $encounter = $this->content->encounter($encounterId);
    } catch (Throwable) {
      throw new RunNodeResolutionIntegrityException('Persisted Rest encounter is unavailable.');
    }
    if (($encounter['kind'] ?? null) !== 'non_combat' || ($encounter['region_id'] ?? null) !== ($run['region_id'] ?? null)) {
      throw new RunNodeResolutionIntegrityException('Persisted Rest encounter does not match this run.');
    }
    $action = null;
    foreach ($encounter['actions'] as $candidate) {
      if ($candidate['id'] === $actionId) { $action = $candidate; break; }
    }
    if ($action === null) throw new RunNodeResolutionException('invalid_node_request', 'Encounter action is unavailable.', 400);
    if (($action['effect']['type'] ?? null) !== 'restore_participating_units_to_max') {
      throw new RunNodeResolutionIntegrityException('Rest effect is unsupported.');
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
      $ownedUnits = $this->units->listActiveByIdsForUser($userId, array_keys($hpById), true);
      if (count($ownedUnits) !== count($hpById)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
      $healing = [];
      foreach ($ownedUnits as $unit) {
        $unitId = (int)$unit['id'];
        if (!array_key_exists($unitId, $hpById)) throw new RunNodeResolutionIntegrityException('Run participation ownership is invalid.');
        $type = $this->content->unitType((string)$unit['unit_type_id']);
        $maximum = $this->stats->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
        $before = $hpById[$unitId];
        if ($before > $maximum) throw new RunNodeResolutionIntegrityException('Persisted run HP exceeds current maximum HP.');
        $this->nodes->persistUnitHp($runId, $unitId, $maximum);
        $healing[] = ['unit_id' => (string)$unitId, 'hp_before' => $before, 'hp_after' => $maximum, 'max_hp' => $maximum];
      }
      return new RunNodeResolutionOutcome('rest', [
        'encounter_id' => $encounterId, 'action_id' => $actionId, 'healing' => $healing,
      ]);
    } catch (RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Rest resolution failed.', 0, $e);
    }
  }
}
