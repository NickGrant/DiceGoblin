<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

final class BossNodeResolutionControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  /** @dataProvider mountainsOwnershipProvider */
  public function testFarmBossVictoryAppliesXpMountainsAndUnlocksOnlyExitExactlyOnce(bool $preOwned): void
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal(
      'boss-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT), 'Boss Fighter');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $services['contentRegistry']))->execute($userId);
    $participantIds = array_map('intval', array_values($fixture['unit_ids']));
    sort($participantIds, SORT_NUMERIC);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `level` = 1, `xp` = 90 WHERE `id` = ?')->execute([$participantIds[0]]);
    if ($preOwned) {
      $this->pdo?->prepare("INSERT INTO `user_unlocks` (`user_id`, `unlock_id`, `granted_at`) VALUES (?, 'unlock.region.mountains', UTC_TIMESTAMP())")
        ->execute([$userId]);
    }
    $run = $services['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], 'boss-start-key');
    $runId = (int)$run['run']['id'];
    $nodes = $this->rows('SELECT `id`, `encounter_id`, `event_id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`', [$runId]);
    $bossId = (int)$nodes[3]['id']; $exitId = (int)$nodes[4]['id'];
    $this->assertSame(['encounter.the_farm_mud_boss_1', 'event.farm_boss_completed'],
      [$nodes[3]['encounter_id'], $nodes[3]['event_id']]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = CASE WHEN `id` = ? THEN 'available' ELSE `status` END WHERE `run_id` = ?")
      ->execute([$bossId, $runId]);
    $unitsBefore = $this->rows('SELECT ui.`id`, ui.`level`, ui.`xp`, rus.`current_hp` FROM `unit_instances` ui
      JOIN `run_unit_state` rus ON rus.`unit_id` = ui.`id` AND rus.`run_id` = ?
      WHERE ui.`user_id` = ? ORDER BY ui.`id`', [$runId, $userId]);
    $revision = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);

    $first = $this->resolve($userId, $runId, $bossId, 'boss-resolve-key');
    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $data = $first['body']['data'];
    $this->assertSame(['boss', 'victory', 'active'], [$data['resolution_type'], $data['battle']['outcome'], $data['run']['status']]);
    $this->assertSame([(string)$exitId], $data['newly_available_node_ids']);
    $this->assertSame($revision + 1, $data['player_revision']);
    $this->assertSame('region.mountains', $data['rewards']['mountains']['region_id']);
    $this->assertSame($preOwned ? 'already_owned' : 'granted', $data['rewards']['mountains']['outcome']);
    $this->assertSame(array_map('strval', array_column($unitsBefore, 'id')),
      array_column($data['rewards']['unit_xp'], 'unit_id'));
    $this->assertSame(array_fill(0, count($unitsBefore), 16), array_column($data['rewards']['unit_xp'], 'amount'));
    $firstTransition = $data['rewards']['unit_xp'][0];
    $this->assertSame([1, 90, 2, 6], [$firstTransition['level_before'], $firstTransition['xp_before'],
      $firstTransition['level_after'], $firstTransition['xp_after']]);
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `run_node_id` = ?', [$runId, $bossId]));
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.mountains'", [$userId]));
    $this->assertSame('applied', $this->scalar("SELECT `status` FROM `resolved_events` WHERE `user_id` = ? AND `event_id` = 'event.farm_boss_completed'", [$userId]));
    $this->assertSame(['completed', 'available'], array_column($this->rows(
      'SELECT `status` FROM `run_nodes` WHERE `id` IN (?, ?) ORDER BY `node_index`', [$bossId, $exitId]), 'status'));
    foreach (['event.farm_boss_completed', 'reward_definition.', 'unlock.region.mountains', 'probability', 'roll', 'run_node:'] as $private) {
      $this->assertStringNotContainsString($private, json_encode($data, JSON_THROW_ON_ERROR));
    }

    $snapshot = $this->rows('SELECT ui.`id`, ui.`level`, ui.`xp`, rus.`current_hp` FROM `unit_instances` ui
      JOIN `run_unit_state` rus ON rus.`unit_id` = ui.`id` AND rus.`run_id` = ? WHERE ui.`user_id` = ? ORDER BY ui.`id`', [$runId, $userId]);
    $replay = $this->resolve($userId, $runId, $bossId, 'boss-resolve-key');
    $this->assertSame($first['body'], $replay['body']);
    $this->assertSame($snapshot, $this->rows('SELECT ui.`id`, ui.`level`, ui.`xp`, rus.`current_hp` FROM `unit_instances` ui
      JOIN `run_unit_state` rus ON rus.`unit_id` = ui.`id` AND rus.`run_id` = ? WHERE ui.`user_id` = ? ORDER BY ui.`id`', [$runId, $userId]));
    $different = $this->resolve($userId, $runId, $bossId, 'boss-other-key');
    $this->assertSame([409, 'run_node_already_resolved'], [$different['status'], $different['body']['error']['code'] ?? null]);
  }

  public function mountainsOwnershipProvider(): array
  {
    return ['new unlock' => [false], 'already owned' => [true]];
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function resolve(int $userId, int $runId, int $nodeId, string $key): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'boss-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'boss-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key; $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '';
    return $this->invoke(fn() => (new RunController())->resolveNode((string)$runId, (string)$nodeId));
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  { $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []; }
}
