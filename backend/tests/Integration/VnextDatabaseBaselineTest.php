<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\AccountCreationService;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class VnextDatabaseBaselineTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  public function testBaselineContainsOnlyAcceptedPackageTablesAndColumns(): void
  {
    $tables = $this->pdo?->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(\PDO::FETCH_COLUMN);
    $this->assertSame([
      'battles',
      'dice_instances',
      'idempotency_requests',
      'password_reset_tokens',
      'run_edges',
      'run_nodes',
      'run_unit_state',
      'runs',
      'squad_units',
      'squads',
      'unit_abilities',
      'unit_ability_dice',
      'unit_ability_loadout',
      'unit_instances',
      'unit_promotions',
      'user_external_identities',
      'user_local_credentials',
      'user_state',
      'users',
    ], $tables);

    $columns = $this->pdo?->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_state' ORDER BY ORDINAL_POSITION")->fetchAll(\PDO::FETCH_COLUMN);
    $this->assertSame(['user_id', 'teeth', 'raw_chaos', 'energy_current', 'energy_last_regen_at', 'active_squad_id', 'player_revision', 'created_at', 'updated_at'], $columns);
    $this->assertNotContains('energy_max', $columns);
    $energyDefault = $this->scalar("SELECT COALESCE(COLUMN_DEFAULT, 'NULL') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_state' AND COLUMN_NAME = 'energy_current'", []);
    $this->assertSame('NULL', (string)$energyDefault);
    $roleChecks = $this->scalar("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_TYPE = 'CHECK'", []);
    $this->assertSame('0', (string)$roleChecks);
    $dieBindingUniqueness = $this->scalar("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_ability_dice' AND INDEX_NAME = 'uq_unit_ability_dice_die' AND NON_UNIQUE = 0", []);
    $this->assertSame('1', (string)$dieBindingUniqueness);

    $battleColumns = $this->pdo?->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'battles' ORDER BY ORDINAL_POSITION")->fetchAll(\PDO::FETCH_COLUMN);
    $this->assertSame(['id', 'run_id', 'run_node_id', 'engine_version', 'playback_version', 'input_snapshot',
      'participant_manifest', 'result_json', 'created_at'], $battleColumns);
    $this->assertSame('2', (string)$this->scalar("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'battles' AND INDEX_NAME = 'uq_battles_run_node' AND NON_UNIQUE = 0", []));
    $this->assertSame('1', (string)$this->scalar("SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'battles' AND CONSTRAINT_NAME = 'fk_battles_run_node'
        AND DELETE_RULE = 'CASCADE'", []));
  }

  public function testActiveCoreCompositionExcludesDormantPrototypeServices(): void
  {
    $core = ControllerServiceFactory::buildCore($this->pdo);

    $this->assertSame(
      ['userRepo', 'playerStateRepo', 'csrfService', 'sessionService', 'passwordResetService'],
      array_keys($core),
    );
    $this->assertArrayNotHasKey('contentRegistry', $core);
    $this->assertArrayNotHasKey('accountCreationService', $core);

    $contentAware = ControllerServiceFactory::buildContentAware($this->pdo, $core, $this->contentRegistry());
    $this->assertSame($core['sessionService'], $contentAware['sessionService']);
    $this->assertArrayHasKey('contentRegistry', $contentAware);
    $this->assertArrayHasKey('accountCreationService', $contentAware);
    $this->assertArrayHasKey('gameBootstrapQuery', $contentAware);
  }

  public function testLocalAccountCreationAtomicallyPersistsCredentialsAndPlayerState(): void
  {
    $users = new UserRepository($this->pdo);
    $states = new PlayerStateRepository($this->pdo);
    $service = $this->accountCreationService($users, $states);
    $hash = password_hash('secret-pass', PASSWORD_DEFAULT);
    $userId = $service->createLocal('  FRESH@example.test ', $hash, 'Fresh Goblin');
    $this->trackUserId($userId);

    $credential = $users->getUserByLocalEmail('fresh@example.test');
    $state = $states->getPlayerState($userId);
    $this->assertSame($userId, (int)($credential['id'] ?? 0));
    $this->assertTrue(password_verify('secret-pass', (string)($credential['password_hash'] ?? '')));
    $this->assertSame(0, $state['teeth'] ?? null);
    $this->assertSame(0, $state['raw_chaos'] ?? null);
    $this->assertSame($this->contentRegistry()->startingEnergy(), $state['energy_current'] ?? null);
    $this->assertSame(1, $state['player_revision'] ?? null);
    $this->assertSame('', (string)$this->scalar('SELECT COALESCE(`active_squad_id`, \'\') FROM `user_state` WHERE `user_id` = ?', [$userId]));
    foreach (['unit_instances', 'dice_instances', 'squads', 'runs'] as $table) {
      $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `$table` WHERE `user_id` = ?", [$userId]));
    }
    foreach (['run_nodes', 'run_edges', 'run_unit_state', 'battles'] as $table) {
      $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `$table`", []));
    }
  }

  public function testExternalAuthenticationCreatesOnceAndUpdatesExistingProfile(): void
  {
    $users = new UserRepository($this->pdo);
    $states = new PlayerStateRepository($this->pdo);
    $service = $this->accountCreationService($users, $states);

    $userId = $service->findOrCreateExternal('discord', 'provider-123', 'First Name', 'https://example.test/first.png', 'first@example.test');
    $this->trackUserId($userId);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `users` WHERE `id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_external_identities` WHERE `user_id` = ? AND `provider` = ? AND `provider_user_id` = ?', [$userId, 'discord', 'provider-123']));
    $this->assertSame($this->contentRegistry()->startingEnergy(), $states->getPlayerState($userId)['energy_current'] ?? null);

    $resolvedId = $service->findOrCreateExternal('discord', 'provider-123', 'Updated Name', 'https://example.test/updated.png', 'updated@example.test');
    $this->assertSame($userId, $resolvedId);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `users` WHERE `id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_external_identities` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('Updated Name', (string)$this->scalar('SELECT `display_name` FROM `users` WHERE `id` = ?', [$userId]));
    $this->assertSame('https://example.test/updated.png', (string)$this->scalar('SELECT `avatar_url` FROM `users` WHERE `id` = ?', [$userId]));
    $this->assertSame($this->contentRegistry()->startingEnergy(), $states->getPlayerState($userId)['energy_current'] ?? null);
  }

  public function testDuplicateLocalCredentialRollsBackNewUserAndState(): void
  {
    $users = new UserRepository($this->pdo);
    $states = new PlayerStateRepository($this->pdo);
    $service = $this->accountCreationService($users, $states);
    $firstUserId = $service->createLocal('unique@example.test', password_hash('password-one', PASSWORD_DEFAULT), 'First');
    $this->trackUserId($firstUserId);
    $before = (int)$this->scalar('SELECT COUNT(*) FROM `users`', []);

    try {
      $service->createLocal('UNIQUE@example.test', password_hash('password-two', PASSWORD_DEFAULT), 'Second');
      $this->fail('Expected duplicate email to fail.');
    } catch (\PDOException $e) {
      $this->assertSame('23000', (string)$e->getCode());
    }

    $this->assertSame($before, (int)$this->scalar('SELECT COUNT(*) FROM `users`', []));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?', [$firstUserId]));
  }

  public function testExternalIdentityFailureRollsBackUserAndState(): void
  {
    $users = new UserRepository($this->pdo);
    $service = $this->accountCreationService($users, new PlayerStateRepository($this->pdo));
    $before = (int)$this->scalar('SELECT COUNT(*) FROM `users`', []);

    try {
      $service->findOrCreateExternal('discord', str_repeat('x', 129), 'Rollback Goblin', null);
      $this->fail('Expected oversized provider identity to fail.');
    } catch (\RuntimeException $e) {
      $this->assertSame('External provider identity is invalid.', $e->getMessage());
    }

    $this->assertSame($before, (int)$this->scalar('SELECT COUNT(*) FROM `users`', []));
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `user_external_identities` WHERE `provider_user_id` = ?", [str_repeat('x', 128)]));
  }

  public function testSessionReadDoesNotProvisionMissingPlayerState(): void
  {
    $users = new UserRepository($this->pdo);
    $userId = $users->createUser('State-less Goblin', null);
    $_SESSION['user_id'] = $userId;

    $payload = (new SessionService($users, new CsrfService()))->getSessionPayload();

    $this->assertTrue($payload['authenticated']);
    $this->trackUserId($userId);
    $stmt = $this->pdo?->prepare('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?');
    $stmt?->execute([$userId]);
    $this->assertSame(0, (int)$stmt?->fetchColumn());
  }

  public function testSessionEndpointRemainsContentIndependent(): void
  {
    $users = new UserRepository($this->pdo);
    $userId = $users->createUser('Session-only Goblin', null);
    $this->trackUserId($userId);
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new ApiController())->session());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertSame(true, $response['body']['data']['authenticated'] ?? null);
    $this->assertSame((string)$userId, $response['body']['data']['user']['id'] ?? null);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  private function accountCreationService(UserRepository $users, PlayerStateRepository $states): AccountCreationService
  {
    return new AccountCreationService($this->pdo, $users, $states, $this->contentRegistry()->startingEnergy());
  }

  private function contentRegistry(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/content');
  }
}
