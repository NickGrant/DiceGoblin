<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ShopController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ShopControllerIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run shop integration tests.';
  }

  public function testCatalogOnlyShowsInitiallyUnlockedUnitTypes(): void
  {
    $userId = $this->insertUser('qa_shop', 'QA Shop');
    $_SESSION['user_id'] = $userId;

    $controller = new ShopController();
    $response = $this->invoke(fn() => $controller->catalog());

    $this->assertSame(200, $response['status']);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $basicUnits = is_array($data['basic_units'] ?? null) ? $data['basic_units'] : [];

    $slugs = array_map(
      static fn(array $row): string => (string)($row['unit_type_slug'] ?? ''),
      $basicUnits
    );

    $this->assertSame([
      'frontline_bruiser_t1',
      'backline_marksman_t1',
    ], $slugs);
  }

  public function testPurchaseRejectsLockedUnitTypeEvenIfTierOne(): void
  {
    $userId = $this->insertUser('qa_shop_buy', 'QA Shop Buy');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new ShopController();
    $this->setJsonBody([
      'item_type' => 'basic_unit',
      'product_id' => 'support_banner_t1',
    ]);
    $response = $this->invoke(fn() => $controller->purchase());

    $this->assertSame(400, $response['status']);
    $this->assertSame('validation_error', (string)($response['body']['error']['code'] ?? ''));
    $this->assertSame('Requested unit is not unlocked yet.', (string)($response['body']['error']['message'] ?? ''));
  }
}
