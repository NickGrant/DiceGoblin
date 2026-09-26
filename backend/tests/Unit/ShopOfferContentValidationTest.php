<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Domain\Shop\ShopNumericContract;
use PHPUnit\Framework\TestCase;

final class ShopOfferContentValidationTest extends TestCase
{
  /** @var list<string> */ private array $roots = [];
  protected function tearDown(): void { foreach ($this->roots as $root) $this->removeTree($root); }

  public function testEmptyProductionCatalogIsValid(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $this->assertSame([], $registry->definitionsOfType('shop_offer'));
    $this->assertSame([], (new ClientContentProjector())->project($registry)['content']['shop_offers']);
  }

  public function testValidItemAndDieOffersLoadAndProjectWithoutPrices(): void
  {
    $registry = $this->registry([$this->itemOffer(), $this->dieOffer()], true);
    $this->assertSame('item', $registry->shopOffer('shop_offer.scrap')['grant']['type']);
    $offers = (new ClientContentProjector())->project($registry)['content']['shop_offers'];
    $this->assertSame(['id' => 'shop_offer.scrap', 'grant' => [
      'type' => 'item', 'item_id' => 'item.test.scrap', 'quantity' => 3,
    ]], $offers['shop_offer.scrap']);
    $this->assertSame(['id' => 'shop_offer.cardboard_d8', 'grant' => [
      'type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => 8,
    ]], $offers['shop_offer.cardboard_d8']);
    $encoded = json_encode($offers, JSON_THROW_ON_ERROR);
    foreach (['price', 'currency_id', 'amount', 'limit', 'random', 'weight'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
  }

  public function testPriceAcceptsClientSafeMaximumAndRejectsTheNextInteger(): void
  {
    $maximum = $this->itemOffer();
    $maximum['price']['amount'] = ShopNumericContract::MAX_CLIENT_SAFE_INTEGER;
    $this->assertSame(
      ShopNumericContract::MAX_CLIENT_SAFE_INTEGER,
      $this->registry([$maximum], true)->shopOffer('shop_offer.scrap')['price']['amount'],
    );

    $tooLarge = $this->itemOffer();
    $tooLarge['price']['amount'] = ShopNumericContract::MAX_CLIENT_SAFE_INTEGER + 1;
    $this->expectException(ContentValidationException::class);
    $this->registry([$tooLarge], true);
  }

  /** @dataProvider malformedOfferProvider */
  public function testMalformedOffersReject(callable $mutate): void
  {
    $offer = $this->itemOffer(); $mutate($offer);
    $this->expectException(ContentValidationException::class);
    $this->registry([$offer], true);
  }

  public function malformedOfferProvider(): array
  {
    return [
      'namespace' => [static fn(array &$o) => $o['id'] = 'offer.scrap'],
      'grant type' => [static fn(array &$o) => $o['grant']['type'] = 'unit'],
      'quantity zero' => [static fn(array &$o) => $o['grant']['quantity'] = 0],
      'currency' => [static fn(array &$o) => $o['price']['currency_id'] = 'raw_chaos'],
      'price zero' => [static fn(array &$o) => $o['price']['amount'] = 0],
      'price negative' => [static fn(array &$o) => $o['price']['amount'] = -1],
      'extra offer field' => [static fn(array &$o) => $o['daily'] = true],
      'extra grant field' => [static fn(array &$o) => $o['grant']['random'] = true],
      'extra price field' => [static fn(array &$o) => $o['price']['discount'] = 1],
    ];
  }

  public function testMissingAndNonStackableItemReferencesReject(): void
  {
    foreach ([false, true] as $includeNonStackable) {
      $offer = $this->itemOffer();
      if (!$includeNonStackable) $offer['grant']['item_id'] = 'item.missing';
      else $offer['grant']['item_id'] = 'item.test.singular';
      try { $this->registry([$offer], $includeNonStackable); $this->fail('Expected invalid item reference.'); }
      catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }

  public function testDieReferenceCompatibilityAndMilestoneSizeCapReject(): void
  {
    $missing = $this->dieOffer(); $missing['grant']['dice_profile_id'] = 'dice_profile.missing';
    $incompatible = $this->dieOffer(); $incompatible['grant']['dice_profile_id'] = 'dice_profile.test_d6';
    foreach ([$missing, $incompatible] as $offer) {
      try { $this->registry([$offer], false); $this->fail('Expected invalid die offer.'); }
      catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
    foreach ([10, 12, 20] as $size) {
      $offer = $this->dieOffer(); $offer['grant']['size'] = $size;
      try { $this->registry([$offer], false); $this->fail('Expected Milestone 7 die-size rejection.'); }
      catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }

  /** @return array<string,mixed> */
  private function itemOffer(): array { return ['id' => 'shop_offer.scrap', 'type' => 'shop_offer',
    'grant' => ['type' => 'item', 'item_id' => 'item.test.scrap', 'quantity' => 3],
    'price' => ['currency_id' => 'teeth', 'amount' => 7]]; }
  /** @return array<string,mixed> */
  private function dieOffer(): array { return ['id' => 'shop_offer.cardboard_d8', 'type' => 'shop_offer',
    'grant' => ['type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => 8],
    'price' => ['currency_id' => 'teeth', 'amount' => 11]]; }

  /** @param list<array<string,mixed>> $offers */
  private function registry(array $offers, bool $withItems): ContentRegistry
  {
    $root = $this->copyCanonicalRoot();
    if ($withItems) file_put_contents($root . '/items/test-shop.json', json_encode(['definitions' => [[
      'id' => 'item.test.scrap', 'type' => 'item', 'display_name' => 'Scrap', 'description' => 'Scrap.',
      'category' => 'material', 'rarity' => 'common', 'icon_key' => 'scrap', 'stackable' => true,
    ], [
      'id' => 'item.test.singular', 'type' => 'item', 'display_name' => 'Singular', 'description' => 'One.',
      'category' => 'material', 'rarity' => 'common', 'icon_key' => 'singular', 'stackable' => false,
    ]]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/dice/test-shop.json', json_encode(['definitions' => [[
      'id' => 'dice_profile.test_d6', 'type' => 'dice_profile', 'display_name' => 'Test d6',
      'material_id' => 'dice_material.cardboard', 'rarity' => 'common', 'aspect_ids' => [], 'allowed_sizes' => [6],
    ]]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/shop_offers/test.json', json_encode(['definitions' => $offers], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }

  private function canonicalRoot(): string { return dirname(__DIR__, 2) . '/content'; }
  private function copyCanonicalRoot(): string
  {
    $root = sys_get_temp_dir() . '/dice-goblins-shop-' . bin2hex(random_bytes(6)); mkdir($root, 0777, true); $this->roots[] = $root;
    $source = $this->canonicalRoot();
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) { if (!$file->isFile()) continue; $relative = substr($file->getPathname(), strlen($source) + 1);
      $target = $root . '/' . str_replace('\\', '/', $relative); if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true); copy($file->getPathname(), $target); }
    return $root;
  }
  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return; $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($root);
  }
}
