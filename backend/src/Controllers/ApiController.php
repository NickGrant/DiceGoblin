<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DateTimeImmutable;
use DateTimeZone;

use DiceGoblins\Combat\Abilities\AbilityRegistry;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\EnergyService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\ProfileService;
use DiceGoblins\Services\SessionService;

use PDO;
use RuntimeException;
use Throwable;

final class ApiController
{
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

        $services['runRepo']->abandonActiveRunsForUser($userId);
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

      // Spend energy (regen + deduct) under the same transaction.
      $energyCost = (int)$region['energy_cost'];
      $this->consumeEnergyWithRegenInTransaction($services['energyRepo'], $userId, $energyCost);

      // Create run + graph.
      $seed = random_int(1, 9223372036854775807);
      $graph = $this->generateRunGraph($regionId, (string)$seed);

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

      $services['runRepo']->applyRunEndCleanup($runIdInt, $userId, true);
      $services['runRepo']->endRun($userId, $runIdInt, 'abandoned');

      $pdo->commit();

      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => (string)$runIdInt,
          'status' => 'abandoned',
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
    $raw = file_get_contents('php://input');
    if ($raw === false) {
      return null;
    }

    $raw = trim($raw);
    if ($raw === '') {
      return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return null;
    }

    return $decoded;
  }

  private function requireCsrf(CsrfService $csrfService): bool
  {
    $provided = $csrfService->extractProvidedToken();

    if (!$csrfService->validateToken($provided)) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'csrf_invalid',
          'message' => 'Invalid CSRF token.',
        ],
      ], 403);
      return false;
    }

    return true;
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
    $max = (int)$row['energy_max'];
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
   * Deterministic small branching run graph.
   *
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  private function generateRunGraph(int $regionId, string $seed): array
  {
    $seedInt = (int)(is_numeric($seed) ? $seed : crc32($seed));
    mt_srand($seedInt ^ ($regionId * 2654435761));

    $midA = (mt_rand(0, 1) === 0) ? 'loot' : 'rest';
    $midB = ($midA === 'loot') ? 'rest' : 'loot';
    $variant = ['combat', 'loot', 'rest'][mt_rand(0, 2)];

    $nodes = [
      ['node_index' => 0, 'node_type' => 'combat', 'status' => 'available', 'meta' => ['col' => 0, 'row' => 1]],
      ['node_index' => 1, 'node_type' => 'combat', 'status' => 'locked',    'meta' => ['col' => 1, 'row' => 0]],
      ['node_index' => 2, 'node_type' => $midA,    'status' => 'locked',    'meta' => ['col' => 1, 'row' => 2]],
      ['node_index' => 3, 'node_type' => 'combat', 'status' => 'locked',    'meta' => ['col' => 2, 'row' => 1]],
      ['node_index' => 4, 'node_type' => $variant, 'status' => 'locked',    'meta' => ['col' => 3, 'row' => 0]],
      ['node_index' => 5, 'node_type' => $midB,    'status' => 'locked',    'meta' => ['col' => 3, 'row' => 2]],
      ['node_index' => 6, 'node_type' => 'combat', 'status' => 'locked',    'meta' => ['col' => 4, 'row' => 1]],
      ['node_index' => 7, 'node_type' => 'combat', 'status' => 'locked',    'meta' => ['col' => 5, 'row' => 1]],
      ['node_index' => 8, 'node_type' => 'boss',   'status' => 'locked',    'meta' => ['col' => 6, 'row' => 1]],
    ];

    $edges = [
      ['from' => 0, 'to' => 1],
      ['from' => 0, 'to' => 2],
      ['from' => 1, 'to' => 3],
      ['from' => 2, 'to' => 3],
      ['from' => 3, 'to' => 4],
      ['from' => 3, 'to' => 5],
      ['from' => 4, 'to' => 6],
      ['from' => 5, 'to' => 6],
      ['from' => 6, 'to' => 7],
      ['from' => 7, 'to' => 8],
    ];

    return ['nodes' => $nodes, 'edges' => $edges];
  }

  /**
   * Simple manual composition (no DI container).
   *
   * @return array{
   *   pdo: PDO,
   *   csrfService: CsrfService,
   *   sessionService: SessionService,
   *   profileService: ProfileService,
   *   runRepo: RunRepository,
   *   regionRepo: RegionRepository,
   *   energyRepo: EnergyRepository,
   *   teamRepo: TeamRepository
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();

    // Repositories
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);
    $teamRepo = new TeamRepository($pdo);
    $unitRepo = new UnitRepository($pdo);
    $diceRepo = new DiceRepository($pdo);
    $regionRepo = new RegionRepository($pdo);
    $runRepo = new RunRepository($pdo);


    // Services
    $csrfService = new CsrfService();
    $grantService = new GrantService();

    $bootstrapper = new PlayerBootstrapper(
      $playerStateRepo,
      $energyRepo,
      $grantService
    );

    $energyService = new EnergyService($energyRepo);

    $sessionService = new SessionService(
      $userRepo,
      $csrfService,
      $bootstrapper
    );

    $profileService = new ProfileService(
      $bootstrapper,
      $energyService,
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
      'csrfService' => $csrfService,
      'sessionService' => $sessionService,
      'profileService' => $profileService,
      'grantService' => $grantService,
      'runRepo' => $runRepo,
      'regionRepo' => $regionRepo,
      'energyRepo' => $energyRepo,
      'teamRepo' => $teamRepo,
    ];
  }
}
