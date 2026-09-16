<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ResolveCombatNodeCommand;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunCombatRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;
use RuntimeException;
use Throwable;

final class CombatNodeResolutionControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testControllerRequiresAuthCsrfEmptyBodyCanonicalIdsAndIdempotency(): void
  {
    $controller = new RunController();
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '';
    $unauthorized = $this->invoke(fn() => $controller->resolveNode('1', '1'));
    $this->assertSame([401, 'unauthorized'], [$unauthorized['status'], $unauthorized['body']['error']['code'] ?? null]);

    [$userId] = $this->fixtureAccount('combat-guard');
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'expected'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '';
    $csrf = $this->invoke(fn() => $controller->resolveNode('1', '1'));
    $this->assertSame([403, 'csrf_invalid'], [$csrf['status'], $csrf['body']['error']['code'] ?? null]);

    $body = $this->httpResolve($userId, 1, 1, 'guard-body-key', '{}');
    $this->assertSame([400, 'invalid_node_request'], [$body['status'], $body['body']['error']['code'] ?? null]);
    $key = $this->httpResolve($userId, 1, 1, null);
    $this->assertSame([400, 'idempotency_key_invalid'], [$key['status'], $key['body']['error']['code'] ?? null]);
    $badId = $this->httpResolveRaw($userId, '01', '1', 'guard-id-key');
    $this->assertSame([404, 'run_node_not_found'], [$badId['status'], $badId['body']['error']['code'] ?? null]);
  }

  public function testRealFarmCombatPersistsExactAuthoritativeBattleAndReplaysWithoutMutation(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('combat-real');
    $bruiser = (int)$fixture['unit_ids']['bruiser'];
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 3 WHERE `id` = ?')->execute([$bruiser]);
    [$runId, $nodeIds] = $this->startRun($userId, 'real-start-key');
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 13 WHERE `run_id` = ? AND `unit_id` = ?')
      ->execute([$runId, $bruiser]);
    $beforeState = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `teeth`, `raw_chaos`, `player_revision`
      FROM `user_state` WHERE `user_id` = ?', [$userId]);

    $first = $this->httpResolve($userId, $runId, $nodeIds[0], 'real-resolve-key');
    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $data = $first['body']['data'];
    $this->assertSame('victory', $data['battle']['outcome'] ?? null);
    $this->assertSame('completed', $data['node']['status'] ?? null);
    $this->assertSame('active', $data['run']['status'] ?? null);
    $this->assertSame([(string)$nodeIds[1]], $data['newly_available_node_ids'] ?? null);
    $this->assertSame((int)$beforeState['player_revision'] + 1, $data['player_revision'] ?? null);
    $encodedResponse = json_encode($data, JSON_THROW_ON_ERROR);
    foreach (['seed', 'combatants', 'events', 'mudwrestler', 'input_snapshot', 'rewards'] as $hidden) {
      $this->assertStringNotContainsString($hidden, $encodedResponse);
    }

    $battle = (new BattlePersistenceRepository($this->pdo))->findById((int)$data['battle']['id']);
    $this->assertNotNull($battle);
    $this->assertSame('combat-seed-v1:', substr((string)$battle?->battle->inputSnapshot['seed'], 0, 15));
    $bruiserPosition = (int)$this->scalar('SELECT `position` FROM `squad_units` WHERE `squad_id` = ? AND `unit_id` = ?',
      [(int)$fixture['active_squad_id'], $bruiser]);
    $bruiserKey = 'player_p' . $bruiserPosition;
    $this->assertSame(13, $battle?->battle->combatInput->combatants[$bruiserKey]['current_hp']);
    $this->assertSame(26, $battle?->battle->combatInput->combatants[$bruiserKey]['max_hp']);
    $this->assertArrayHasKey('mudwrestler', $battle?->battle->combatInput->combatants ?? []);
    $formation = $this->rows('SELECT `unit_id`, `position` FROM `squad_units` WHERE `squad_id` = ? ORDER BY `position`', [(int)$fixture['active_squad_id']]);
    $manifest = [];
    foreach ($battle?->battle->manifestArray() ?? [] as $entry) $manifest[$entry['combatant_key']] = $entry;
    foreach ($formation as $row) {
      $key = 'player_p' . (int)$row['position'];
      $this->assertSame((int)$row['unit_id'], $manifest[$key]['unit_id'] ?? null);
      $this->assertSame(['x' => (int)$row['position'] % 3, 'y' => intdiv((int)$row['position'], 3)],
        $battle?->battle->combatInput->combatants[$key]['position'] ?? null);
      $loadout = $this->rows('SELECT `ability_id` FROM `unit_ability_loadout` WHERE `unit_id` = ? ORDER BY `equip_order`', [(int)$row['unit_id']]);
      $combatant = $battle?->battle->combatInput->combatants[$key] ?? [];
      $this->assertSame(array_column($loadout, 'ability_id'), array_column($combatant['active_abilities'] ?? [], 'id'));
      foreach ($combatant['active_abilities'] ?? [] as $ability) {
        $bindings = $this->rows('SELECT uad.`dice_instance_id`, di.`size`, di.`profile_id` FROM `unit_ability_dice` uad
          JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`
          WHERE uad.`unit_id` = ? AND uad.`ability_id` = ? ORDER BY uad.`slot_index`', [(int)$row['unit_id'], $ability['id']]);
        foreach ($bindings as $slot => $binding) {
          $profile = $this->content()->diceProfile((string)$binding['profile_id']);
          $this->assertSame('die_i' . $binding['dice_instance_id'], $ability['dice'][$slot]['key']);
          $this->assertSame((int)$binding['size'], $ability['dice'][$slot]['sides']);
          $this->assertSame((string)$binding['profile_id'], $ability['dice'][$slot]['profile_id']);
          $this->assertSame($profile['aspect_ids'], array_column($ability['dice'][$slot]['effects'], 'id'));
        }
      }
    }
    $this->assertContains('ability.thick_hide', array_column(
      $battle?->battle->combatInput->combatants[$bruiserKey]['passive_abilities'] ?? [], 'id'));
    $dieKeys = [];
    foreach ($battle?->battle->inputSnapshot['combatants'] ?? [] as $combatant) {
      if (($combatant['side'] ?? null) !== 'player') continue;
      foreach ($combatant['active_abilities'] as $ability) foreach ($ability['dice'] as $die) $dieKeys[] = $die['key'];
    }
    $this->assertSame(count($dieKeys), count(array_unique($dieKeys)));
    $this->assertSame($battle?->battle->result['outcome'], $data['battle']['outcome']);
    foreach ($manifest as $key => $entry) {
      if ($entry['side'] !== 'player') continue;
      $terminal = $this->terminal($battle?->battle->result ?? [], $key);
      $this->assertSame((int)$terminal['current_hp'], (int)$this->scalar(
        'SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, (int)$entry['unit_id']]));
    }
    $this->assertSame(['completed', 'available', 'locked', 'locked', 'locked'], array_column(
      $this->rows('SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status'));
    $afterState = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `teeth`, `raw_chaos`, `player_revision`
      FROM `user_state` WHERE `user_id` = ?', [$userId]);
    foreach (['energy_current', 'energy_last_regen_at', 'teeth', 'raw_chaos'] as $field) $this->assertSame($beforeState[$field], $afterState[$field]);
    $current = (new CurrentRunQuery(new RunPersistenceRepository($this->pdo), new PlayerStateRepository($this->pdo), $this->content()))->execute($userId);
    $this->assertSame(['completed', 'available', 'locked', 'locked', 'locked'], array_column($current['run']['nodes'], 'status'));

    $snapshot = $this->stateSnapshot($userId, $runId);
    $replay = $this->httpResolve($userId, $runId, $nodeIds[0], 'real-resolve-key');
    $this->assertSame($first['body'], $replay['body']);
    $this->assertSame($snapshot, $this->stateSnapshot($userId, $runId));
    $another = $this->httpResolve($userId, $runId, $nodeIds[0], 'another-resolve-key');
    $this->assertSame([409, 'run_node_already_resolved'], [$another['status'], $another['body']['error']['code'] ?? null]);
    $conflict = $this->httpResolve($userId, $runId, $nodeIds[1], 'real-resolve-key');
    $this->assertSame([409, 'idempotency_conflict'], [$conflict['status'], $conflict['body']['error']['code'] ?? null]);
  }

  public function testDefeatAndStalematePersistTerminalFailureWithoutEnergyOrUnlocks(): void
  {
    foreach (['defeat', 'stalemate'] as $outcome) {
      [$userId] = $this->fixtureAccount('combat-' . $outcome);
      [$runId, $nodes] = $this->startRun($userId, $outcome . '-start-key');
      $before = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
      $resolver = $this->fixedResolver($outcome);
      $result = $this->command($resolver, $this->fixedClock('2026-09-15 12:34:56'))->execute(
        $userId, $runId, $nodes[0], $outcome . '-resolve-key',
      );
      $this->assertSame($outcome, $result['battle']['outcome']);
      $this->assertSame('failed', $result['run']['status']);
      $this->assertSame('2026-09-15T12:34:56Z', $result['run']['ended_at']);
      $this->assertSame([], $result['newly_available_node_ids']);
      $this->assertSame(['completed', 'locked', 'locked', 'locked', 'locked'], array_column(
        $this->rows('SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status'));
      $run = $this->row('SELECT `status`, `ended_at` FROM `runs` WHERE `id` = ?', [$runId]);
      $this->assertSame(['failed', '2026-09-15 12:34:56'], [$run['status'], $run['ended_at']]);
      $after = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
      $this->assertSame([$before['energy_current'], $before['energy_last_regen_at']], [$after['energy_current'], $after['energy_last_regen_at']]);
      $this->assertSame((int)$before['player_revision'] + 1, (int)$after['player_revision']);
      $this->assertNull((new CurrentRunQuery(new RunPersistenceRepository($this->pdo), new PlayerStateRepository($this->pdo), $this->content()))->execute($userId)['run']);
      $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ?', [$runId]));
      if ($outcome === 'defeat') {
        $this->assertSame('0', (string)$this->scalar('SELECT COALESCE(SUM(`current_hp`), 0) FROM `run_unit_state` WHERE `run_id` = ?', [$runId]));
      }
      $replay = $this->command($resolver, $this->fixedClock('2027-01-01 00:00:00'))->execute(
        $userId, $runId, $nodes[0], $outcome . '-resolve-key',
      );
      $this->assertSame($result, $replay);
      $this->assertSame(1, $resolver->calls);
    }
  }

  public function testOwnershipAvailabilityAndNodeTypeRejectionsAreNonMutating(): void
  {
    [$owner] = $this->fixtureAccount('combat-owner');
    [$other] = $this->fixtureAccount('combat-other');
    [$runId, $nodes] = $this->startRun($owner, 'eligibility-start-key');
    $before = $this->stateSnapshot($owner, $runId);

    $foreign = $this->httpResolve($other, $runId, $nodes[0], 'foreign-resolve-key');
    $missing = $this->httpResolve($owner, 999999999, 999999999, 'missing-resolve-key');
    $locked = $this->httpResolve($owner, $runId, $nodes[1], 'locked-resolve-key');
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `id` = ?")->execute([$nodes[1]]);
    $unsupported = $this->httpResolve($owner, $runId, $nodes[1], 'unsupported-resolve-key');

    $this->assertSame([404, 'run_node_not_found'], [$foreign['status'], $foreign['body']['error']['code'] ?? null]);
    $this->assertSame([404, 'run_node_not_found'], [$missing['status'], $missing['body']['error']['code'] ?? null]);
    $this->assertSame([409, 'run_node_unavailable'], [$locked['status'], $locked['body']['error']['code'] ?? null]);
    $this->assertSame([422, 'run_node_unsupported'], [$unsupported['status'], $unsupported['body']['error']['code'] ?? null]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'locked' WHERE `id` = ?")->execute([$nodes[1]]);
    $this->assertSame($before, $this->stateSnapshot($owner, $runId));
  }

  public function testPostEngineFailureAndInvalidConfigurationRollBackEverything(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('combat-rollback');
    [$runId, $nodes] = $this->startRun($userId, 'rollback-start-key');
    $before = $this->stateSnapshot($userId, $runId);
    $throwingClock = new class implements Clock {
      public function now(): DateTimeImmutable { throw new RuntimeException('Injected post-engine failure.'); }
    };
    try {
      $this->command($this->fixedResolver('victory'), $throwingClock)->execute($userId, $runId, $nodes[0], 'rollback-resolve-key');
      $this->fail('Expected injected failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Injected post-engine failure.', $e->getMessage());
    }
    $this->assertSame($before, $this->stateSnapshot($userId, $runId));

    $unitId = (int)$fixture['unit_ids']['bruiser'];
    $this->pdo?->prepare('DELETE FROM `unit_ability_dice` WHERE `unit_id` = ? LIMIT 1')->execute([$unitId]);
    $invalid = $this->httpResolve($userId, $runId, $nodes[0], 'invalid-config-key');
    $this->assertSame([422, 'combat_configuration_invalid'], [$invalid['status'], $invalid['body']['error']['code'] ?? null]);
    $this->assertSame($before, $this->stateSnapshot($userId, $runId));
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(string $prefix): array
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal($prefix . '-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Fighter');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $this->content()))->execute($userId);
    return [$userId, $fixture];
  }

  /** @return array{0:int,1:array<int,int>} */
  private function startRun(int $userId, string $key): array
  {
    $result = ControllerServiceFactory::buildContentAware($this->pdo)['startRunCommand']->execute(
      $userId, ['region_id' => 'region.the_farm'], $key,
    );
    $runId = (int)$result['run']['id'];
    $rows = $this->rows('SELECT `id`, `node_index` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]);
    return [$runId, array_map('intval', array_column($rows, 'id'))];
  }

  private function command(CombatResolver $resolver, Clock $clock): ResolveCombatNodeCommand
  {
    $content = $this->content();
    $units = new WarbandUnitRepository($this->pdo);
    return new ResolveCombatNodeCommand($this->pdo, new PlayerStateRepository($this->pdo), new RunPersistenceRepository($this->pdo),
      new RunCombatRepository($this->pdo), new BattlePersistenceRepository($this->pdo), new IdempotencyRequestRepository($this->pdo),
      new CombatSnapshotAssembler($content, new SquadRepository($this->pdo), new UnitDetailQuery($units, $content),
        new WarbandDiceRepository($this->pdo)), $resolver, new CombatSeedDeriver(), $clock);
  }

  private function fixedResolver(string $outcome): CombatResolver
  {
    return new class($outcome) implements CombatResolver {
      public int $calls = 0;
      public function __construct(private readonly string $outcome) {}
      public function resolve(CombatInput $input): CombatResult {
        $this->calls++;
        $terminal = [];
        foreach ($input->combatants as $key => $unit) {
          $hp = match ($this->outcome) {
            'victory' => $unit['side'] === 'enemy' ? 0 : $unit['current_hp'],
            'defeat' => $unit['side'] === 'player' ? 0 : $unit['current_hp'],
            default => $unit['current_hp'],
          };
          $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $hp, 'max_hp' => $unit['max_hp'],
            'is_defeated' => $hp === 0, 'statuses' => $unit['statuses']];
        }
        $events = new PlaybackRecorder();
        $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($input->combatants)]);
        $events->add('battle_ended', 1, 1, ['outcome' => $this->outcome]);
        return new CombatResult($this->outcome, 1, 1, $terminal, $events->events());
      }
    };
  }

  private function fixedClock(string $time): Clock
  {
    return new class(new DateTimeImmutable($time, new DateTimeZone('UTC'))) implements Clock {
      public function __construct(private readonly DateTimeImmutable $time) {}
      public function now(): DateTimeImmutable { return $this->time; }
    };
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function httpResolve(int $userId, int $runId, int $nodeId, ?string $key, string $body = ''): array
  {
    return $this->httpResolveRaw($userId, (string)$runId, (string)$nodeId, $key, $body);
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function httpResolveRaw(int $userId, string $runId, string $nodeId, ?string $key, string $body = ''): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'combat-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'combat-csrf';
    if ($key === null) unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); else $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = $body;
    return $this->invoke(fn() => (new RunController())->resolveNode($runId, $nodeId));
  }

  /** @return array<string,mixed> */
  private function stateSnapshot(int $userId, int $runId): array
  {
    return ['state' => $this->row('SELECT `energy_current`, `energy_last_regen_at`, `teeth`, `raw_chaos`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      'run' => $this->row('SELECT `status`, `ended_at` FROM `runs` WHERE `id` = ?', [$runId]),
      'nodes' => $this->rows('SELECT `id`, `status`, `completed_at` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]),
      'hp' => $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]),
      'battles' => (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ?', [$runId]),
      'receipts' => (string)$this->scalar("SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ? AND `operation_type` = 'resolve_run_node'", [$userId])];
  }

  /** @return array<string,mixed> */
  private function terminal(array $result, string $key): array
  {
    foreach ($result['combatants'] ?? [] as $combatant) if (($combatant['key'] ?? null) === $key) return $combatant;
    return [];
  }

  /** @param list<int|string> $params @return array<string,mixed> */
  private function row(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  private function content(): ContentRegistry { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }
}
