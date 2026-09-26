<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\InventoryIntegrityException;
use DiceGoblins\Application\Queries\ItemCollectionQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\InventoryController;
use DiceGoblins\Domain\Inventory\InsufficientInventoryException;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class InventoryFoundationTest extends IntegrationTestCase
{
  private ?string $contentRoot = null;

  protected function supportsVnextBaseline(): bool { return true; }

  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->contentRoot !== null) $this->removeTree($this->contentRoot);
    $this->contentRoot = null;
  }

  public function testRepositoryListsOnlyCallerPositiveStacksInStableOrder(): void
  {
    $owner = $this->user('Inventory Owner'); $other = $this->user('Inventory Other');
    $this->insertStack($owner, 'item.spark_tonic', 2);
    $this->insertStack($owner, 'item.goblin_scrap', 7);
    $this->insertStack($owner, 'item.zero', 0);
    $this->insertStack($other, 'item.secret', 99);

    $this->assertSame([
      ['item_id' => 'item.goblin_scrap', 'quantity' => 7],
      ['item_id' => 'item.spark_tonic', 'quantity' => 2],
    ], (new UserItemRepository($this->pdo))->listPositiveForUser($owner));
  }

  public function testRepositoryIncrementSpendInsufficientAndZeroRemovalAreExact(): void
  {
    $userId = $this->user('Inventory Mutation'); $items = new UserItemRepository($this->pdo);
    $this->expectTransactionRequired(fn() => $items->increment($userId, 'item.goblin_scrap', 1));

    $this->pdo?->beginTransaction();
    $this->assertSame(3, $items->increment($userId, 'item.goblin_scrap', 3));
    $this->assertSame(['item_id' => 'item.goblin_scrap', 'quantity' => 3], $items->lockOwnedStack($userId, 'item.goblin_scrap'));
    $this->assertSame(2, $items->decrement($userId, 'item.goblin_scrap', 1));
    $this->pdo?->commit();

    $this->pdo?->beginTransaction();
    try {
      $items->decrement($userId, 'item.goblin_scrap', 3);
      $this->fail('Expected insufficient inventory.');
    } catch (InsufficientInventoryException) {
      $this->pdo?->rollBack();
    }
    $this->assertSame('2', (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ? AND `item_id` = ?', [$userId, 'item.goblin_scrap']));

    $this->pdo?->beginTransaction();
    $this->assertSame(0, $items->decrement($userId, 'item.goblin_scrap', 2));
    $this->pdo?->commit();
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_items` WHERE `user_id` = ? AND `item_id` = ?', [$userId, 'item.goblin_scrap']));
  }

  public function testQueryValidatesAuthoredIdentityAndStackability(): void
  {
    $userId = $this->user('Inventory Query');
    $this->insertStack($userId, 'item.goblin_scrap', 4);
    $query = new ItemCollectionQuery(new UserItemRepository($this->pdo), $this->content());
    $this->assertSame([['item_id' => 'item.goblin_scrap', 'quantity' => 4]], $query->execute($userId));

    $this->insertStack($userId, 'item.stale', 1);
    $this->expectException(InventoryIntegrityException::class);
    $query->execute($userId);
  }

  public function testItemsEndpointIsAuthenticatedDeterministicReadOnlyAndOwnerScoped(): void
  {
    $owner = $this->user('Inventory API'); $other = $this->user('Inventory API Other');
    $this->insertStack($owner, 'item.spark_tonic', 2);
    $this->insertStack($owner, 'item.goblin_scrap', 7);
    $this->insertStack($other, 'item.goblin_scrap', 99);
    $controller = new InventoryController($this->content());

    $unauthorized = $this->invoke(fn() => $controller->items());
    $this->assertSame(401, $unauthorized['status']);
    $this->assertSame('unauthorized', $unauthorized['body']['error']['code'] ?? null);

    $_SESSION['user_id'] = $owner;
    $revision = $this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$owner]);
    $response = $this->invoke(fn() => $controller->items());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame([
      ['item_id' => 'item.goblin_scrap', 'quantity' => 7],
      ['item_id' => 'item.spark_tonic', 'quantity' => 2],
    ], $response['body']['data']['items'] ?? null);
    $this->assertSame($revision, $this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$owner]));

    $this->pdo?->prepare('DELETE FROM `user_items` WHERE `user_id` = ?')->execute([$owner]);
    $empty = $this->invoke(fn() => $controller->items());
    $this->assertSame([], $empty['body']['data']['items'] ?? null);
  }

  public function testItemsEndpointRejectsStaleAuthoredIdentityWithoutDisclosure(): void
  {
    $userId = $this->user('Inventory Stale'); $this->insertStack($userId, 'item.removed', 1);
    $_SESSION['user_id'] = $userId;
    $response = $this->invoke(fn() => (new InventoryController($this->content()))->items());
    $this->assertSame(500, $response['status']);
    $this->assertSame('inventory_data_integrity_error', $response['body']['error']['code'] ?? null);
    $this->assertStringNotContainsString('item.removed', json_encode($response['body'], JSON_THROW_ON_ERROR));
  }

  public function testUserDeleteCascadesOwnedStacks(): void
  {
    $userId = $this->user('Inventory Cascade'); $this->insertStack($userId, 'item.goblin_scrap', 1);
    $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$userId]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_items` WHERE `user_id` = ?', [$userId]));
  }

  private function expectTransactionRequired(callable $operation): void
  {
    try { $operation(); $this->fail('Expected a caller-owned transaction requirement.'); }
    catch (\RuntimeException $error) { $this->assertStringContainsString('caller-owned transaction', $error->getMessage()); }
  }

  private function user(string $name): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)'); $stmt?->execute([$name]);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$id]);
    return $id;
  }

  private function insertStack(int $userId, string $itemId, int $quantity): void
  {
    $this->pdo?->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)')->execute([$userId, $itemId, $quantity]);
  }

  private function content(): ContentRegistry
  {
    if ($this->contentRoot === null) {
      $source = dirname(__DIR__, 2) . '/content';
      $this->contentRoot = sys_get_temp_dir() . '/dice-goblins-inventory-' . bin2hex(random_bytes(6));
      mkdir($this->contentRoot, 0777, true);
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
      foreach ($files as $file) {
        if (!$file->isFile()) continue;
        $relative = substr($file->getPathname(), strlen($source) + 1);
        $target = $this->contentRoot . '/' . str_replace('\\', '/', $relative);
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
        copy($file->getPathname(), $target);
      }
      file_put_contents($this->contentRoot . '/items/test.json', json_encode(['definitions' => [[
        'id' => 'item.goblin_scrap', 'type' => 'item', 'display_name' => 'Goblin Scrap',
        'description' => 'Useful scrap.', 'category' => 'material', 'rarity' => 'common',
        'icon_key' => 'icon_item_goblin_scrap', 'stackable' => true,
      ], [
        'id' => 'item.spark_tonic', 'type' => 'item', 'display_name' => 'Spark Tonic',
        'description' => 'Restores Energy.', 'category' => 'consumable', 'rarity' => 'common',
        'icon_key' => 'icon_item_spark_tonic', 'stackable' => true,
        'effect' => ['type' => 'energy_restore', 'amount' => 5],
      ]]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    return ContentRegistry::load($this->contentRoot);
  }

  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return;
    $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
  }
}
