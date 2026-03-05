<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Controllers\TeamController.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;

use PDO;
use RuntimeException;
use Throwable;

final class TeamController
{
  /**
   * POST /api/v1/teams
   * Body: { name: string, make_active?: bool }
   */
  public function createTeam(): void
  {
    $svc = $this->services();

    try {
      $userId = $svc['sessionService']->requireUserId();
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

    if (!$this->requireCsrf($svc['csrfService'])) {
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

    $name = (string)($body['name'] ?? '');
    $makeActive = !empty($body['make_active']);

    try {
      $teamId = $svc['teamRepo']->createTeam($userId, $name, $makeActive);

      Response::json([
        'ok' => true,
        'data' => [
          'team_id' => (string)$teamId,
        ],
      ]);
    } catch (RuntimeException $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => $e->getMessage(),
        ],
      ], 400);
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
   * POST /api/v1/teams/:teamId/activate
   */
  public function activateTeam(?string $teamId = null): void
  {
    $svc = $this->services();

    try {
      $userId = $svc['sessionService']->requireUserId();
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

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $teamIdInt = $this->requirePositiveInt($teamId, 'teamId');
    if ($teamIdInt === null) return;

    try {
      $svc['teamRepo']->setActiveTeam($userId, $teamIdInt);

      Response::json([
        'ok' => true,
        'data' => (object)[],
      ]);
    } catch (RuntimeException $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => $e->getMessage(),
        ],
      ], 400);
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
   * PUT /api/v1/teams/:teamId
   * Body: { unit_ids: string[], formation: [{cell:string, unit_instance_id:string|null}], name?: string }
   */
  public function updateTeam(?string $teamId = null): void
  {
    $svc = $this->services();

    try {
      $userId = $svc['sessionService']->requireUserId();
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

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $teamIdInt = $this->requirePositiveInt($teamId, 'teamId');
    if ($teamIdInt === null) return;

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

    $unitIdsRaw = $body['unit_ids'] ?? null;
    $formationRaw = $body['formation'] ?? null;
    $nameRaw = $body['name'] ?? null;

    if (!is_array($unitIdsRaw) || !is_array($formationRaw)) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'unit_ids and formation are required.',
        ],
      ], 400);
      return;
    }

    $unitIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $unitIdsRaw)));
    $unitIdSet = [];
    foreach ($unitIds as $unitId) {
      $unitIdSet[$unitId] = true;
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];

    try {
      $pdo->beginTransaction();

      // Replace membership
      $svc['teamRepo']->setTeamUnits($userId, $teamIdInt, $unitIds);

      if ($nameRaw !== null) {
        $svc['teamRepo']->renameTeam($userId, $teamIdInt, (string)$nameRaw);
      }

      // Replace formation:
      // - clear first
      // - set only non-null placements
      $svc['teamRepo']->clearFormation($userId, $teamIdInt);

      foreach ($formationRaw as $row) {
        if (!is_array($row)) continue;

        $cell = (string)($row['cell'] ?? '');
        $uidRaw = $row['unit_instance_id'] ?? null;

        $uid = null;
        if ($uidRaw !== null && $uidRaw !== '') {
          $uid = (int)$uidRaw;

          if (!isset($unitIdSet[$uid])) {
            throw new RuntimeException('formation.unit_instance_id must exist in unit_ids.');
          }
        }

        // Only persist occupied cells; missing cells are treated as empty in UI
        if ($uid !== null) {
          $svc['teamRepo']->setFormationCell($userId, $teamIdInt, $cell, $uid);
        }
      }

      $pdo->commit();

      Response::json([
        'ok' => true,
        'data' => (object)[],
      ]);
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => $e->getMessage(),
        ],
      ], 400);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();

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
   * DELETE /api/v1/teams/:teamId
   */
  public function deleteTeam(?string $teamId = null): void
  {
    $svc = $this->services();

    try {
      $userId = $svc['sessionService']->requireUserId();
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

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $teamIdInt = $this->requirePositiveInt($teamId, 'teamId');
    if ($teamIdInt === null) return;

    try {
      $team = $svc['teamRepo']->getTeamForUser($userId, $teamIdInt);
      if ($team === null) {
        throw new RuntimeException('Team not found or not owned by user.');
      }

      if ($svc['teamRepo']->countTeamsForUser($userId) <= 1) {
        throw new RuntimeException('Cannot delete your only squad.');
      }

      $activeRun = $svc['runRepo']->getActiveRunForUser($userId);
      if ($activeRun !== null && (bool)$team['is_active']) {
        throw new RuntimeException('Cannot delete active squad while a run is active.');
      }

      $svc['teamRepo']->deleteTeam($userId, $teamIdInt);

      Response::json([
        'ok' => true,
        'data' => [
          'team_id' => (string)$teamIdInt,
        ],
      ]);
    } catch (RuntimeException $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => $e->getMessage(),
        ],
      ], 400);
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

  // -----------------------------
  // Internals
  // -----------------------------

  private function services(): array
  {
    $pdo = Db::pdo();

    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);

    $csrfService = new CsrfService();
    $grantService = new GrantService();
    $bootstrapper = new PlayerBootstrapper($playerStateRepo, $energyRepo, $grantService);
    $sessionService = new SessionService($userRepo, $csrfService, $bootstrapper);

    return [
      'pdo' => $pdo,
      'teamRepo' => new TeamRepository($pdo),
      'runRepo' => new RunRepository($pdo),
      'sessionService' => $sessionService,
      'csrfService' => $csrfService,
    ];
  }

  private function readJsonBody(): ?array
  {
    $raw = file_get_contents('php://input');
    if ($raw === false) return null;

    $raw = trim($raw);
    if ($raw === '') return [];

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return null;

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

  private function requirePositiveInt(?string $raw, string $field): ?int
  {
    $v = (int)($raw ?? 0);
    if ($v <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => "{$field} is required.",
        ],
      ], 400);
      return null;
    }
    return $v;
  }
}
