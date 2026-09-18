<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\RunNodes\RunNodeResolutionHandler;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class ResolveRunNodeCommand
{
  private const OPERATION = 'resolve_run_node';
  /** @var array<string,RunNodeResolutionHandler> */
  private array $handlers = [];

  /** @param list<RunNodeResolutionHandler> $handlers */
  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly RunPersistenceRepository $runs,
    private readonly RunNodeResolutionRepository $nodes,
    private readonly IdempotencyRequestRepository $idempotency,
    array $handlers,
    private readonly Clock $clock,
  ) {
    foreach ($handlers as $handler) {
      $type = $handler->nodeTypeId();
      if (isset($this->handlers[$type])) throw new RuntimeException('Duplicate run-node resolution handler.');
      $this->handlers[$type] = $handler;
    }
  }

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
      if ($state === null) throw new RunNodeResolutionIntegrityException('Required player state is missing.');

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
        throw new RunNodeResolutionIntegrityException('Owned run participation is invalid.');
      }
      $node = $this->nodes->findNodeForUpdate($runId, $nodeId);
      if ($node === null) throw new RunNodeResolutionException('run_node_not_found', 'Run node is unavailable.', 404);
      if (($node['status'] ?? null) === 'completed') {
        throw new RunNodeResolutionException('run_node_already_resolved', 'Run node is already resolved.', 409);
      }
      if ($this->nodes->battleExists($runId, $nodeId)) {
        throw new RunNodeResolutionIntegrityException('Run node battle lifecycle is invalid.');
      }
      if (($run['status'] ?? null) !== 'active' || $run['ended_at'] !== null) {
        throw new RunNodeResolutionException('run_not_active', 'Run is not active.', 409);
      }
      if (($node['status'] ?? null) !== 'available') {
        throw new RunNodeResolutionException('run_node_unavailable', 'Run node is unavailable.', 409);
      }
      $handler = $this->handlers[(string)($node['node_type_id'] ?? '')] ?? null;
      if ($handler === null) throw new RunNodeResolutionException('run_node_unsupported', 'Run node type is not resolvable.', 422);

      $outcome = $handler->resolve($userId, $state, $run, $node);
      $completedAt = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
      $this->nodes->completeNode($runId, $nodeId, $completedAt);
      $available = [];
      $runStatus = 'active';
      $endedAt = null;
      if ($outcome->runFailed) {
        $this->nodes->failRun($userId, $runId, $completedAt);
        $runStatus = 'failed';
        $endedAt = $this->timestamp($completedAt);
      } else {
        $available = $this->nodes->unlockDirectOutgoingNodes($runId, $nodeId);
      }
      $revision = $this->playerState->incrementRevision($userId);
      $response = array_merge([
        'resolution_type' => $outcome->resolutionType,
      ], $outcome->facts, [
        'node' => ['id' => (string)$nodeId, 'status' => 'completed', 'completed_at' => $this->timestamp($completedAt)],
        'newly_available_node_ids' => array_map('strval', $available),
        'run' => ['id' => (string)$runId, 'status' => $runStatus, 'ended_at' => $endedAt],
        'player_revision' => $revision,
      ]);
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
