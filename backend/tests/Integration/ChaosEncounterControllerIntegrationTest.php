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
    $nodeId = $this->insertRunNode($runId, 'hazard', 'available');

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

    $this->authenticate($otherUserId);
    $wrongOwner = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$exitNodeId));
    $this->assertSame(404, $wrongOwner['status'], json_encode($wrongOwner['body']));
    $this->assertSame('run_node_not_found', (string)($wrongOwner['body']['error']['code'] ?? ''));

    $this->authenticate($ownerId);
    $invalid = $this->invoke(fn() => (new ChaosEncounterController())->generate((string)$runId, (string)$exitNodeId));
    $this->assertSame(409, $invalid['status'], json_encode($invalid['body']));
    $this->assertSame('invalid_chaos_node', (string)($invalid['body']['error']['code'] ?? ''));
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
