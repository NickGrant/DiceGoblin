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
    $featureUnlocks = is_array($data['feature_unlocks'] ?? null) ? $data['feature_unlocks'] : [];
    $dailyDeals = is_array($data['daily_deals'] ?? null) ? $data['daily_deals'] : [];

    $slugs = array_map(
      static fn(array $row): string => (string)($row['unit_type_slug'] ?? ''),
      $basicUnits
    );

    $this->assertSame([
      'frontline_bruiser_t1',
      'backline_marksman_t1',
    ], $slugs);
    $this->assertSame('academy', (string)($featureUnlocks[0]['product_id'] ?? ''));
    $this->assertSame(250, (int)($featureUnlocks[0]['cost'] ?? 0));
    $this->assertFalse((bool)($featureUnlocks[0]['is_unlocked'] ?? true));
    $this->assertSame('bigger_squad', (string)($featureUnlocks[1]['product_id'] ?? ''));
    $this->assertTrue((bool)($featureUnlocks[1]['is_available'] ?? false));
    $this->assertSame('biggerest_squad', (string)($featureUnlocks[2]['product_id'] ?? ''));
    $this->assertFalse((bool)($featureUnlocks[2]['is_available'] ?? true));
    $this->assertSame('shop_discount', (string)($featureUnlocks[3]['product_id'] ?? ''));
    $this->assertSame(500, (int)($featureUnlocks[3]['cost'] ?? 0));
    $this->assertSame('sell_bonus', (string)($featureUnlocks[4]['product_id'] ?? ''));
    $this->assertSame(500, (int)($featureUnlocks[4]['cost'] ?? 0));
    $this->assertSame('market_mastery', (string)($featureUnlocks[5]['product_id'] ?? ''));
    $this->assertSame(1000, (int)($featureUnlocks[5]['cost'] ?? 0));
    $this->assertFalse((bool)($featureUnlocks[5]['is_available'] ?? true));
    $this->assertSame('second_daily_deal', (string)($featureUnlocks[6]['product_id'] ?? ''));
    $this->assertSame(500, (int)($featureUnlocks[6]['cost'] ?? 0));
    $this->assertCount(1, $dailyDeals);
    $this->assertSame('daily_deal_1', (string)($dailyDeals[0]['product_id'] ?? ''));
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

  public function testPurchaseCanUnlockAcademyFeature(): void
  {
    $userId = $this->insertUser('qa_shop_feature', 'QA Shop Feature');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->pdo?->prepare('UPDATE `player_state` SET `currency_soft` = 300 WHERE `user_id` = ?')->execute([$userId]);

    $controller = new ShopController();
    $this->setJsonBody([
      'item_type' => 'feature_unlock',
      'product_id' => 'academy',
    ]);
    $response = $this->invoke(fn() => $controller->purchase());

    $this->assertSame(200, $response['status']);
    $this->assertSame(250, (int)($response['body']['data']['cost'] ?? 0));
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = 'academy'",
        [$userId]
      )
    );
  }

  public function testPurchaseCanUnlockBiggerSquadFeature(): void
  {
    $userId = $this->insertUser('qa_shop_squad', 'QA Shop Squad');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->pdo?->prepare('UPDATE `player_state` SET `currency_soft` = 300 WHERE `user_id` = ?')->execute([$userId]);

    $controller = new ShopController();
    $this->setJsonBody([
      'item_type' => 'feature_unlock',
      'product_id' => 'bigger_squad',
    ]);
    $response = $this->invoke(fn() => $controller->purchase());

    $this->assertSame(200, $response['status']);
    $this->assertSame(500, (int)($response['body']['data']['cost'] ?? 0));
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = 'bigger_squad'",
        [$userId]
      )
    );
  }

  public function testPurchaseRejectsBiggerestSquadUntilBiggerSquadIsUnlocked(): void
  {
    $userId = $this->insertUser('qa_shop_squad', 'QA Shop Squad');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->pdo?->prepare('UPDATE `player_state` SET `currency_soft` = 1000 WHERE `user_id` = ?')->execute([$userId]);

    $controller = new ShopController();
    $this->setJsonBody([
      'item_type' => 'feature_unlock',
      'product_id' => 'biggerest_squad',
    ]);
    $response = $this->invoke(fn() => $controller->purchase());

    $this->assertSame(400, $response['status']);
    $this->assertSame('validation_error', (string)($response['body']['error']['code'] ?? ''));
    $this->assertSame('Bigger Squad must be unlocked first.', (string)($response['body']['error']['message'] ?? ''));
  }

  public function testPurchaseRejectsMarketMasteryUntilBothEconomyUnlocksAreOwned(): void
  {
    $userId = $this->insertUser('qa_shop_market_gate', 'QA Shop Market Gate');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->pdo?->prepare('UPDATE `player_state` SET `currency_soft` = 1000 WHERE `user_id` = ?')->execute([$userId]);
    $this->grantUnlock($userId, 'feature', 'shop_discount');

    $controller = new ShopController();
    $this->setJsonBody([
      'item_type' => 'feature_unlock',
      'product_id' => 'market_mastery',
    ]);
    $response = $this->invoke(fn() => $controller->purchase());

    $this->assertSame(400, $response['status']);
    $this->assertSame('validation_error', (string)($response['body']['error']['code'] ?? ''));
    $this->assertSame('Coupon Book and Sharp Dealer must be unlocked first.', (string)($response['body']['error']['message'] ?? ''));
  }

  public function testCatalogDiscountsCostsAfterShopDiscountUnlock(): void
  {
    $userId = $this->insertUser('qa_shop_discount', 'QA Shop Discount');
    $_SESSION['user_id'] = $userId;
    $this->grantUnlock($userId, 'feature', 'shop_discount');

    $controller = new ShopController();
    $response = $this->invoke(fn() => $controller->catalog());

    $this->assertSame(200, $response['status']);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $basicUnits = is_array($data['basic_units'] ?? null) ? $data['basic_units'] : [];
    $featureUnlocks = is_array($data['feature_unlocks'] ?? null) ? $data['feature_unlocks'] : [];

    $this->assertSame(29, (int)($basicUnits[0]['cost'] ?? 0));
    $this->assertSame(225, (int)($featureUnlocks[0]['cost'] ?? 0));
    $this->assertSame(450, (int)($featureUnlocks[1]['cost'] ?? 0));
    $this->assertSame(450, (int)($featureUnlocks[3]['cost'] ?? 0));
    $this->assertSame(900, (int)($featureUnlocks[5]['cost'] ?? 0));
  }

  public function testPurchaseCanUnlockSellBonusFeature(): void
  {
    $userId = $this->insertUser('qa_shop_sell_bonus', 'QA Shop Sell Bonus');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->pdo?->prepare('UPDATE `player_state` SET `currency_soft` = 600 WHERE `user_id` = ?')->execute([$userId]);

    $controller = new ShopController();
    $this->setJsonBody([
      'item_type' => 'feature_unlock',
      'product_id' => 'sell_bonus',
    ]);
    $response = $this->invoke(fn() => $controller->purchase());

    $this->assertSame(200, $response['status']);
    $this->assertSame(500, (int)($response['body']['data']['cost'] ?? 0));
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = 'sell_bonus'",
        [$userId]
      )
    );
  }

  public function testCatalogAppliesTwentyPercentEconomyModifiersAfterMarketMasteryUnlock(): void
  {
    $userId = $this->insertUser('qa_shop_market_mastery', 'QA Shop Market Mastery');
    $_SESSION['user_id'] = $userId;
    $this->grantUnlock($userId, 'feature', 'shop_discount');
    $this->grantUnlock($userId, 'feature', 'sell_bonus');
    $this->grantUnlock($userId, 'feature', 'market_mastery');

    $controller = new ShopController();
    $response = $this->invoke(fn() => $controller->catalog());

    $this->assertSame(200, $response['status']);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $basicUnits = is_array($data['basic_units'] ?? null) ? $data['basic_units'] : [];
    $featureUnlocks = is_array($data['feature_unlocks'] ?? null) ? $data['feature_unlocks'] : [];

    $this->assertSame(26, (int)($basicUnits[0]['cost'] ?? 0));
    $this->assertSame(200, (int)($featureUnlocks[0]['cost'] ?? 0));
    $this->assertSame(400, (int)($featureUnlocks[3]['cost'] ?? 0));
    $this->assertSame(800, (int)($featureUnlocks[5]['cost'] ?? 0));
    $this->assertTrue((bool)($featureUnlocks[5]['is_unlocked'] ?? false));
  }

  public function testCatalogShowsSecondDailyDealAfterUnlock(): void
  {
    $userId = $this->insertUser('qa_shop_second_deal', 'QA Shop Second Deal');
    $_SESSION['user_id'] = $userId;
    $this->grantUnlock($userId, 'feature', 'second_daily_deal');

    $controller = new ShopController();
    $response = $this->invoke(fn() => $controller->catalog());

    $this->assertSame(200, $response['status']);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $dailyDeals = is_array($data['daily_deals'] ?? null) ? $data['daily_deals'] : [];

    $this->assertCount(2, $dailyDeals);
    $this->assertSame('daily_deal_1', (string)($dailyDeals[0]['product_id'] ?? ''));
    $this->assertSame('daily_deal_2', (string)($dailyDeals[1]['product_id'] ?? ''));
  }
}
