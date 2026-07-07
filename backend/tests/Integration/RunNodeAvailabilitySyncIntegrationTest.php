<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunNodeAvailabilitySyncIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run node availability sync integration tests.';
  }

  public function testCurrentRunReopensLockedNodeWhenItsParentPathWasAlreadyCleared(): void
  {
    $userId = $this->insertUser('qa_run_sync', 'QA Run Sync');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $regionId = (int)$this->scalar("SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1", []);
    $this->assertGreaterThan(0, $regionId);

    $runId = $this->insertRun($userId, $regionId, 777001, 'active');
    $startNodeId = $this->insertCustomRunNode($runId, 0, 'combat', 'cleared', ['col' => 0, 'row' => 1]);
    $branchNodeId = $this->insertCustomRunNode($runId, 1, 'combat', 'locked', ['col' => 1, 'row' => 2]);
    $forwardNodeId = $this->insertCustomRunNode($runId, 2, 'combat', 'available', ['col' => 1, 'row' => 0]);

    $this->insertCustomRunEdge($runId, $startNodeId, $branchNodeId);
    $this->insertCustomRunEdge($runId, $startNodeId, $forwardNodeId);

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);

    $nodes = is_array($response['body']['data']['map']['nodes'] ?? null)
      ? $response['body']['data']['map']['nodes']
      : [];
    $branchNode = $this->findNodeById($nodes, (string)$branchNodeId);
    $this->assertNotNull($branchNode);
    $this->assertSame('available', (string)($branchNode['status'] ?? ''));

    $persistedStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$branchNodeId]);
    $this->assertSame('available', $persistedStatus);
  }

  /**
   * @param array<string,int> $meta
   */
  private function insertCustomRunNode(int $runId, int $nodeIndex, string $nodeType, string $status, array $meta): int
  {
    $stmt = $this->pdo?->prepare("
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `meta_json`)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt?->execute([$runId, $nodeIndex, $nodeType, $status, json_encode($meta, JSON_UNESCAPED_SLASHES)]);

    return (int)$this->pdo?->lastInsertId();
  }

  private function insertCustomRunEdge(int $runId, int $fromNodeId, int $toNodeId): void
  {
    $stmt = $this->pdo?->prepare("
      INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`)
      VALUES (?, ?, ?)
    ");
    $stmt?->execute([$runId, $fromNodeId, $toNodeId]);
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @return array<string,mixed>|null
   */
  private function findNodeById(array $nodes, string $nodeId): ?array
  {
    foreach ($nodes as $node) {
      if ((string)($node['id'] ?? '') === $nodeId) {
        return $node;
      }
    }

    return null;
  }
}
