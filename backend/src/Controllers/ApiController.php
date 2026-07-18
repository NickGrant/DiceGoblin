<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Controllers\ApiController.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Controllers;

use DateTimeImmutable;
use DateTimeZone;

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;

use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\DiceAffixService;
use DiceGoblins\Services\EnergyService;
use DiceGoblins\Services\ProfileService;
use DiceGoblins\Services\ProfileDtoMapper;
use DiceGoblins\Services\RunGraphGenerator;
use DiceGoblins\Services\RunLifecycleService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\SquadCapacityService;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Services\UserDataSyncService;
use DiceGoblins\Services\UserUnlockService;

use PDO;
use RuntimeException;
use Throwable;

final class ApiController
{
  use RequiresCsrf;

  /**
   * GET /health
   */
  public function health(): void
  {
    $dbOk = false;

    try {
      $pdo = Db::pdo();
      $pdo->query('SELECT 1')->fetchColumn();
      $dbOk = true;
    } catch (Throwable $e) {
      $dbOk = false;
    }

    Response::json([
      'ok' => true,
      'service' => 'dice-goblins-backend',
      'env' => Env::get('APP_ENV', 'unknown'),
      'time' => gmdate('c'),
      'db_ok' => $dbOk,
    ]);
  }

  /**
   * GET /api/v1/session
   */
  public function session(): void
  {
    $services = $this->services();

    try {
      $payload = $services['sessionService']->getSessionPayload();

      Response::json([
        'ok' => true,
        'data' => $payload,
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * GET /api/v1/profile
   *
   * Hydrates the player state used by the frontend: squads, units, dice, energy, unlocks, active run, etc.
   */
  public function profile(): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    try {
      $services['userDataSyncService']->syncForUser($userId);
      $data = $services['profileService']->getProfile($userId);

      Response::json([
        'ok' => true,
        'data' => $data,
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * GET /api/v1/runs/current
   *
   * Returns the user's current active run and its map graph.
   */
  public function currentRun(): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    try {
      $run = $services['runRepo']->getActiveRunForUser($userId);

      if ($run === null) {
        Response::json([
          'ok' => true,
          'data' => [
            'run' => null,
            'map' => null,
          ],
        ]);
        return;
      }

      $runId = (int)$run['run_id'];
      $services['runNodeRepo']->syncAvailableNodesFromClearedParents($runId);
      $regionId = (int)($run['region_id'] ?? 0);
      $region = $regionId > 0 ? $services['regionRepo']->getRegionById($regionId) : null;
      if ($region !== null) {
        $run['region_slug'] = (string)$region['slug'];
        $run['region_name'] = (string)$region['name'];
        $run['region_theme'] = (string)$region['theme'];
        $run['recommended_level'] = (int)$region['recommended_level'];
        $run['energy_cost'] = (int)$region['energy_cost'];
      }

      Response::json([
        'ok' => true,
        'data' => [
          'run' => $run,
          'map' => [
            'nodes' => $services['runRepo']->getRunNodes($runId),
            'edges' => $services['runRepo']->getRunEdges($runId),
          ],
          'run_unit_state' => $services['runRepo']->getRunUnitState($runId),
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * POST /api/v1/runs
   *
   * Creates a new run and generates its nodes/edges.
   *
   * Body:
   *  {
   *    "region_id": 1,
   *    "abandon_active": false
   *  }
   *
   * Response:
   *  { "ok": true }
   */
  public function createRun(): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    // CSRF required for state-changing calls.
    if (!$this->requireCsrf($services['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'invalid_request',
          'message' => 'Invalid JSON body.',
        ],
      ], 400);
      return;
    }

    $regionId = (int)($body['region_id'] ?? 1);
    if ($regionId <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'invalid_request',
          'message' => 'region_id is required.',
        ],
      ], 400);
      return;
    }

    $abandonActive = !empty($body['abandon_active']);

    /** @var PDO $pdo */
    $pdo = $services['pdo'];

    try {
      $pdo->beginTransaction();

      // Enforce at-most-one active run.
      $active = $services['runRepo']->getActiveRunForUser($userId);
      if ($active !== null) {
        if (!$abandonActive) {
          $pdo->rollBack();
          Response::json([
            'ok' => false,
            'error' => [
              'code' => 'run_already_active',
              'message' => 'You already have an active run.',
              'details' => [
                'active_run_id' => $active['run_id'],
              ],
            ],
          ], 409);
          return;
        }

        $services['runLifecycleService']->abandonRun($userId, (int)$active['run_id']);
      }

      // Validate region.
      $region = $services['regionRepo']->getRegionById($regionId);
      if ($region === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'region_not_found',
            'message' => 'Region not found.',
          ],
        ], 404);
        return;
      }

      if (!$region['is_enabled']) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'region_disabled',
            'message' => 'Region is disabled.',
          ],
        ], 403);
        return;
      }

      // Ensure unlocked.
      if (!$services['regionRepo']->isRegionUnlocked($userId, $regionId)) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'region_locked',
            'message' => 'Region is not unlocked for this user.',
          ],
        ], 403);
        return;
      }

      $activeTeam = $services['teamRepo']->getActiveTeamForUser($userId);
      if ($activeTeam === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'validation_error',
            'message' => 'No active squad found. Create and activate a squad before starting a run.',
          ],
        ], 400);
        return;
      }

      (new UnitLoadoutService($pdo))->ensureStateForUser($userId);

      $squadUnitCap = (new SquadCapacityService($pdo))->getCapForUser($userId);
      $teamUnitIds = $services['teamRepo']->getTeamUnitIds($userId, (int)$activeTeam['id']);
      if (count($teamUnitIds) > $squadUnitCap) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'validation_error',
            'message' => "Active squad exceeds your current {$squadUnitCap}-unit cap. Trim the squad before starting a run.",
          ],
        ], 409);
        return;
      }

      // Spend energy (regen + deduct) under the same transaction.
      $energyCost = (int)$region['energy_cost'];
      $this->consumeEnergyWithRegenInTransaction($services['energyRepo'], $userId, $energyCost);

      // Create run + graph.
      $seed = random_int(1, 9223372036854775807);
      $graphGenerator = new RunGraphGenerator($pdo);
      $graph = $graphGenerator->generate($regionId, (string)$region['slug'], (string)$seed);
      $treasureSenseRevealChance = $this->activeTeamTreasureSenseRevealChance($pdo, $teamUnitIds);
      if ($treasureSenseRevealChance > 0.0) {
        $graph = $graphGenerator->applyTreasureSenseReveal($regionId, $graph, (string)$seed, $treasureSenseRevealChance);
      }

      $created = $services['runRepo']->createRunGraph(
        $userId,
        $regionId,
        (string)$seed,
        $graph['nodes'],
        $graph['edges']
      );
      $services['runRepo']->seedRunUnitStateFromTeam(
        (int)$created['run_id'],
        $userId,
        (int)$activeTeam['id']
      );

      $pdo->commit();

      Response::json(['ok' => true]);
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      // Map known domain errors to stable API codes where helpful.
      $msg = $e->getMessage();

      if ($msg === 'insufficient_energy') {
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'insufficient_energy',
            'message' => 'Not enough energy to start a run.',
          ],
        ], 409);
        return;
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'invalid_request',
          'message' => $msg !== '' ? $msg : 'Invalid request.',
        ],
      ], 400);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * POST /api/v1/runs/:runId/abandon
   *
   * Ends an active run as abandoned and applies run-end cleanup rules.
   */
  public function abandonRun(?string $runId = null): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    if (!$this->requireCsrf($services['csrfService'])) {
      return;
    }

    $runIdInt = (int)($runId ?? 0);
    if ($runIdInt <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'runId is required.',
        ],
      ], 400);
      return;
    }

    /** @var PDO $pdo */
    $pdo = $services['pdo'];

    try {
      $pdo->beginTransaction();

      $run = $services['runRepo']->getRunForUser($userId, $runIdInt);
      if ($run === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'forbidden',
            'message' => 'Run not found or not owned by user.',
          ],
        ], 403);
        return;
      }

      if (($run['status'] ?? null) !== 'active') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'run_not_active',
            'message' => 'Run is not active.',
          ],
        ], 409);
        return;
      }

      $result = $services['runLifecycleService']->abandonRun($userId, $runIdInt);

      $pdo->commit();

      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => $result['run_id'],
          'status' => $result['status'],
          'run_summary' => $result['run_summary'],
        ],
      ]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * POST /api/v1/runs/:runId/exit
   *
   * Completes an active run through the exit node path and applies run-end cleanup.
   */
  public function exitRun(?string $runId = null): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    if (!$this->requireCsrf($services['csrfService'])) {
      return;
    }

    $runIdInt = (int)($runId ?? 0);
    if ($runIdInt <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'runId is required.',
        ],
      ], 400);
      return;
    }

    /** @var PDO $pdo */
    $pdo = $services['pdo'];

    try {
      $pdo->beginTransaction();

      $run = $services['runRepo']->getRunForUser($userId, $runIdInt);
      if ($run === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'forbidden',
            'message' => 'Run not found or not owned by user.',
          ],
        ], 403);
        return;
      }

      if (($run['status'] ?? null) !== 'active') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'run_not_active',
            'message' => 'Run is not active.',
          ],
        ], 409);
        return;
      }

      $services['runNodeRepo']->syncAvailableNodesFromClearedParents($runIdInt);
      $exitNode = $services['runNodeRepo']->getFirstByTypeForUpdate($runIdInt, 'exit');
      if ($exitNode === null || (string)$exitNode['status'] !== 'available') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'run_exit_unavailable',
            'message' => 'Exit is not currently available.',
          ],
        ], 409);
        return;
      }

      $result = $services['runLifecycleService']->completeRun(
        $userId,
        $runIdInt,
        (int)($run['region_id'] ?? 0),
        (int)$exitNode['id'],
      );

      $pdo->commit();

      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => $result['run_id'],
          'status' => $result['status'],
          'exit_node_id' => $result['exit_node_id'],
          'run_summary' => $result['run_summary'],
        ],
      ]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * GET /api/v1/abilities
   *
   * Returns the canonical ability catalog (stable IDs, display metadata, and default config).
   * This is intentionally DB-independent and safe to cache on the client.
   */
  public function abilities(): void {
    try {
      $registry = new AbilityRegistry();
      $payload = $registry->toCatalogPayload();

      Response::json([
        'ok' => true,
        'data' => $payload,
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * -----------------------------
   * Request/response helpers
   * -----------------------------
   */

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(): ?array
  {
    return JsonRequestBody::decode();
  }

  /**
   * POST /api/v1/dialogues/:dialogueId/seen
   */
  public function markDialogueSeen(?string $dialogueId = null): void
  {
    $services = $this->services();

    try {
      $userId = $services['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    if (!$this->requireCsrf($services['csrfService'])) {
      return;
    }

    $dialogueId = trim((string)($dialogueId ?? ''));
    if ($dialogueId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/i', $dialogueId)) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'Invalid dialogue id.',
        ],
      ], 400);
      return;
    }

    try {
      $unlockService = new UserUnlockService(Db::pdo());
      $unlockService->grant($userId, UserUnlockService::NAMESPACE_DIALOGUE, $dialogueId);

      Response::json([
        'ok' => true,
        'data' => [
          'dialogue_id' => $dialogueId,
          'seen' => true,
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * -----------------------------
   * Domain helpers
   * -----------------------------
   */

  /**
   * Consume energy in the current open transaction:
   *  - Lock row
   *  - Apply regen ticks
   *  - Deduct cost
   *
   * Throws RuntimeException('insufficient_energy') if not enough after regen.
   */
  private function consumeEnergyWithRegenInTransaction(EnergyRepository $energyRepo, int $userId, int $cost): void
  {
    if ($cost <= 0) {
      return;
    }

    $row = $energyRepo->getEnergyStateForUpdate($userId);
    if (!$row) {
      throw new RuntimeException('Energy state not found.');
    }

    $current = (int)$row['energy_current'];
    $featureUnlocks = (new UserUnlockService($energyRepo->pdo()))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);
    $effectiveMax = UserUnlockService::resolveEnergyMaxFromFeatureUnlocks($featureUnlocks);
    if ((int)$row['energy_max'] !== $effectiveMax) {
      $current = min($current, $effectiveMax);
      $stmt = $energyRepo->pdo()->prepare('
        UPDATE `energy_state`
        SET `energy_max` = ?, `energy_current` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([$effectiveMax, $current, $userId]);
    }

    $max = $effectiveMax;
    $rate = (float)$row['regen_rate_per_hour'];
    $lastSql = (string)$row['last_regen_at'];

    // Apply regen (discrete ticks), matching your EnergyService approach but without opening a nested tx.
    $last = new DateTimeImmutable($lastSql, new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if ($max < 0) $max = 0;
    if ($current < 0) $current = 0;
    if ($current > $max) $current = $max;

    if ($rate > 0.0 && $max > 0 && $current < $max) {
      $deltaSeconds = max(0, $now->getTimestamp() - $last->getTimestamp());
      $secondsPerEnergy = (int)floor(3600.0 / $rate);
      if ($secondsPerEnergy <= 0) {
        $secondsPerEnergy = 1;
      }

      $ticks = (int)floor($deltaSeconds / $secondsPerEnergy);
      if ($ticks > 0) {
        $newCurrent = min($max, $current + $ticks);
        $advanceSeconds = $ticks * $secondsPerEnergy;
        $newLast = $last->modify('+' . $advanceSeconds . ' seconds');

        $current = $newCurrent;
        $lastSql = $newLast->format('Y-m-d H:i:s');
      }
    } elseif ($current >= $max) {
      // If already full, keep last_regen_at moving to avoid large “banked regen” behavior.
      $lastSql = $now->format('Y-m-d H:i:s');
    }

    if ($current < $cost) {
      // Still update regen timestamp if we advanced it above; useful for consistency.
      $energyRepo->setEnergyCurrentAndLastRegenAt($userId, $current, $lastSql);
      throw new RuntimeException('insufficient_energy');
    }

    $energyRepo->setEnergyCurrentAndLastRegenAt($userId, $current - $cost, $lastSql);
  }

  /**
   * Simple manual composition (no DI container).
   *
   * @return array{
   *   pdo: PDO,
   *   csrfService: CsrfService,
   *   sessionService: SessionService,
   *   userDataSyncService: UserDataSyncService,
   *   profileService: ProfileService,
   *   runRepo: RunRepository,
   *   runNodeRepo: RunNodeRepository,
   *   regionRepo: RegionRepository,
   *   energyRepo: EnergyRepository,
   *   teamRepo: TeamRepository,
   *   runLifecycleService: RunLifecycleService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);
    $playerStateRepo = $core['playerStateRepo'];
    $energyRepo = $core['energyRepo'];
    $teamRepo = new TeamRepository($pdo);
    $unitRepo = new UnitRepository($pdo);
    $diceRepo = new DiceRepository($pdo);
    $regionRepo = new RegionRepository($pdo);
    $runRepo = new RunRepository($pdo);

    $energyService = new EnergyService($energyRepo);
    $diceAffixService = new DiceAffixService($pdo);
    $unitLoadoutService = new UnitLoadoutService($pdo);

    $profileService = new ProfileService(
      $energyService,
      new ProfileDtoMapper(),
      $playerStateRepo,
      $teamRepo,
      $unitRepo,
      $diceRepo,
      $regionRepo,
      $runRepo,
      $pdo
    );

    return [
      'pdo' => $pdo,
      'csrfService' => $core['csrfService'],
      'sessionService' => $core['sessionService'],
      'userDataSyncService' => new UserDataSyncService(
        $core['bootstrapper'],
        $unitLoadoutService,
        $diceAffixService
      ),
      'profileService' => $profileService,
      'starterPackProvisioningService' => $core['starterPackProvisioningService'],
      'runRepo' => $runRepo,
      'runNodeRepo' => new RunNodeRepository($pdo),
      'regionRepo' => $regionRepo,
      'energyRepo' => $energyRepo,
      'teamRepo' => $teamRepo,
      'runLifecycleService' => new RunLifecycleService(
        $pdo,
        $runRepo,
        $regionRepo,
        new RunNodeRepository($pdo),
      ),
    ];
  }

  /**
   * @param array<int,int> $teamUnitIds
   */
  private function activeTeamTreasureSenseRevealChance(PDO $pdo, array $teamUnitIds): float
  {
    if ($teamUnitIds === []) {
      return 0.0;
    }

    $abilityRegistry = new AbilityRegistry();
    if (!$abilityRegistry->has('treasure_sense')) {
      return 0.0;
    }

    $placeholders = implode(',', array_fill(0, count($teamUnitIds), '?'));
    $stmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM `unit_instance_unlocked_abilities`
      WHERE `ability_id` = 'treasure_sense'
        AND `unit_instance_id` IN ($placeholders)
    ");
    $stmt->execute($teamUnitIds);
    $count = (int)$stmt->fetchColumn();

    if ($count <= 0) {
      return 0.0;
    }

    return max(0.0, (float)($abilityRegistry->get('treasure_sense')->defaultParams['reveal_chance'] ?? 0.0));
  }
}
