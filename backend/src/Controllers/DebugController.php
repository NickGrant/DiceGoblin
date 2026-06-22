<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\DevToolsService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;
use Throwable;

final class DebugController
{
  use RequiresCsrf;

  public function catalog(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $svc['sessionService']->requireUserId();
      Response::json([
        'ok' => true,
        'data' => $svc['devTools']->getCatalog($svc['sessionService']->requireUserId()),
      ]);
    } catch (Throwable $e) {
      $this->respondUnauthorized();
    }
  }

  public function grantCurrency(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      $this->respondUnauthorized();
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      $this->respondInvalidBody();
      return;
    }

    try {
      $soft = max(0, (int) ($body['soft'] ?? 0));
      $hard = max(0, (int) ($body['hard'] ?? 0));
      Response::json([
        'ok' => true,
        'data' => [
          'currency' => $svc['devTools']->grantCurrency($userId, $soft, $hard),
        ],
      ]);
    } catch (Throwable $e) {
      $this->respondServerError();
    }
  }

  public function grantUnit(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      $this->respondUnauthorized();
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      $this->respondInvalidBody();
      return;
    }

    $unitTypeSlug = trim((string) ($body['unit_type_slug'] ?? ''));
    if ($unitTypeSlug === '') {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'unit_type_slug is required.',
        ],
      ], 400);
      return;
    }

    try {
      $count = max(1, (int) ($body['count'] ?? 1));
      Response::json([
        'ok' => true,
        'data' => [
          'granted_units' => $svc['devTools']->grantUnits($userId, $unitTypeSlug, $count),
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'Unable to grant requested unit.',
        ],
      ], 400);
    }
  }

  public function grantDice(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      $this->respondUnauthorized();
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      $this->respondInvalidBody();
      return;
    }

    $sides = (int) ($body['sides'] ?? 0);
    $rarity = trim((string) ($body['rarity'] ?? ''));
    if ($sides <= 0 || $rarity === '') {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'sides and rarity are required.',
        ],
      ], 400);
      return;
    }

    try {
      $count = max(1, (int) ($body['count'] ?? 1));
      Response::json([
        'ok' => true,
        'data' => [
          'granted_dice' => $svc['devTools']->grantDice($userId, $sides, $rarity, $count),
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'Unable to grant requested die.',
        ],
      ], 400);
    }
  }

  public function grantRegionItem(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      $this->respondUnauthorized();
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      $this->respondInvalidBody();
      return;
    }

    $regionItemSlug = trim((string) ($body['region_item_slug'] ?? ''));
    if ($regionItemSlug === '') {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'region_item_slug is required.',
        ],
      ], 400);
      return;
    }

    try {
      $quantity = max(1, (int) ($body['quantity'] ?? 1));
      Response::json([
        'ok' => true,
        'data' => [
          'region_item' => $svc['devTools']->grantRegionItem($userId, $regionItemSlug, $quantity),
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'Unable to grant requested region item.',
        ],
      ], 400);
    }
  }

  public function resetAccount(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      $this->respondUnauthorized();
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    try {
      Response::json([
        'ok' => true,
        'data' => [
          'reset' => $svc['devTools']->resetAccount($userId),
        ],
      ]);
    } catch (Throwable $e) {
      $this->respondServerError();
    }
  }

  public function setUnitLevel(): void
  {
    $svc = $this->services();
    if (!$svc['devTools']->isEnabled()) {
      $this->respondDisabled();
      return;
    }

    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      $this->respondUnauthorized();
      return;
    }

    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      $this->respondInvalidBody();
      return;
    }

    $unitId = (int)($body['unit_instance_id'] ?? 0);
    $level = (int)($body['level'] ?? 0);
    if ($unitId <= 0 || $level <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'unit_instance_id and level are required.',
        ],
      ], 400);
      return;
    }

    try {
      Response::json([
        'ok' => true,
        'data' => [
          'unit' => $svc['devTools']->setUnitLevel($userId, $unitId, $level),
        ],
      ]);
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to update unit level.',
        ],
      ], 400);
    }
  }

  /**
   * @return array{
   *   csrfService: CsrfService,
   *   sessionService: SessionService,
   *   devTools: DevToolsService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'csrfService' => $core['csrfService'],
      'sessionService' => $core['sessionService'],
      'devTools' => new DevToolsService(
        $pdo,
        $core['bootstrapper'],
        $core['playerStateRepo'],
        new UnitRepository($pdo),
        new DiceRepository($pdo),
        new RegionRepository($pdo),
      ),
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(): ?array
  {
    return JsonRequestBody::decode();
  }

  private function respondUnauthorized(): void
  {
    Response::json([
      'ok' => false,
      'error' => [
        'code' => 'unauthorized',
        'message' => 'No active session.',
      ],
    ], 401);
  }

  private function respondDisabled(): void
  {
    Response::json([
      'ok' => false,
      'error' => [
        'code' => 'forbidden',
        'message' => 'Debug endpoints are disabled.',
      ],
    ], 403);
  }

  private function respondInvalidBody(): void
  {
    Response::json([
      'ok' => false,
      'error' => [
        'code' => 'validation_error',
        'message' => 'Invalid JSON body.',
      ],
    ], 400);
  }

  private function respondServerError(): void
  {
    Response::json([
      'ok' => false,
      'error' => [
        'code' => 'server_error',
        'message' => 'Unexpected error.',
      ],
    ], 500);
  }
}
