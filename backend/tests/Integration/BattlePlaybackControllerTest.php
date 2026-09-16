<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Controllers\BattlePlaybackController;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\VnextBattleFixture;
use PDO;

final class BattlePlaybackControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testAuthenticationCanonicalIdAndOwnershipAreNonDisclosing(): void
  {
    [$owner, $unitId, $runId, $nodeId, $battleId] = $this->scenario('active', 'victory');
    [$other] = $this->scenario('failed', 'defeat');
    $controller = new BattlePlaybackController();

    $unauthorized = $this->invoke(fn() => $controller->playback((string)$battleId));
    $this->assertSame([401, 'unauthorized'], [$unauthorized['status'], $unauthorized['body']['error']['code'] ?? null]);
    $foreign = $this->read($other, (string)$battleId);
    $missing = $this->read($owner, '999999999');
    $malformed = $this->read($owner, '01');
    foreach ([$foreign, $missing, $malformed] as $response) {
      $this->assertSame([404, 'battle_not_found', 'Battle is unavailable.'], [
        $response['status'], $response['body']['error']['code'] ?? null, $response['body']['error']['message'] ?? null,
      ]);
    }
    $this->assertGreaterThan(0, $unitId + $runId + $nodeId);
  }

  public function testActiveBattleProjectionUsesImmutableEvidenceAndReadsMutateNothing(): void
  {
    [$userId, $unitId, $runId, , $battleId] = $this->scenario('active', 'victory');
    $stored = (new BattlePersistenceRepository($this->pdo))->findById($battleId);
    $this->pdo?->prepare("UPDATE `unit_instances` SET `display_name` = 'Changed now', `unit_type_id` = 'unit_type.scout' WHERE `id` = ?")
      ->execute([$unitId]);
    $this->pdo?->prepare('UPDATE `run_unit_state` SET `current_hp` = 3 WHERE `run_id` = ? AND `unit_id` = ?')
      ->execute([$runId, $unitId]);
    $before = $this->snapshot($userId, $runId);

    $response = $this->read($userId, (string)$battleId);

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $response['body']['data']; $battle = $data['battle'];
    $this->assertSame([(string)$battleId, (string)$runId, 1, 1, 'victory'], [
      $battle['id'], $battle['run_id'], $battle['engine_version'], $battle['playback_version'], $battle['outcome'],
    ]);
    $this->assertSame($stored?->battle->result['ending_round'], $battle['ending_round']);
    $this->assertSame($stored?->battle->result['ending_tick'], $battle['ending_tick']);
    $persistedResult = json_decode((string)$this->scalar('SELECT `result_json` FROM `battles` WHERE `id` = ?', [$battleId]), true);
    $this->assertEquals($persistedResult['events'] ?? null, $battle['events']);
    $direct = (new \DiceGoblins\Application\Queries\BattlePlaybackQuery(
      new BattlePersistenceRepository($this->pdo), new \DiceGoblins\Repositories\PlayerStateRepository($this->pdo),
    ))->execute($userId, $battleId);
    $directJson = json_encode($direct, JSON_THROW_ON_ERROR);
    $this->assertStringContainsString('"type":"round_started","facts":{}', $directJson);
    $player = array_values(array_filter($battle['participants'], static fn(array $p): bool => $p['side'] === 'player'))[0];
    $this->assertSame(['Bash', 'unit.bruiser', 'unit_type.bruiser', ['x' => 1, 'y' => 1], 20, 20], [
      $player['display_name'], $player['art_key'], $player['unit_type_id'], $player['position'], $player['initial_hp'], $player['max_hp'],
    ]);
    $storedTerminal = [];
    foreach ($stored?->battle->result['combatants'] ?? [] as $terminal) $storedTerminal[$terminal['key']] = $terminal;
    $this->assertSame($storedTerminal['mudwrestler']['current_hp'] ?? null,
      $battle['participants'][1]['terminal_hp'] ?? null);
    $encoded = json_encode($response['body'], JSON_THROW_ON_ERROR);
    foreach (['seed', 'input_snapshot', 'active_abilities', 'passive_abilities', 'power_ratio', '"stats"', 'precision'] as $hidden) {
      $this->assertStringNotContainsString($hidden, $encoded);
    }
    $this->assertSame(4, $data['player_revision']);
    $this->assertSame($before, $this->snapshot($userId, $runId));
  }

  public function testFailedAndLaterAbandonedBattlesRemainReadable(): void
  {
    [$failedUser, , $failedRun, , $defeatBattle] = $this->scenario('failed', 'defeat');
    [$stalemateUser, , $stalemateRun, , $stalemateBattle] = $this->scenario('failed', 'stalemate');
    [$abandonedUser, , $abandonedRun, , $victoryBattle] = $this->scenario('active', 'victory');
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = UTC_TIMESTAMP() WHERE `id` = ?")
      ->execute([$abandonedRun]);

    $failed = $this->read($failedUser, (string)$defeatBattle);
    $stalemate = $this->read($stalemateUser, (string)$stalemateBattle);
    $abandoned = $this->read($abandonedUser, (string)$victoryBattle);

    $this->assertSame([200, 'defeat'], [$failed['status'], $failed['body']['data']['battle']['outcome'] ?? null]);
    $this->assertSame([200, 'stalemate'], [$stalemate['status'], $stalemate['body']['data']['battle']['outcome'] ?? null]);
    $this->assertSame([200, 'victory'], [$abandoned['status'], $abandoned['body']['data']['battle']['outcome'] ?? null]);
    $this->assertSame('failed', $this->scalar('SELECT `status` FROM `runs` WHERE `id` = ?', [$failedRun]));
    $this->assertSame('failed', $this->scalar('SELECT `status` FROM `runs` WHERE `id` = ?', [$stalemateRun]));
    $this->assertSame('abandoned', $this->scalar('SELECT `status` FROM `runs` WHERE `id` = ?', [$abandonedRun]));
  }

  public function testCorruptStoredPayloadFailsThroughCodecIntegrityBoundary(): void
  {
    [$userId, , , , $battleId] = $this->scenario('active', 'victory');
    $this->pdo?->prepare("UPDATE `battles` SET `result_json` = JSON_SET(`result_json`, '$.engine_version', 2) WHERE `id` = ?")
      ->execute([$battleId]);

    $response = $this->read($userId, (string)$battleId);

    $this->assertSame([500, 'battle_data_integrity_error'], [
      $response['status'], $response['body']['error']['code'] ?? null,
    ]);
  }

  /** @return array{int,int,int,int,int} */
  private function scenario(string $runStatus, string $outcome): array
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)');
    $stmt?->execute(['Playback owner ' . bin2hex(random_bytes(4))]);
    $userId = (int)$this->pdo?->lastInsertId(); $this->trackUserId($userId);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`, `player_revision`) VALUES (?, 17, 4)')->execute([$userId]);
    $this->pdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`) VALUES (?, ?, ?, ?)')
      ->execute([$userId, 'unit_type.bruiser', 'kin.gutter', 'Mutable Bash']);
    $unitId = (int)$this->pdo?->lastInsertId();
    $ended = $runStatus === 'active' ? null : '2026-09-15 12:00:00';
    $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `status`, `ended_at`) VALUES (?, ?, ?, ?)')
      ->execute([$userId, 'region.the_farm', $runStatus, $ended]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare("INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type_id`, `encounter_id`, `status`, `completed_at`, `generated_metadata`)
      VALUES (?, 0, 'run_node_type.combat', 'encounter.the_farm_mud_combat_1', 'completed', '2026-09-15 12:00:00', JSON_OBJECT('position', JSON_OBJECT('column', 0, 'row', 1)))")
      ->execute([$runId]);
    $nodeId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, 20)')->execute([$runId, $unitId]);
    $battle = $outcome === 'victory' ? VnextBattleFixture::battle($unitId) : $this->terminalBattle($unitId, $outcome);
    $battleId = (new BattlePersistenceRepository($this->pdo))->insertFinalized($runId, $nodeId, $battle);
    return [$userId, $unitId, $runId, $nodeId, $battleId];
  }

  private function terminalBattle(int $unitId, string $outcome): FinalizedBattle
  {
    $input = VnextBattleFixture::input(); $normalized = new CombatInput($input); $terminal = [];
    foreach ($normalized->combatants as $key => $combatant) {
      $hp = $outcome === 'defeat' && $combatant['side'] === 'player' ? 0 : $combatant['current_hp'];
      $terminal[] = ['key' => $key, 'side' => $combatant['side'], 'current_hp' => $hp,
        'max_hp' => $combatant['max_hp'], 'is_defeated' => $hp === 0, 'statuses' => []];
    }
    $result = ['engine_version' => 1, 'playback_version' => 1, 'outcome' => $outcome, 'ending_round' => 1,
      'ending_tick' => 1, 'combatants' => $terminal, 'events' => [
        ['sequence' => 0, 'type' => 'battle_started', 'round' => 0, 'tick' => 0,
          'facts' => ['combatant_keys' => array_keys($normalized->combatants)]],
        ['sequence' => 1, 'type' => 'battle_ended', 'round' => 1, 'tick' => 1, 'facts' => ['outcome' => $outcome]],
      ]];
    return new FinalizedBattle(1, 1, $input, VnextBattleFixture::manifest($unitId), $result);
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function read(int $userId, string $battleId): array
  {
    $_SESSION['user_id'] = $userId;
    return $this->invoke(fn() => (new BattlePlaybackController())->playback($battleId));
  }

  /** @return array<string,mixed> */
  private function snapshot(int $userId, int $runId): array
  {
    return [
      'state' => $this->rows('SELECT * FROM `user_state` WHERE `user_id` = ?', [$userId]),
      'run' => $this->rows('SELECT * FROM `runs` WHERE `id` = ?', [$runId]),
      'nodes' => $this->rows('SELECT * FROM `run_nodes` WHERE `run_id` = ?', [$runId]),
      'hp' => $this->rows('SELECT * FROM `run_unit_state` WHERE `run_id` = ?', [$runId]),
      'battles' => $this->rows('SELECT * FROM `battles` WHERE `run_id` = ?', [$runId]),
      'receipts' => $this->rows('SELECT * FROM `idempotency_requests` WHERE `user_id` = ?', [$userId]),
    ];
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
