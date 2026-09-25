<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\RegionAvailabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Energy\EnergySpendCalculator;
use DiceGoblins\Domain\Energy\InsufficientEnergyException;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\RunGeneration\RunGraphGenerator;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class StartRunCommand
{
  private const OPERATION = 'start_run';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly SquadRepository $squads,
    private readonly RunPersistenceRepository $runs,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly UserUnlockRepository $unlocks,
    private readonly ContentRegistry $content,
    private readonly RegionAvailabilityPolicy $regionAvailability,
    private readonly RunGraphGenerator $generator,
    private readonly RunParticipationValidator $participation,
    private readonly EnergySpendCalculator $energy,
    private readonly Clock $clock,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    $input = StartRunRequest::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    try {
      $hash = hash('sha256', json_encode($input->canonicalRequest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    } catch (JsonException $e) {
      throw new RunStartException('invalid_run_request', 'Run request is invalid.', 422);
    }

    try {
      $this->pdo->beginTransaction();
      $state = $this->playerState->getPlayerStateForUpdate($userId);
      if ($state === null) throw new RunStartIntegrityException('Required player state is missing.');

      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        if ($prior['operation_type'] !== self::OPERATION || !hash_equals($prior['request_hash'], $hash)) {
          throw new IdempotencyConflictException('Idempotency key was already used for another request.');
        }
        $this->pdo->commit();
        return $prior['result'];
      }

      $this->validateRegion($userId, $input->regionId);
      if ($this->runs->findActiveRunIdForUser($userId) !== null) {
        throw new RunStartException('active_run_exists', 'An active run already exists.', 409);
      }

      $squadId = $state['active_squad_id'] !== null ? (int)$state['active_squad_id'] : null;
      if ($squadId === null) {
        throw new RunStartException('active_squad_required', 'An active squad is required.', 409);
      }
      if ($this->squads->getForUser($userId, $squadId, true) === null) {
        throw new RunStartIntegrityException('Persisted active squad ownership is invalid.');
      }
      $unitIds = $this->participation->validate(
        $userId,
        $this->squads->listFormationRowsForSquad($squadId, true),
      );

      $now = $this->clock->now();
      $utc = new DateTimeZone('UTC');
      $lastRegenerationAt = new DateTimeImmutable((string)$state['energy_last_regen_at'], $utc);
      try {
        $spend = $this->energy->spend(
          (int)$state['energy_current'],
          $lastRegenerationAt,
          $this->content->energyNormalMaximum(),
          $this->content->energyRegenerationPerHour(),
          $this->content->runEnergyCost(),
          $now,
        );
      } catch (InsufficientEnergyException) {
        throw new RunStartException('insufficient_energy', 'There is not enough Energy to start a run.', 409);
      }

      $generation = $this->content->runGenerationForRegion($input->regionId);
      $graph = $this->generator->generate($generation);
      $runId = $this->runs->createRun($userId, $input->regionId, $squadId);
      $nodeIds = $this->runs->insertNodes($runId, $graph['nodes']);
      $this->runs->insertEdges($runId, $graph['edges'], $nodeIds);
      $this->runs->insertParticipatingUnits($runId, $unitIds);

      $revision = $this->playerState->persistEnergyAndIncrementRevision(
        $userId,
        $spend->persistedCurrent,
        $spend->persistedLastRegenerationAt,
      );
      $result = [
        'run' => [
          'id' => (string)$runId,
          'region_id' => $input->regionId,
          'squad_id' => (string)$squadId,
          'status' => 'active',
        ],
        'energy' => $spend->view->toArray(),
        'player_revision' => $revision,
      ];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $hash, $result);
      $finalized = $this->idempotency->getForUser($userId, $key);
      if ($finalized === null) throw new RuntimeException('Finalized run-start receipt is unavailable.');
      $this->pdo->commit();
      return $finalized['result'];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }

  private function validateRegion(int $userId, string $regionId): void
  {
    if (!$this->regionAvailability->isPlayable($regionId)) {
      throw new RunStartException('run_region_unsupported', 'The requested region is unavailable.', 422);
    }
    if ($regionId !== $this->content->startingRegionId()) {
      $available = $this->regionAvailability->availableRegionIds($this->unlocks->listIdsForUser($userId, true));
      if (!in_array($regionId, $available, true)) {
        throw new RunStartException('run_region_locked', 'The requested region is locked.', 403);
      }
    }
  }
}
