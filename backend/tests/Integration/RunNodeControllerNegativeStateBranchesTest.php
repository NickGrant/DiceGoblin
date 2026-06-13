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
    $this->setJsonBody(['team_id' => (string)$otherTeamId]);

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

  public function testResolveNodeRejectsOversizedSquadForCurrentCap(): void
  {
    $userId = $this->insertUser('qa_run_node', 'QA Run Node');
    $regionId = $this->insertRegion(5, true, 'qa-node-region', 'QA Node Region');
    $teamId = $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 7777, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    for ($index = 0; $index < 5; $index++) {
      $unitId = $this->insertUnitInstance($userId);
      $stmt = $this->pdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
      $stmt?->execute([$teamId, $unitId]);
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new RunNodeController();
    $res = $this->invoke(fn() => $controller->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(409, $res['status']);
    $this->assertSame('validation_error', (string)($res['body']['error']['code'] ?? ''));
    $this->assertSame(
      'Selected squad exceeds your current 4-unit cap. Trim the squad before starting the run.',
      (string)($res['body']['error']['message'] ?? '')
    );
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

  private function insertUnitInstance(int $userId): int
  {
    $unitTypeId = $this->insertUnitType();
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, 0, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertUnitType(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_types`
      (`slug`, `name`, `role`, `base_stats_json`, `ability_set_json`, `max_level`, `attack_per_level`, `defense_per_level`, `max_hp_per_level`)
      VALUES (?, ?, ?, ?, ?, 50, 1, 1, 5)
    ');
    $stmt?->execute([
      "qa_run_unit_$token",
      "QA Run Unit $token",
      'fighter',
      json_encode([
        'attack' => 5,
        'defense' => 3,
        'max_hp' => 20,
        'formation' => ['w' => 1, 'h' => 1],
      ], JSON_THROW_ON_ERROR),
      json_encode(['active' => [], 'passive' => []], JSON_THROW_ON_ERROR),
    ]);

    return (int)$this->pdo?->lastInsertId();
  }
}
