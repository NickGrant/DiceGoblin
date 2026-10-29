<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Commands\OnboardingProvisionException;
use DiceGoblins\Application\Commands\ProvisionStartingWarbandCommand;
use DiceGoblins\Application\NormalUnitCreationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use Throwable;

final class OnboardingController
{
  use RequiresCsrf;

  public function __construct(private readonly ?ContentRegistry $content = null) {}

  public function status(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
      return;
    }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }
    try {
      $content = $this->content ?? ContentRegistry::loadConfigured();
      $query = new OnboardingQuery(new WarbandUnitRepository($pdo), new UserCodexRepository($pdo),
        new PlayerStateRepository($pdo), $content);
      Response::json(['ok' => true, 'data' => $query->execute($userId)]);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'onboarding_state_integrity_error',
        'message' => 'Onboarding state is unavailable.']], 500);
    }
  }

  public function provision(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
      return;
    }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }
    if (!$this->requireCsrf($core['csrfService'])) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'invalid_onboarding_provision',
        'message' => 'Provision request must be empty.']], 422);
      return;
    }
    try {
      $content = $this->content ?? ContentRegistry::loadConfigured();
      $state = new PlayerStateRepository($pdo);
      $units = new WarbandUnitRepository($pdo);
      $query = new OnboardingQuery($units, new UserCodexRepository($pdo), $state, $content);
      $command = new ProvisionStartingWarbandCommand($pdo, $state, new IdempotencyRequestRepository($pdo),
        $query, new NormalUnitCreationService($units, $content), $units, new WarbandDiceRepository($pdo),
        new SquadRepository($pdo), $content);
      $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
      $result = $command->execute($userId, $body, is_string($key) ? $key : null);
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      Response::json(['ok' => false, 'error' => ['code' => 'idempotency_key_invalid',
        'message' => 'Idempotency-Key is invalid.']], 400);
    } catch (IdempotencyConflictException) {
      Response::json(['ok' => false, 'error' => ['code' => 'idempotency_conflict',
        'message' => 'Idempotency-Key conflicts with an earlier request.']], 409);
    } catch (OnboardingProvisionException $e) {
      Response::json(['ok' => false, 'error' => ['code' => $e->errorCode, 'message' => $e->publicMessage]], $e->httpStatus);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'onboarding_state_integrity_error',
        'message' => 'Onboarding state is unavailable.']], 500);
    }
  }
}
