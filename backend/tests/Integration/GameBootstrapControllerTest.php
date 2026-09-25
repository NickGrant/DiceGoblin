<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\GameBootstrapController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class GameBootstrapControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  public function testUnauthenticatedBootstrapIsRejected(): void
  {
    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());

    $this->assertSame(401, $response['status']);
    $this->assertSame(false, $response['body']['ok'] ?? null);
    $this->assertSame('unauthorized', $response['body']['error']['code'] ?? null);
  }

  public function testFreshAuthenticatedAccountReceivesMilestoneOneBootstrap(): void
  {
    $userId = $this->createAccount('fresh-bootstrap@example.test', 'Fresh Bootstrap');
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $response['body']['data'] ?? [];
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertSame((string)$userId, $data['account']['id'] ?? null);
    $this->assertSame('Fresh Bootstrap', $data['account']['display_name'] ?? null);
    $this->assertSame('user', $data['account']['role'] ?? null);
    $this->assertSame(0, $data['player']['teeth'] ?? null);
    $this->assertSame(0, $data['player']['raw_chaos'] ?? null);
    $this->assertSame(50, $data['player']['energy']['current'] ?? null);
    $this->assertSame(50, $data['player']['energy']['normal_max'] ?? null);
    $this->assertSame(12, $data['player']['energy']['regeneration_per_hour'] ?? null);
    $this->assertSame(300, $data['player']['energy']['regeneration_interval_seconds'] ?? null);
    $this->assertIsString($data['player']['energy']['last_regeneration_at'] ?? null);
    $this->assertNull($data['player']['energy']['next_regeneration_at'] ?? null);
    $this->assertNull($data['player']['energy']['fully_regenerated_at'] ?? null);
    $this->assertSame(1, $data['player']['player_revision'] ?? null);
    $this->assertSame(true, $data['session']['authenticated'] ?? null);
    $this->assertIsString($data['session']['csrf_token'] ?? null);
    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string)($data['server_time'] ?? ''));
    $this->assertSame($this->contentRegistry()->revision(), $data['content_revision'] ?? null);
    $this->assertSame([
      'unlock_ids' => [],
      'available_region_ids' => ['region.the_farm'],
    ], $data['progression'] ?? null);
    $this->assertArrayHasKey('active_squad', $data);
    $this->assertNull($data['active_squad']);
    $this->assertArrayHasKey('active_run', $data);
    $this->assertNull($data['active_run']);
  }

  public function testBootstrapUsesAuthoritativeRowsAndDoesNotPersistCalculatedEnergy(): void
  {
    $userId = $this->createAccount('authoritative-bootstrap@example.test', 'Original Name');
    $this->pdo?->prepare("UPDATE `users` SET `display_name` = 'Database Goblin', `role` = 'admin' WHERE `id` = ?")->execute([$userId]);
    $this->pdo?->prepare('UPDATE `user_state` SET `teeth` = 4321, `raw_chaos` = 87, `energy_current` = 41, `energy_last_regen_at` = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), `player_revision` = 29 WHERE `user_id` = ?')->execute([$userId]);
    $before = $this->playerStateRow($userId);
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $response['body']['data'] ?? [];
    $this->assertSame('Database Goblin', $data['account']['display_name'] ?? null);
    $this->assertSame('admin', $data['account']['role'] ?? null);
    $this->assertSame(4321, $data['player']['teeth'] ?? null);
    $this->assertSame(87, $data['player']['raw_chaos'] ?? null);
    $this->assertSame(43, $data['player']['energy']['current'] ?? null);
    $this->assertSame(29, $data['player']['player_revision'] ?? null);
    $this->assertSame($before, $this->playerStateRow($userId));
  }

  public function testBootstrapReadsSortedOwnedUnlocksWithoutCrossUserLeakageOrWrites(): void
  {
    $userId = $this->createAccount('owned-unlocks@example.test', 'Owned Unlocks');
    $otherUserId = $this->createAccount('other-unlocks@example.test', 'Other Unlocks');
    $this->pdo?->prepare('INSERT INTO `user_unlocks` (`user_id`, `unlock_id`, `granted_at`) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)')
      ->execute([
        $userId, 'unlock.region.zz_test', '2026-09-17 09:00:00',
        $userId, 'unlock.region.mountains', '2026-09-17 08:00:00',
        $otherUserId, 'unlock.region.other_user', '2026-09-17 07:00:00',
      ]);
    $beforeState = $this->playerStateRow($userId);
    $beforeUnlocks = $this->unlockRows($userId);
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(
      ['unlock.region.mountains', 'unlock.region.zz_test'],
      $response['body']['data']['progression']['unlock_ids'] ?? null,
    );
    $this->assertSame(
      ['region.the_farm', 'region.mountains'],
      $response['body']['data']['progression']['available_region_ids'] ?? null,
    );
    $this->assertSame($beforeState, $this->playerStateRow($userId));
    $this->assertSame($beforeUnlocks, $this->unlockRows($userId));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?', [$otherUserId]));
  }

  public function testMissingPlayerStateReturnsControlledIntegrityFailureWithoutProvisioning(): void
  {
    $core = ControllerServiceFactory::buildCore($this->pdo);
    $userId = $core['userRepo']->createUser('State-less Bootstrap', null);
    $this->trackUserId($userId);
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());

    $this->assertSame(500, $response['status']);
    $this->assertSame('player_state_integrity_error', $response['body']['error']['code'] ?? null);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testBootstrapUsesNonEvenAuthoredRateDeterministicallyWithoutDurableWrites(): void
  {
    $userId = $this->createAccount('authored-energy@example.test', 'Authored Energy');
    $this->pdo?->prepare('UPDATE `user_state` SET `energy_current` = 20, `energy_last_regen_at` = ? WHERE `user_id` = ?')
      ->execute(['2026-09-10 12:00:00', $userId]);
    $before = $this->playerStateRow($userId);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $this->contentRegistry(50, 7.0));

    $data = $services['gameBootstrapQuery']->execute(
      $userId,
      new DateTimeImmutable('2026-09-10 13:00:00', new DateTimeZone('UTC')),
    );

    $this->assertSame(50, $data['player']['energy']['normal_max'] ?? null);
    $this->assertSame(7.0, $data['player']['energy']['regeneration_per_hour'] ?? null);
    $this->assertSame(27, $data['player']['energy']['current'] ?? null);
    $this->assertEqualsWithDelta(514.285714, (float)($data['player']['energy']['regeneration_interval_seconds'] ?? 0), 0.000001);
    $this->assertSame('2026-09-10T13:08:35Z', $data['player']['energy']['next_regeneration_at'] ?? null);
    $this->assertSame('2026-09-10T16:17:09Z', $data['player']['energy']['fully_regenerated_at'] ?? null);
    $this->assertSame($before, $this->playerStateRow($userId));
  }

  private function createAccount(string $email, string $displayName): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $this->contentRegistry());
    $userId = $services['accountCreationService']->createLocal(
      $email,
      password_hash('test-password', PASSWORD_DEFAULT),
      $displayName,
    );
    $this->trackUserId($userId);
    return $userId;
  }

  /** @return array<string,mixed> */
  private function playerStateRow(int $userId): array
  {
    $stmt = $this->pdo?->prepare('SELECT `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`, `created_at`, `updated_at` FROM `user_state` WHERE `user_id` = ?');
    $stmt?->execute([$userId]);
    $row = $stmt?->fetch(\PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  /** @return list<array<string,string>> */
  private function unlockRows(int $userId): array
  {
    $stmt = $this->pdo?->prepare('SELECT `unlock_id`, `granted_at` FROM `user_unlocks` WHERE `user_id` = ? ORDER BY `unlock_id`');
    $stmt?->execute([$userId]);
    return $stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  private function contentRegistry(int $normalMaximum = 50, float $regenerationPerHour = 12.0): ContentRegistry
  {
    if ($normalMaximum === 50 && $regenerationPerHour === 12.0) {
      return ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    }

    $root = sys_get_temp_dir() . '/dice-goblins-bootstrap-content-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $document = [
      'definitions' => [
        [
          'id' => 'config.gameplay',
          'type' => 'gameplay_config',
          'starting_energy' => 50,
          'energy_normal_max' => $normalMaximum,
          'energy_regeneration_per_hour' => $regenerationPerHour,
          'run_energy_cost' => 10,
          'starting_region_id' => 'region.test',
        ],
        [
          'id' => 'region.test',
          'type' => 'region',
          'display_name' => 'The Farm',
          'art_key' => 'farm',
          'run_generation_id' => 'run_generation.test',
        ],
        ['id' => 'run_node_type.combat', 'type' => 'run_node_type', 'display_name' => 'Combat', 'description' => 'Fight.', 'icon_key' => 'combat'],
        ['id' => 'run_node_type.loot', 'type' => 'run_node_type', 'display_name' => 'Loot', 'description' => 'Loot.', 'icon_key' => 'loot'],
        ['id' => 'run_node_type.rest', 'type' => 'run_node_type', 'display_name' => 'Rest', 'description' => 'Rest.', 'icon_key' => 'rest'],
        ['id' => 'run_node_type.boss', 'type' => 'run_node_type', 'display_name' => 'Boss', 'description' => 'Boss.', 'icon_key' => 'boss'],
        ['id' => 'run_node_type.exit', 'type' => 'run_node_type', 'display_name' => 'Exit', 'description' => 'Exit.', 'icon_key' => 'exit'],
        [
          'id' => 'run_generation.test', 'type' => 'run_generation', 'algorithm' => 'fixed_graph_v1', 'start_node_key' => 'combat',
          'nodes' => [
            ['key' => 'combat', 'node_type_id' => 'run_node_type.combat', 'position' => ['column' => 0, 'row' => 1]],
            ['key' => 'loot', 'node_type_id' => 'run_node_type.loot', 'position' => ['column' => 1, 'row' => 1]],
            ['key' => 'rest', 'node_type_id' => 'run_node_type.rest', 'position' => ['column' => 2, 'row' => 1]],
            ['key' => 'boss', 'node_type_id' => 'run_node_type.boss', 'position' => ['column' => 3, 'row' => 1]],
            ['key' => 'exit', 'node_type_id' => 'run_node_type.exit', 'position' => ['column' => 4, 'row' => 1]],
          ],
          'edges' => [
            ['from' => 'combat', 'to' => 'loot'], ['from' => 'loot', 'to' => 'rest'], ['from' => 'rest', 'to' => 'boss'], ['from' => 'boss', 'to' => 'exit'],
          ],
        ],
      ],
    ];
    file_put_contents($root . '/content.json', json_encode($document, JSON_THROW_ON_ERROR));
    $registry = ContentRegistry::load($root);
    unlink($root . '/content.json');
    rmdir($root);
    return $registry;
  }
}
