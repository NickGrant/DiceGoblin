<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\ShopCatalogQuery;
use DiceGoblins\Application\Queries\ShopIntegrityException;
use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ShopCatalogController;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ShopCatalogFoundationTest extends IntegrationTestCase
{
  /** @var list<string> */ private array $roots = [];
  protected function tearDown(): void { parent::tearDown(); foreach ($this->roots as $root) $this->removeTree($root); }

  public function testShopReadIsLockedUntilFarmUnlock(): void
  {
    $userId = $this->user('Shop Gate', 300, 1);
    $this->pdo?->prepare('DELETE FROM `user_unlocks` WHERE `user_id` = ?')->execute([$userId]);
    $query = $this->query(ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content'));
    $this->expectException(\DiceGoblins\Application\Queries\ShopLockedException::class);
    $query->execute($userId);
  }

  public function testLockedShopEndpointReturnsForbiddenWithoutCatalog(): void
  {
    $userId = $this->user('Shop Locked API', 300, 1);
    $this->pdo?->prepare('DELETE FROM `user_unlocks` WHERE `user_id` = ?')->execute([$userId]);
    $_SESSION['user_id'] = $userId;
    $response = $this->invoke(fn() => (new ShopCatalogController())->catalog());
    $this->assertSame(403, $response['status']);
    $this->assertSame('shop_locked', $response['body']['error']['code'] ?? null);
    $this->assertArrayNotHasKey('data', $response['body']);
  }

  public function testAcademyAccessOfferIsAvailableOnceThenOwned(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $price = $content->shopOffer('shop_offer.academy_access')['price']['amount'];
    $userId = $this->user('Academy Offer', $price + 50, 1);
    $query = $this->query($content);
    $offers = array_column($query->execute($userId)['offers'], null, 'offer_id');
    $this->assertTrue($offers['shop_offer.academy_access']['available']);
    $this->assertTrue($offers['shop_offer.academy_access']['can_afford']);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.capability.academy_access');
    $offers = array_column($query->execute($userId)['offers'], null, 'offer_id');
    $this->assertFalse($offers['shop_offer.academy_access']['available']);
  }

  public function testProductionShopReturnsCanonicalOffersWithCurrentWalletAndRevision(): void
  {
    $userId = $this->user('Canonical Shop', 13, 4);
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $result = $this->query($content)->execute($userId);
    $this->assertSame(13, $result['teeth']);
    $this->assertSame(4, $result['player_revision']);
    $this->assertEqualsCanonicalizing(array_keys($content->definitionsOfType('shop_offer')),
      array_column($result['offers'], 'offer_id'));
    $this->assertCount(count($content->definitionsOfType('shop_offer')), $result['offers']);
    $offers = array_column($result['offers'], null, 'offer_id');
    $this->assertFalse($offers['shop_offer.goblin_bruiser']['available']);
  }

  public function testCanonicalAcademyUnitOffersRequireTheirExactUnlock(): void
  {
    $userId = $this->user('Academy Shop Units', 20, 2);
    $query = $this->query(ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content'));
    $locked = array_column($query->execute($userId)['offers'], 'available', 'offer_id');
    foreach (['guardian', 'marksman', 'bannerbearer', 'saboteur'] as $unit) {
      $this->assertFalse($locked['shop_offer.goblin_' . $unit]);
    }
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.unit_type.guardian');
    $available = array_column($query->execute($userId)['offers'], 'available', 'offer_id');
    $this->assertTrue($available['shop_offer.goblin_guardian']);
    $this->assertFalse($available['shop_offer.goblin_marksman']);
  }

  public function testFixtureOffersAreDeterministicAuthoritativeAndAffordabilityIsPerUser(): void
  {
    $content = $this->content(); $query = $this->query($content);
    $below = $this->user('Shop Below', 6, 2); $exact = $this->user('Shop Exact', 7, 3); $above = $this->user('Shop Above', 12, 5);
    $belowResult = $query->execute($below); $exactResult = $query->execute($exact); $aboveResult = $query->execute($above);
    $this->assertSame(['shop_offer.a1', 'shop_offer.a_'], array_column($belowResult['offers'], 'offer_id'));
    $this->assertSame(['currency_id' => 'teeth', 'amount' => 7], $belowResult['offers'][0]['price']);
    $this->assertSame([true, true], array_column($belowResult['offers'], 'available'));
    $this->assertSame([false, false], array_column($belowResult['offers'], 'can_afford'));
    $this->assertSame([true, false], array_column($exactResult['offers'], 'can_afford'));
    $this->assertSame([true, true], array_column($aboveResult['offers'], 'can_afford'));
    $this->assertSame(6, $belowResult['teeth']); $this->assertSame(2, $belowResult['player_revision']);
  }

  public function testAuthenticatedEndpointIsReadOnlyAndNonDisclosing(): void
  {
    $content = $this->content(); $controller = new ShopCatalogController($content);
    $unauthorized = $this->invoke(fn() => $controller->catalog());
    $this->assertSame(401, $unauthorized['status']); $this->assertSame('unauthorized', $unauthorized['body']['error']['code'] ?? null);
    $userId = $this->user('Shop API', 7, 9); $_SESSION['user_id'] = $userId;
    $before = $this->pdo?->query("SELECT `teeth`, `player_revision` FROM `user_state` WHERE `user_id` = {$userId}")->fetch(\PDO::FETCH_ASSOC);
    $response = $this->invoke(fn() => $controller->catalog());
    $after = $this->pdo?->query("SELECT `teeth`, `player_revision` FROM `user_state` WHERE `user_id` = {$userId}")->fetch(\PDO::FETCH_ASSOC);
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(['teeth' => 7, 'player_revision' => 9], array_intersect_key($response['body']['data'], ['teeth' => true, 'player_revision' => true]));
    $this->assertSame($before, $after);
  }

  public function testMissingPlayerStateIsIntegrityFailure(): void
  {
    $this->expectException(ShopIntegrityException::class);
    $this->query($this->content())->execute(999999999);
  }

  public function testHigherDieOfferAvailabilityFollowsOwnedCapability(): void
  {
    $root = $this->copyCanonicalRoot();
    $offers = [];
    foreach ([10, 12, 20] as $size) $offers[] = [
      'id' => 'shop_offer.d' . $size, 'type' => 'shop_offer',
      'grant' => ['type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => $size],
      'price' => ['currency_id' => 'teeth', 'amount' => 7],
    ];
    file_put_contents($root . '/shop_offers/test.json', json_encode(['definitions' => $offers], JSON_THROW_ON_ERROR));
    $query = $this->query(ContentRegistry::load($root));
    $userId = $this->user('Shop Higher Dice', 20, 2);
    $this->assertSame([false, false, false], array_column($query->execute($userId)['offers'], 'available'));
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.capability.die_size_d12');
    $this->assertSame([true, true, false], array_column($query->execute($userId)['offers'], 'available'));
    $this->assertSame('2', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testClientSafeMaximumWalletRevisionAndPriceAreReturnedExactly(): void
  {
    $maximum = ClientSafeInteger::MAXIMUM;
    $userId = $this->user('Shop Maximum', $maximum, $maximum);
    $result = $this->query($this->content($maximum))->execute($userId);

    $this->assertSame($maximum, $result['teeth']);
    $this->assertSame($maximum, $result['player_revision']);
    $this->assertSame($maximum, $result['offers'][0]['price']['amount']);
    $this->assertTrue($result['offers'][0]['can_afford']);
  }

  public function testWalletAndRevisionAboveClientSafeMaximumAreIntegrityFailures(): void
  {
    $tooLarge = ClientSafeInteger::MAXIMUM + 1;
    $query = $this->query($this->content());
    foreach ([[$tooLarge, 1], [1, $tooLarge]] as [$teeth, $revision]) {
      $userId = $this->user('Shop Unsafe', $teeth, $revision);
      try {
        $query->execute($userId);
        $this->fail('Expected unsafe Shop state to fail integrity validation.');
      } catch (ShopIntegrityException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  public function testUnitOfferAvailabilityUsesOnlyExactOwnedAuthoredEntitlementAndDoesNotMutate(): void
  {
    $content = $this->unitContent(); $query = $this->query($content); $unlocks = new UserUnlockRepository($this->pdo);
    $owner = $this->user('Shop Unit Owner', 20, 7); $other = $this->user('Shop Unit Other', 20, 3);
    $unlocks->insertIfAbsent($owner, 'unlock.stale');
    $unlocks->insertIfAbsent($owner, 'unlock.unit_type.marksman');
    $unlocks->insertIfAbsent($other, 'unlock.unit_type.bruiser');
    $locked = $query->execute($owner);
    $this->assertSame([false, true], array_column($locked['offers'], 'available'));
    $this->assertSame([true, true], array_column($locked['offers'], 'can_afford'));
    $unlocks->insertIfAbsent($owner, 'unlock.unit_type.bruiser');
    $available = $query->execute($owner);
    $this->assertSame([true, true], array_column($available['offers'], 'available'));
    $this->assertSame(7, $available['player_revision']);
    $this->assertSame('7', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$owner]));
  }

  private function user(string $name, int $teeth, int $revision): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)'); $stmt?->execute([$name]);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `energy_current`, `player_revision`) VALUES (?, ?, 50, ?)')->execute([$id, $teeth, $revision]);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($id, 'unlock.capability.shop_access');
    return $id;
  }
  private function query(ContentRegistry $content): ShopCatalogQuery
  {
    return new ShopCatalogQuery(new PlayerStateRepository($this->pdo), new UserUnlockRepository($this->pdo),
      new UnitTypeAvailabilityPolicy($content), $content);
  }

  private function content(int $firstPrice = 7): ContentRegistry
  {
    $root = $this->copyCanonicalRoot();
    file_put_contents($root . '/items/test-shop.json', json_encode(['definitions' => [[
      'id' => 'item.test.scrap', 'type' => 'item', 'display_name' => 'Scrap', 'description' => 'Scrap.',
      'category' => 'material', 'rarity' => 'common', 'icon_key' => 'scrap', 'stackable' => true,
    ]]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/shop_offers/test.json', json_encode(['definitions' => [[
      'id' => 'shop_offer.a_', 'type' => 'shop_offer', 'grant' => ['type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => 8], 'price' => ['currency_id' => 'teeth', 'amount' => 10],
    ], [
      'id' => 'shop_offer.a1', 'type' => 'shop_offer', 'grant' => ['type' => 'item', 'item_id' => 'item.test.scrap', 'quantity' => 2], 'price' => ['currency_id' => 'teeth', 'amount' => $firstPrice],
    ]]], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }
  private function unitContent(): ContentRegistry
  {
    $root = $this->copyCanonicalRoot();
    file_put_contents($root . '/unlocks/test-shop-units.json', json_encode(['definitions' => [[
      'id' => 'unlock.unit_type.bruiser', 'type' => 'unlock', 'target_type' => 'unit_type', 'target_id' => 'unit_type.bruiser',
    ], [
      'id' => 'unlock.unit_type.marksman', 'type' => 'unlock', 'target_type' => 'unit_type', 'target_id' => 'unit_type.marksman',
    ]]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/shop_offers/test-shop-units.json', json_encode(['definitions' => [[
      'id' => 'shop_offer.bruiser', 'type' => 'shop_offer', 'grant' => ['type' => 'unit', 'unit_type_id' => 'unit_type.bruiser', 'kin_id' => 'kin.goblin'], 'price' => ['currency_id' => 'teeth', 'amount' => 7],
    ], [
      'id' => 'shop_offer.marksman', 'type' => 'shop_offer', 'grant' => ['type' => 'unit', 'unit_type_id' => 'unit_type.marksman', 'kin_id' => 'kin.goblin'], 'price' => ['currency_id' => 'teeth', 'amount' => 8],
    ]]], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }
  private function copyCanonicalRoot(): string
  {
    $source = dirname(__DIR__, 2) . '/tests/Fixtures/content'; $root = sys_get_temp_dir() . '/dice-goblins-shop-api-' . bin2hex(random_bytes(6)); mkdir($root, 0777, true); $this->roots[] = $root;
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) { if (!$file->isFile()) continue; $relative = substr($file->getPathname(), strlen($source) + 1); $target = $root . '/' . str_replace('\\', '/', $relative); if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true); copy($file->getPathname(), $target); }
    file_put_contents($root . '/shop_offers/catalog.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/unlocks/unit-types.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/academy_upgrades/catalog.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/events/academy.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/rewards/academy.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    return $root;
  }
  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return; $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST); foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($root);
  }
}
