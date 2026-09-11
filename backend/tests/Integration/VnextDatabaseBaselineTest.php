<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\AccountCreationService;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Tests\Support\DatabaseTestCase;

final class VnextDatabaseBaselineTest extends DatabaseTestCase
{
  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  public function testBaselineContainsOnlyAcceptedPackageTablesAndColumns(): void
  {
    $tables = $this->testPdo?->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(\PDO::FETCH_COLUMN);
    $this->assertSame(['password_reset_tokens', 'user_external_identities', 'user_local_credentials', 'user_state', 'users'], $tables);

    $columns = $this->testPdo?->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_state' ORDER BY ORDINAL_POSITION")->fetchAll(\PDO::FETCH_COLUMN);
    $this->assertSame(['user_id', 'teeth', 'raw_chaos', 'energy_current', 'energy_last_regen_at', 'player_revision', 'created_at', 'updated_at'], $columns);
    $this->assertNotContains('energy_max', $columns);
  }

  public function testLocalAccountCreationAtomicallyPersistsCredentialsAndPlayerState(): void
  {
    $this->testPdo?->rollBack();
    $users = new UserRepository($this->testPdo);
    $states = new PlayerStateRepository($this->testPdo);
    $service = new AccountCreationService($this->testPdo, $users, $states);
    $hash = password_hash('secret-pass', PASSWORD_DEFAULT);
    $userId = $service->createLocal('  FRESH@example.test ', $hash, 'Fresh Goblin');

    $credential = $users->getUserByLocalEmail('fresh@example.test');
    $state = $states->getPlayerState($userId);
    $this->assertSame($userId, (int)($credential['id'] ?? 0));
    $this->assertTrue(password_verify('secret-pass', (string)($credential['password_hash'] ?? '')));
    $this->assertSame(0, $state['teeth'] ?? null);
    $this->assertSame(0, $state['raw_chaos'] ?? null);
    $this->assertSame(50, $state['energy_current'] ?? null);
    $this->assertSame(1, $state['player_revision'] ?? null);
    $this->testPdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$userId]);
  }

  public function testSessionReadDoesNotProvisionMissingPlayerState(): void
  {
    $users = new UserRepository($this->testPdo);
    $userId = $users->createUser('State-less Goblin', null);
    $_SESSION['user_id'] = $userId;

    $payload = (new SessionService($users, new CsrfService()))->getSessionPayload();

    $this->assertTrue($payload['authenticated']);
    $stmt = $this->testPdo?->prepare('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?');
    $stmt?->execute([$userId]);
    $this->assertSame(0, (int)$stmt?->fetchColumn());
  }
}
