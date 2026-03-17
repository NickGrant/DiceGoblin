<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\ApiControllerEnvelopeContractTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ApiControllerEnvelopeContractTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run endpoint integration tests.';
  }

  public function testSessionReturnsAuthenticatedSuccessEnvelope(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->session());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertSame(true, $data['authenticated'] ?? null);
    $this->assertIsString($data['csrf_token'] ?? null);
    $this->assertNotSame('', (string)($data['csrf_token'] ?? ''));
    $this->assertIsArray($data['user'] ?? null);
    $this->assertIsString($data['user']['id'] ?? null);
  }

  public function testProfileReturnsSuccessEnvelopeWithContractKeys(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertIsString($data['server_time_iso'] ?? null);
    $this->assertIsArray($data['squads'] ?? null);
    $this->assertIsArray($data['units'] ?? null);
    $this->assertIsArray($data['dice'] ?? null);
    $this->assertIsArray($data['currency'] ?? null);
    $this->assertIsInt($data['currency']['soft'] ?? null);
    $this->assertIsInt($data['currency']['hard'] ?? null);
    $this->assertIsArray($data['energy'] ?? null);
    $this->assertIsInt($data['energy']['current'] ?? null);
    $this->assertIsInt($data['energy']['max'] ?? null);
    $this->assertIsNumeric($data['energy']['regen_rate_per_hour'] ?? null);
    $this->assertIsString($data['energy']['last_regen_at'] ?? null);
    $this->assertIsArray($data['region_unlocks'] ?? null);
    $this->assertIsArray($data['region_items'] ?? null);
    $this->assertArrayHasKey('active_run', $data);
  }

  public function testCurrentRunReturnsSuccessEnvelopeWhenNoActiveRun(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertNull($data['run'] ?? null);
    $this->assertNull($data['map'] ?? null);
  }

  public function testCurrentRunReturnsSuccessEnvelopeWithRunMapArrays(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId);
    $nodeA = $this->insertRunNode($runId, 0, 'combat', 'available');
    $nodeB = $this->insertRunNode($runId, 1, 'loot', 'locked');
    $this->insertRunEdge($runId, $nodeA, $nodeB);

    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status']);
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);

    $data = $response['body']['data'];
    $this->assertIsArray($data['run'] ?? null);
    $this->assertIsArray($data['map'] ?? null);
    $this->assertIsArray($data['map']['nodes'] ?? null);
    $this->assertIsArray($data['map']['edges'] ?? null);
    $this->assertArrayHasKey('run_unit_state', $data);
    $this->assertIsArray($data['run_unit_state']);
  }

  private function insertRunNode(int $runId, int $nodeIndex, string $nodeType, string $status): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, ?, ?, ?, NULL, ?)
    ');
    $stmt?->execute([$runId, $nodeIndex, $nodeType, $status, '{"col":0,"row":0}']);
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
}
