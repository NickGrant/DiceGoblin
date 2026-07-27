<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\ChaosEncounterController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

final class ChaosEncounterControllerIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run chaos encounter integration tests.';
  }

  public function testGenerateIsIdempotentAndRerollIsSingleUse(): void
  {
    $userId = $this->insertUser('chaos_encounter', 'Chaos Encounter User');
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId, 424242, 'active');
    $nodeId = $this->insertRunNode($runId, 'chaos', 'available');

    $this->authenticate($userId);
    $first = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$nodeId));
    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $firstData = $this->assertSuccess($first);
    $firstResult = is_array($firstData['chaos_result'] ?? null) ? $firstData['chaos_result'] : [];
    $this->assertSame('generated', (string)($firstResult['status'] ?? ''));
    $this->assertCount(3, is_array($firstResult['reels'] ?? null) ? $firstResult['reels'] : []);
    $this->assertGreaterThan(1.0, (float)($firstResult['reward_multiplier'] ?? 0));
    $this->assertSame(true, (bool)($firstResult['manipulation']['available'] ?? false));

    $this->authenticate($userId);
    $second = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$nodeId));
    $this->assertSame(200, $second['status'], json_encode($second['body']));
    $secondResult = $this->assertSuccess($second)['chaos_result'] ?? null;
    $this->assertSame($firstResult, $secondResult, 'Generate should return the persisted result instead of rerolling.');

    $originalFirstReel = (string)($firstResult['reels'][0]['symbol'] ?? '');
    $this->authenticate($userId);
    $this->setJsonBody(['reel_index' => 0]);
    $reroll = $this->invoke(fn() => (new ChaosEncounterController())->reroll((string)$runId, (string)$nodeId));
    $this->assertSame(200, $reroll['status'], json_encode($reroll['body']));
    $rerollResult = $this->assertSuccess($reroll)['chaos_result'] ?? [];
    $this->assertIsArray($rerollResult);
    $this->assertSame('manipulated', (string)($rerollResult['status'] ?? ''));
    $this->assertSame(false, (bool)($rerollResult['manipulation']['available'] ?? true));
    $this->assertSame(0, (int)($rerollResult['manipulation']['rerolled_reel_index'] ?? -1));
    $this->assertNotSame($originalFirstReel, (string)($rerollResult['reels'][0]['symbol'] ?? ''));

    $stored = (string)$this->scalar('SELECT `reels_json` FROM `chaos_encounter_results` WHERE `node_id` = ?', [$nodeId]);
    $storedReels = json_decode($stored, true);
    $this->assertSame($rerollResult['reels'], $storedReels);

    $this->authenticate($userId);
    $this->setJsonBody(['reel_index' => 1]);
    $secondReroll = $this->invoke(fn() => (new ChaosEncounterController())->reroll((string)$runId, (string)$nodeId));
    $this->assertSame(409, $secondReroll['status'], json_encode($secondReroll['body']));
    $this->assertSame('chaos_reroll_spent', (string)($secondReroll['body']['error']['code'] ?? ''));
  }

  public function testReelCatalogMeetsLaunchBreadthTarget(): void
  {
    $catalog = (new \DiceGoblins\Services\ChaosEncounterService($this->pdo))->reelCatalog();

    $this->assertCount(3, $catalog);
    foreach ([0 => 'enemy_family', 1 => 'encounter_shape', 2 => 'rule_reward'] as $index => $reelName) {
      $pool = $catalog[$index] ?? [];
      $symbols = [];

      $this->assertGreaterThanOrEqual(10, count($pool), "{$reelName} should have at least ten entries.");
      foreach ($pool as $entry) {
        $symbol = (string)($entry['symbol'] ?? '');
        $this->assertNotSame('', $symbol);
        $this->assertNotContains($symbol, $symbols);
        $this->assertNotSame('', (string)($entry['label'] ?? ''));
        $this->assertNotSame('', (string)($entry['effect'] ?? ''));
        $this->assertGreaterThan(0, (int)($entry['weight'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int)($entry['risk'] ?? 0));
        $symbols[] = $symbol;
      }
    }
  }

  public function testGenerateRejectsWrongOwnerAndInvalidNodeTypes(): void
  {
    $ownerId = $this->insertUser('chaos_owner', 'Chaos Owner');
    $otherUserId = $this->insertUser('chaos_other', 'Chaos Other');
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($ownerId, $regionId, 989898, 'active');
    $exitNodeId = $this->insertRunNode($runId, 'exit', 'available');
    $hazardNodeId = $this->insertRunNode($runId, 'hazard', 'available');

    $this->authenticate($otherUserId);
    $wrongOwner = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$exitNodeId));
    $this->assertSame(404, $wrongOwner['status'], json_encode($wrongOwner['body']));
    $this->assertSame('run_node_not_found', (string)($wrongOwner['body']['error']['code'] ?? ''));

    $this->authenticate($ownerId);
    $invalid = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$exitNodeId));
    $this->assertSame(409, $invalid['status'], json_encode($invalid['body']));
    $this->assertSame('invalid_chaos_node', (string)($invalid['body']['error']['code'] ?? ''));

    $this->authenticate($ownerId);
    $invalidHazard = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$hazardNodeId));
    $this->assertSame(409, $invalidHazard['status'], json_encode($invalidHazard['body']));
    $this->assertSame('invalid_chaos_node', (string)($invalidHazard['body']['error']['code'] ?? ''));
  }

  public function testFinalizeLocksReelsThenResolveCreatesClaimableBattle(): void
  {
    $userId = $this->insertUser('chaos_finalize', 'Chaos Finalize User');
    $this->setSoftCurrency($userId, 10);
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $unitTypeId = $this->pickUnitTypeId();
    $runId = $this->insertRun($userId, $regionId, 515151, 'active');
    $nodeId = $this->insertRunNode($runId, 'chaos', 'available');
    $nextNodeId = $this->insertRunNode($runId, 'combat', 'locked');
    $this->insertRunEdge($runId, $nodeId, $nextNodeId);
    $unitId = $this->insertUnit($userId, $unitTypeId);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 20);

    $this->authenticate($userId);
    $generate = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$nodeId));
    $this->assertSame(200, $generate['status'], json_encode($generate['body']));
    $generatedResult = $this->assertSuccess($generate)['chaos_result'] ?? [];
    $this->assertIsArray($generatedResult);
    $this->assertSame('generated', (string)($generatedResult['status'] ?? ''));

    $this->authenticate($userId);
    $firstFinalize = $this->invoke(fn() => (new ChaosEncounterController())->finalize((string)$runId, (string)$nodeId));
    $this->assertSame(200, $firstFinalize['status'], json_encode($firstFinalize['body']));
    $firstData = $this->assertSuccess($firstFinalize);
    $firstRewards = is_array($firstData['rewards'] ?? null) ? $firstData['rewards'] : [];

    $this->assertSame('confirmed', (string)($firstData['chaos_result']['status'] ?? ''));
    $this->assertSame((float)($generatedResult['reward_multiplier'] ?? 0), (float)($firstRewards['reward_multiplier'] ?? -1));
    $this->assertSame([], $firstData['next']['unlocked_node_ids'] ?? []);
    $this->assertSame('available', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nodeId]));
    $this->assertSame('locked', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nextNodeId]));
    $this->assertSame('10', (string)$this->scalar('SELECT `currency_soft` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertNotSame('', (string)$this->scalar('SELECT `encounter_template_id` FROM `run_nodes` WHERE `id` = ?', [$nodeId]));
    $this->assertNotSame('', (string)$this->scalar('SELECT `finalized_rewards_json` FROM `chaos_encounter_results` WHERE `node_id` = ?', [$nodeId]));

    $this->authenticate($userId);
    $resolve = $this->invoke(fn() => (new RunNodeController())->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $resolve['status'], json_encode($resolve['body']));
    $resolveData = $this->assertSuccess($resolve);
    $battleId = (int)($resolveData['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $this->assertSame('chaos', (string)($resolveData['battle']['log']['meta']['node_type'] ?? ''));
    $this->assertIsArray($resolveData['battle']['log']['meta']['chaos'] ?? null);
    $this->assertContains('battle_start', array_map(
      static fn($event): string => is_array($event) ? (string)($event['type'] ?? '') : '',
      is_array($resolveData['battle']['log']['events'] ?? null) ? $resolveData['battle']['log']['events'] : []
    ));

    $storedRewards = json_decode((string)$this->scalar('SELECT `rewards_json` FROM `battle_rewards` WHERE `battle_id` = ?', [$battleId]), true);
    $this->assertIsArray($storedRewards);
    $this->assertIsArray($storedRewards['chaos_bonus'] ?? null);

    $this->authenticate($userId);
    $claim = $this->invoke(fn() => (new BattleController())->claimBattle((string)$battleId));
    $this->assertSame(200, $claim['status'], json_encode($claim['body']));
    $claimData = $this->assertSuccess($claim);

    if ((string)($resolveData['battle']['outcome'] ?? '') === 'victory') {
      $this->assertSame('cleared', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nodeId]));
      $this->assertSame('available', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nextNodeId]));
    }
    $this->assertGreaterThan(10, (int)$this->scalar('SELECT `currency_soft` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame('claimed', (string)($claimData['status'] ?? 'claimed'));
  }

  public function testFinalizeHonorsCrossBiomeFamilyAndRewardReels(): void
  {
    $userId = $this->insertUser('chaos_cross_family', 'Chaos Cross Family User');
    $swampsRegionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1", []);
    $this->assertGreaterThan(0, $swampsRegionId);

    $runId = $this->insertRun($userId, $swampsRegionId, 626262, 'active');
    $nodeId = $this->insertRunNode($runId, 'chaos', 'available');
    $reels = [[
      'reel_index' => 0,
      'reel' => 'enemy_family',
      'symbol' => 'pigs',
      'label' => 'Pigs',
      'weight' => 30,
      'risk' => 1,
      'effect' => 'Pig-family pressure.',
    ], [
      'reel_index' => 1,
      'reel' => 'encounter_shape',
      'symbol' => 'horde',
      'label' => 'Horde',
      'weight' => 30,
      'risk' => 2,
      'effect' => 'More bodies than usual.',
    ], [
      'reel_index' => 2,
      'reel' => 'rule_reward',
      'symbol' => 'guaranteed_loot',
      'label' => 'Guaranteed Loot',
      'weight' => 30,
      'risk' => 1,
      'effect' => 'Victory promises extra loot.',
    ]];
    $this->insertChaosResult($userId, $runId, $nodeId, 707070, $reels, 1.6);

    $this->authenticate($userId);
    $finalize = $this->invoke(fn() => (new ChaosEncounterController())->finalize((string)$runId, (string)$nodeId));
    $this->assertSame(200, $finalize['status'], json_encode($finalize['body']));
    $data = $this->assertSuccess($finalize);
    $rewards = is_array($data['rewards'] ?? null) ? $data['rewards'] : [];

    $boundTemplateSlug = (string)$this->scalar(
      'SELECT et.`slug`
       FROM `run_nodes` rn
       JOIN `encounter_templates` et ON et.`id` = rn.`encounter_template_id`
       WHERE rn.`id` = ?
       LIMIT 1',
      [$nodeId]
    );
    $this->assertStringContainsString('mud', $boundTemplateSlug);
    $this->assertStringNotContainsString('frogman', $boundTemplateSlug);
    $this->assertSame('pigs', (string)($rewards['applied_reels']['enemy_family']['symbol'] ?? ''));
    $this->assertSame('horde', (string)($rewards['applied_reels']['encounter_shape']['symbol'] ?? ''));
    $this->assertSame('guaranteed_loot', (string)($rewards['applied_reels']['rule_reward']['symbol'] ?? ''));
    $this->assertSame([['rarity' => 'common', 'sides' => 6]], $rewards['dice_grants'] ?? null);
    $this->assertContains('1 Common D6', is_array($rewards['labels'] ?? null) ? $rewards['labels'] : []);
  }

  private function insertRunNode(int $runId, string $nodeType, string $status): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, ?, ?, ?, NULL, NULL)
    ');
    $stmt?->execute([$runId, random_int(1, 9999), $nodeType, $status]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertRunEdge(int $runId, int $fromNodeId, int $toNodeId): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`)
      VALUES (?, ?, ?)
    ');
    $stmt?->execute([$runId, $fromNodeId, $toNodeId]);
  }

  private function pickUnitTypeId(): int
  {
    $stmt = $this->pdo?->query('SELECT `id` FROM `unit_types` ORDER BY `id` ASC LIMIT 1');
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    $this->assertIsArray($row, 'Expected seeded unit_types rows in test database.');
    return (int)$row['id'];
  }

  private function insertUnit(int $userId, int $unitTypeId): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, 0, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertTeamUnit(int $teamId, int $unitId): void
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
    $stmt?->execute([$teamId, $unitId]);
  }

  private function insertRunUnitState(int $runId, int $unitId, int $hp): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, ?, 0, ?, ?)
    ');
    $stmt?->execute([$runId, $unitId, $hp, '{}', '[]']);
  }

  /**
   * @param array<int,array<string,mixed>> $reels
   */
  private function insertChaosResult(
    int $userId,
    int $runId,
    int $nodeId,
    int $seed,
    array $reels,
    float $rewardMultiplier
  ): void {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `chaos_encounter_results` (
        `user_id`, `run_id`, `node_id`, `status`, `seed`, `reels_json`, `reward_multiplier`
      ) VALUES (?, ?, ?, \'generated\', ?, ?, ?)
    ');
    $stmt?->execute([
      $userId,
      $runId,
      $nodeId,
      $seed,
      json_encode($reels, JSON_UNESCAPED_SLASHES),
      $rewardMultiplier,
    ]);
  }

  /**
   * @param array{status:int,body:array<string,mixed>} $response
   * @return array<string,mixed>
   */
  private function assertSuccess(array $response): array
  {
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);
    return $response['body']['data'];
  }

  private function authenticate(int $userId): void
  {
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
  }
}
