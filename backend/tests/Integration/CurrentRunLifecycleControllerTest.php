<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Commands\AbandonRunCommand;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\GameBootstrapController;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

final class CurrentRunLifecycleControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testCurrentRequiresAuthenticationAndNoRunIsSuccessful(): void
  {
    $unauthorized = $this->invoke(fn() => (new RunController())->current());
    $this->assertSame(401, $unauthorized['status']);
    $this->assertSame('unauthorized', $unauthorized['body']['error']['code'] ?? null);

    [$userId] = $this->fixtureAccount('current-none');
    $_SESSION['user_id'] = $userId;
    $response = $this->invoke(fn() => (new RunController())->current());
    $this->assertSame(200, $response['status']);
    $this->assertSame(['run' => null, 'player_revision' => 2], $response['body']['data'] ?? null);
  }

  public function testCurrentReturnsPersistedSafeAggregateWithoutMutatingIt(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('current-aggregate');
    $bruiser = (int)$fixture['unit_ids']['bruiser'];
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 3 WHERE `id` = ?')->execute([$bruiser]);
    $runId = $this->start($userId);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 9 WHERE `run_id` = ? AND `unit_id` = ?')
      ->execute([$runId, $bruiser]);
    $before = $this->snapshot($userId, $runId);
    $response = $this->current($userId);
    $after = $this->snapshot($userId, $runId);

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $response['body']['data'] ?? [];
    $run = $data['run'] ?? [];
    $this->assertSame((string)$runId, $run['id'] ?? null);
    $this->assertSame('region.the_farm', $run['region_id'] ?? null);
    $this->assertSame('active', $run['status'] ?? null);
    $this->assertCount(5, $run['nodes'] ?? []);
    $this->assertCount(4, $run['edges'] ?? []);
    $this->assertCount(5, $run['units'] ?? []);
    $bruiserState = array_values(array_filter($run['units'], static fn(array $unit): bool => $unit['unit_id'] === (string)$bruiser));
    $this->assertSame([['unit_id' => (string)$bruiser, 'current_hp' => 9]], $bruiserState);
    $reloadedPdo = new PDO((string)getenv('TEST_DB_DSN'), (string)getenv('TEST_DB_USER'), (string)getenv('TEST_DB_PASS'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $reloaded = (new CurrentRunQuery(new RunPersistenceRepository($reloadedPdo),
      new PlayerStateRepository($reloadedPdo), $this->content()))->execute($userId);
    $reloadedBruiser = array_values(array_filter($reloaded['run']['units'] ?? [],
      static fn(array $unit): bool => $unit['unit_id'] === (string)$bruiser));
    $this->assertSame([['unit_id' => (string)$bruiser, 'current_hp' => 9]], $reloadedBruiser);
    $this->assertSame([0, 1, 2, 3, 4], array_column($run['nodes'], 'node_index'));
    $this->assertSame([0, 1, 2, 3, 4], array_column(array_column($run['nodes'], 'position'), 'column'));
    $this->assertSame([1, 1, 1, 1, 1], array_column(array_column($run['nodes'], 'position'), 'row'));
    $encoded = json_encode($data);
    foreach (['generated_metadata', 'run_generation_id', 'algorithm', 'local_key', 'encounter_id'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
    $this->assertStringNotContainsString('encounter.the_farm_mud_combat_1', $encoded);
    $this->assertStringNotContainsString('Mudwrestler', $encoded);
    $this->assertSame($before, $after);

    $nodeIds = array_column($run['nodes'], 'id');
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = '2026-09-13 12:00:00' WHERE `run_id` = ? AND `node_index` = 0")
      ->execute([$runId]);
    $mutable = $this->current($userId);
    $this->assertSame(200, $mutable['status']);
    $this->assertSame('completed', $mutable['body']['data']['run']['nodes'][0]['status'] ?? null);
    $this->assertSame($nodeIds, array_column($mutable['body']['data']['run']['nodes'], 'id'));
  }

  public function testCurrentAndBootstrapFailSafelyForObservableCorruption(): void
  {
    foreach (['region', 'node_type', 'squad_owner', 'unit_owner', 'graph'] as $kind) {
      [$userId, $fixture] = $this->fixtureAccount('corrupt-' . $kind);
      $runId = $this->start($userId);
      $otherId = $this->createAccount('corrupt-other-' . $kind);
      if ($kind === 'region') {
        $this->pdo?->prepare("UPDATE `runs` SET `region_id` = 'region.missing' WHERE `id` = ?")->execute([$runId]);
      } elseif ($kind === 'node_type') {
        $this->pdo?->prepare("UPDATE `run_nodes` SET `node_type_id` = 'run_node_type.missing' WHERE `run_id` = ? LIMIT 1")->execute([$runId]);
      } elseif ($kind === 'squad_owner') {
        $this->pdo?->prepare('UPDATE `squads` SET `user_id` = ? WHERE `id` = ?')->execute([$otherId, (int)$fixture['active_squad_id']]);
      } elseif ($kind === 'unit_owner') {
        $this->pdo?->prepare('UPDATE `unit_instances` SET `user_id` = ? WHERE `id` = ?')->execute([$otherId, (int)$fixture['unit_ids']['bruiser']]);
      } else {
        $this->pdo?->prepare('DELETE FROM `run_edges` WHERE `run_id` = ? ORDER BY `from_node_id` LIMIT 1')->execute([$runId]);
      }
      $current = $this->current($userId);
      $this->assertSame(500, $current['status'], $kind . ':' . json_encode($current['body']));
      $this->assertSame('run_data_integrity_error', $current['body']['error']['code'] ?? null);

      if (in_array($kind, ['region', 'squad_owner'], true)) {
        $_SESSION['user_id'] = $userId;
        $bootstrap = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
        $this->assertSame(500, $bootstrap['status']);
        $this->assertSame('player_state_integrity_error', $bootstrap['body']['error']['code'] ?? null);
      }
    }
  }

  public function testBootstrapReturnsOnlyCompactActiveSummary(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('bootstrap-run');
    $runId = $this->start($userId);
    $_SESSION['user_id'] = $userId;
    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame([
      'id' => (string)$runId, 'region_id' => 'region.the_farm',
      'squad_id' => $fixture['active_squad_id'], 'status' => 'active',
    ], $response['body']['data']['active_run'] ?? null);
    $encoded = json_encode($response['body']['data']['active_run']);
    $this->assertStringNotContainsString('nodes', $encoded);
    $this->assertStringNotContainsString('edges', $encoded);
    $this->assertSame(3, $response['body']['data']['player']['player_revision'] ?? null);
  }

  public function testAbandonSecurityLifecycleRetentionAndNaturalRetry(): void
  {
    [$userId] = $this->fixtureAccount('abandon');
    [$otherId] = $this->fixtureAccount('abandon-other');
    $runId = $this->start($userId);
    $otherRunId = $this->start($otherId);
    $before = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);

    $unauthorized = $this->invoke(fn() => (new RunController())->abandon((string)$runId));
    $this->assertSame(401, $unauthorized['status']);
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'expected'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $csrf = $this->invoke(fn() => (new RunController())->abandon((string)$runId));
    $this->assertSame(403, $csrf['status']);
    $foreign = $this->abandon($userId, $otherRunId);
    $missing = $this->abandon($userId, 999999999);
    $malformed = $this->abandonRaw($userId, '01');
    foreach ([$foreign, $missing, $malformed] as $response) {
      $this->assertSame(404, $response['status']);
      $this->assertSame('run_not_found', $response['body']['error']['code'] ?? null);
    }

    $instant = new DateTimeImmutable('2026-09-13 21:14:15', new DateTimeZone('UTC'));
    $clock = new class($instant) implements Clock {
      public function __construct(private readonly DateTimeImmutable $instant) {}
      public function now(): DateTimeImmutable { return $this->instant; }
    };
    $command = new AbandonRunCommand($this->pdo, new PlayerStateRepository($this->pdo),
      new RunPersistenceRepository($this->pdo), $this->content(), $clock);
    $first = $command->execute($userId, $runId);
    $replay = $command->execute($userId, $runId);
    $this->assertSame($first, $replay);
    $this->assertSame('2026-09-13T21:14:15Z', $first['run']['ended_at']);
    $this->assertSame('abandoned', $first['run']['status']);
    $this->assertNull($first['active_run']);
    $this->assertSame((int)$before['player_revision'] + 1, $first['player_revision']);
    $after = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $this->assertSame($before['energy_current'], $after['energy_current']);
    $this->assertSame($before['energy_last_regen_at'], $after['energy_last_regen_at']);
    $this->assertSame((int)$before['player_revision'] + 1, (int)$after['player_revision']);
    $this->assertSame(5, (int)$this->scalar('SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ?', [$runId]));
    $this->assertSame(4, (int)$this->scalar('SELECT COUNT(*) FROM `run_edges` WHERE `run_id` = ?', [$runId]));
    $this->assertSame(5, (int)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ?', [$runId]));

    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'completed' WHERE `id` = ?")->execute([$runId]);
    $terminal = $this->abandon($userId, $runId);
    $this->assertSame(409, $terminal['status']);
    $this->assertSame('run_lifecycle_conflict', $terminal['body']['error']['code'] ?? null);
    $this->assertSame((int)$after['player_revision'], $this->revision($userId));
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(string $token): array
  {
    $userId = $this->createAccount($token);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $this->content()))->execute($userId);
    return [$userId, $fixture];
  }

  private function createAccount(string $token): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $id = $services['accountCreationService']->createLocal($token . '-' . bin2hex(random_bytes(3)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Runner');
    $this->trackUserId($id);
    return $id;
  }

  private function start(int $userId): int
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'run-' . bin2hex(random_bytes(8));
    $this->setJsonBody(['region_id' => 'region.the_farm']);
    $response = $this->invoke(fn() => (new RunController())->start());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    return (int)$response['body']['data']['run']['id'];
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function current(int $userId): array
  {
    $_SESSION['user_id'] = $userId;
    return $this->invoke(fn() => (new RunController())->current());
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function abandon(int $userId, int $runId): array { return $this->abandonRaw($userId, (string)$runId); }

  /** @return array{status:int,body:array<string,mixed>} */
  private function abandonRaw(int $userId, string $runId): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    return $this->invoke(fn() => (new RunController())->abandon($runId));
  }

  /** @return array<string,mixed> */
  private function snapshot(int $userId, int $runId): array
  {
    return ['state' => $this->rows('SELECT * FROM `user_state` WHERE `user_id` = ?', [$userId]),
      'run' => $this->rows('SELECT * FROM `runs` WHERE `id` = ?', [$runId]),
      'nodes' => $this->rows('SELECT * FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]),
      'edges' => $this->rows('SELECT * FROM `run_edges` WHERE `run_id` = ? ORDER BY `from_node_id`', [$runId]),
      'units' => $this->rows('SELECT * FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId])];
  }

  /** @param list<int|string> $params @return array<string,mixed> */
  private function row(string $sql, array $params): array { return $this->rows($sql, $params)[0] ?? []; }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }

  private function revision(int $userId): int
  {
    return (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }
}
