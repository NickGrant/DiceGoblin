<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\UserUnlockService;
use PDO;
use RuntimeException;
use Throwable;

final class AcademyController
{
  use RequiresCsrf;

  private const DEFAULT_UNLOCK_COST = 500;
  private const TIER_ONE_UNLOCK_COST = 250;

  public function catalog(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      $this->requireAcademyUnlocked($svc['pdo'], $userId);
      Response::json([
        'ok' => true,
        'data' => $this->buildCatalog($svc['pdo'], $svc['playerStateRepo'], $userId),
      ]);
    } catch (RuntimeException $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'feature_locked', 'message' => $e->getMessage()]], 403);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function unlockUnitType(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = JsonRequestBody::decode();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid JSON body.']], 400);
      return;
    }

    $unitTypeSlug = trim((string)($body['unit_type_slug'] ?? ''));
    if ($unitTypeSlug === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'unit_type_slug is required.']], 400);
      return;
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      $this->requireAcademyUnlocked($pdo, $userId);
      $pdo->beginTransaction();

      $catalogEntry = $this->loadUnlockableUnitType($pdo, $unitTypeSlug);
      if ($catalogEntry === null) {
        throw new RuntimeException('Requested unit type is not available for Academy unlocks.');
      }

      $unlockService = new UserUnlockService($pdo);
      if ($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $unitTypeSlug)) {
        throw new RuntimeException('Requested unit type is already unlocked.');
      }

      $svc['playerStateRepo']->ensurePlayerState($userId);
      $state = $svc['playerStateRepo']->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        throw new RuntimeException('Player state unavailable.');
      }

      $cost = $this->unlockCostForSlug($unitTypeSlug);
      $currentSoft = max(0, (int)($state['currency_soft'] ?? 0));
      if ($currentSoft < $cost) {
        Response::json(['ok' => false, 'error' => ['code' => 'insufficient_currency', 'message' => 'Not enough soft currency.']], 409);
        $pdo->rollBack();
        return;
      }

      $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $unitTypeSlug);
      $nextSoft = $currentSoft - $cost;
      $svc['playerStateRepo']->setCurrency($userId, $nextSoft, max(0, (int)($state['currency_hard'] ?? 0)));

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'unit_type_slug' => $unitTypeSlug,
          'cost' => $cost,
          'currency_soft' => $nextSoft,
        ],
      ]);
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  /**
   * @return array{
   *   currency_soft:int,
   *   unit_unlocks:array<int,array{unit_type_slug:string,name:string,role:string,cost:int,is_unlocked:bool}>
   * }
   */
  private function buildCatalog(PDO $pdo, PlayerStateRepository $playerStateRepo, int $userId): array
  {
    $playerStateRepo->ensurePlayerState($userId);
    $currency = $playerStateRepo->getCurrency($userId);
    $unlockService = new UserUnlockService($pdo);
    $unlocked = array_fill_keys(
      $unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE),
      true
    );

    $stmt = $pdo->query("
      SELECT `slug`, `name`, `role`
      FROM `unit_types`
      WHERE RIGHT(`slug`, 3) IN ('_t1', '_t2')
      ORDER BY CASE
        WHEN RIGHT(`slug`, 3) = '_t1' THEN 1
        WHEN RIGHT(`slug`, 3) = '_t2' THEN 2
        ELSE 3
      END ASC,
      `id` ASC
    ");

    $unitUnlocks = array_map(function (array $row) use ($unlocked): array {
      $slug = (string)$row['slug'];
      return [
        'unit_type_slug' => $slug,
        'name' => (string)$row['name'],
        'role' => (string)$row['role'],
        'cost' => $this->unlockCostForSlug($slug),
        'is_unlocked' => isset($unlocked[$slug]),
      ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    return [
      'currency_soft' => max(0, (int)($currency['soft'] ?? 0)),
      'unit_unlocks' => $unitUnlocks,
    ];
  }

  /**
   * @return array{slug:string,name:string,role:string}|null
   */
  private function loadUnlockableUnitType(PDO $pdo, string $unitTypeSlug): ?array
  {
    $stmt = $pdo->prepare("
      SELECT `slug`, `name`, `role`
      FROM `unit_types`
      WHERE `slug` = ? AND RIGHT(`slug`, 3) IN ('_t1', '_t2')
      LIMIT 1
    ");
    $stmt->execute([$unitTypeSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return null;
    }

    return [
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'role' => (string)$row['role'],
    ];
  }

  private function unlockCostForSlug(string $unitTypeSlug): int
  {
    return str_ends_with($unitTypeSlug, '_t1')
      ? self::TIER_ONE_UNLOCK_COST
      : self::DEFAULT_UNLOCK_COST;
  }

  private function requireAcademyUnlocked(PDO $pdo, int $userId): void
  {
    if (!(new UserUnlockService($pdo))->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ACADEMY)) {
      throw new RuntimeException('Academy has not been unlocked yet.');
    }
  }

  private function requireUserId(SessionService $sessionService): ?int
  {
    try {
      return $sessionService->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return null;
    }
  }

  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'pdo' => $pdo,
      'csrfService' => $core['csrfService'],
      'sessionService' => $core['sessionService'],
      'bootstrapper' => $core['bootstrapper'],
      'playerStateRepo' => new PlayerStateRepository($pdo),
    ];
  }
}
