<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ChaosEncounterController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

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

  public function testFinalizeAppliesPersistedRewardsOnceAndClearsNode(): void
  {
    $userId = $this->insertUser('chaos_finalize', 'Chaos Finalize User');
    $this->setSoftCurrency($userId, 10);
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId, 515151, 'active');
    $nodeId = $this->insertRunNode($runId, 'chaos', 'available');
    $nextNodeId = $this->insertRunNode($runId, 'combat', 'locked');
    $this->insertRunEdge($runId, $nodeId, $nextNodeId);

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
    $softAward = (int)($firstRewards['currency']['soft'] ?? 0);

    $this->assertSame('confirmed', (string)($firstData['chaos_result']['status'] ?? ''));
    $this->assertSame((float)($generatedResult['reward_multiplier'] ?? 0), (float)($firstRewards['reward_multiplier'] ?? -1));
    $this->assertGreaterThanOrEqual(8, $softAward);
    $this->assertSame(['' . $nextNodeId], $firstData['next']['unlocked_node_ids'] ?? []);
    $this->assertSame('cleared', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nodeId]));
    $this->assertSame('available', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nextNodeId]));
    $this->assertSame((string)(10 + $softAward), (string)$this->scalar('SELECT `currency_soft` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertNotSame('', (string)$this->scalar('SELECT `finalized_rewards_json` FROM `chaos_encounter_results` WHERE `node_id` = ?', [$nodeId]));

    $this->authenticate($userId);
    $secondFinalize = $this->invoke(fn() => (new ChaosEncounterController())->finalize((string)$runId, (string)$nodeId));
    $this->assertSame(200, $secondFinalize['status'], json_encode($secondFinalize['body']));
    $secondData = $this->assertSuccess($secondFinalize);

    $this->assertEquals($firstRewards, $secondData['rewards'] ?? null);
    $this->assertSame([], $secondData['next']['unlocked_node_ids'] ?? null);
    $this->assertSame((string)(10 + $softAward), (string)$this->scalar('SELECT `currency_soft` FROM `player_state` WHERE `user_id` = ?', [$userId]));
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
