<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Commands\ShopPurchaseException;
use DiceGoblins\Application\Commands\ShopPurchaseIntegrityException;
use DiceGoblins\Application\Queries\ShopIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class ShopCatalogController
{
  use RequiresCsrf;

  public function __construct(private readonly ?ContentRegistry $content = null) {}

  /** GET /api/v1/shop */
  public function catalog(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      $this->serverError(); return;
    }
    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }
    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core, $this->content);
      Response::json(['ok' => true, 'data' => $services['shopCatalogQuery']->execute($userId)]);
    } catch (ShopIntegrityException) {
      Response::json(['ok' => false, 'error' => [
        'code' => 'shop_data_integrity_error', 'message' => 'Shop data is unavailable.',
      ]], 500);
    } catch (Throwable) {
      $this->serverError();
    }
  }

  /** POST /api/v1/shop/purchase */
  public function purchase(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      $this->serverError(); return;
    }
    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      $this->error('unauthorized', 'No active session.', 401); return;
    }
    if (!$this->requireCsrf($core['csrfService'])) return;
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->error('invalid_shop_purchase', 'Shop purchase request is invalid.', 422); return;
    }
    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core, $this->content);
      $result = $services['purchaseShopOfferCommand']->execute(
        $userId,
        $body,
        is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null,
      );
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (ShopPurchaseException $e) {
      $this->error($e->errorCode, $e->publicMessage, $e->httpStatus);
    } catch (ShopPurchaseIntegrityException) {
      $this->error('shop_data_integrity_error', 'Shop data is unavailable.', 500);
    } catch (Throwable) {
      $this->serverError();
    }
  }

  private function serverError(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
  }

  private function error(string $code, string $message, int $status): void
  {
    Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
  }
}
