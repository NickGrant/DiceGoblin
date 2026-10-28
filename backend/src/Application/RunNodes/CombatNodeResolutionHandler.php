<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\CombatResolutionIntegrityException;
use DiceGoblins\Application\Rewards\VictoryItemGrantService;
use DiceGoblins\Application\Objectives\ObjectiveProgressService;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\RunModifierRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;

final class CombatNodeResolutionHandler implements RunNodeResolutionHandler
{
  public function __construct(
    private readonly RunNodeResolutionRepository $nodes,
    private readonly BattlePersistenceRepository $battles,
    private readonly CombatSnapshotAssembler $assembler,
    private readonly CombatResolver $resolver,
    private readonly CombatSeedDeriver $seeds,
    private readonly VictoryItemGrantService $itemGrants,
    private readonly RunModifierRepository $modifiers,
    private readonly ContentRegistry $content,
    private readonly ?ObjectiveProgressService $objectives = null,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.combat'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    $runId = (int)$run['id'];
    $nodeId = (int)$node['id'];
    $encounterId = $node['encounter_id'];
    if (!is_string($encounterId) || preg_match('/^encounter\.[a-z0-9][a-z0-9_.-]*$/', $encounterId) !== 1) {
      throw new CombatResolutionIntegrityException('Persisted combat encounter identity is invalid.');
    }
    $seed = $this->seeds->derive($runId, $nodeId, $encounterId);
    $activeModifiers = $this->modifiers->listActive($runId, true);
    $attackBonus = 0;
    foreach ($activeModifiers as $instance) {
      if ((int)$instance['run_id'] !== $runId || (int)$instance['id'] < 1
        || (int)$instance['source_node_id'] < 1 || (int)$instance['remaining_combats'] !== 1) {
        throw new CombatResolutionIntegrityException('Run modifier instance is invalid.');
      }
      try {
        $definition = $this->content->runModifier((string)$instance['modifier_id']);
      } catch (\Throwable $e) {
        throw new CombatResolutionIntegrityException('Run modifier definition is unavailable.', 0, $e);
      }
      if (($definition['kind'] ?? null) !== 'next_combat_attack_bonus'
        || !is_int($definition['attack_bonus'] ?? null)
        || $definition['attack_bonus'] < 1 || $definition['attack_bonus'] > 10) {
        throw new CombatResolutionIntegrityException('Run modifier definition is unsupported.');
      }
      $attackBonus += $definition['attack_bonus'];
      if ($attackBonus > 1000) throw new CombatResolutionIntegrityException('Run modifier stack exceeds the supported boundary.');
    }
    $assembled = $this->assembler->assemble($userId, (int)$run['squad_id'],
      $this->nodes->listParticipatingUnitsForUpdate($runId), $encounterId, $seed, $attackBonus);
    $combatResult = $this->resolver->resolve($assembled->input);
    $result = $combatResult->toArray();
    $battle = new FinalizedBattle((int)$result['engine_version'], (int)$result['playback_version'],
      $assembled->input->snapshot, $assembled->manifest, $result);
    $battleId = $this->battles->insertFinalized($runId, $nodeId, $battle);

    $terminalByKey = [];
    foreach ($result['combatants'] as $terminal) $terminalByKey[$terminal['key']] = $terminal;
    $terminalHp = [];
    foreach ($battle->manifestArray() as $participant) {
      if ($participant['side'] !== 'player') continue;
      $unitId = (int)$participant['unit_id'];
      $terminal = $terminalByKey[$participant['combatant_key']] ?? null;
      if (!is_array($terminal)) throw new CombatResolutionIntegrityException('Terminal player state is unavailable.');
      $hp = (int)$terminal['current_hp'];
      $this->nodes->persistUnitHp($runId, $unitId, $hp);
      $terminalHp[(string)$unitId] = $hp;
    }
    if ($this->objectives !== null) {
      $defeatedByType = [];
      foreach ($battle->manifestArray() as $participant) {
        if ($participant['side'] !== 'enemy') continue;
        $terminal = $terminalByKey[$participant['combatant_key']] ?? null;
        if (!is_array($terminal)) throw new CombatResolutionIntegrityException('Terminal enemy state is unavailable.');
        if ((int)$terminal['current_hp'] > 0) continue;
        $typeId = $participant['enemy_unit_type_id'];
        $defeatedByType[$typeId] = ($defeatedByType[$typeId] ?? 0) + 1;
      }
      foreach ($defeatedByType as $typeId => $count) $this->objectives->apply($userId, 'defeat_enemy_unit_type', $typeId, $count);
    }
    ksort($terminalHp, SORT_NUMERIC);
    $victory = $result['outcome'] === 'victory';
    $grantedItems = $victory ? $this->itemGrants->grant($userId, $encounterId) : [];
    if ($victory && $activeModifiers !== []) {
      $this->modifiers->consume($runId, array_map(static fn(array $row): int => (int)$row['id'], $activeModifiers));
    }
    $appliedModifiers = array_map(static fn(array $row): array => [
      'id' => (string)$row['id'], 'modifier_id' => (string)$row['modifier_id'],
      'remaining_combats' => $victory ? 0 : 1,
    ], $activeModifiers);

    return new RunNodeResolutionOutcome('combat', [
      'battle' => ['id' => (string)$battleId, 'outcome' => $result['outcome'],
        'engine_version' => $result['engine_version'], 'playback_version' => $result['playback_version'],
        'ending_round' => $result['ending_round'], 'ending_tick' => $result['ending_tick']],
      'terminal_player_hp' => $terminalHp,
      ...($appliedModifiers !== [] ? ['applied_modifiers' => $appliedModifiers] : []),
      ...($grantedItems !== [] ? ['item_grants' => $grantedItems] : []),
    ], !$victory);
  }
}
