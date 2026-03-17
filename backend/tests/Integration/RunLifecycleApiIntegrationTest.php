<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\RunLifecycleApiIntegrationTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunLifecycleApiIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run lifecycle integration tests.';
  }

  public function testStartRunResolveNodeAndClaimBattleLifecycleContracts(): void
  {
    $userId = $this->insertUser('qa_lifecycle', 'QA Lifecycle');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $apiController = new ApiController();
    $sessionRes = $this->invoke(fn() => $apiController->session());
    $this->assertSame(200, $sessionRes['status'], json_encode($sessionRes['body']));
    $this->assertSame(true, $sessionRes['body']['ok'] ?? null);
    $this->assertSame(true, $sessionRes['body']['data']['authenticated'] ?? null);

    $createRes = $this->invoke(fn() => $apiController->createRun());
    $this->assertSame(200, $createRes['status'], json_encode($createRes['body']));
    $this->assertSame(true, $createRes['body']['ok'] ?? null);

    $runId = $this->fetchActiveRunId($userId);
    $this->assertGreaterThan(0, $runId);

    $exitNodeCount = (int)$this->scalar(
      'SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ? AND `node_type` = \'exit\'',
      [$runId]
    );
    $this->assertSame(1, $exitNodeCount, 'Run graph should include exactly one visible exit node.');

    $bossToExitEdgeCount = (int)$this->scalar(
      'SELECT COUNT(*) FROM `run_edges` re
       JOIN `run_nodes` src ON src.`id` = re.`from_node_id`
       JOIN `run_nodes` dst ON dst.`id` = re.`to_node_id`
       WHERE re.`run_id` = ? AND src.`node_type` = \'boss\' AND dst.`node_type` = \'exit\'',
      [$runId]
    );
    $this->assertSame(1, $bossToExitEdgeCount, 'Exit should be reachable only through boss path.');

    $nonExitWithoutTemplate = (int)$this->scalar(
      'SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ? AND `node_type` != \'exit\' AND `encounter_template_id` IS NULL',
      [$runId]
    );
    $this->assertSame(0, $nonExitWithoutTemplate, 'All generated non-exit nodes should carry an encounter template id.');

    $nodeId = $this->fetchAvailableNodeId($runId);
    $this->assertGreaterThan(0, $nodeId);

    $runNodeController = new RunNodeController();
    $resolveRes = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));
    $this->assertSame(200, $resolveRes['status'], json_encode($resolveRes['body']));
    $this->assertSame(true, $resolveRes['body']['ok'] ?? null);

    $battle = is_array($resolveRes['body']['data']['battle'] ?? null) ? $resolveRes['body']['data']['battle'] : [];
    $battleId = (int)($battle['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);
    $this->assertContains((string)($battle['outcome'] ?? ''), ['victory', 'defeat']);
    $this->assertIsArray($battle['log'] ?? null);
    $this->assertIsArray($battle['log']['events'] ?? null);

    $statusAfterResolve = (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$battleId]);
    $this->assertSame('completed', $statusAfterResolve);

    $battleController = new BattleController();
    $firstClaim = $this->invoke(fn() => $battleController->claimBattle((string)$battleId));
    $secondClaim = $this->invoke(fn() => $battleController->claimBattle((string)$battleId));

    $this->assertSame(200, $firstClaim['status'], json_encode($firstClaim['body']));
    $this->assertSame(200, $secondClaim['status'], json_encode($secondClaim['body']));
    $this->assertSame(true, $firstClaim['body']['ok'] ?? null);

    $firstData = is_array($firstClaim['body']['data'] ?? null) ? $firstClaim['body']['data'] : [];
    $secondData = is_array($secondClaim['body']['data'] ?? null) ? $secondClaim['body']['data'] : [];
    $this->assertSame('claimed', (string)($firstData['status'] ?? ''));
    $this->assertSame($firstData, $secondData, 'Claim payload should be idempotent across repeated calls.');

    $this->assertArrayHasKey('xp', $firstData);
    $this->assertArrayHasKey('rewards', $firstData);
    $this->assertArrayHasKey('updated_run_unit_state', $firstData);
    $this->assertArrayHasKey('run_resolution', $firstData);

    $statusAfterClaim = (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$battleId]);
    $this->assertSame('claimed', $statusAfterClaim);

    $runStatus = (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $this->assertContains($runStatus, ['active', 'completed', 'failed', 'abandoned']);
  }

  private function fetchActiveRunId(int $userId): int
  {
    return (int)$this->scalar(
      'SELECT `id` FROM `region_runs` WHERE `user_id` = ? AND `status` = \'active\' ORDER BY `id` DESC LIMIT 1',
      [$userId]
    );
  }

  private function fetchAvailableNodeId(int $runId): int
  {
    return (int)$this->scalar(
      'SELECT `id` FROM `run_nodes` WHERE `run_id` = ? AND `status` = \'available\' ORDER BY `node_index` ASC LIMIT 1',
      [$runId]
    );
  }

}
