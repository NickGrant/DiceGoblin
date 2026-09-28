<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DiceGoblins\Application\Commands\AcademyUpgradeException;
use DiceGoblins\Application\Commands\AcademyUpgradeIntegrityException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\UpgradeAcademyCommand;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\AcademyUpgradeController;
use DiceGoblins\Domain\Energy\EnergyCapacityTransition;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Infrastructure\CryptoRewardRollSource;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Services\DiceValuationService;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class AcademyUpgradeCommandTest extends IntegrationTestCase
{
  /** @var list<string> */ private array $roots = [];
  protected function supportsVnextBaseline(): bool { return true; }
  protected function tearDown(): void
  { parent::tearDown(); foreach ($this->roots as $root) { if (is_file($root . '/all.json')) unlink($root . '/all.json'); rmdir($root); } }

  public function testUnitUpgradeSpendsGrantsOnceAndReplaysBeforeCurrentOwnership(): void
  {
    $user = $this->user(); $command = $this->command();
    $first = $command->execute($user, $this->request('guardian', 5), 'academy-guardian-1234');
    $this->assertEqualsCanonicalizing(['currency_id' => 'raw_chaos', 'amount' => 5, 'balance_before' => 50, 'balance_after' => 45], $first['spend']);
    $this->assertSame(['unlock_id' => 'unlock.unit_type.guardian'], $first['grant']);
    $this->assertNull($first['energy']); $this->assertSame(2, $first['player_revision']);
    $this->assertSame($first, $command->execute($user, ['expected_price' => ['amount' => 5, 'currency_id' => 'raw_chaos'],
      'upgrade_id' => 'academy_upgrade.guardian'], 'academy-guardian-1234'));
    $this->assertSame($first, $this->command('2026-09-28T12:00:00Z', null, $this->contentWithoutGuardian())
      ->execute($user, $this->request('guardian', 5), 'academy-guardian-1234'));
    $this->assertSame('45', (string)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$user]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$user]));
    $this->assertError('academy_upgrade_owned', fn() => $command->execute($user, $this->request('guardian', 5), 'academy-guardian-new-1234'));
    try { $command->execute($user, $this->request('marksman', 5), 'academy-guardian-1234'); $this->fail('Expected key conflict.'); }
    catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
  }

  public function testPrerequisiteChainsAndImmediateShopAvailability(): void
  {
    $user = $this->user(50, 300); $command = $this->command();
    $this->assertError('academy_upgrade_unavailable', fn() => $command->execute($user, $this->request('die_size_d12', 10), 'academy-d12-early-1234'));
    $content = $this->content();
    $shop = \DiceGoblins\Controllers\ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['shopCatalogQuery'];
    $offers = array_column($shop->execute($user)['offers'], 'available', 'offer_id');
    foreach ([10, 12, 20] as $size) $this->assertFalse($offers['shop_offer.cardboard_d' . $size]);
    foreach ([10, 12, 20] as $size) {
      $command->execute($user, $this->request('die_size_d' . $size, [10 => 5, 12 => 10, 20 => 20][$size]), 'academy-d' . $size . '-1234');
      $offers = array_column($shop->execute($user)['offers'], 'available', 'offer_id');
      foreach ([10, 12, 20] as $candidate) $this->assertSame($candidate <= $size, $offers['shop_offer.cardboard_d' . $candidate]);
    }
    $command->execute($user, $this->request('guardian', 5), 'academy-unit-1234');
    $offers = array_column($shop->execute($user)['offers'], 'available', 'offer_id');
    $this->assertTrue($offers['shop_offer.goblin_guardian']);
    $this->assertFalse($offers['shop_offer.goblin_marksman']);
    $purchase = \DiceGoblins\Controllers\ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['purchaseShopOfferCommand'];
    foreach ([10 => 34, 12 => 42, 20 => 60] as $size => $price) {
      $bought = $purchase->execute($user, ['offer_id' => 'shop_offer.cardboard_d' . $size,
        'expected_price' => ['currency_id' => 'teeth', 'amount' => $price]], 'academy-buy-d' . $size . '-1234');
      $this->assertSame($size, $bought['output']['die']['size']);
    }
  }

  public function testEnergyCapTransitionPreventsRetroactiveRegenerationAndPreservesFractionAndOvercharge(): void
  {
    $atCap = $this->user(50, 0, 50, '2026-09-28 09:00:00');
    $result = $this->command('2026-09-28T12:00:00Z')->execute($atCap, $this->request('energy_max_75', 5), 'academy-energy-atcap');
    $this->assertSame(50, $result['energy']['current']);
    $this->assertSame(75, $result['energy']['normal_max']);
    $this->assertSame('2026-09-28T12:00:00Z', $result['energy']['last_regeneration_at']);
    $this->assertSame('2026-09-28T12:05:00Z', $result['energy']['next_regeneration_at']);
    $this->assertSame(2, $result['player_revision']);
    $bootstrap = \DiceGoblins\Controllers\ControllerServiceFactory::buildContentAware($this->pdo)['gameBootstrapQuery']
      ->execute($atCap, new DateTimeImmutable('2026-09-28T12:00:00Z'));
    $this->assertSame(75, $bootstrap['player']['energy']['normal_max']);
    $this->assertError('academy_upgrade_unavailable', fn() => $this->command()->execute($this->user(), $this->request('energy_max_100', 10), 'academy-energy-early'));
    $next = $this->command('2026-09-28T12:05:00Z')->execute($atCap, $this->request('energy_max_100', 10), 'academy-energy-100');
    $this->assertSame([51, 100, 3], [$next['energy']['current'], $next['energy']['normal_max'], $next['player_revision']]);
    $bootstrap = \DiceGoblins\Controllers\ControllerServiceFactory::buildContentAware($this->pdo)['gameBootstrapQuery']
      ->execute($atCap, new DateTimeImmutable('2026-09-28T12:05:00Z'));
    $this->assertSame(100, $bootstrap['player']['energy']['normal_max']);

    $below = $this->user(50, 0, 43, '2026-09-28 11:48:00');
    $partial = $this->command('2026-09-28T12:00:00Z')->execute($below, $this->request('energy_max_75', 5), 'academy-energy-below');
    $this->assertSame(45, $partial['energy']['current']);
    $this->assertSame('2026-09-28T11:58:00Z', $partial['energy']['last_regeneration_at']);
    $this->assertSame('2026-09-28T12:03:00Z', $partial['energy']['next_regeneration_at']);
    $over = $this->user(50, 0, 90, '2026-09-28 09:00:00');
    $overResult = $this->command('2026-09-28T12:00:00Z')->execute($over, $this->request('energy_max_75', 5), 'academy-energy-over');
    $this->assertSame(90, $overResult['energy']['current']);
    $this->assertNull($overResult['energy']['next_regeneration_at']);
  }

  public function testFailuresAndInjectedFailureLeaveNoEffects(): void
  {
    $user = $this->user(4); $command = $this->command();
    $this->assertError('insufficient_raw_chaos', fn() => $command->execute($user, $this->request('guardian', 5), 'academy-poor-1234'));
    $this->assertError('academy_upgrade_changed', fn() => $command->execute($user, $this->request('guardian', 6), 'academy-changed-1234'));
    $this->assertError('academy_upgrade_not_found', fn() => $command->execute($user, $this->request('missing', 5), 'academy-missing-1234'));
    $this->assertSame('4', (string)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$user]));
    $user = $this->user(50, 0, 50, '2026-09-28 09:00:00');
    try { $this->command('2026-09-28T12:00:00Z', static function (): void { throw new RuntimeException('fail'); })
      ->execute($user, $this->request('energy_max_75', 5), 'academy-rollback-1234'); $this->fail('Expected rollback.'); }
    catch (AcademyUpgradeIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame(['50', '50', '2026-09-28 09:00:00', '1'], array_map('strval', array_values($this->pdo?->query(
      "SELECT `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = {$user}")->fetch(\PDO::FETCH_ASSOC))));
    foreach (['user_unlocks', 'resolved_events', 'idempotency_requests'] as $table)
      $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `user_id` = ?", [$user]));
  }

  public function testReceiptCorruptionIsAnIntegrityFailureAndDifferentExpectedPriceConflicts(): void
  {
    $user = $this->user(); $command = $this->command();
    $command->execute($user, $this->request('guardian', 5), 'academy-receipt-test');
    try { $command->execute($user, $this->request('guardian', 6), 'academy-receipt-test'); $this->fail('Expected key conflict.'); }
    catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    $this->pdo?->prepare('UPDATE `idempotency_requests` SET `result_json` = ? WHERE `user_id` = ?')
      ->execute(['{"upgrade_id":"academy_upgrade.guardian"}', $user]);
    try { $command->execute($user, $this->request('guardian', 5), 'academy-receipt-test'); $this->fail('Expected integrity failure.'); }
    catch (AcademyUpgradeIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('45', (string)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
  }

  public function testAppliedRewardEventWithMissingUnlockCannotBecomeASecondPurchase(): void
  {
    $user = $this->user(); $command = $this->command();
    $command->execute($user, $this->request('guardian', 5), 'academy-original-reward');
    $this->pdo?->prepare('DELETE FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?')
      ->execute([$user, 'unlock.unit_type.guardian']);
    try { $command->execute($user, $this->request('guardian', 5), 'academy-corrupt-reward'); $this->fail('Expected reward integrity failure.'); }
    catch (AcademyUpgradeIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('45', (string)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$user]));
  }

  public function testEnergyPersistenceRequiresCallerOwnedTransaction(): void
  {
    $user = $this->user();
    try { (new PlayerStateRepository($this->pdo))->persistEnergyWithoutRevision($user, 40,
      new DateTimeImmutable('2026-09-28T12:00:00Z')); $this->fail('Expected transaction requirement.'); }
    catch (RuntimeException) { $this->addToAssertionCount(1); }
    $this->assertSame('50', (string)$this->scalar('SELECT `energy_current` FROM `user_state` WHERE `user_id` = ?', [$user]));
  }

  public function testEndpointRequiresAuthCsrfAndExactPayload(): void
  {
    $controller = new AcademyUpgradeController($this->content());
    $this->setJsonBody($this->request('guardian', 5));
    $this->assertSame(401, $this->invoke(fn() => $controller->upgrade())['status']);
    $user = $this->user(); $_SESSION['user_id'] = $user; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $this->setJsonBody($this->request('guardian', 5));
    $this->assertSame(403, $this->invoke(fn() => $controller->upgrade())['status']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    foreach ([[], ['upgrade_id' => 'academy_upgrade.guardian', 'expected_price' => ['currency_id' => 'teeth', 'amount' => 5]],
      ['upgrade_id' => 'academy_upgrade.guardian', 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => 5], 'extra' => true]] as $index => $bad) {
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'academy-bad-' . $index;
      $this->setJsonBody($bad); $response = $this->invoke(fn() => $controller->upgrade());
      $this->assertSame([422, 'invalid_academy_upgrade'], [$response['status'], $response['body']['error']['code'] ?? null]);
    }
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); $this->setJsonBody($this->request('guardian', 5));
    $this->assertSame(400, $this->invoke(fn() => $controller->upgrade())['status']);
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'academy-valid-endpoint'; $this->setJsonBody($this->request('guardian', 5));
    $response = $this->invoke(fn() => $controller->upgrade());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame('unlock.unit_type.guardian', $response['body']['data']['grant']['unlock_id'] ?? null);
  }

  public function testCanonicalShopDiePricesAlwaysExceedDeterministicSellAward(): void
  {
    $content = $this->content();
    foreach ([4 => 12, 6 => 18, 8 => 28, 10 => 34, 12 => 42, 20 => 60] as $size => $price) {
      $offer = $content->shopOffer('shop_offer.cardboard_d' . $size);
      $this->assertSame($price, $offer['price']['amount']);
      $this->assertSame('dice_profile.cardboard_plain', $offer['grant']['dice_profile_id']);
      $this->assertGreaterThan(DiceValuationService::calculateSellValue($size, 'common'), $price);
    }
  }

  private function user(int $chaos = 50, int $teeth = 200, int $energy = 50, string $anchor = '2026-09-28 12:00:00'): int
  {
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Academy Upgrade Test']);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `raw_chaos`, `teeth`, `energy_current`, `energy_last_regen_at`, `player_revision`) VALUES (?, ?, ?, ?, ?, 1)')
      ->execute([$id, $chaos, $teeth, $energy, $anchor]);
    return $id;
  }
  /** @return array<string,mixed> */
  private function request(string $suffix, int $price): array
  { return ['upgrade_id' => 'academy_upgrade.' . $suffix, 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $price]]; }
  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
  private function command(string $now = '2026-09-28T12:00:00Z', ?\Closure $beforeCommit = null, ?ContentRegistry $content = null): UpgradeAcademyCommand
  {
    $content ??= $this->content(); $players = new PlayerStateRepository($this->pdo); $unlocks = new UserUnlockRepository($this->pdo);
    $rewards = new RewardApplicationService($this->pdo, $content, new RewardFinalizer(new CryptoRewardRollSource()),
      new ResolvedEventRepository($this->pdo), $players, new WarbandUnitRepository($this->pdo), $unlocks);
    $clock = new class($now) implements Clock {
      public function __construct(private readonly string $now) {}
      public function now(): DateTimeImmutable { return new DateTimeImmutable($this->now); }
    };
    return new UpgradeAcademyCommand($this->pdo, $players, new IdempotencyRequestRepository($this->pdo), $unlocks,
      $rewards, $content, new EnergyCapacityTransition(), $clock, $beforeCommit);
  }
  private function assertError(string $code, callable $action): void
  { try { $action(); $this->fail('Expected Academy error.'); } catch (AcademyUpgradeException $e) { $this->assertSame($code, $e->errorCode); } }

  private function contentWithoutGuardian(): ContentRegistry
  {
    $original = $this->content(); $all = [];
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability', 'dice_material', 'dice_aspect',
      'dice_profile', 'run_node_type', 'run_generation', 'unlock', 'capability', 'academy_upgrade', 'event', 'reward_definition',
      'item', 'shop_offer'] as $type) foreach ($original->definitionsOfType($type) as $definition) {
        if ($definition['id'] !== 'academy_upgrade.guardian') $all[] = $definition;
      }
    $root = sys_get_temp_dir() . '/dice-goblins-academy-replay-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true); $this->roots[] = $root;
    file_put_contents($root . '/all.json', json_encode(['definitions' => $all], JSON_THROW_ON_ERROR));
    return ContentRegistry::load($root);
  }
}
