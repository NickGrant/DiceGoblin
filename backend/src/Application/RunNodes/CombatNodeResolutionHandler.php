<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\CombatResolutionIntegrityException;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;

final class CombatNodeResolutionHandler implements RunNodeResolutionHandler
{
  public function __construct(
    private readonly RunNodeResolutionRepository $nodes,
    private readonly BattlePersistenceRepository $battles,
    private readonly CombatSnapshotAssembler $assembler,
    private readonly CombatResolver $resolver,
    private readonly CombatSeedDeriver $seeds,
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
    $assembled = $this->assembler->assemble($userId, (int)$run['squad_id'],
      $this->nodes->listParticipatingUnitsForUpdate($runId), $encounterId, $seed);
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
    ksort($terminalHp, SORT_NUMERIC);

    return new RunNodeResolutionOutcome('combat', [
      'battle' => ['id' => (string)$battleId, 'outcome' => $result['outcome'],
        'engine_version' => $result['engine_version'], 'playback_version' => $result['playback_version'],
        'ending_round' => $result['ending_round'], 'ending_tick' => $result['ending_tick']],
      'terminal_player_hp' => $terminalHp,
    ], $result['outcome'] !== 'victory');
  }
}
