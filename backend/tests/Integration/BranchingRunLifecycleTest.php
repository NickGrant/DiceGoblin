<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\BranchingRunDefinitionFixture;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

final class BranchingRunLifecycleTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  /** @dataProvider routeChoices */
  public function testPersistedExplorationFrontierIsReplaySafeAndReconnectSafe(int $choice, bool $deadEnd): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, null, $this->victoryResolver());
    $userId = $services['accountCreationService']->createLocal(
      'branch-' . $choice . '-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Pathfinder');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))
      ->execute($userId);
    $graph = (new FixedGraphRunGenerator())->generate(BranchingRunDefinitionFixture::definition($content, $deadEnd));
    $repository = new RunPersistenceRepository($this->pdo);
    $runId = $repository->createRun($userId, 'region.the_farm', (int)$fixture['active_squad_id']);
    $ids = $repository->insertNodes($runId, $graph['nodes']);
    $repository->insertEdges($runId, $graph['edges'], $ids);
    $participants = [];
    foreach ($this->rows('SELECT ui.`id`, ui.`unit_type_id`, ui.`level` FROM `squad_units` su
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE su.`squad_id` = ? AND ui.`user_id` = ? ORDER BY su.`position`',
      [(int)$fixture['active_squad_id'], $userId]) as $unit) {
      $type = $content->unitType((string)$unit['unit_type_id']);
      $maximum = (new BaseLevelStatResolver())->resolve(
        $type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
      $participants[] = ['unit_id' => (int)$unit['id'], 'current_hp' => $maximum];
    }
    $repository->insertParticipatingUnits($runId, $participants);

    $query = $services['currentRunQuery'];
    $command = $services['resolveRunNodeCommand'];
    $this->assertSame([(string)$ids[0]], $query->execute($userId)['run']['available_node_ids']);
    $this->assertUnavailable($command, $userId, $runId, $ids[3], 'early-rest');
    $combat = $command->execute($userId, $runId, $ids[0], 'branch-start-' . $choice);
    $this->assertSame([(string)$ids[1], (string)$ids[2]], $combat['newly_available_node_ids']);
    $this->assertSame([(string)$ids[1], (string)$ids[2]], $query->execute($userId)['run']['available_node_ids']);
    $this->assertSame([(string)$ids[1], (string)$ids[2]], $query->execute($userId)['run']['available_node_ids']);

    $otherUserId = $services['accountCreationService']->createLocal(
      'branch-other-' . $choice . '-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Intruder');
    $this->trackUserId($otherUserId);
    try {
      $command->execute($otherUserId, $runId, $ids[$choice], 'foreign-branch-' . $choice);
      $this->fail('Foreign run node should not be accessible.');
    } catch (RunNodeResolutionException $e) {
      $this->assertSame(404, $e->httpStatus);
    }

    $revision = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    $chosen = $command->execute($userId, $runId, $ids[$choice], 'choose-branch-' . $choice);
    $this->assertSame($deadEnd && $choice === 2 ? [] : [(string)$ids[3]], $chosen['newly_available_node_ids']);
    $this->assertSame($revision + 1, $chosen['player_revision']);
    $this->assertSame($chosen, $command->execute($userId, $runId, $ids[$choice], 'choose-branch-' . $choice));
    $other = $choice === 1 ? 2 : 1;
    $this->assertUnavailable($command, $userId, $runId, $ids[$choice], 'stale-branch-' . $choice);
    $this->assertSame($revision + 1,
      (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(['completed', $choice === 1 ? 'completed' : 'available',
      $choice === 2 ? 'completed' : 'available', $deadEnd && $choice === 2 ? 'locked' : 'available', 'locked', 'locked'],
      array_column($this->rows('SELECT `status` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]), 'status'));
    $expectedFrontier = $choice === 1 ? [(string)$ids[2], (string)$ids[3]]
      : ($deadEnd ? [(string)$ids[1]] : [(string)$ids[1], (string)$ids[3]]);
    $this->assertSame($expectedFrontier, $query->execute($userId)['run']['available_node_ids']);
    $this->assertSame('active', $query->execute($userId)['run']['status']);

    $reloadedPdo = new PDO((string)getenv('TEST_DB_DSN'), (string)getenv('TEST_DB_USER'),
      (string)getenv('TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $reloaded = (new CurrentRunQuery(new RunPersistenceRepository($reloadedPdo),
      new PlayerStateRepository($reloadedPdo), $content))->execute($userId)['run'];
    $this->assertSame($expectedFrontier, $reloaded['available_node_ids']);

    $otherResult = $command->execute($userId, $runId, $ids[$other], 'other-branch-' . $choice);
    $this->assertSame($deadEnd && $choice === 2 ? [(string)$ids[3]] : [], $otherResult['newly_available_node_ids']);
    $this->assertSame([(string)$ids[3]], $query->execute($userId)['run']['available_node_ids']);
    $this->assertSame('available', $this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$ids[3]]));

    $command->execute($userId, $runId, $ids[3], 'branch-rest-' . $choice,
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
    $command->execute($userId, $runId, $ids[4], 'branch-boss-' . $choice);
    $exit = $command->execute($userId, $runId, $ids[5], 'branch-exit-' . $choice);
    $this->assertSame(['completed', []], [$exit['run']['status'], $exit['newly_available_node_ids']]);
    $this->assertNull($query->execute($userId)['run']);
    $this->assertUnavailable($command, $userId, $runId, $ids[$other], 'terminal-branch-' . $choice);
  }

  public function routeChoices(): array { return [
    'first route with convergence' => [1, false], 'second route with convergence' => [2, false],
    'dead end before continuing' => [2, true],
  ]; }

  private function assertUnavailable(mixed $command, int $userId, int $runId, int $nodeId, string $key): void
  {
    try {
      $command->execute($userId, $runId, $nodeId, $key);
      $this->fail('Unreachable node was resolved.');
    } catch (RunNodeResolutionException $e) {
      $this->assertSame(409, $e->httpStatus);
    }
  }

  private function victoryResolver(): CombatResolver
  {
    return new class implements CombatResolver {
      public function resolve(CombatInput $input): CombatResult {
        $terminal = [];
        foreach ($input->combatants as $key => $unit) {
          $hp = $unit['side'] === 'enemy' ? 0 : $unit['current_hp'];
          $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $hp, 'max_hp' => $unit['max_hp'],
            'is_defeated' => $hp === 0, 'statuses' => $unit['statuses']];
        }
        $events = new PlaybackRecorder();
        $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($input->combatants)]);
        $events->add('battle_ended', 1, 1, ['outcome' => 'victory']);
        return new CombatResult('victory', 1, 1, $terminal, $events->events());
      }
    };
  }

  /** @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
