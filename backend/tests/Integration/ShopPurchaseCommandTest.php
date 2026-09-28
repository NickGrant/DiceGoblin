<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\PurchaseShopOfferCommand;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Commands\ShopPurchaseException;
use DiceGoblins\Application\Commands\ShopPurchaseIntegrityException;
use DiceGoblins\Application\NormalUnitCreationService;
use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Application\Queries\UnitCollectionQuery;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\ShopCatalogController;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Support\ClientSafeInteger;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class ShopPurchaseCommandTest extends IntegrationTestCase
{
  /** @var list<string> */ private array $roots = [];
  protected function supportsVnextBaseline(): bool { return true; }
  protected function tearDown(): void { parent::tearDown(); foreach ($this->roots as $root) $this->removeTree($root); }

  public function testItemPurchaseIsAtomicAndExactRetryReplaysAfterOfferRemoval(): void
  {
    $userId = $this->user(10); $command = $this->command($this->content());
    $request = $this->request('shop_offer.item', 7);
    $first = $command->execute($userId, $request, 'purchase-item-key');
    $this->assertSame(3, $first['spend']['balance_after']);
    $this->assertSame(['type' => 'item', 'item_id' => 'item.test.scrap', 'quantity_granted' => 2, 'owned_quantity_after' => 2], $first['output']);
    $this->assertSame(2, $first['player_revision']);

    $normalizedReplay = ['expected_price' => ['amount' => 7, 'currency_id' => 'teeth'], 'offer_id' => 'shop_offer.item'];
    $replay = $this->command(ContentRegistry::load(dirname(__DIR__, 2) . '/content'))
      ->execute($userId, $normalizedReplay, 'purchase-item-key');
    $this->assertSame($first, $replay);
    $this->assertSame($first, $this->command($this->content(99))->execute($userId, $request, 'purchase-item-key'));
    $this->assertSame('3', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('2', (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
  }

  /** @dataProvider basicDieSizes */
  public function testFixedBasicDiePurchaseCreatesOneActiveUnboundOwnedDie(int $size): void
  {
    $userId = $this->user(7); $offer = 'shop_offer.d' . $size;
    $result = $this->command($this->content())->execute($userId, $this->request($offer, 7), 'purchase-die-' . $size);
    $this->assertSame('die', $result['output']['type']);
    $this->assertSame($size, $result['output']['die']['size']);
    $this->assertSame('active', $result['output']['die']['lifecycle_status']);
    $this->assertSame(0, $result['spend']['balance_after']);
    $id = (int)$result['output']['die']['id'];
    $row = $this->pdo?->query("SELECT `user_id`, `size`, `profile_id`, `lifecycle_status` FROM `dice_instances` WHERE `id` = {$id}")->fetch();
    $this->assertSame((string)$userId, (string)$row['user_id']); $this->assertSame($size, (int)$row['size']);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_ability_dice` WHERE `dice_instance_id` = ?', [$id]));
    $this->assertSame($result, $this->command($this->content())->execute($userId, $this->request($offer, 7), 'purchase-die-' . $size));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]));
  }

  /** @return list<array{int}> */
  public static function basicDieSizes(): array { return [[4], [6], [8]]; }

  /** @dataProvider higherDieSizes */
  public function testHigherDieOfferRejectsAtomicallyUntilCapabilityIsOwned(int $size): void
  {
    $userId = $this->user(7);
    $content = $this->content();
    $offerId = 'shop_offer.d' . $size;
    try {
      $this->command($content)->execute($userId, $this->request($offerId, 7), 'locked-d' . $size);
      $this->fail('Expected locked size.');
    } catch (ShopPurchaseException $e) { $this->assertSame('shop_offer_unavailable', $e->errorCode); }
    $this->assertSame('7', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.capability.die_size_d' . $size);
    $result = $this->command($content)->execute($userId, $this->request($offerId, 7), 'locked-d' . $size);
    $this->assertSame($size, $result['output']['die']['size']);
    $this->assertSame(0, $result['spend']['balance_after']);
  }

  /** @return list<array{int}> */
  public static function higherDieSizes(): array { return [[10], [12], [20]]; }

  public function testInsufficientChangedAndMissingOffersChangeNothing(): void
  {
    foreach ([
      [$this->content(), 6, $this->request('shop_offer.item', 7), 'insufficient_teeth'],
      [$this->content(), 10, $this->request('shop_offer.item', 8), 'shop_offer_changed'],
      [ContentRegistry::load(dirname(__DIR__, 2) . '/content'), 10, $this->request('shop_offer.item', 7), 'shop_offer_not_found'],
    ] as $index => [$content, $teeth, $request, $code]) {
      $userId = $this->user($teeth);
      try { $this->command($content)->execute($userId, $request, 'purchase-fail-' . $index); $this->fail('Expected purchase failure.'); }
      catch (ShopPurchaseException $e) { $this->assertSame($code, $e->errorCode); }
      $this->assertSame((string)$teeth, (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
      $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_items` WHERE `user_id` = ?', [$userId]));
      $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
    }
  }

  public function testSameKeyDifferentOfferOrPriceConflictsWithoutSecondMutation(): void
  {
    $userId = $this->user(30); $command = $this->command($this->content());
    $command->execute($userId, $this->request('shop_offer.item', 7), 'purchase-conflict');
    foreach ([$this->request('shop_offer.d4', 7), $this->request('shop_offer.item', 8)] as $request) {
      try { $command->execute($userId, $request, 'purchase-conflict'); $this->fail('Expected conflict.'); }
      catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    }
    $this->assertSame('23', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('2', (string)$this->scalar('SELECT `quantity` FROM `user_items` WHERE `user_id` = ?', [$userId]));
  }

  public function testItemAndRevisionOverflowRejectAtomically(): void
  {
    $content = $this->content();
    $itemUser = $this->user(10);
    $this->pdo?->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)')
      ->execute([$itemUser, 'item.test.scrap', ClientSafeInteger::MAXIMUM]);
    $revisionUser = $this->user(10, ClientSafeInteger::MAXIMUM);
    foreach ([$itemUser, $revisionUser] as $index => $userId) {
      try { $this->command($content)->execute($userId, $this->request('shop_offer.item', 7), 'purchase-overflow-' . $index); $this->fail('Expected integrity failure.'); }
      catch (ShopPurchaseIntegrityException) { $this->addToAssertionCount(1); }
      $this->assertSame('10', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
      $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
    }
  }

  public function testFailureBeforeCommitRollsBackSpendOutputRevisionAndReceipt(): void
  {
    $userId = $this->user(10);
    try {
      $this->command($this->content(), static fn() => throw new RuntimeException('stop'))
        ->execute($userId, $this->request('shop_offer.item', 7), 'purchase-rollback');
      $this->fail('Expected rollback failure.');
    } catch (ShopPurchaseIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame(['teeth' => 10, 'player_revision' => 1], array_map('intval', $this->pdo?->query("SELECT `teeth`, `player_revision` FROM `user_state` WHERE `user_id` = {$userId}")->fetch()));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_items` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
  }

  public function testPersistenceMutationPrimitivesRequireCallerOwnedTransaction(): void
  {
    $userId = $this->user(10);
    foreach ([
      fn() => (new PlayerStateRepository($this->pdo))->applyCurrencyDebitTransition($userId, 'teeth', 10, 3),
      fn() => (new UserItemRepository($this->pdo))->increment($userId, 'item.test.scrap', 1),
      fn() => (new WarbandDiceRepository($this->pdo))->createActive($userId, 6, 'dice_profile.cardboard_plain'),
      fn() => (new WarbandUnitRepository($this->pdo))->createActive($userId, 'unit_type.bruiser', 'kin.goblin', 'Bruiser'),
    ] as $mutation) {
      try { $mutation(); $this->fail('Expected transaction enforcement.'); }
      catch (RuntimeException) { $this->addToAssertionCount(1); }
    }
  }

  public function testPurchaseEndpointRequiresAuthCsrfStrictBodyAndIdempotency(): void
  {
    $controller = new ShopCatalogController($this->content());
    $this->setJsonBody($this->request('shop_offer.item', 7));
    $this->assertSame(401, $this->invoke(fn() => $controller->purchase())['status']);
    $userId = $this->user(10); $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $this->setJsonBody($this->request('shop_offer.item', 7));
    $this->assertSame(403, $this->invoke(fn() => $controller->purchase())['status']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    foreach ([null, 'short'] as $key) {
      if ($key === null) unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); else $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
      $this->setJsonBody($this->request('shop_offer.item', 7)); $response = $this->invoke(fn() => $controller->purchase());
      $this->assertSame(400, $response['status']); $this->assertSame('idempotency_key_invalid', $response['body']['error']['code'] ?? null);
    }
    foreach ([
      [], ['offer_id' => 'item.bad', 'expected_price' => ['currency_id' => 'teeth', 'amount' => 7]],
      ['offer_id' => 'shop_offer.item', 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => 7]],
      ['offer_id' => 'shop_offer.item', 'expected_price' => ['currency_id' => 'teeth', 'amount' => 0]],
      ['offer_id' => 'shop_offer.item', 'expected_price' => ['currency_id' => 'teeth', 'amount' => 7, 'extra' => true]],
      ['offer_id' => 'shop_offer.item', 'expected_price' => ['currency_id' => 'teeth', 'amount' => 7], 'grant' => []],
    ] as $index => $bad) {
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'invalid-purchase-' . $index;
      $this->setJsonBody($bad); $response = $this->invoke(fn() => $controller->purchase());
      $this->assertSame(422, $response['status']); $this->assertSame('invalid_shop_purchase', $response['body']['error']['code'] ?? null);
    }
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'valid-purchase-key';
    $this->setJsonBody($this->request('shop_offer.item', 7)); $response = $this->invoke(fn() => $controller->purchase());
    $this->assertSame(200, $response['status'], json_encode($response['body'])); $this->assertSame('item', $response['body']['data']['output']['type'] ?? null);
  }

  public function testPurchaseEndpointMapsBusinessFailuresWithoutMutation(): void
  {
    $userId = $this->user(6); $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    foreach ([
      [new ShopCatalogController($this->content()), $this->request('shop_offer.item', 7), 'api-insufficient', 409, 'insufficient_teeth'],
      [new ShopCatalogController($this->content()), $this->request('shop_offer.item', 8), 'api-changed', 409, 'shop_offer_changed'],
      [new ShopCatalogController(ContentRegistry::load(dirname(__DIR__, 2) . '/content')), $this->request('shop_offer.item', 7), 'api-missing', 404, 'shop_offer_not_found'],
    ] as [$controller, $request, $key, $status, $code]) {
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key; $this->setJsonBody($request);
      $response = $this->invoke(fn() => $controller->purchase());
      $this->assertSame($status, $response['status']); $this->assertSame($code, $response['body']['error']['code'] ?? null);
    }
    $this->assertSame('6', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
  }

  public function testLockedUnitOfferAndAnotherUsersUnlockCannotMutate(): void
  {
    $content = $this->content(); $buyer = $this->user(20); $other = $this->user(20);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($other, 'unlock.unit_type.bruiser');
    try { $this->command($content)->execute($buyer, $this->request('shop_offer.unit', 13), 'locked-unit-key'); $this->fail('Expected unavailable offer.'); }
    catch (ShopPurchaseException $e) { $this->assertSame(['shop_offer_unavailable', 403], [$e->errorCode, $e->httpStatus]); }
    $this->assertSame('20', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$buyer]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$buyer]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$buyer]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$buyer]));
  }

  public function testUnlockedUnitPurchaseCreatesOneUnconfiguredNormalUnitAndRetrySurvivesUnlockRemoval(): void
  {
    $content = $this->content(); $userId = $this->user(20); $unlocks = new UserUnlockRepository($this->pdo);
    $unlocks->insertIfAbsent($userId, 'unlock.unit_type.bruiser');
    $request = $this->request('shop_offer.unit', 13); $command = $this->command($content);
    $first = $command->execute($userId, $request, 'purchase-unit-key');
    $unit = $first['output']['unit']; $unitId = (int)$unit['id'];
    $this->assertSame(['Bruiser', 'unit_type.bruiser', 'kin.goblin', 1, 0, 'active'], [
      $unit['display_name'], $unit['unit_type_id'], $unit['kin_id'], $unit['level'], $unit['xp'], $unit['lifecycle_status'],
    ]);
    $expectedAbilities = $content->unitType('unit_type.bruiser')['ability_ids'];
    $stmt = $this->pdo?->prepare('SELECT `ability_id` FROM `unit_abilities` WHERE `unit_id` = ? ORDER BY `unlocked_at`, `ability_id`');
    $stmt?->execute([$unitId]); $this->assertSame($expectedAbilities, $stmt?->fetchAll(\PDO::FETCH_COLUMN));
    foreach (['unit_promotions', 'unit_ability_loadout', 'unit_ability_dice'] as $table) {
      $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `unit_id` = ?", [$unitId]));
    }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]));
    $this->assertCount(1, (new UnitCollectionQuery(new WarbandUnitRepository($this->pdo), $content))->execute($userId));
    $detail = (new UnitDetailQuery(new WarbandUnitRepository($this->pdo), $content))->execute($userId, $unitId);
    $this->assertSame([$expectedAbilities, [], []], [$detail['owned_ability_ids'], $detail['ability_loadout'], $detail['dice_bindings']]);
    $this->pdo?->prepare('DELETE FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?')->execute([$userId, 'unlock.unit_type.bruiser']);
    $this->assertSame($first, $command->execute($userId, $request, 'purchase-unit-key'));
    $this->assertSame('7', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]));
  }

  public function testUnitCreationRollbackAndTransactionBoundary(): void
  {
    $content = $this->content(); $userId = $this->user(20); $unlocks = new UserUnlockRepository($this->pdo);
    $unlocks->insertIfAbsent($userId, 'unlock.unit_type.bruiser');
    $service = new NormalUnitCreationService(new WarbandUnitRepository($this->pdo), $content);
    try { $service->create($userId, 'unit_type.bruiser', 'kin.goblin'); $this->fail('Expected transaction enforcement.'); }
    catch (RuntimeException) { $this->addToAssertionCount(1); }
    try {
      $this->command($content, static fn() => throw new RuntimeException('stop'))
        ->execute($userId, $this->request('shop_offer.unit', 13), 'unit-rollback-key');
      $this->fail('Expected rollback.');
    } catch (ShopPurchaseIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('20', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]));
  }

  public function testPurchasedUnconfiguredUnitCannotStartRun(): void
  {
    $content = $this->content(); $userId = $this->user(30); (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.unit_type.bruiser');
    $result = $this->command($content)->execute($userId, $this->request('shop_offer.unit', 13), 'unit-run-key');
    $unitId = (int)$result['output']['unit']['id'];
    $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)')->execute([$userId, 'Unconfigured']);
    $squadId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, 0)')->execute([$squadId, $unitId]);
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$squadId, $userId]);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    try { $services['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], 'unit-run-start'); $this->fail('Expected run configuration rejection.'); }
    catch (RunStartException $e) { $this->assertSame('run_configuration_invalid', $e->errorCode); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `runs` WHERE `user_id` = ?', [$userId]));
  }

  private function user(int $teeth, int $revision = 1): int
  {
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Shop Purchase ' . bin2hex(random_bytes(4))]);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `energy_current`, `player_revision`) VALUES (?, ?, 50, ?)')->execute([$id, $teeth, $revision]);
    return $id;
  }
  /** @return array{offer_id:string,expected_price:array{currency_id:string,amount:int}} */
  private function request(string $offerId, int $price): array { return ['offer_id' => $offerId, 'expected_price' => ['currency_id' => 'teeth', 'amount' => $price]]; }
  private function command(ContentRegistry $content, ?\Closure $beforeCommit = null): PurchaseShopOfferCommand
  {
    $units = new WarbandUnitRepository($this->pdo); $unlocks = new UserUnlockRepository($this->pdo);
    return new PurchaseShopOfferCommand($this->pdo, new PlayerStateRepository($this->pdo), new IdempotencyRequestRepository($this->pdo),
      new UserItemRepository($this->pdo), new WarbandDiceRepository($this->pdo), $unlocks,
      new UnitTypeAvailabilityPolicy($content), new NormalUnitCreationService($units, $content), $content, $beforeCommit);
  }
  private function content(int $itemPrice = 7): ContentRegistry
  {
    $source = dirname(__DIR__, 2) . '/content'; $root = sys_get_temp_dir() . '/dice-goblins-purchase-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true); $this->roots[] = $root;
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) { if (!$file->isFile()) continue; $relative = substr($file->getPathname(), strlen($source) + 1); $target = $root . '/' . str_replace('\\', '/', $relative); if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true); copy($file->getPathname(), $target); }
    file_put_contents($root . '/shop_offers/catalog.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/unlocks/unit-types.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/academy_upgrades/catalog.json', json_encode(['definitions' => []], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/items/test-purchase.json', json_encode(['definitions' => [[
      'id' => 'item.test.scrap', 'type' => 'item', 'display_name' => 'Scrap', 'description' => 'Scrap.', 'category' => 'material', 'rarity' => 'common', 'icon_key' => 'scrap', 'stackable' => true,
    ]]], JSON_THROW_ON_ERROR));
    $offers = [[
      'id' => 'shop_offer.item', 'type' => 'shop_offer', 'grant' => ['type' => 'item', 'item_id' => 'item.test.scrap', 'quantity' => 2], 'price' => ['currency_id' => 'teeth', 'amount' => $itemPrice],
    ]];
    foreach ([4, 6, 8, 10, 12, 20] as $size) $offers[] = ['id' => 'shop_offer.d' . $size, 'type' => 'shop_offer',
      'grant' => ['type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => $size], 'price' => ['currency_id' => 'teeth', 'amount' => 7]];
    $offers[] = ['id' => 'shop_offer.unit', 'type' => 'shop_offer',
      'grant' => ['type' => 'unit', 'unit_type_id' => 'unit_type.bruiser', 'kin_id' => 'kin.goblin'],
      'price' => ['currency_id' => 'teeth', 'amount' => 13]];
    file_put_contents($root . '/unlocks/test-purchase.json', json_encode(['definitions' => [[
      'id' => 'unlock.unit_type.bruiser', 'type' => 'unlock', 'target_type' => 'unit_type', 'target_id' => 'unit_type.bruiser',
    ]]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/shop_offers/test-purchase.json', json_encode(['definitions' => $offers], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }
  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return; $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($root);
  }
}
