<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\RunParticipationValidator;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Commands\StartRunCommand;
use DiceGoblins\Application\Commands\UnitConfigurationSupport;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Domain\Energy\EnergySpendCalculator;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\RunGeneration\RunGraphGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;
use RuntimeException;
use Throwable;

final class RunStartControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testRouteRequiresAuthenticationAndCsrf(): void
  {
    $controller = new RunController();
    $this->setJsonBody(['region_id' => 'region.the_farm']);
    $unauthorized = $this->invoke(fn() => $controller->start());
    $this->assertSame(401, $unauthorized['status']);
    $this->assertSame('unauthorized', $unauthorized['body']['error']['code'] ?? null);

    [$userId] = $this->fixtureAccount('run-security@example.test');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $this->setJsonBody(['region_id' => 'region.the_farm']);
    $csrf = $this->invoke(fn() => $controller->start());
    $this->assertSame(403, $csrf['status']);
    $this->assertSame('csrf_invalid', $csrf['body']['error']['code'] ?? null);
    $this->assertSame(0, $this->runCount($userId));
  }

  public function testExactRequestShapeAndIdempotencyKeyAreRequiredBeforeMutation(): void
  {
    [$userId] = $this->fixtureAccount('run-request@example.test');
    foreach ([
      [],
      ['region_id' => 1],
      ['region_id' => 'farm'],
      ['region_id' => 'region.TheFarm'],
      ['region_id' => 'region.the_farm', 'squad_id' => '1'],
      ['region_id' => 'region.the_farm', 'unit_ids' => []],
      ['region_id' => 'region.the_farm', 'energy_cost' => 1],
      ['region_id' => 'region.the_farm', 'graph' => []],
    ] as $index => $body) {
      $response = $this->httpCommand($userId, $body, 'invalid-body-' . $index);
      $this->assertSame(422, $response['status'], json_encode($response['body']));
      $this->assertSame('invalid_run_request', $response['body']['error']['code'] ?? null);
    }

    foreach ([null, 'short', str_repeat('x', 129), 'bad key!'] as $key) {
      $response = $this->httpCommand($userId, ['region_id' => 'region.the_farm'], $key);
      $this->assertSame(400, $response['status']);
      $this->assertSame('idempotency_key_invalid', $response['body']['error']['code'] ?? null);
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'run-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'run-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'malformed-json-key';
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '{bad json';
    $malformed = $this->invoke(fn() => (new RunController())->start());
    $this->assertSame(422, $malformed['status']);
    $this->assertSame('invalid_run_request', $malformed['body']['error']['code'] ?? null);
    $this->assertSame(0, $this->runCount($userId));
    $this->assertSame(2, $this->revision($userId));
  }

  public function testHttpEndpointReturnsTheAuthoritativeSuccessEnvelope(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('run-http-success@example.test');
    $response = $this->httpCommand($userId, ['region_id' => 'region.the_farm'], 'http-success-key');

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertTrue($response['body']['ok'] ?? false);
    $this->assertSame('region.the_farm', $response['body']['data']['run']['region_id'] ?? null);
    $this->assertSame($fixture['active_squad_id'], $response['body']['data']['run']['squad_id'] ?? null);
    $this->assertSame(40, $response['body']['data']['energy']['current'] ?? null);
    $this->assertSame(3, $response['body']['data']['player_revision'] ?? null);
  }

  public function testSuccessfulFarmStartPersistsAuthoritativeAggregateAndResponse(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('run-success@example.test');
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 4 WHERE `id` = ?')
      ->execute([(int)$fixture['unit_ids']['bruiser']]);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 1 WHERE `id` = ?')
      ->execute([(int)$fixture['unit_ids']['guardian']]);
    $this->setPlayerState($userId, 50, '2026-09-13 12:00:00');
    $result = $this->commandAt($userId, '2026-09-13 13:00:00')->execute(
      $userId,
      ['region_id' => 'region.the_farm'],
      'run-success-key',
    );

    $runId = (int)$result['run']['id'];
    $this->assertSame('region.the_farm', $result['run']['region_id']);
    $this->assertSame($fixture['active_squad_id'], $result['run']['squad_id']);
    $this->assertSame('active', $result['run']['status']);
    $this->assertSame(40, $result['energy']['current']);
    $this->assertSame(50, $result['energy']['normal_max']);
    $this->assertSame(12, $result['energy']['regeneration_per_hour']);
    $this->assertSame(300, $result['energy']['regeneration_interval_seconds']);
    $this->assertSame('2026-09-13T13:00:00Z', $result['energy']['last_regeneration_at']);
    $this->assertSame('2026-09-13T13:05:00Z', $result['energy']['next_regeneration_at']);
    $this->assertSame('2026-09-13T13:50:00Z', $result['energy']['fully_regenerated_at']);
    $this->assertSame(3, $result['player_revision']);
    $this->assertSame(3, $this->revision($userId));

    $run = $this->row('SELECT `user_id`, `region_id`, `squad_id`, `status` FROM `runs` WHERE `id` = ?', [$runId]);
    $this->assertSame((string)$userId, (string)$run['user_id']);
    $this->assertSame('region.the_farm', $run['region_id']);
    $this->assertSame($fixture['active_squad_id'], (string)$run['squad_id']);
    $this->assertSame('active', $run['status']);

    $nodes = $this->rows('SELECT `id`, `node_index`, `node_type_id`, `encounter_id`, `status`, `generated_metadata` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]);
    $this->assertSame([0, 1, 2, 3, 4], array_map('intval', array_column($nodes, 'node_index')));
    $this->assertSame(['run_node_type.combat', 'run_node_type.loot', 'run_node_type.rest', 'run_node_type.boss', 'run_node_type.exit'], array_column($nodes, 'node_type_id'));
    $this->assertSame(['available', 'locked', 'locked', 'locked', 'locked'], array_column($nodes, 'status'));
    $this->assertSame(['encounter.the_farm_mud_combat_1', null, null, null, null], array_column($nodes, 'encounter_id'));
    foreach ($nodes as $index => $node) {
      $this->assertEquals(['position' => ['column' => $index, 'row' => 1]], json_decode((string)$node['generated_metadata'], true));
    }

    $edges = $this->rows('SELECT f.`node_index` AS `from_index`, t.`node_index` AS `to_index`, e.`generated_metadata`
      FROM `run_edges` e JOIN `run_nodes` f ON f.`id` = e.`from_node_id` AND f.`run_id` = e.`run_id`
      JOIN `run_nodes` t ON t.`id` = e.`to_node_id` AND t.`run_id` = e.`run_id`
      WHERE e.`run_id` = ? ORDER BY f.`node_index`', [$runId]);
    $this->assertSame([[0, 1], [1, 2], [2, 3], [3, 4]], array_map(
      static fn(array $edge): array => [(int)$edge['from_index'], (int)$edge['to_index']],
      $edges,
    ));
    $this->assertSame([null, null, null, null], array_column($edges, 'generated_metadata'));

    $participants = $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ? ORDER BY `unit_id`', [$runId]);
    $expectedUnits = array_map('intval', [$fixture['unit_ids']['bruiser'], $fixture['unit_ids']['guardian'], $fixture['unit_ids']['marksman'], $fixture['unit_ids']['bannerbearer'], $fixture['unit_ids']['saboteur']]);
    sort($expectedUnits, SORT_NUMERIC);
    $this->assertSame($expectedUnits, array_map('intval', array_column($participants, 'unit_id')));
    $persisted = $this->rows('SELECT rus.`unit_id`, rus.`current_hp`, ui.`unit_type_id`, ui.`level`
      FROM `run_unit_state` rus JOIN `unit_instances` ui ON ui.`id` = rus.`unit_id`
      WHERE rus.`run_id` = ? ORDER BY rus.`unit_id`', [$runId]);
    $stats = new BaseLevelStatResolver();
    foreach ($persisted as $unit) {
      $type = $this->content()->unitType((string)$unit['unit_type_id']);
      $expectedHp = $stats->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
      $this->assertSame($expectedHp, (int)$unit['current_hp']);
    }
    $this->assertSame(28, (int)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?',
      [$runId, (int)$fixture['unit_ids']['bruiser']]));
    $this->assertSame(24, (int)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?',
      [$runId, (int)$fixture['unit_ids']['guardian']]));

    $encoded = json_encode($result, JSON_THROW_ON_ERROR);
    foreach (['nodes', 'edges', 'algorithm', 'start_node_key', 'run_generation'] as $privateField) {
      $this->assertStringNotContainsString($privateField, $encoded);
    }
    $this->assertStringNotContainsString('encounter.the_farm_mud_combat_1', $encoded);
    $this->assertStringNotContainsString('Mudwrestler', $encoded);
    $this->assertSame(1, $this->receiptCount($userId));
  }

  /** @dataProvider successfulEnergyStateProvider */
  public function testSuccessfulSpendPersistsControlledAnchorSemantics(
    int $current,
    string $anchor,
    string $now,
    int $expectedCurrent,
    string $expectedAnchor,
  ): void {
    [$userId] = $this->fixtureAccount('run-energy-' . bin2hex(random_bytes(3)) . '@example.test');
    $this->setPlayerState($userId, $current, $anchor);

    $result = $this->commandAt($userId, $now)->execute($userId, ['region_id' => 'region.the_farm'], 'energy-state-key');

    $state = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $this->assertSame($expectedCurrent, (int)$state['energy_current']);
    $this->assertSame($expectedAnchor, (string)$state['energy_last_regen_at']);
    $this->assertSame($expectedCurrent, $result['energy']['current']);
    $this->assertSame(3, (int)$state['player_revision']);
  }

  public function successfulEnergyStateProvider(): array
  {
    return [
      'exact authored cost from full' => [50, '2026-09-13 12:00:00', '2026-09-13 13:00:00', 40, '2026-09-13 13:00:00'],
      'elapsed regeneration enables run' => [8, '2026-09-13 12:00:00', '2026-09-13 12:10:00', 0, '2026-09-13 12:10:00'],
      'below-cap fraction preserved' => [40, '2026-09-13 12:00:00', '2026-09-13 12:27:00', 35, '2026-09-13 12:25:00'],
      'reached cap discards capped time' => [48, '2026-09-13 12:00:00', '2026-09-13 13:00:00', 40, '2026-09-13 13:00:00'],
      'over-cap resumes from spend' => [57, '2026-09-01 12:00:00', '2026-09-13 13:00:00', 47, '2026-09-13 13:00:00'],
    ];
  }

  public function testInsufficientEffectiveEnergyDoesNotMaterializeOrMutate(): void
  {
    [$userId] = $this->fixtureAccount('run-insufficient@example.test');
    $this->setPlayerState($userId, 8, '2026-09-13 12:00:00');

    try {
      $this->commandAt($userId, '2026-09-13 12:09:59')->execute($userId, ['region_id' => 'region.the_farm'], 'insufficient-key');
      $this->fail('Expected insufficient Energy.');
    } catch (RunStartException $e) {
      $this->assertSame('insufficient_energy', $e->errorCode);
    }

    $state = $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $this->assertSame(8, (int)$state['energy_current']);
    $this->assertSame('2026-09-13 12:00:00', $state['energy_last_regen_at']);
    $this->assertSame(2, (int)$state['player_revision']);
    $this->assertSame(0, $this->runCount($userId));
    $this->assertSame(0, $this->receiptCount($userId));
  }

  public function testNoActiveSquadEmptySquadAndForeignActiveSquadFailSafely(): void
  {
    $noSquad = $this->createAccount('run-no-squad@example.test');
    $noSquadResponse = $this->httpCommand($noSquad, ['region_id' => 'region.the_farm'], 'no-squad-key');
    $this->assertSame(409, $noSquadResponse['status']);
    $this->assertSame('active_squad_required', $noSquadResponse['body']['error']['code'] ?? null);

    $empty = $this->createAccount('run-empty-squad@example.test');
    $emptySquad = $this->insertSquad($empty, 'Empty');
    $this->setActiveSquad($empty, $emptySquad);
    $emptyResponse = $this->httpCommand($empty, ['region_id' => 'region.the_farm'], 'empty-squad-key');
    $this->assertSame(422, $emptyResponse['status']);
    $this->assertSame('active_squad_empty', $emptyResponse['body']['error']['code'] ?? null);

    $owner = $this->createAccount('run-foreign-active-owner@example.test');
    $other = $this->createAccount('run-foreign-active-other@example.test');
    $foreignSquad = $this->insertSquad($other, 'Secret Squad');
    $this->setActiveSquad($owner, $foreignSquad);
    $foreign = $this->httpCommand($owner, ['region_id' => 'region.the_farm'], 'foreign-squad-key');
    $this->assertSame(500, $foreign['status']);
    $this->assertSame('run_data_integrity_error', $foreign['body']['error']['code'] ?? null);
    $this->assertStringNotContainsString('Secret Squad', json_encode($foreign['body']));

    foreach ([$noSquad, $empty, $owner] as $userId) {
      $this->assertSame(0, $this->runCount($userId));
      $this->assertSame(1, $this->revision($userId));
      $this->assertSame(0, $this->receiptCount($userId));
    }
  }

  /** @dataProvider corruptParticipationProvider */
  public function testCorruptOrIncompleteParticipationFailsWithoutMutation(string $corruption, int $status, string $code): void
  {
    [$userId, $fixture] = $this->fixtureAccount('run-corrupt-' . $corruption . '@example.test');
    $otherId = $this->createAccount('run-corrupt-other-' . $corruption . '@example.test');
    $unitId = (int)$fixture['unit_ids']['bruiser'];
    $this->applyCorruption($corruption, $unitId, $otherId);

    $response = $this->httpCommand($userId, ['region_id' => 'region.the_farm'], 'corruption-key');

    $this->assertSame($status, $response['status'], json_encode($response['body']));
    $this->assertSame($code, $response['body']['error']['code'] ?? null);
    $this->assertStringNotContainsString((string)$unitId, json_encode($response['body']));
    $this->assertSame(0, $this->runCount($userId));
    $this->assertSame(2, $this->revision($userId));
    $this->assertSame(0, $this->receiptCount($userId));
  }

  public function corruptParticipationProvider(): array
  {
    return [
      'foreign unit relationship' => ['foreign_unit', 500, 'run_data_integrity_error'],
      'terminal unit' => ['terminal_unit', 500, 'run_data_integrity_error'],
      'invalid unit type' => ['invalid_unit_type', 500, 'run_data_integrity_error'],
      'invalid kin' => ['invalid_kin', 500, 'run_data_integrity_error'],
      'empty loadout' => ['empty_loadout', 422, 'run_configuration_invalid'],
      'invalid durable ability' => ['invalid_owned_ability', 422, 'run_configuration_invalid'],
      'passive loadout' => ['passive_loadout', 422, 'run_configuration_invalid'],
      'incomplete binding' => ['incomplete_binding', 422, 'run_configuration_invalid'],
      'foreign die' => ['foreign_die', 422, 'run_configuration_invalid'],
      'terminal die' => ['terminal_die', 422, 'run_configuration_invalid'],
      'invalid die profile' => ['invalid_profile', 422, 'run_configuration_invalid'],
    ];
  }

  public function testExistingActiveRunAndUnsupportedRegionFailBeforeSpend(): void
  {
    [$activeUser, $fixture] = $this->fixtureAccount('run-active@example.test');
    $this->pdo?->prepare("INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`, `status`) VALUES (?, 'region.the_farm', ?, 'active')")
      ->execute([$activeUser, (int)$fixture['active_squad_id']]);
    $before = $this->stateSnapshot($activeUser);
    $active = $this->httpCommand($activeUser, ['region_id' => 'region.the_farm'], 'active-run-key');
    $this->assertSame(409, $active['status']);
    $this->assertSame('active_run_exists', $active['body']['error']['code'] ?? null);
    $this->assertSame($before, $this->stateSnapshot($activeUser));
    $this->assertSame(1, $this->runCount($activeUser));
    $this->assertSame(0, $this->receiptCount($activeUser));

    [$unsupportedUser] = $this->fixtureAccount('run-region@example.test');
    $unsupportedBefore = $this->stateSnapshot($unsupportedUser);
    $unsupported = $this->httpCommand($unsupportedUser, ['region_id' => 'region.mountains'], 'unsupported-key');
    $this->assertSame(422, $unsupported['status']);
    $this->assertSame('run_region_unsupported', $unsupported['body']['error']['code'] ?? null);
    $this->assertSame($unsupportedBefore, $this->stateSnapshot($unsupportedUser));
    $this->assertSame(0, $this->runCount($unsupportedUser));
  }

  public function testIdempotencyReplayConflictAndUserScoping(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('run-replay@example.test');
    $command = $this->commandAt($userId, '2026-09-13 13:00:00');
    $first = $command->execute($userId, ['region_id' => 'region.the_farm'], 'shared-run-key');
    $runId = (int)$first['run']['id'];
    $bruiser = (int)$fixture['unit_ids']['bruiser'];
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 7 WHERE `run_id` = ? AND `unit_id` = ?')
      ->execute([$runId, $bruiser]);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 4 WHERE `id` = ?')->execute([$bruiser]);
    $replay = $command->execute($userId, ['region_id' => 'region.the_farm'], 'shared-run-key');
    $this->assertSame($first, $replay);
    $this->assertSame('7', (string)$this->scalar('SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?', [$runId, $bruiser]));
    $this->assertSame(5, (int)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ?', [$runId]));
    $this->assertSame(1, $this->runCount($userId));
    $this->assertSame(40, (int)$this->scalar('SELECT `energy_current` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(3, $this->revision($userId));
    $this->assertSame(1, $this->receiptCount($userId));

    try {
      $command->execute($userId, ['region_id' => 'region.mountains'], 'shared-run-key');
      $this->fail('Expected an idempotency conflict.');
    } catch (IdempotencyConflictException) {
      $this->addToAssertionCount(1);
    }
    try {
      $command->execute($userId, ['region_id' => 'region.the_farm'], 'different-run-key');
      $this->fail('Expected the active run to reject another key.');
    } catch (RunStartException $e) {
      $this->assertSame('active_run_exists', $e->errorCode);
    }
    $this->assertSame(3, $this->revision($userId));

    [$operationUser] = $this->fixtureAccount('run-operation-conflict@example.test');
    (new IdempotencyRequestRepository($this->pdo))->insertFinalized(
      $operationUser,
      'operation-key',
      'create_squad',
      hash('sha256', '{}'),
      ['unrelated' => true],
    );
    $this->expectOperationConflict($operationUser);

    [$otherUser] = $this->fixtureAccount('run-other-key@example.test');
    $other = $this->commandAt($otherUser, '2026-09-13 13:00:00')->execute(
      $otherUser,
      ['region_id' => 'region.the_farm'],
      'shared-run-key',
    );
    $this->assertSame('active', $other['run']['status']);
    $this->assertSame(1, $this->runCount($otherUser));
  }

  public function testGenerationAndPersistenceFailuresRollBackEverything(): void
  {
    foreach (['generation', 'persistence'] as $failure) {
      [$userId] = $this->fixtureAccount('run-rollback-' . $failure . '@example.test');
      $this->setPlayerState($userId, 40, '2026-09-13 12:00:00');
      $before = $this->stateSnapshot($userId);
      $generator = $failure === 'generation'
        ? new class implements RunGraphGenerator {
            public function generate(array $generationDefinition): array { throw new RuntimeException('Injected generation failure.'); }
          }
        : new class implements RunGraphGenerator {
            public function generate(array $generationDefinition): array {
              return ['nodes' => [
                ['node_index' => 0, 'node_type_id' => 'run_node_type.combat', 'encounter_id' => null, 'status' => 'available', 'generated_metadata' => null],
                ['node_index' => 0, 'node_type_id' => 'run_node_type.exit', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => null],
              ], 'edges' => []];
            }
          };

      try {
        $this->commandAt($userId, '2026-09-13 12:27:00', $generator)->execute(
          $userId,
          ['region_id' => 'region.the_farm'],
          'rollback-' . $failure,
        );
        $this->fail('Expected the injected failure.');
      } catch (Throwable $e) {
        $this->assertNotInstanceOf(RunStartException::class, $e);
      }

      $this->assertSame($before, $this->stateSnapshot($userId));
      $this->assertSame(0, $this->runCount($userId));
      $this->assertSame(0, $this->receiptCount($userId));
    }
  }

  public function testUnresolvableParticipatingHpRollsBackWithoutEnergySpend(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('run-stat-rollback@example.test');
    $this->setPlayerState($userId, 40, '2026-09-13 12:00:00');
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 4294967295 WHERE `id` = ?')
      ->execute([(int)$fixture['unit_ids']['bruiser']]);
    $before = $this->stateSnapshot($userId);
    try {
      $this->commandAt($userId, '2026-09-13 12:27:00')->execute($userId, ['region_id' => 'region.the_farm'], 'stat-rollback-key');
      $this->fail('Expected participating HP resolution to fail.');
    } catch (\DiceGoblins\Application\Commands\RunStartIntegrityException) {
      $this->addToAssertionCount(1);
    }
    $this->assertSame($before, $this->stateSnapshot($userId));
    $this->assertSame(0, $this->runCount($userId));
    $this->assertSame(0, $this->receiptCount($userId));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` rus JOIN `runs` r ON r.`id` = rus.`run_id` WHERE r.`user_id` = ?', [$userId]));
  }

  private function applyCorruption(string $corruption, int $unitId, int $otherUserId): void
  {
    if ($corruption === 'foreign_unit') {
      $this->pdo?->prepare('UPDATE `unit_instances` SET `user_id` = ? WHERE `id` = ?')->execute([$otherUserId, $unitId]);
    } elseif ($corruption === 'terminal_unit') {
      $this->pdo?->prepare("UPDATE `unit_instances` SET `lifecycle_status` = 'retired' WHERE `id` = ?")->execute([$unitId]);
    } elseif ($corruption === 'invalid_unit_type') {
      $this->pdo?->prepare("UPDATE `unit_instances` SET `unit_type_id` = 'unit_type.missing' WHERE `id` = ?")->execute([$unitId]);
    } elseif ($corruption === 'invalid_kin') {
      $this->pdo?->prepare("UPDATE `unit_instances` SET `kin_id` = 'kin.missing' WHERE `id` = ?")->execute([$unitId]);
    } elseif ($corruption === 'empty_loadout') {
      $this->pdo?->prepare('DELETE FROM `unit_ability_loadout` WHERE `unit_id` = ?')->execute([$unitId]);
    } elseif ($corruption === 'invalid_owned_ability') {
      $this->pdo?->prepare("INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, 'ability.missing')")->execute([$unitId]);
    } elseif ($corruption === 'passive_loadout') {
      $this->pdo?->prepare("UPDATE `unit_ability_loadout` SET `ability_id` = 'ability.thick_hide' WHERE `unit_id` = ? AND `equip_order` = 0")->execute([$unitId]);
    } elseif ($corruption === 'incomplete_binding') {
      $this->pdo?->prepare('DELETE FROM `unit_ability_dice` WHERE `unit_id` = ? LIMIT 1')->execute([$unitId]);
    } else {
      $dieId = (int)$this->scalar('SELECT `dice_instance_id` FROM `unit_ability_dice` WHERE `unit_id` = ? ORDER BY `slot_index` LIMIT 1', [$unitId]);
      if ($corruption === 'foreign_die') {
        $this->pdo?->prepare('UPDATE `dice_instances` SET `user_id` = ? WHERE `id` = ?')->execute([$otherUserId, $dieId]);
      } elseif ($corruption === 'terminal_die') {
        $this->pdo?->prepare("UPDATE `dice_instances` SET `lifecycle_status` = 'destroyed' WHERE `id` = ?")->execute([$dieId]);
      } elseif ($corruption === 'invalid_profile') {
        $this->pdo?->prepare("UPDATE `dice_instances` SET `profile_id` = 'dice_profile.missing' WHERE `id` = ?")->execute([$dieId]);
      }
    }
  }

  private function expectOperationConflict(int $userId): void
  {
    try {
      $this->commandAt($userId, '2026-09-13 13:00:00')->execute(
        $userId,
        ['region_id' => 'region.the_farm'],
        'operation-key',
      );
      $this->fail('Expected an operation conflict.');
    } catch (IdempotencyConflictException) {
      $this->addToAssertionCount(1);
    }
    $this->assertSame(0, $this->runCount($userId));
    $this->assertSame(2, $this->revision($userId));
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(string $email): array
  {
    $userId = $this->createAccount($email);
    $content = $this->content();
    $fixture = (new ProvisionWarbandFixtureCommand(
      $this->pdo,
      new WarbandFixtureRepository($this->pdo),
      $content,
    ))->execute($userId);
    return [$userId, $fixture];
  }

  private function createAccount(string $email): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $id = $services['accountCreationService']->createLocal(
      $email,
      password_hash('test-password', PASSWORD_DEFAULT),
      'Runner',
    );
    $this->trackUserId($id);
    return $id;
  }

  private function commandAt(int $userId, string $now, ?RunGraphGenerator $generator = null): StartRunCommand
  {
    $content = $this->content();
    $playerState = new PlayerStateRepository($this->pdo);
    $squads = new SquadRepository($this->pdo);
    $units = new WarbandUnitRepository($this->pdo);
    $dice = new WarbandDiceRepository($this->pdo);
    $unitDetails = new UnitDetailQuery($units, $content);
    $unitConfiguration = new UnitConfigurationSupport($playerState, $dice, $unitDetails, $content);
    $instant = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    $clock = new class($instant) implements Clock {
      public function __construct(private readonly DateTimeImmutable $instant) {}
      public function now(): DateTimeImmutable { return $this->instant; }
    };
    return new StartRunCommand(
      $this->pdo,
      $playerState,
      $squads,
      new RunPersistenceRepository($this->pdo),
      new IdempotencyRequestRepository($this->pdo),
      $content,
      $generator ?? new FixedGraphRunGenerator(),
      new RunParticipationValidator($content, $unitConfiguration),
      new EnergySpendCalculator(),
      $clock,
    );
  }

  /** @param array<string,mixed> $body @return array{status:int,body:array<string,mixed>} */
  private function httpCommand(int $userId, array $body, ?string $key): array
  {
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'run-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'run-csrf';
    if ($key === null) unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); else $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    $this->setJsonBody($body);
    return $this->invoke(fn() => (new RunController())->start());
  }

  private function content(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/content');
  }

  private function setPlayerState(int $userId, int $energy, string $anchor): void
  {
    $this->pdo?->prepare('UPDATE `user_state` SET `energy_current` = ?, `energy_last_regen_at` = ? WHERE `user_id` = ?')
      ->execute([$energy, $anchor, $userId]);
  }

  private function insertSquad(int $userId, string $name): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt?->execute([$userId, $name]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function setActiveSquad(int $userId, int $squadId): void
  {
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$squadId, $userId]);
  }

  /** @param list<int|string> $params @return array<string,mixed> */
  private function row(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
  }

  /** @param list<int|string> $params @return array<int,array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  /** @return array<string,mixed> */
  private function stateSnapshot(int $userId): array
  {
    return $this->row('SELECT `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }

  private function revision(int $userId): int
  {
    return (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }

  private function runCount(int $userId): int
  {
    return (int)$this->scalar('SELECT COUNT(*) FROM `runs` WHERE `user_id` = ?', [$userId]);
  }

  private function receiptCount(int $userId): int
  {
    return (int)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]);
  }
}
