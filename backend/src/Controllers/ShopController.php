<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\HandlesControllerRequests;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Services\ShopService;
use RuntimeException;
use Throwable;

final class ShopController
{
  use HandlesControllerRequests;
  use RequiresCsrf;

  public function catalog(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      $data = $svc['shopService']->buildCatalog($userId);
      Response::json(['ok' => true, 'data' => $data]);
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function purchase(): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $itemType = trim((string)($body['item_type'] ?? ''));
    $productId = trim((string)($body['product_id'] ?? ''));
    if (!in_array($itemType, ['basic_unit', 'basic_dice', 'daily_deal', 'feature_unlock'], true)) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'item_type is required.']], 400);
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      Response::json([
        'ok' => true,
        'data' => $svc['shopService']->purchase($userId, $itemType, $productId),
      ]);
    } catch (RuntimeException $e) {
      if ($e->getMessage() === 'Not enough soft currency.') {
        Response::json(['ok' => false, 'error' => ['code' => 'insufficient_currency', 'message' => $e->getMessage()]], 409);
        return;
      }
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
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
      'shopService' => new ShopService($pdo, $core['playerStateRepo']),
    ];
  }
}
