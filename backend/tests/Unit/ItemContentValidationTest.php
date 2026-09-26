<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use PHPUnit\Framework\TestCase;

final class ItemContentValidationTest extends TestCase
{
  /** @var list<string> */
  private array $roots = [];

  protected function tearDown(): void
  {
    foreach ($this->roots as $root) $this->removeTree($root);
  }

  public function testEmptyProductionCatalogIsValidAndProjected(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $this->assertSame([], $registry->definitionsOfType('item'));
    $this->assertSame([], (new ClientContentProjector())->project($registry)['content']['items']);
  }

  public function testValidMaterialAndConcreteConsumableLoadAndProjectOnlySafeFields(): void
  {
    $registry = $this->registryWithItems([
      $this->material(),
      $this->consumable(),
    ]);
    $this->assertSame('material', $registry->item('item.goblin_scrap')['category']);
    $projection = (new ClientContentProjector())->project($registry);
    $this->assertSame([
      'id' => 'item.spark_tonic', 'display_name' => 'Spark Tonic', 'description' => 'Restores Energy.',
      'category' => 'consumable', 'rarity' => 'common', 'icon_key' => 'icon_item_spark_tonic',
      'stackable' => true, 'effect' => ['type' => 'energy_restore', 'amount' => 5],
    ], $projection['content']['items']['item.spark_tonic']);
    $encoded = json_encode($projection['content']['items'], JSON_THROW_ON_ERROR);
    foreach (['price', 'cost', 'offer', 'weight', 'probability'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
  }

  /** @dataProvider invalidItemProvider */
  public function testMalformedItemsAreRejected(callable $mutate): void
  {
    $item = $this->consumable();
    $mutate($item);
    $this->expectException(ContentValidationException::class);
    $this->registryWithItems([$item]);
  }

  public function invalidItemProvider(): array
  {
    return [
      'id namespace' => [static fn(array &$item) => $item['id'] = 'thing.spark_tonic'],
      'empty presentation' => [static fn(array &$item) => $item['display_name'] = ''],
      'unknown category' => [static fn(array &$item) => $item['category'] = 'quest'],
      'unknown rarity' => [static fn(array &$item) => $item['rarity'] = 'mythic'],
      'non-boolean stackability' => [static fn(array &$item) => $item['stackable'] = 1],
      'unknown effect type' => [static fn(array &$item) => $item['effect']['type'] = 'script'],
      'invalid effect amount' => [static fn(array &$item) => $item['effect']['amount'] = 0],
      'extra effect field' => [static fn(array &$item) => $item['effect']['target'] = 'anything'],
      'missing consumable effect' => [static function(array &$item): void { unset($item['effect']); }],
      'material effect' => [static function(array &$item): void { $item['category'] = 'material'; }],
      'unknown top-level field' => [static fn(array &$item) => $item['shop_price'] = 4],
    ];
  }

  public function testDuplicateItemIdentityIsRejected(): void
  {
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage("Duplicate stable id 'item.goblin_scrap'");
    $this->registryWithItems([$this->material(), $this->material()]);
  }

  /** @param list<array<string,mixed>> $items */
  private function registryWithItems(array $items): ContentRegistry
  {
    $root = $this->copyCanonicalRoot();
    file_put_contents($root . '/items/test.json', json_encode(['definitions' => $items], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }

  /** @return array<string,mixed> */
  private function material(): array
  {
    return ['id' => 'item.goblin_scrap', 'type' => 'item', 'display_name' => 'Goblin Scrap',
      'description' => 'A stack of useful scrap.', 'category' => 'material', 'rarity' => 'common',
      'icon_key' => 'icon_item_goblin_scrap', 'stackable' => true];
  }

  /** @return array<string,mixed> */
  private function consumable(): array
  {
    return ['id' => 'item.spark_tonic', 'type' => 'item', 'display_name' => 'Spark Tonic',
      'description' => 'Restores Energy.', 'category' => 'consumable', 'rarity' => 'common',
      'icon_key' => 'icon_item_spark_tonic', 'stackable' => true,
      'effect' => ['type' => 'energy_restore', 'amount' => 5]];
  }

  private function canonicalRoot(): string { return dirname(__DIR__, 2) . '/content'; }

  private function copyCanonicalRoot(): string
  {
    $root = sys_get_temp_dir() . '/dice-goblins-items-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true); $this->roots[] = $root;
    $source = $this->canonicalRoot();
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      if (!$file->isFile()) continue;
      $relative = substr($file->getPathname(), strlen($source) + 1);
      $target = $root . '/' . str_replace('\\', '/', $relative);
      if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
      copy($file->getPathname(), $target);
    }
    return $root;
  }

  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return;
    $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
  }
}
