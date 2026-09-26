<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\ShopCatalogQuery;
use DiceGoblins\Application\Queries\ShopIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ShopCatalogController;
use DiceGoblins\Domain\Shop\ShopNumericContract;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ShopCatalogFoundationTest extends IntegrationTestCase
{
  /** @var list<string> */ private array $roots = [];
  protected function supportsVnextBaseline(): bool { return true; }
  protected function tearDown(): void { parent::tearDown(); foreach ($this->roots as $root) $this->removeTree($root); }

  public function testEmptyProductionShopReturnsCurrentWalletAndRevision(): void
  {
    $userId = $this->user('Empty Shop', 13, 4);
    $result = (new ShopCatalogQuery(new PlayerStateRepository($this->pdo), ContentRegistry::load(dirname(__DIR__, 2) . '/content')))->execute($userId);
    $this->assertSame(['teeth' => 13, 'player_revision' => 4, 'offers' => []], $result);
  }

  public function testFixtureOffersAreDeterministicAuthoritativeAndAffordabilityIsPerUser(): void
  {
    $content = $this->content(); $query = new ShopCatalogQuery(new PlayerStateRepository($this->pdo), $content);
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
    (new ShopCatalogQuery(new PlayerStateRepository($this->pdo), $this->content()))->execute(999999999);
  }

  public function testClientSafeMaximumWalletRevisionAndPriceAreReturnedExactly(): void
  {
    $maximum = ShopNumericContract::MAX_CLIENT_SAFE_INTEGER;
    $userId = $this->user('Shop Maximum', $maximum, $maximum);
    $result = (new ShopCatalogQuery(new PlayerStateRepository($this->pdo), $this->content($maximum)))->execute($userId);

    $this->assertSame($maximum, $result['teeth']);
    $this->assertSame($maximum, $result['player_revision']);
    $this->assertSame($maximum, $result['offers'][0]['price']['amount']);
    $this->assertTrue($result['offers'][0]['can_afford']);
  }

  public function testWalletAndRevisionAboveClientSafeMaximumAreIntegrityFailures(): void
  {
    $tooLarge = ShopNumericContract::MAX_CLIENT_SAFE_INTEGER + 1;
    $query = new ShopCatalogQuery(new PlayerStateRepository($this->pdo), $this->content());
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

  private function user(string $name, int $teeth, int $revision): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)'); $stmt?->execute([$name]);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `energy_current`, `player_revision`) VALUES (?, ?, 50, ?)')->execute([$id, $teeth, $revision]);
    return $id;
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
  private function copyCanonicalRoot(): string
  {
    $source = dirname(__DIR__, 2) . '/content'; $root = sys_get_temp_dir() . '/dice-goblins-shop-api-' . bin2hex(random_bytes(6)); mkdir($root, 0777, true); $this->roots[] = $root;
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) { if (!$file->isFile()) continue; $relative = substr($file->getPathname(), strlen($source) + 1); $target = $root . '/' . str_replace('\\', '/', $relative); if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true); copy($file->getPathname(), $target); }
    return $root;
  }
  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return; $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST); foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($root);
  }
}
