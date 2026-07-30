<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunRepositoryNodeTypesIntegrationTest extends IntegrationTestCase
{
  public function testCreateRunGraphAcceptsGeneratedProceduralNodeTypes(): void
  {
    $userId = $this->insertUser('qa_run_node_types', 'QA Run Node Types');
    $regionId = $this->insertRegion(0, true, 'qa-node-types', 'QA Node Types');

    $result = (new RunRepository($this->pdo))->createRunGraph($userId, $regionId, 123456, [
      ['node_index' => 0, 'node_type' => 'combat', 'status' => 'available'],
      ['node_index' => 1, 'node_type' => 'loot'],
      ['node_index' => 2, 'node_type' => 'rest'],
      ['node_index' => 3, 'node_type' => 'hazard'],
      ['node_index' => 4, 'node_type' => 'shrine'],
      ['node_index' => 5, 'node_type' => 'chaos'],
      ['node_index' => 6, 'node_type' => 'dialogue', 'meta' => ['dialogue_id' => 'qa']],
      ['node_index' => 7, 'node_type' => 'boss'],
      ['node_index' => 8, 'node_type' => 'exit'],
    ], []);

    $this->assertGreaterThan(0, $result['run_id']);
    $this->assertCount(9, $result['node_id_by_index']);
    $this->assertSame(
      3,
      (int)$this->scalar(
        'SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ? AND `node_type` IN (?, ?, ?)',
        [$result['run_id'], 'hazard', 'shrine', 'chaos']
      )
    );
  }

  public function testClearedNodesUnlockConnectedNodesRegardlessOfStoredEdgeDirection(): void
  {
    $userId = $this->insertUser('qa_connected_unlocks', 'QA Connected Unlocks');
    $regionId = $this->insertRegion(0, true, 'qa-connected-unlocks', 'QA Connected Unlocks');

    $result = (new RunRepository($this->pdo))->createRunGraph($userId, $regionId, 654321, [
      ['node_index' => 0, 'node_type' => 'combat', 'status' => 'cleared'],
      ['node_index' => 1, 'node_type' => 'combat', 'status' => 'locked'],
    ], [
      ['from' => 1, 'to' => 0],
    ]);

    $unlocked = (new RunNodeRepository($this->pdo))->syncAvailableNodesFromClearedParents((int)$result['run_id']);
    $this->assertSame([(string)$result['node_id_by_index'][1]], $unlocked);
    $this->assertSame(
      'available',
      (string)$this->scalar(
        'SELECT `status` FROM `run_nodes` WHERE `id` = ?',
        [$result['node_id_by_index'][1]]
      )
    );
  }
}
