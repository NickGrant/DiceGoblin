<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunCombatRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class ResolveCombatNodeCommand
{
  private const OPERATION = 'resolve_run_node';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly RunPersistenceRepository $runs,
    private readonly RunCombatRepository $runCombat,
    private readonly BattlePersistenceRepository $battles,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly CombatSnapshotAssembler $assembler,
    private readonly CombatResolver $resolver,
    private readonly CombatSeedDeriver $seeds,
    private readonly Clock $clock,
  ) {}

  /** @return array<string,mixed> */
  public function execute(int $userId, int $runId, int $nodeId, ?string $providedKey): array
  {
    if ($userId < 1 || $runId < 1 || $nodeId < 1) {
      throw new RunNodeResolutionException('run_node_not_found', 'Run node is unavailable.', 404);
    }
    $key = IdempotencyKey::validate($providedKey);
    try {
      $hash = hash('sha256', json_encode(['run_id' => (string)$runId, 'node_id' => (string)$nodeId],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    } catch (JsonException $e) {
      throw new RunNodeResolutionException('invalid_node_request', 'Node resolution request is invalid.', 400);
    }

    try {
      $this->pdo->beginTransaction();
      $state = $this->playerState->getPlayerStateForUpdate($userId);
      if ($state === null) throw new CombatResolutionIntegrityException('Required player state is missing.');

      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        if ($prior['operation_type'] !== self::OPERATION || !hash_equals($prior['request_hash'], $hash)) {
          throw new IdempotencyConflictException('Idempotency key was already used for another request.');
        }
        $this->pdo->commit();
        return $prior['result'];
      }

      $run = $this->runs->findOwnedRunForUpdate($userId, $runId);
      if ($run === null) throw new RunNodeResolutionException('run_node_not_found', 'Run node is unavailable.', 404);
      if ((int)($run['user_id'] ?? 0) !== $userId || $run['squad_id'] === null
        || (int)($run['squad_user_id'] ?? 0) !== $userId) {
        throw new CombatResolutionIntegrityException('Owned run participation is invalid.');
      }
      $node = $this->runCombat->findNodeForUpdate($runId, $nodeId);
      if ($node === null) throw new RunNodeResolutionException('run_node_not_found', 'Run node is unavailable.', 404);
      if ($this->battles->findForRunNode($runId, $nodeId) !== null || ($node['status'] ?? null) === 'completed') {
        throw new RunNodeResolutionException('run_node_already_resolved', 'Run node is already resolved.', 409);
      }
      if (($run['status'] ?? null) !== 'active' || $run['ended_at'] !== null) {
        throw new RunNodeResolutionException('run_not_active', 'Run is not active.', 409);
      }
      if (($node['status'] ?? null) !== 'available') {
        throw new RunNodeResolutionException('run_node_unavailable', 'Run node is unavailable.', 409);
      }
      if (($node['node_type_id'] ?? null) !== 'run_node_type.combat') {
        throw new RunNodeResolutionException('run_node_unsupported', 'Run node type is not resolvable.', 422);
      }
      $encounterId = $node['encounter_id'];
      if (!is_string($encounterId) || $encounterId === '') {
        throw new RunNodeResolutionException('run_node_encounter_invalid', 'Combat encounter is unavailable.', 422);
      }

      $seed = $this->seeds->derive($runId, $nodeId, $encounterId);
      $assembled = $this->assembler->assemble(
        $userId,
        (int)$run['squad_id'],
        $this->runCombat->listParticipatingUnitsForUpdate($runId),
        $encounterId,
        $seed,
      );
      $combatResult = $this->resolver->resolve($assembled->input);
      $result = $combatResult->toArray();
      $battle = new FinalizedBattle(
        (int)$result['engine_version'],
        (int)$result['playback_version'],
        $assembled->input->snapshot,
        $assembled->manifest,
        $result,
      );
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
        $this->runCombat->persistUnitHp($runId, $unitId, $hp);
        $terminalHp[(string)$unitId] = $hp;
      }
      ksort($terminalHp, SORT_NUMERIC);

      $completedAt = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
      $this->runCombat->completeNode($runId, $nodeId, $completedAt);
      $available = [];
      $runStatus = 'active';
      $endedAt = null;
      if ($result['outcome'] === 'victory') {
        $available = $this->runCombat->unlockDirectOutgoingNodes($runId, $nodeId);
      } else {
        $this->runCombat->failRun($userId, $runId, $completedAt);
        $runStatus = 'failed';
        $endedAt = $this->timestamp($completedAt);
      }
      $revision = $this->playerState->incrementRevision($userId);
      $response = [
        'battle' => ['id' => (string)$battleId, 'outcome' => $result['outcome'],
          'engine_version' => $result['engine_version'], 'playback_version' => $result['playback_version'],
          'ending_round' => $result['ending_round'], 'ending_tick' => $result['ending_tick']],
        'node' => ['id' => (string)$nodeId, 'status' => 'completed', 'completed_at' => $this->timestamp($completedAt)],
        'newly_available_node_ids' => array_map('strval', $available),
        'terminal_player_hp' => $terminalHp,
        'run' => ['id' => (string)$runId, 'status' => $runStatus, 'ended_at' => $endedAt],
        'player_revision' => $revision,
      ];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $response);
      $finalized = $this->idempotency->getForUser($userId, $key);
      if ($finalized === null) throw new RuntimeException('Finalized node-resolution receipt is unavailable.');
      $this->pdo->commit();
      return $finalized['result'];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }

  private function timestamp(DateTimeImmutable $value): string
  {
    return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
  }
}
