<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ActiveRunConfigurationLockedException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\PromoteUnitCommand;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\UnitPromotionException;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\Queries\UnitNotFoundException;
use DiceGoblins\Application\UnitPromotionPolicy;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\WarbandController;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Application\Commands\ActiveRunConfigurationPolicy;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class PromoteUnitCommandTest extends IntegrationTestCase
{
  /** @var list<string> */ private array $temporaryRoots = [];
  protected function supportsVnextBaseline(): bool { return true; }
  protected function tearDown(): void
  {
    parent::tearDown();
    foreach ($this->temporaryRoots as $root) {
      $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
      rmdir($root);
    }
  }

  public function testBruiserPromotionPreservesInstanceConfigurationAndReplaysExactly(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['bruiser'];
    $before = $this->detail($user, $unitId); $state = $this->snapshot($user, $unitId);
    $command = $this->command();
    $request = $this->request('bruiser.enforcer', 5);
    $first = $command->execute($user, $unitId, $request, 'promotion-bruiser-1');
    $this->assertEqualsCanonicalizing(['currency_id' => 'raw_chaos', 'amount' => 5, 'balance_before' => 50, 'balance_after' => 45], $first['spend']);
    $this->assertSame(3, $first['player_revision']);
    $this->assertSame('unit_type.enforcer', $first['unit']['unit_type_id']);
    foreach (['id', 'display_name', 'kin_id', 'level', 'xp', 'xp_to_next_level', 'lifecycle_status', 'ability_loadout', 'dice_bindings'] as $field)
      $this->assertSame($before[$field], $first['unit'][$field], $field);
    $this->assertCount(count($before['promotion_history']) + 1, $first['unit']['promotion_history']);
    foreach ($before['owned_ability_ids'] as $abilityId) $this->assertContains($abilityId, $first['unit']['owned_ability_ids']);
    foreach ($first['promotion']['granted_ability_ids'] as $abilityId) $this->assertContains($abilityId, $first['unit']['owned_ability_ids']);
    $this->assertSame($first, $command->execute($user, $unitId, $request, 'promotion-bruiser-1'));
    $this->assertSame(1, $this->countRows('unit_promotions', $unitId));
    $this->assertSame($state['abilities'] + count($first['promotion']['granted_ability_ids']), $this->countRows('unit_abilities', $unitId));
    $this->assertSame(45, (int)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $this->assertSame(3, (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$user]));
    $options = ControllerServiceFactory::buildContentAware($this->pdo)['unitPromotionOptionsQuery']->execute($user, $unitId);
    $this->assertSame(['unit_promotion.enforcer.juggernaut'], array_column($options['options'], 'promotion_id'));
  }

  public function testPitFighterBranchAndConvergingTierThreeRetainPermanentAbilities(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['bruiser'];
    $command = $this->command();
    $first = $command->execute($user, $unitId, $this->request('bruiser.pit_fighter', 5), 'promotion-pit-fighter');
    $this->assertSame('unit_type.pit_fighter', $first['unit']['unit_type_id']);
    $this->assertContains('ability.desperate_swing', $first['unit']['owned_ability_ids']);
    $this->assertContains('ability.counterpunch', $first['unit']['owned_ability_ids']);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 6, `xp` = 120 WHERE `id` = ?')->execute([$unitId]);
    $second = $command->execute($user, $unitId, $this->request('pit_fighter.juggernaut', 10), 'promotion-juggernaut');
    $this->assertSame('unit_type.juggernaut', $second['unit']['unit_type_id']);
    $this->assertSame(['ability.skullcrack', 'ability.menacing_follow_through'], $second['promotion']['granted_ability_ids']);
    foreach (['ability.desperate_swing', 'ability.counterpunch'] as $abilityId)
      $this->assertContains($abilityId, $second['unit']['owned_ability_ids']);
    $this->assertCount(2, $second['unit']['promotion_history']);
    $this->assertSame([], ControllerServiceFactory::buildContentAware($this->pdo)['unitPromotionOptionsQuery']->execute($user, $unitId)['options']);
  }

  public function testEnforcerConvergenceAllowsZeroAbilityGrant(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['enforcer'];
    $before = $this->detail($user, $unitId);
    $result = $this->command()->execute($user, $unitId, $this->request('enforcer.juggernaut', 10), 'promotion-enforcer-juggernaut');
    $this->assertSame([], $result['promotion']['granted_ability_ids']);
    $this->assertSame($before['owned_ability_ids'], $result['unit']['owned_ability_ids']);
    $this->assertSame($before['ability_loadout'], $result['unit']['ability_loadout']);
    $this->assertSame($before['dice_bindings'], $result['unit']['dice_bindings']);
  }

  public function testAnotherConvergingFamilyRetainsBranchAbilities(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['guardian'];
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 3, `xp` = 50 WHERE `id` = ?')->execute([$unitId]);
    $first = $this->command()->execute($user, $unitId, $this->request('guardian.shieldbreaker', 5), 'promotion-shieldbreaker');
    $branchAbilities = $first['unit']['owned_ability_ids'];
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 6, `xp` = 50 WHERE `id` = ?')->execute([$unitId]);
    $second = $this->command()->execute($user, $unitId, $this->request('shieldbreaker.ironwall', 10), 'promotion-ironwall');
    $this->assertSame('unit_type.ironwall', $second['unit']['unit_type_id']);
    foreach ($branchAbilities as $abilityId) $this->assertContains($abilityId, $second['unit']['owned_ability_ids']);
    $this->assertCount(2, $second['unit']['promotion_history']);
  }

  public function testReceiptConflictsAndReplaysBeforeCurrentUnitOrPriceChecks(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['bruiser'];
    $request = $this->request('bruiser.enforcer', 5); $command = $this->command();
    $first = $command->execute($user, $unitId, $request, 'promotion-conflict-key');
    foreach ([
      [(int)$fixture['unit_ids']['guardian'], $request],
      [$unitId, $this->request('bruiser.pit_fighter', 5)],
      [$unitId, $this->request('bruiser.enforcer', 6)],
    ] as [$otherUnit, $otherRequest]) {
      try { $command->execute($user, $otherUnit, $otherRequest, 'promotion-conflict-key'); $this->fail('Expected key conflict.'); }
      catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    }
    $this->assertError('unit_promotion_unavailable', fn() => $command->execute($user, $unitId, $request, 'promotion-new-key'));
    $changed = $this->contentWithChangedPrice('unit_promotion.bruiser.enforcer', 17);
    $this->assertSame($first, $this->command(null, $changed)->execute($user, $unitId, $request, 'promotion-conflict-key'));
    $this->assertSame(45, (int)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $this->assertSame(1, $this->countRows('unit_promotions', $unitId));
  }

  public function testActiveRunLocksOnlyParticipatingUnit(): void
  {
    [$user, $fixture] = $this->fixture();
    ControllerServiceFactory::buildContentAware($this->pdo)['startRunCommand']->execute(
      $user, ['region_id' => 'region.the_farm'], 'promotion-active-run-start',
    );
    try { $this->command()->execute($user, (int)$fixture['unit_ids']['bruiser'],
      $this->request('bruiser.enforcer', 5), 'promotion-locked-bruiser'); $this->fail('Expected lock.'); }
    catch (ActiveRunConfigurationLockedException) { $this->addToAssertionCount(1); }
    $this->assertSame(50, (int)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $reserve = $this->command()->execute($user, (int)$fixture['unit_ids']['enforcer'],
      $this->request('enforcer.juggernaut', 10), 'promotion-reserve-enforcer');
    $this->assertSame('unit_type.juggernaut', $reserve['unit']['unit_type_id']);
    $this->assertSame(0, $this->countRows('unit_promotions', (int)$fixture['unit_ids']['bruiser']));
  }

  public function testCorruptHistoryOrAbilityAndUnsafeWalletRejectBeforeDebit(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['bruiser']; $request = $this->request('bruiser.enforcer', 5);
    $before = $this->snapshot($user, $unitId);
    $this->pdo?->prepare('INSERT INTO `unit_promotions` (`unit_id`, `from_unit_type_id`, `to_unit_type_id`) VALUES (?, ?, ?)')
      ->execute([$unitId, 'unit_type.guardian', 'unit_type.bulwark']);
    $this->assertIntegrity(fn() => $this->command()->execute($user, $unitId, $request, 'promotion-bad-history'));
    $this->pdo?->prepare('DELETE FROM `unit_promotions` WHERE `unit_id` = ?')->execute([$unitId]);
    $this->pdo?->prepare('INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)')
      ->execute([$unitId, 'ability.missing']);
    $this->assertIntegrity(fn() => $this->command()->execute($user, $unitId, $request, 'promotion-bad-ability'));
    $this->pdo?->prepare('DELETE FROM `unit_abilities` WHERE `unit_id` = ? AND `ability_id` = ?')
      ->execute([$unitId, 'ability.missing']);
    foreach (['raw_chaos', 'player_revision'] as $column) {
      $this->pdo?->prepare("UPDATE `user_state` SET `{$column}` = ? WHERE `user_id` = ?")
        ->execute([9007199254740992, $user]);
      $this->assertIntegrity(fn() => $this->command()->execute($user, $unitId, $request, 'promotion-unsafe-' . $column));
      $this->pdo?->prepare("UPDATE `user_state` SET `{$column}` = ? WHERE `user_id` = ?")
        ->execute([$column === 'raw_chaos' ? 50 : 2, $user]);
    }
    $this->pdo?->prepare('UPDATE `user_state` SET `player_revision` = ? WHERE `user_id` = ?')
      ->execute([9007199254740991, $user]);
    $this->assertIntegrity(fn() => $this->command()->execute($user, $unitId, $request, 'promotion-max-revision'));
    $this->pdo?->prepare('UPDATE `user_state` SET `player_revision` = 2 WHERE `user_id` = ?')->execute([$user]);
    $this->assertSame($before, $this->snapshot($user, $unitId));
  }

  public function testMissingForeignInactiveAndTerminalUnitsUseNonDisclosingNotFound(): void
  {
    [$user, $fixture] = $this->fixture(); [$other, $otherFixture] = $this->fixture();
    $command = $this->command(); $request = $this->request('bruiser.enforcer', 5);
    foreach ([999999999, (int)$otherFixture['unit_ids']['bruiser']] as $unitId) {
      try { $command->execute($user, $unitId, $request, 'promotion-unavailable-' . $unitId); $this->fail('Expected not found.'); }
      catch (UnitNotFoundException) { $this->addToAssertionCount(1); }
    }
    $inactive = (int)$fixture['unit_ids']['guardian'];
    $this->pdo?->prepare('UPDATE `unit_instances` SET `lifecycle_status` = ? WHERE `id` = ?')->execute(['retired', $inactive]);
    try { $command->execute($user, $inactive, $this->request('guardian.bulwark', 5), 'promotion-inactive-unit');
      $this->fail('Expected inactive not found.'); }
    catch (UnitNotFoundException) { $this->addToAssertionCount(1); }
    $terminal = (int)$fixture['unit_ids']['enforcer'];
    $command->execute($user, $terminal, $this->request('enforcer.juggernaut', 10), 'promotion-terminal-first');
    try { $command->execute($user, $terminal, $this->request('enforcer.juggernaut', 10), 'promotion-terminal-second');
      $this->fail('Expected terminal not found.'); }
    catch (UnitNotFoundException) { $this->addToAssertionCount(1); }
  }

  public function testGuardedTypeWriteAndCorruptReceiptAreAtomicFailures(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['bruiser'];
    $before = $this->snapshot($user, $unitId);
    $this->pdo?->beginTransaction();
    try { (new WarbandUnitRepository($this->pdo))->updatePromotedType($user, $unitId, 'unit_type.guardian', 'unit_type.enforcer');
      $this->fail('Expected stale type guard.'); }
    catch (RuntimeException) { $this->pdo?->rollBack(); $this->addToAssertionCount(1); }
    $this->assertSame($before, $this->snapshot($user, $unitId));
    $request = $this->request('bruiser.enforcer', 5);
    $this->command()->execute($user, $unitId, $request, 'promotion-corrupt-receipt');
    $this->pdo?->prepare('UPDATE `idempotency_requests` SET `result_json` = ? WHERE `user_id` = ? AND `idempotency_key` = ?')
      ->execute(['{"promotion":{}}', $user, 'promotion-corrupt-receipt']);
    $this->assertIntegrity(fn() => $this->command()->execute($user, $unitId, $request, 'promotion-corrupt-receipt'));
  }

  public function testEndpointRequiresAuthCsrfAndExactRequest(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (string)$fixture['unit_ids']['bruiser'];
    $controller = new WarbandController(); $this->setJsonBody($this->request('bruiser.enforcer', 5));
    $this->assertSame(401, $this->invoke(fn() => $controller->promoteUnit($unitId))['status']);
    $_SESSION['user_id'] = $user; $_SESSION['csrf_token'] = 'promotion-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $this->assertSame(403, $this->invoke(fn() => $controller->promoteUnit($unitId))['status']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'promotion-csrf';
    foreach ([[], ['promotion_id' => 'unit_promotion.bruiser.enforcer', 'expected_price' => ['currency_id' => 'teeth', 'amount' => 5]],
      [...$this->request('bruiser.enforcer', 5), 'extra' => true]] as $index => $body) {
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'promotion-invalid-' . $index;
      $this->setJsonBody($body);
      $response = $this->invoke(fn() => $controller->promoteUnit($unitId));
      $this->assertSame([422, 'invalid_unit_promotion'], [$response['status'], $response['body']['error']['code']]);
    }
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); $this->setJsonBody($this->request('bruiser.enforcer', 5));
    $this->assertSame(400, $this->invoke(fn() => $controller->promoteUnit($unitId))['status']);
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'promotion-http-valid';
    $this->setJsonBody($this->request('bruiser.enforcer', 5));
    $missing = $this->invoke(fn() => $controller->promoteUnit('999999999'));
    $this->assertSame(404, $missing['status'], json_encode($missing['body']));
    $this->assertSame(404, $this->invoke(fn() => $controller->promoteUnit('01'))['status']);
    $this->setJsonBody($this->request('bruiser.enforcer', 5));
    $response = $this->invoke(fn() => $controller->promoteUnit($unitId));
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame('unit_type.enforcer', $response['body']['data']['unit']['unit_type_id']);
  }

  public function testRejectionsAndRollbackLeaveStateUnchanged(): void
  {
    [$user, $fixture] = $this->fixture(); $unitId = (int)$fixture['unit_ids']['bruiser'];
    $before = $this->snapshot($user, $unitId); $command = $this->command();
    $this->assertError('unit_promotion_level_required', fn() => $command->execute($user, (int)$fixture['unit_ids']['guardian'], $this->request('guardian.bulwark', 5), 'promotion-low-level'));
    $this->assertError('unit_promotion_changed', fn() => $command->execute($user, $unitId, $this->request('bruiser.enforcer', 6), 'promotion-price-changed'));
    $this->assertError('unit_promotion_not_found', fn() => $command->execute($user, $unitId, $this->request('bruiser.missing', 5), 'promotion-unknown'));
    $this->assertError('unit_promotion_unavailable', fn() => $command->execute($user, $unitId, $this->request('guardian.bulwark', 5), 'promotion-unavailable'));
    $this->pdo?->prepare('UPDATE `user_state` SET `raw_chaos` = 4 WHERE `user_id` = ?')->execute([$user]);
    $this->assertError('insufficient_raw_chaos', fn() => $command->execute($user, $unitId, $this->request('bruiser.enforcer', 5), 'promotion-insufficient'));
    $this->pdo?->prepare('UPDATE `user_state` SET `raw_chaos` = 50 WHERE `user_id` = ?')->execute([$user]);
    $this->assertSame($before, $this->snapshot($user, $unitId));
    try { $this->command(static function (): void { throw new RuntimeException('precommit'); })
      ->execute($user, $unitId, $this->request('bruiser.enforcer', 5), 'promotion-rollback'); $this->fail('Expected rollback.'); }
    catch (WarbandIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame($before, $this->snapshot($user, $unitId));
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixture(): array
  {
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Promotion Test']);
    $user = (int)$this->pdo?->lastInsertId(); $this->trackUserId($user);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`)
      VALUES (?, 50, 50, ?, 1)')->execute([$user, '2026-09-28 12:00:00']);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $this->content()))->execute($user);
    return [$user, $fixture];
  }
  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
  private function command(?\Closure $beforeCommit = null, ?ContentRegistry $content = null): PromoteUnitCommand
  {
    $content ??= $this->content(); $units = new WarbandUnitRepository($this->pdo);
    return new PromoteUnitCommand($this->pdo, new PlayerStateRepository($this->pdo), new IdempotencyRequestRepository($this->pdo),
      $units, new UnitDetailQuery($units, $content), new UnitPromotionPolicy($content),
      new ActiveRunConfigurationPolicy(new RunPersistenceRepository($this->pdo)), $beforeCommit);
  }
  private function detail(int $user, int $unitId): array
  { return (new UnitDetailQuery(new WarbandUnitRepository($this->pdo), $this->content()))->execute($user, $unitId); }
  private function request(string $suffix, int $price): array
  { return ['promotion_id' => 'unit_promotion.' . $suffix, 'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => $price]]; }
  private function countRows(string $table, int $unitId): int
  { return (int)$this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `unit_id` = ?", [$unitId]); }
  private function snapshot(int $user, int $unitId): array
  {
    return ['state' => $this->pdo?->query("SELECT `raw_chaos`, `player_revision` FROM `user_state` WHERE `user_id` = {$user}")->fetch(),
      'unit' => $this->pdo?->query("SELECT `unit_type_id`, `display_name`, `kin_id`, `level`, `xp` FROM `unit_instances` WHERE `id` = {$unitId}")->fetch(),
      'abilities' => $this->countRows('unit_abilities', $unitId),
      'history' => $this->countRows('unit_promotions', $unitId),
      'loadout' => $this->countRows('unit_ability_loadout', $unitId),
      'bindings' => $this->countRows('unit_ability_dice', $unitId),
      'receipts' => (int)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$user])];
  }
  private function assertError(string $code, callable $action): void
  { try { $action(); $this->fail('Expected promotion rejection.'); } catch (UnitPromotionException $e) { $this->assertSame($code, $e->errorCode); } }
  private function assertIntegrity(callable $action): void
  { try { $action(); $this->fail('Expected integrity failure.'); } catch (WarbandIntegrityException) { $this->addToAssertionCount(1); } }

  private function contentWithChangedPrice(string $promotionId, int $price): ContentRegistry
  {
    $source = dirname(__DIR__, 2) . '/content';
    $root = sys_get_temp_dir() . '/dice-goblins-promotion-replay-' . bin2hex(random_bytes(6));
    $this->temporaryRoots[] = $root;
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      if (!$file->isFile()) continue;
      $relative = substr($file->getPathname(), strlen($source) + 1);
      $target = $root . '/' . str_replace('\\', '/', $relative);
      if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
      $document = json_decode((string)file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
      foreach ($document['definitions'] as &$definition) {
        if ($definition['id'] === $promotionId) $definition['price']['amount'] = $price;
      }
      unset($definition);
      file_put_contents($target, json_encode($document, JSON_THROW_ON_ERROR));
    }
    return ContentRegistry::load($root);
  }
}
