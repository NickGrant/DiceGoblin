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
    $data = $this->assertSuccessEnvelopeShape($response);
    $this->assertSame(true, $data['authenticated'] ?? null);
    $this->assertIsString($data['csrf_token'] ?? null);
    $this->assertNotSame('', (string)($data['csrf_token'] ?? ''));
    $this->assertIsArray($data['user'] ?? null);
    $this->assertIsString($data['user']['id'] ?? null);
    $this->assertIsString($data['user']['display_name'] ?? null);
  }

  public function testProfileReturnsSuccessEnvelopeWithContractKeys(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $this->assertIsString($data['server_time_iso'] ?? null);
    $this->assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T/', (string)$data['server_time_iso']);
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
    $this->assertIsInt($data['squad_unit_cap'] ?? null);
    $this->assertIsArray($data['feature_unlocks'] ?? null);
    $this->assertIsArray($data['unit_type_unlocks'] ?? null);
    $this->assertIsArray($data['regions'] ?? null);
    $this->assertIsArray($data['region_unlocks'] ?? null);
    $this->assertIsArray($data['region_items'] ?? null);
    $this->assertIsArray($data['objectives'] ?? null);
    $this->assertNotEmpty($data['objectives']);
    $firstObjective = is_array($data['objectives'][0] ?? null) ? $data['objectives'][0] : [];
    $this->assertIsString($firstObjective['id'] ?? null);
    $this->assertIsString($firstObjective['title'] ?? null);
    $this->assertIsString($firstObjective['status'] ?? null);
    $this->assertIsInt($firstObjective['priority'] ?? null);
    $this->assertIsInt($firstObjective['progress_current'] ?? null);
    $this->assertIsInt($firstObjective['progress_target'] ?? null);
    $this->assertIsString($firstObjective['route'] ?? null);
    $this->assertArrayHasKey('active_run', $data);
    $this->assertTrue(is_array($data['active_run']) || $data['active_run'] === null);
  }

  public function testProfileUnitPayloadIncludesProgressionReworkFields(): void
  {
    $userId = $this->insertUser('profile_progression', 'Profile Progression User');
    $unitTypeId = (int)$this->scalar('SELECT `id` FROM `unit_types` WHERE `slug` = ? LIMIT 1', ['frontline_bruiser_t1']);
    $this->assertGreaterThan(0, $unitTypeId);

    $unitInsert = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 10, 0, 0)
    ');
    $unitInsert?->execute([$userId, $unitTypeId]);
    $unitId = (int)$this->pdo?->lastInsertId();

    $unlockInsert = $this->pdo?->prepare('
      INSERT INTO `unit_instance_unlocked_abilities` (`unit_instance_id`, `ability_id`, `source_tier`, `source_unit_type_id`)
      VALUES (?, ?, 1, ?)
    ');
    $unlockInsert?->execute([$unitId, 'finisher', $unitTypeId]);

    $capstoneInsert = $this->pdo?->prepare('
      INSERT INTO `unit_instance_capstone_choices` (`unit_instance_id`, `source_unit_type_id`, `ability_id`)
      VALUES (?, ?, ?)
    ');
    $capstoneInsert?->execute([$unitId, $unitTypeId, 'finisher']);

    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $units = is_array($data['units'] ?? null) ? $data['units'] : [];
    $this->assertNotEmpty($units);
    $unit = is_array($units[0] ?? null) ? $units[0] : [];
    $this->assertSame('basic_goblin', (string)($unit['splice_variant_slug'] ?? ''));
    $this->assertSame('Basic Goblin', (string)($unit['splice_variant_name'] ?? ''));
    $this->assertSame(10, (int)($unit['max_level'] ?? 0));
    $this->assertSame(6, (int)($unit['promotion_level'] ?? 0));
    $this->assertSame(5, (int)($unit['total_precision'] ?? 0));
    $this->assertSame(5, (int)($unit['total_resolve'] ?? 0));
    $this->assertSame(true, (bool)($unit['promotion_eligible'] ?? false));
    $this->assertSame(true, (bool)($unit['is_mastered'] ?? false));
    $this->assertIsArray($unit['capstone_choices'] ?? null);
    $this->assertSame('selected', (string)($unit['current_capstone_state'] ?? ''));
    $this->assertSame('finisher', (string)($unit['selected_capstone']['ability_id'] ?? ''));
    $this->assertIsArray($unit['capstone_selections'] ?? null);
    $this->assertIsArray($unit['promotion_grants'] ?? null);
    $this->assertIsArray($unit['inherited_passive_abilities'] ?? null);
  }

  public function testProfileUnitPayloadAppliesSpliceVariantMetadataAndStats(): void
  {
    $userId = $this->insertUser('profile_splice', 'Profile Splice User');
    $unitTypeId = (int)$this->scalar('SELECT `id` FROM `unit_types` WHERE `slug` = ? LIMIT 1', ['backline_marksman_t1']);
    $this->assertGreaterThan(0, $unitTypeId);

    $unitInsert = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `splice_variant_slug`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, ?, 1, 1, 0, 0)
    ');
    $unitInsert?->execute([$userId, $unitTypeId, 'toad_splice']);

    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $units = is_array($data['units'] ?? null) ? $data['units'] : [];
    $this->assertNotEmpty($units);
    $unit = is_array($units[0] ?? null) ? $units[0] : [];
    $this->assertSame('toad_splice', (string)($unit['splice_variant_slug'] ?? ''));
    $this->assertSame('Toad-Spliced', (string)($unit['splice_variant_name'] ?? ''));
    $this->assertSame('+2 HP, +1 Resolve, -1 Precision.', (string)($unit['splice_variant_passive_summary'] ?? ''));
    $this->assertSame(5, (int)($unit['total_precision'] ?? 0));
    $this->assertSame(5, (int)($unit['total_resolve'] ?? 0));
    $this->assertSame(20, (int)($unit['max_hp'] ?? 0));
  }

  public function testCurrentRunReturnsSuccessEnvelopeWhenNoActiveRun(): void
  {
    $userId = $this->insertUser();
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status']);
    $data = $this->assertSuccessEnvelopeShape($response);
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
    $data = $this->assertSuccessEnvelopeShape($response);
    $this->assertIsArray($data['run'] ?? null);
    $this->assertIsArray($data['map'] ?? null);
    $this->assertIsArray($data['map']['nodes'] ?? null);
    $this->assertIsArray($data['map']['edges'] ?? null);
    $this->assertArrayHasKey('run_unit_state', $data);
    $this->assertIsArray($data['run_unit_state']);

    $run = is_array($data['run']) ? $data['run'] : [];
    $this->assertArrayHasKey('run_id', $run);
    $this->assertArrayHasKey('status', $run);
    $this->assertArrayHasKey('seed', $run);
    $this->assertArrayHasKey('region_slug', $run);
    $this->assertArrayHasKey('region_theme', $run);

    $nodes = is_array($data['map']['nodes']) ? $data['map']['nodes'] : [];
    $edges = is_array($data['map']['edges']) ? $data['map']['edges'] : [];
    $this->assertNotEmpty($nodes);

    $firstNode = is_array($nodes[0] ?? null) ? $nodes[0] : [];
    $this->assertArrayHasKey('id', $firstNode);
    $this->assertArrayHasKey('node_type', $firstNode);
    $this->assertArrayHasKey('status', $firstNode);

    if ($edges !== []) {
      $firstEdge = is_array($edges[0] ?? null) ? $edges[0] : [];
      $this->assertArrayHasKey('from_node_id', $firstEdge);
      $this->assertArrayHasKey('to_node_id', $firstEdge);
    }
  }

  /**
   * @param array{status:int,body:array<string,mixed>} $response
   * @return array<string,mixed>
   */
  private function assertSuccessEnvelopeShape(array $response): array
  {
    $this->assertIsArray($response['body']);
    $this->assertArrayHasKey('ok', $response['body']);
    $this->assertArrayHasKey('data', $response['body']);
    $this->assertArrayNotHasKey('error', $response['body']);
    $this->assertSame(true, $response['body']['ok']);
    $this->assertIsArray($response['body']['data']);

    return $response['body']['data'];
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
