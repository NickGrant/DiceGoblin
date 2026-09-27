<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class EconomyLifecycleIntegrationTest extends IntegrationTestCase
{
  private ?string $contentRoot = null;

  protected function supportsVnextBaseline(): bool { return true; }

  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->contentRoot !== null) $this->removeTree($this->contentRoot);
  }

  public function testProductionCompositionPersistsTheCompleteEconomyLifecycleAndExactRetries(): void
  {
    $content = $this->content();
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Economy Closure']);
    $userId = (int)$this->pdo?->lastInsertId(); $this->trackUserId($userId);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`) VALUES (?, 100, 0, 5, UTC_TIMESTAMP(), 1)')
      ->execute([$userId]);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.unit_type.bruiser');

    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $initial = $services['gameBootstrapQuery']->execute($userId, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $catalog = $services['shopCatalogQuery']->execute($userId);
    $this->assertSame([100, 1, 5], [$initial['player']['teeth'], $initial['player']['player_revision'], count($catalog['offers'])]);
    $this->assertTrue($catalog['offers'][4]['available']);

    $purchase = $services['purchaseShopOfferCommand'];
    $energyPurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.test.energy', 5), 'closure-purchase-energy');
    $this->assertSame($energyPurchase, $purchase->execute($userId, $this->purchaseRequest('shop_offer.test.energy', 5), 'closure-purchase-energy'));
    $this->assertRevision($energyPurchase, 2);
    $healPurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.test.heal', 6), 'closure-purchase-heal');
    $this->assertRevision($healPurchase, 3);
    $sellDiePurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.test.d8', 10), 'closure-purchase-d8');
    $this->assertRevision($sellDiePurchase, 4);
    $salvageDiePurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.test.d6', 8), 'closure-purchase-d6');
    $this->assertRevision($salvageDiePurchase, 5);
    $unitPurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.test.unit', 13), 'closure-purchase-unit');
    $this->assertRevision($unitPurchase, 6);

    $unit = $unitPurchase['output']['unit']; $unitId = (int)$unit['id'];
    $this->assertSame(['unit_type.bruiser', 'kin.goblin', 1, 0, 'active'], [
      $unit['unit_type_id'], $unit['kin_id'], $unit['level'], $unit['xp'], $unit['lifecycle_status'],
    ]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_ability_loadout` WHERE `unit_id` = ?', [$unitId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_ability_dice` WHERE `unit_id` = ?', [$unitId]));

    $this->pdo?->prepare("INSERT INTO `squads` (`user_id`, `name`) VALUES (?, 'Closure Squad')")->execute([$userId]);
    $squadId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, 0)')->execute([$squadId, $unitId]);
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$squadId, $userId]);
    $this->pdo?->prepare("INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`) VALUES (?, 'region.the_farm', ?)")->execute([$userId, $squadId]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare("INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type_id`, `status`, `generated_metadata`) VALUES (?, 0, 'run_node_type.combat', 'available', JSON_OBJECT('position', JSON_OBJECT('column', 0, 'row', 0)))")
      ->execute([$runId]);
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, 1)')->execute([$runId, $unitId]);
    $topologyBefore = (string)$this->scalar('SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ?', [$runId]);

    $energy = $services['restoreEnergyCommand']->execute($userId, ['item_id' => 'item.test.energy'], 'closure-energy-use');
    $this->assertSame($energy, $services['restoreEnergyCommand']->execute($userId, ['item_id' => 'item.test.energy'], 'closure-energy-use'));
    $this->assertSame([12, 1, 7], [$energy['energy']['current'], $energy['owned_quantity_after'], $energy['player_revision']]);

    $maximumHp = (new BaseLevelStatResolver())->resolve(
      $content->unitType('unit_type.bruiser')['base_stats'],
      $content->unitType('unit_type.bruiser')['growth_per_level'],
      1,
    )->hp;
    $heal = $services['healRunUnitCommand']->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'closure-run-heal');
    $this->assertSame($heal, $services['healRunUnitCommand']->execute($userId, $runId, $unitId, ['item_id' => 'item.test.heal'], 'closure-run-heal'));
    $this->assertSame([1, min($maximumHp, 10), 1, 8], [
      $heal['unit']['hp_before'], $heal['unit']['hp_after'], $heal['owned_quantity_after'], $heal['player_revision'],
    ]);
    $this->assertSame($topologyBefore, (string)$this->scalar('SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ?', [$runId]));

    $sellDieId = (int)$sellDiePurchase['output']['die']['id'];
    $salvageDieId = (int)$salvageDiePurchase['output']['die']['id'];
    $sold = $services['diceLifecycleCommand']->sell($userId, $sellDieId, 'closure-sell-die');
    $this->assertSame($sold, $services['diceLifecycleCommand']->sell($userId, $sellDieId, 'closure-sell-die'));
    $this->assertRevision($sold, 9);
    $salvaged = $services['diceLifecycleCommand']->salvage($userId, $salvageDieId, 'closure-salvage-die');
    $this->assertSame($salvaged, $services['diceLifecycleCommand']->salvage($userId, $salvageDieId, 'closure-salvage-die'));
    $this->assertRevision($salvaged, 10);

    $reloaded = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $bootstrap = $reloaded['gameBootstrapQuery']->execute($userId, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $this->assertSame([$salvaged['raw_chaos'], $sold['teeth'], 10, 12, 'active'], [
      $bootstrap['player']['raw_chaos'], $bootstrap['player']['teeth'], $bootstrap['player']['player_revision'],
      $bootstrap['player']['energy']['current'], $bootstrap['active_run']['status'],
    ]);
    $this->assertSame([
      ['item_id' => 'item.test.energy', 'quantity' => 1],
      ['item_id' => 'item.test.heal', 'quantity' => 1],
    ], $reloaded['itemCollectionQuery']->execute($userId));
    $this->assertSame([], $reloaded['diceCollectionQuery']->execute($userId));
    $this->assertSame(['sold', 'salvaged'], $this->column(
      'SELECT `lifecycle_status` FROM `dice_instances` WHERE `id` IN (?, ?) ORDER BY `id`', [$sellDieId, $salvageDieId],
    ));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]));
    $this->assertSame((string)$heal['unit']['hp_after'], (string)$this->scalar(
      'SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $unitId],
    ));
    $this->assertSame('1', (string)$this->scalar('SELECT `level` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
    $this->assertSame($bootstrap['player']['teeth'], $reloaded['shopCatalogQuery']->execute($userId)['teeth']);
    $this->assertLessThanOrEqual(8, max(array_map(
      'intval',
      $this->column('SELECT `size` FROM `dice_instances` WHERE `user_id` = ?', [$userId]),
    )));
  }

  /** @return array{offer_id:string,expected_price:array{currency_id:string,amount:int}} */
  private function purchaseRequest(string $offerId, int $price): array
  { return ['offer_id' => $offerId, 'expected_price' => ['currency_id' => 'teeth', 'amount' => $price]]; }

  /** @param array<string,mixed> $result */
  private function assertRevision(array $result, int $expected): void
  { $this->assertSame($expected, $result['player_revision']); }

  /** @param list<int> $parameters @return list<string> */
  private function column(string $sql, array $parameters): array
  { $stmt = $this->pdo?->prepare($sql); $stmt?->execute($parameters); return array_map('strval', $stmt?->fetchAll(\PDO::FETCH_COLUMN) ?: []); }

  private function content(): ContentRegistry
  {
    $source = dirname(__DIR__, 2) . '/content';
    $root = sys_get_temp_dir() . '/dice-goblins-economy-closure-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true); $this->contentRoot = $root;
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      if (!$file->isFile()) continue;
      $relative = substr($file->getPathname(), strlen($source) + 1);
      $target = $root . '/' . str_replace('\\', '/', $relative);
      if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
      copy($file->getPathname(), $target);
    }
    file_put_contents($root . '/items/economy-closure.json', json_encode(['definitions' => [
      ['id' => 'item.test.energy', 'type' => 'item', 'display_name' => 'Spark', 'description' => 'Energy.', 'category' => 'consumable', 'rarity' => 'common', 'icon_key' => 'spark', 'stackable' => true, 'effect' => ['type' => 'energy_restore', 'amount' => 7]],
      ['id' => 'item.test.heal', 'type' => 'item', 'display_name' => 'Poultice', 'description' => 'Healing.', 'category' => 'consumable', 'rarity' => 'common', 'icon_key' => 'heal', 'stackable' => true, 'effect' => ['type' => 'unit_heal', 'amount' => 9]],
    ]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/unlocks/economy-closure.json', json_encode(['definitions' => [[
      'id' => 'unlock.unit_type.bruiser', 'type' => 'unlock', 'target_type' => 'unit_type', 'target_id' => 'unit_type.bruiser',
    ]]], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/shop_offers/economy-closure.json', json_encode(['definitions' => [
      ['id' => 'shop_offer.test.energy', 'type' => 'shop_offer', 'grant' => ['type' => 'item', 'item_id' => 'item.test.energy', 'quantity' => 2], 'price' => ['currency_id' => 'teeth', 'amount' => 5]],
      ['id' => 'shop_offer.test.heal', 'type' => 'shop_offer', 'grant' => ['type' => 'item', 'item_id' => 'item.test.heal', 'quantity' => 2], 'price' => ['currency_id' => 'teeth', 'amount' => 6]],
      ['id' => 'shop_offer.test.d8', 'type' => 'shop_offer', 'grant' => ['type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => 8], 'price' => ['currency_id' => 'teeth', 'amount' => 10]],
      ['id' => 'shop_offer.test.d6', 'type' => 'shop_offer', 'grant' => ['type' => 'die', 'dice_profile_id' => 'dice_profile.cardboard_plain', 'size' => 6], 'price' => ['currency_id' => 'teeth', 'amount' => 8]],
      ['id' => 'shop_offer.test.unit', 'type' => 'shop_offer', 'grant' => ['type' => 'unit', 'unit_type_id' => 'unit_type.bruiser', 'kin_id' => 'kin.goblin'], 'price' => ['currency_id' => 'teeth', 'amount' => 13]],
    ]], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }

  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return;
    $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
  }
}
