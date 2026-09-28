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
  protected function supportsVnextBaseline(): bool { return true; }

  public function testProductionCompositionPersistsTheCompleteEconomyLifecycleAndExactRetries(): void
  {
    $content = $this->content();
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Economy Closure']);
    $userId = (int)$this->pdo?->lastInsertId(); $this->trackUserId($userId);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`) VALUES (?, 100, 0, 5, UTC_TIMESTAMP(), 1)')
      ->execute([$userId]);

    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $initial = $services['gameBootstrapQuery']->execute($userId, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $lockedCatalog = $services['shopCatalogQuery']->execute($userId);
    $this->assertSame([100, 1, 6], [$initial['player']['teeth'], $initial['player']['player_revision'], count($lockedCatalog['offers'])]);
    $this->assertFalse($this->offer($lockedCatalog, 'shop_offer.goblin_bruiser')['available']);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, 'unlock.unit_type.bruiser');
    $catalog = $services['shopCatalogQuery']->execute($userId);
    $this->assertTrue($this->offer($catalog, 'shop_offer.goblin_bruiser')['available']);
    $this->assertSame(['item.field_poultice', 'item.spark_tonic'], array_keys($content->definitionsOfType('item')));

    $purchase = $services['purchaseShopOfferCommand'];
    $energyPurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.spark_tonic', 4), 'closure-purchase-energy');
    $this->assertSame($energyPurchase, $purchase->execute($userId, $this->purchaseRequest('shop_offer.spark_tonic', 4), 'closure-purchase-energy'));
    $this->assertRevision($energyPurchase, 2);
    $healPurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.field_poultice', 4), 'closure-purchase-heal');
    $this->assertRevision($healPurchase, 3);
    $sellDiePurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.cardboard_d8', 8), 'closure-purchase-d8');
    $this->assertRevision($sellDiePurchase, 4);
    $salvageDiePurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.cardboard_d6', 6), 'closure-purchase-d6');
    $this->assertRevision($salvageDiePurchase, 5);
    $unitPurchase = $purchase->execute($userId, $this->purchaseRequest('shop_offer.goblin_bruiser', 8), 'closure-purchase-unit');
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

    $energy = $services['restoreEnergyCommand']->execute($userId, ['item_id' => 'item.spark_tonic'], 'closure-energy-use');
    $this->assertSame($energy, $services['restoreEnergyCommand']->execute($userId, ['item_id' => 'item.spark_tonic'], 'closure-energy-use'));
    $this->assertSame([17, 0, 7], [$energy['energy']['current'], $energy['owned_quantity_after'], $energy['player_revision']]);

    $maximumHp = (new BaseLevelStatResolver())->resolve(
      $content->unitType('unit_type.bruiser')['base_stats'],
      $content->unitType('unit_type.bruiser')['growth_per_level'],
      1,
    )->hp;
    $heal = $services['healRunUnitCommand']->execute($userId, $runId, $unitId, ['item_id' => 'item.field_poultice'], 'closure-run-heal');
    $this->assertSame($heal, $services['healRunUnitCommand']->execute($userId, $runId, $unitId, ['item_id' => 'item.field_poultice'], 'closure-run-heal'));
    $this->assertSame([1, min($maximumHp, 10), 0, 8], [
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
    $this->assertSame([$salvaged['raw_chaos'], $sold['teeth'], 10, 17, 'active'], [
      $bootstrap['player']['raw_chaos'], $bootstrap['player']['teeth'], $bootstrap['player']['player_revision'],
      $bootstrap['player']['energy']['current'], $bootstrap['active_run']['status'],
    ]);
    $this->assertSame([], $reloaded['itemCollectionQuery']->execute($userId));
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

  /** @param array{offers:list<array<string,mixed>>} $catalog @return array<string,mixed> */
  private function offer(array $catalog, string $offerId): array
  {
    foreach ($catalog['offers'] as $offer) if ($offer['offer_id'] === $offerId) return $offer;
    $this->fail("Missing Shop offer '{$offerId}'.");
  }

  /** @param list<int> $parameters @return list<string> */
  private function column(string $sql, array $parameters): array
  { $stmt = $this->pdo?->prepare($sql); $stmt?->execute($parameters); return array_map('strval', $stmt?->fetchAll(\PDO::FETCH_COLUMN) ?: []); }

  private function content(): ContentRegistry
  { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
}
