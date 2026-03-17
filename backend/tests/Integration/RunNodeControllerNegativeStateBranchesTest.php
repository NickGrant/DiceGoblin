<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunNodeControllerNegativeStateBranchesTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run run-node negative branch tests.';
  }

  public function testResolveNodeRejectsRunNotActive(): void
  {
    $userId = $this->insertUser('qa_run_node', 'QA Run Node');
    $regionId = $this->insertRegion(5, true, 'qa-node-region', 'QA Node Region');
    $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 7777, 'failed');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('run_not_active', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeRejectsLockedNode(): void
  {
    $userId = $this->insertUser('qa_run_node', 'QA Run Node');
    $regionId = $this->insertRegion(5, true, 'qa-node-region', 'QA Node Region');
    $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 7777, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'locked');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('node_not_available', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeRejectsOwnershipMismatch(): void
  {
    $ownerId = $this->insertUser('qa_run_node', 'QA Run Node');
    $attackerId = $this->insertUser('qa_run_node', 'QA Run Node');
    $regionId = $this->insertRegion(5, true, 'qa-node-region', 'QA Node Region');
    $this->insertTeam($ownerId, 'QA Team', true);
    $runId = $this->insertRun($ownerId, $regionId, 7777, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $attackerId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(403, $res['status']);
    $this->assertSame('forbidden', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeRejectsTeamIdNotOwnedByRequester(): void
  {
    $userId = $this->insertUser('qa_run_node', 'QA Run Node');
    $otherUserId = $this->insertUser('qa_run_node', 'QA Run Node');
    $regionId = $this->insertRegion(5, true, 'qa-node-region', 'QA Node Region');
    $this->insertTeam($userId, 'QA Team', true);
    $otherTeamId = $this->insertTeam($otherUserId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 7777, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['team_id' => (string)$otherTeamId];

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(400, $res['status']);
    $this->assertSame('validation_error', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testResolveNodeFailsFastWhenCombatEncounterHasNoEnemies(): void
  {
    $userId = $this->insertUser('qa_run_node', 'QA Run Node');
    $regionId = $this->insertRegion(5, true, 'qa-node-region', 'QA Node Region');
    $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 7777, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('combat_no_enemies', (string)($res['body']['error']['code'] ?? ''));
    $this->assertSame(0, (int)($res['body']['error']['details']['ticks'] ?? -1));
    $this->assertSame(0, (int)($res['body']['error']['details']['rounds'] ?? -1));

    $battleCount = (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `node_id` = ?', [$runId, $nodeId]);
    $this->assertSame(0, $battleCount);
  }

  private function insertRunNode(int $runId, string $nodeType, string $status): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, 1, ?, ?, NULL, NULL)
    ');
    $stmt?->execute([$runId, $nodeType, $status]);
    return (int)$this->pdo?->lastInsertId();
  }
}
