<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Services\ShopService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ShopServiceIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run shop service integration tests.';
  }

  public function testPurchaseFeatureUnlockSpendsCurrencyAndGrantsFeature(): void
  {
    $userId = $this->insertUser('qa_shop_service_feature', 'QA Shop Service Feature');
    $this->setSoftCurrency($userId, 300);

    $result = $this->service()->purchase($userId, 'feature_unlock', 'academy');

    $this->assertSame('feature_unlock', $result['item_type']);
    $this->assertSame('academy', $result['product_id']);
    $this->assertSame(250, (int)$result['cost']);
    $this->assertSame(50, (int)$result['currency_soft']);
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = 'academy'",
        [$userId]
      )
    );
  }

  public function testCatalogDomainReflectsUnlockedSecondDailyDealSlot(): void
  {
    $userId = $this->insertUser('qa_shop_service_catalog', 'QA Shop Service Catalog');
    $this->grantUnlock($userId, 'feature', 'second_daily_deal');

    $catalog = $this->service()->buildCatalog($userId);

    $this->assertCount(2, $catalog['daily_deals']);
    $this->assertSame('daily_deal_1', (string)($catalog['daily_deals'][0]['product_id'] ?? ''));
    $this->assertSame('daily_deal_2', (string)($catalog['daily_deals'][1]['product_id'] ?? ''));
  }

  private function service(): ShopService
  {
    $pdo = $this->pdo;
    \assert($pdo instanceof \PDO);

    return new ShopService($pdo, new PlayerStateRepository($pdo));
  }
}
