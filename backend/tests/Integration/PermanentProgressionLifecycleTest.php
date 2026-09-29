<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Queries\AcademyQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Services\DiceValuationService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class PermanentProgressionLifecycleTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testProductionCatalogLinksEveryPermanentProgressionDefinition(): void
  {
    $content = $this->content();
    $this->assertCount(9, $content->definitionsOfType('academy_upgrade'));
    $this->assertCount(20, $content->definitionsOfType('unit_promotion'));
    foreach ($content->definitionsOfType('academy_upgrade') as $upgrade) {
      $this->assertSame('raw_chaos', $upgrade['price']['currency_id']);
      $content->unlock($upgrade['grant_unlock_id']);
      $event = $content->event($upgrade['event_id']);
      $content->rewardDefinition($event['reward_definition_id']);
      foreach ($upgrade['prerequisite_unlock_ids'] as $id) $content->unlock($id);
      $this->addToAssertionCount(1);
    }
    foreach ($content->definitionsOfType('unit_promotion') as $promotion) {
      $content->unitType($promotion['from_unit_type_id']); $content->unitType($promotion['to_unit_type_id']);
      foreach ($content->unitType($promotion['to_unit_type_id'])['ability_ids'] as $abilityId) $content->ability($abilityId);
      $this->addToAssertionCount(1);
    }
    foreach ([4, 6, 8, 10, 12, 20] as $size) {
      $offer = $content->shopOffer('shop_offer.cardboard_d' . $size);
      $this->assertSame($size, $offer['grant']['size']);
      $this->assertSame('dice_profile.cardboard_plain', $offer['grant']['dice_profile_id']);
      $this->assertGreaterThanOrEqual(DiceValuationService::calculateSellValue($size, 'common'),
        $offer['price']['amount']);
    }
  }

  public function testSalvageResearchShopEnergyDiceAndPromotionSurviveReload(): void
  {
    $content = $this->content(); $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Progression Lifecycle']);
    $user = (int)$this->pdo?->lastInsertId(); $this->trackUserId($user);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`) VALUES (?, 500, 80, 50, ?, 1)')
      ->execute([$user, '2026-09-28 12:00:00']);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))->execute($user);
    $this->pdo?->prepare('INSERT INTO `dice_instances` (`user_id`, `size`, `profile_id`) VALUES (?, 6, ?)')
      ->execute([$user, 'dice_profile.wood_plain']);
    $dieId = (int)$this->pdo?->lastInsertId();
    $salvage = $services['diceLifecycleCommand']->salvage($user, $dieId, 'lifecycle-salvage');
    $this->assertSame(84, $salvage['raw_chaos']);
    $this->assertSame((int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$user]), $salvage['player_revision']);
    $academy = new AcademyQuery(new PlayerStateRepository($this->pdo), new UserUnlockRepository($this->pdo), $content);
    $this->assertContains('academy_upgrade.guardian', array_column($academy->execute($user)['upgrades'], 'upgrade_id'));
    $offers = fn(): array => array_column($services['shopCatalogQuery']->execute($user)['offers'], 'available', 'offer_id');
    $this->assertFalse($offers()['shop_offer.goblin_guardian']);
    foreach ([10, 12, 20] as $size) $this->assertFalse($offers()['shop_offer.cardboard_d' . $size]);

    $upgrade = $services['upgradeAcademyCommand'];
    $guardianRequest = $this->upgradeRequest('guardian', 5);
    $guardian = $upgrade->execute($user, $guardianRequest, 'lifecycle-guardian');
    $this->assertSame($guardian, $upgrade->execute($user, $guardianRequest, 'lifecycle-guardian'));
    $this->assertTrue($offers()['shop_offer.goblin_guardian']);
    $purchase = $services['purchaseShopOfferCommand'];
    $recruit = $purchase->execute($user, $this->shopRequest('shop_offer.goblin_guardian', 8), 'lifecycle-recruit');
    $this->assertSame('unit_type.guardian', $recruit['output']['unit']['unit_type_id']);

    $energy75 = $upgrade->execute($user, $this->upgradeRequest('energy_max_75', 5), 'lifecycle-energy-75');
    $this->assertSame(75, $energy75['energy']['normal_max']);
    $this->assertSame(50, $energy75['energy']['current']);
    $energy100 = $upgrade->execute($user, $this->upgradeRequest('energy_max_100', 10), 'lifecycle-energy-100');
    $this->assertSame(100, $energy100['energy']['normal_max']);
    $acquiredDiceIds = [];
    foreach ([10 => 5, 12 => 10, 20 => 20] as $size => $price) {
      $upgrade->execute($user, $this->upgradeRequest('die_size_d' . $size, $price), 'lifecycle-cap-d' . $size);
      $this->assertTrue($offers()['shop_offer.cardboard_d' . $size]);
      if ($size < 20) $this->assertFalse($offers()['shop_offer.cardboard_d' . ($size === 10 ? 12 : 20)]);
      $die = $purchase->execute($user, $this->shopRequest('shop_offer.cardboard_d' . $size,
        [10 => 34, 12 => 42, 20 => 60][$size]), 'lifecycle-buy-d' . $size);
      $this->assertSame($size, $die['output']['die']['size']);
      $acquiredDiceIds[] = $die['output']['die']['id'];
    }

    $unitId = (int)$fixture['unit_ids']['bruiser'];
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 3, `xp` = 50 WHERE `id` = ?')->execute([$unitId]);
    $detail = $services['unitDetailQuery']->execute($user, $unitId);
    $options = $services['unitPromotionOptionsQuery']->execute($user, $unitId);
    $this->assertContains('unit_promotion.bruiser.enforcer', array_column($options['options'], 'promotion_id'));
    $promotion = $services['promoteUnitCommand']; $firstRequest = $this->promotionRequest('bruiser.enforcer', 5);
    $first = $promotion->execute($user, $unitId, $firstRequest, 'lifecycle-promote-t2');
    $this->assertSame($first, $promotion->execute($user, $unitId, $firstRequest, 'lifecycle-promote-t2'));
    foreach (['id', 'display_name', 'kin_id', 'level', 'xp', 'ability_loadout', 'dice_bindings'] as $key)
      $this->assertSame($detail[$key], $first['unit'][$key]);
    $this->assertCount(1, $first['unit']['promotion_history']);
    $this->assertSame(['unit_promotion.enforcer.juggernaut'],
      array_column($services['unitPromotionOptionsQuery']->execute($user, $unitId)['options'], 'promotion_id'));
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 6, `xp` = 120 WHERE `id` = ?')->execute([$unitId]);
    $second = $promotion->execute($user, $unitId, $this->promotionRequest('enforcer.juggernaut', 10), 'lifecycle-promote-t3');
    $this->assertCount(2, $second['unit']['promotion_history']);
    foreach ($first['unit']['owned_ability_ids'] as $abilityId) $this->assertContains($abilityId, $second['unit']['owned_ability_ids']);
    $this->assertSame([], $services['unitPromotionOptionsQuery']->execute($user, $unitId)['options']);
    $reloaded = ControllerServiceFactory::buildContentAware($this->pdo, null, ContentRegistry::load(dirname(__DIR__, 2) . '/content'));
    $reloadedDetail = $reloaded['unitDetailQuery']->execute($user, $unitId);
    foreach ($second['unit'] as $key => $value) $this->assertEqualsCanonicalizing($value, $reloadedDetail[$key], $key);
    $bootstrap = $reloaded['gameBootstrapQuery']->execute($user, new DateTimeImmutable('2026-09-28T12:00:00Z'));
    $this->assertSame(100, $bootstrap['player']['energy']['normal_max']);
    foreach (['guardian', 'energy_max_75', 'energy_max_100', 'die_size_d10', 'die_size_d12', 'die_size_d20'] as $suffix)
      $this->assertContains('unlock.' . ($suffix === 'guardian' ? 'unit_type.guardian' : 'capability.' . $suffix), $bootstrap['progression']['unlock_ids']);
    $this->assertContains($recruit['output']['unit']['id'], array_column($reloaded['unitCollectionQuery']->execute($user), 'id'));
    foreach ($acquiredDiceIds as $id) $this->assertContains($id, array_column($reloaded['diceCollectionQuery']->execute($user), 'id'));
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ? AND `idempotency_key` = ?', [$user, 'lifecycle-guardian']));
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ? AND `idempotency_key` = ?', [$user, 'lifecycle-promote-t2']));
    $this->assertSame(20, count($content->definitionsOfType('unit_promotion')));
    $this->assertSame((int)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]), $bootstrap['player']['raw_chaos']);
    try { $upgrade->execute($user, $this->upgradeRequest('marksman', 5), 'lifecycle-guardian'); $this->fail('Expected Academy key conflict.'); }
    catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    try { $promotion->execute($user, $unitId, $this->promotionRequest('bruiser.pit_fighter', 5), 'lifecycle-promote-t2'); $this->fail('Expected promotion key conflict.'); }
    catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
  private function upgradeRequest(string $suffix, int $price): array
  { return ['upgrade_id' => 'academy_upgrade.' . $suffix, 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $price]]; }
  private function promotionRequest(string $suffix, int $price): array
  { return ['promotion_id' => 'unit_promotion.' . $suffix, 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $price]]; }
  private function shopRequest(string $id, int $price): array
  { return ['offer_id' => $id, 'expected_price' => ['currency_id' => 'teeth', 'amount' => $price]]; }
}
