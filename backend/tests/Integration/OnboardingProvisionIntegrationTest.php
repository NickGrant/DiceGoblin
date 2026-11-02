<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\OnboardingProvisionException;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Commands\ProvisionStartingWarbandCommand;
use DiceGoblins\Application\NormalUnitCreationService;
use DiceGoblins\Application\FarmAccessReconciler;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\OnboardingController;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class OnboardingProvisionIntegrationTest extends IntegrationTestCase
{
  public function testReadyPlayerGetsCompleteCoreLoopStateAndReceiptFirstReplay(): void
  {
    $user = $this->user(); $other = $this->user();
    $this->ready($user);
    $first = $this->command()->execute($user, [], 'onboard-ready-1');
    $this->assertSame('complete', $first['onboarding_status']);
    $this->assertSame(2, $first['player_revision']);
    $this->assertSame('complete', $this->query()->execute($user)['status']);
    $this->assertSame($first, $this->command()->execute($user, [], 'onboard-ready-1'));
    $this->assertSame(2, (new PlayerStateRepository($this->pdo))->getPlayerState($user)['player_revision']);
    $this->assertSame(4, (int)$this->scalar('SELECT COUNT(*) FROM unit_instances WHERE user_id = ?', [$user]));
    $this->assertSame(4, (int)$this->scalar('SELECT COUNT(*) FROM dice_instances WHERE user_id = ?', [$user]));
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM squads WHERE user_id = ?', [$user]));
    $this->assertSame(4, (int)$this->scalar('SELECT COUNT(*) FROM squad_units WHERE squad_id = ?', [(int)$first['active_squad_id']]));
    $this->assertCount(4, $first['unit_ids']);
    $this->assertCount(4, $first['die_ids']);
    $expected = $this->content()->onboardingFlow()['starting_warband']['units'];
    $detail = new UnitDetailQuery(new WarbandUnitRepository($this->pdo), $this->content());
    foreach ($expected as $index => $member) {
      $unit = $detail->execute($user, (int)$first['unit_ids'][$index]);
      $this->assertSame('kin.goblin', $unit['kin_id']);
      $this->assertSame($member['unit_type_id'], $unit['unit_type_id']);
      $this->assertNotSame($this->content()->unitType($member['unit_type_id'])['display_name'], $unit['display_name']);
      $this->assertMatchesRegularExpression('/^[A-Z][a-z]+ [A-Z][a-z]+$/', $unit['display_name']);
      $this->assertCount(1, $unit['ability_loadout']);
      $this->assertSame($member['ability_id'], $unit['ability_loadout'][0]['ability_id']);
      $this->assertCount(1, $unit['dice_bindings']);
      $this->assertSame($first['die_ids'][$index], $unit['dice_bindings'][0]['dice_instance_id']);
      $this->assertSame((int)$first['unit_ids'][$index], (int)$this->scalar(
        'SELECT unit_id FROM squad_units WHERE squad_id = ? AND position = ?',
        [(int)$first['active_squad_id'], $member['position']],
      ));
    }
    $this->assertSame(['unlock.region.the_farm'], (new UserUnlockRepository($this->pdo))->listIdsForUser($user));
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM unit_promotions WHERE unit_id IN (?, ?, ?, ?)',
      array_map('intval', $first['unit_ids'])));
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM unit_instances WHERE user_id = ?', [$other]));
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM dice_instances WHERE user_id = ?', [$other]));

    $this->ready($other);
    $otherReceipt = $this->command()->execute($other, [], 'onboard-ready-1');
    $this->assertNotSame($first['unit_ids'], $otherReceipt['unit_ids']);
    $this->assertSame('complete', $this->query()->execute($other)['status']);

    try { $this->command()->execute($user, [], 'onboard-ready-2'); $this->fail('Expected a distinct key to reject completion.'); }
    catch (OnboardingProvisionException $e) { $this->assertSame('onboarding_not_ready', $e->errorCode); }
    $this->assertSame(4, (int)$this->scalar('SELECT COUNT(*) FROM unit_instances WHERE user_id = ?', [$user]));
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM idempotency_requests WHERE user_id = ?', [$user]));
    $services = ControllerServiceFactory::buildContentAware($this->pdo,
      ControllerServiceFactory::buildCore($this->pdo), $this->content());
    $run = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'onboard-farm-1');
    $this->assertSame('active', $run['run']['status']);
  }

  public function testRequiredInProgressAndReturningPlayersCannotProvision(): void
  {
    $required = $this->user(); $inProgress = $this->user(); $returning = $this->user();
    (new UserCodexRepository($this->pdo))->insertIfAbsent($inProgress, 'codex.mystic_cave.whim_arrival');
    $this->pdo?->prepare("INSERT INTO unit_instances (user_id, unit_type_id, kin_id, display_name, level, xp, lifecycle_status)
      VALUES (?, 'unit_type.bruiser', 'kin.goblin', 'Veteran', 1, 0, 'active')")->execute([$returning]);
    foreach ([$required, $inProgress, $returning] as $user) {
      try { $this->command()->execute($user, [], 'onboard-gated-1'); $this->fail('Expected provisioning to be gated.'); }
      catch (OnboardingProvisionException $e) { $this->assertSame('onboarding_not_ready', $e->errorCode); }
      $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM idempotency_requests WHERE user_id = ?', [$user]));
      $this->assertSame(1, (new PlayerStateRepository($this->pdo))->getPlayerState($user)['player_revision']);
    }
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM unit_instances WHERE user_id = ?', [$returning]));
  }

  public function testRollbackLeavesReadyPlayerAndNoAssetsOrReceipt(): void
  {
    $user = $this->user(); $this->ready($user);
    $command = $this->command(static function (): void { throw new RuntimeException('Injected failure before commit.'); });
    try { $command->execute($user, [], 'onboard-fail-1'); $this->fail('Expected rollback.'); }
    catch (RuntimeException $e) { $this->assertSame('Injected failure before commit.', $e->getMessage()); }
    foreach (['unit_instances', 'dice_instances', 'squads', 'idempotency_requests'] as $table) {
      $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM `$table` WHERE user_id = ?", [$user]));
    }
    $this->assertSame('ready_to_provision', $this->query()->execute($user)['status']);
    $this->assertSame(1, (new PlayerStateRepository($this->pdo))->getPlayerState($user)['player_revision']);
    $this->assertSame('complete', $this->command()->execute($user, [], 'onboard-fail-1')['onboarding_status']);
  }

  public function testControllerRequiresAuthenticationCsrfAndRejectsClientAuthoredAssets(): void
  {
    $user = $this->user(); $this->ready($user);
    $controller = new OnboardingController($this->content());
    $this->setJsonBody([]);
    $this->assertSame(401, $this->invoke(fn() => $controller->provision())['status']);
    $_SESSION['user_id'] = $user;
    $this->setJsonBody([]);
    $this->assertSame(403, $this->invoke(fn() => $controller->provision())['status']);
    $_SESSION['csrf_token'] = 'test-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'onboard-http-1';
    $this->setJsonBody(['unit_type_id' => 'unit_type.bruiser']);
    $this->assertSame(422, $this->invoke(fn() => $controller->provision())['status']);
    $this->setJsonBody([]);
    $response = $this->invoke(fn() => $controller->provision());
    $this->assertSame(200, $response['status']);
    $this->assertEqualsCanonicalizing(['unit_ids', 'die_ids', 'active_squad_id', 'player_revision', 'onboarding_status'],
      array_keys($response['body']['data']));
    $encoded = json_encode($response['body'], JSON_THROW_ON_ERROR);
    foreach (['knowledge_id', 'encounter_id', 'reward_definition', 'machine_capability_id', 'starting_warband'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
    $this->setJsonBody([]);
    $this->assertSame($response['body'], $this->invoke(fn() => $controller->provision())['body']);
  }

  public function testConflictingKeyCannotReuseAnotherOperationReceipt(): void
  {
    $user = $this->user(); $this->ready($user);
    (new IdempotencyRequestRepository($this->pdo))->insertFinalized($user, 'onboard-conflict', 'other_operation',
      hash('sha256', '{}'), ['player_revision' => 999]);
    $this->expectException(IdempotencyConflictException::class);
    $this->command()->execute($user, [], 'onboard-conflict');
  }

  public function testExistingUnitOwnerReceivesFarmAccessOnceWithoutGrantingFreshAccount(): void
  {
    $returning = $this->user(); $fresh = $this->user();
    (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo),
      $this->content()))->execute($returning);
    $this->pdo?->prepare("DELETE FROM user_unlocks WHERE user_id = ? AND unlock_id = 'unlock.region.the_farm'")
      ->execute([$returning]);
    $services = ControllerServiceFactory::buildContentAware($this->pdo,
      ControllerServiceFactory::buildCore($this->pdo), $this->content());
    try {
      $services['startRunCommand']->execute($returning, ['region_id' => 'region.the_farm'], 'reconciled-farm-start');
      $this->fail('Farm start bypassed its missing unlock.');
    } catch (RunStartException $e) { $this->assertSame('run_region_locked', $e->errorCode); }
    $reconciler = new FarmAccessReconciler($this->pdo, new PlayerStateRepository($this->pdo),
      new WarbandUnitRepository($this->pdo), new UserUnlockRepository($this->pdo), $this->content());
    $this->assertFalse($reconciler->reconcile($fresh));
    $this->assertSame([], (new UserUnlockRepository($this->pdo))->listIdsForUser($fresh));
    $this->assertTrue($reconciler->reconcile($returning));
    $this->assertFalse($reconciler->reconcile($returning));
    $this->assertSame(['unlock.region.the_farm'], (new UserUnlockRepository($this->pdo))->listIdsForUser($returning));
    $this->assertSame(3, (new PlayerStateRepository($this->pdo))->getPlayerState($returning)['player_revision']);
    $this->assertSame('active', $services['startRunCommand']->execute($returning,
      ['region_id' => 'region.the_farm'], 'reconciled-farm-start')['run']['status']);
  }

  private function user(): int
  {
    $id = (new UserRepository($this->pdo))->createUser('Onboarding Provision Test', null);
    $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO user_state (user_id, energy_current) VALUES (?, 50)')->execute([$id]);
    return $id;
  }

  private function ready(int $userId): void
  {
    $codex = new UserCodexRepository($this->pdo);
    foreach ($this->content()->onboardingFlow()['steps'] as $step) $codex->insertIfAbsent($userId, $step['knowledge_id']);
  }

  private function content(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
  }

  private function query(): OnboardingQuery
  {
    return new OnboardingQuery(new WarbandUnitRepository($this->pdo), new UserCodexRepository($this->pdo),
      new PlayerStateRepository($this->pdo), $this->content());
  }

  private function command(?\Closure $beforeCommit = null): ProvisionStartingWarbandCommand
  {
    $content = $this->content(); $units = new WarbandUnitRepository($this->pdo);
    return new ProvisionStartingWarbandCommand($this->pdo, new PlayerStateRepository($this->pdo),
      new IdempotencyRequestRepository($this->pdo), $this->query(), new NormalUnitCreationService($units, $content),
      $units, new WarbandDiceRepository($this->pdo), new SquadRepository($this->pdo),
      new UserUnlockRepository($this->pdo), $content, $beforeCommit);
  }
}
