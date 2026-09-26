<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Queries\InventoryIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use Throwable;

final class InventoryController
{
  public function __construct(private readonly ?ContentRegistry $content = null) {}

  /** GET /api/v1/items */
  public function items(): void
  {
    try {
      $pdo = Db::pdo();
      $core = ControllerServiceFactory::buildCore($pdo);
    } catch (Throwable) {
      $this->serverError();
      return;
    }
    try {
      $userId = $core['sessionService']->requireUserId();
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return;
    }
    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core, $this->content);
      Response::json(['ok' => true, 'data' => ['items' => $services['itemCollectionQuery']->execute($userId)]]);
    } catch (InventoryIntegrityException) {
      Response::json(['ok' => false, 'error' => [
        'code' => 'inventory_data_integrity_error', 'message' => 'Inventory data is unavailable.',
      ]], 500);
    } catch (Throwable) {
      $this->serverError();
    }
  }

  private function serverError(): void
  {
    Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
  }
}
