<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\ApiControllerEnvelopeContractTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Services\CodexOwnershipService;
use DiceGoblins\Services\LineageUnlockService;
use DiceGoblins\Services\UserUnlockService;
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
    $this->assertIsInt($data['currency']['raw_chaos'] ?? null);
    $this->assertIsArray($data['energy'] ?? null);
    $this->assertIsInt($data['energy']['current'] ?? null);
    $this->assertIsInt($data['energy']['max'] ?? null);
    $this->assertIsNumeric($data['energy']['regen_rate_per_hour'] ?? null);
    $this->assertIsString($data['energy']['last_regen_at'] ?? null);
    $this->assertIsInt($data['squad_unit_cap'] ?? null);
    $this->assertIsArray($data['feature_unlocks'] ?? null);
    $this->assertIsArray($data['unit_type_unlocks'] ?? null);
    $this->assertIsArray($data['lineage_unlocks'] ?? null);
    $this->assertIsArray($data['regions'] ?? null);
    $this->assertIsArray($data['region_unlocks'] ?? null);
    $this->assertIsArray($data['items'] ?? null);
    $this->assertIsArray($data['region_items'] ?? null);
    $this->assertIsArray($data['objectives'] ?? null);
    $this->assertIsArray($data['codex'] ?? null);
    $this->assertIsArray($data['codex']['owned_entries'] ?? null);
    $this->assertIsArray($data['codex']['owned_by_type'] ?? null);
    $this->assertIsArray($data['codex']['details_by_type'] ?? null);
    foreach (['enemy', 'biome', 'feature', 'unit_type', 'kin', 'affix', 'item', 'lore'] as $entryType) {
      $this->assertIsArray($data['codex']['owned_by_type'][$entryType] ?? null);
      $this->assertIsArray($data['codex']['details_by_type'][$entryType] ?? null);
    }
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

  public function testProfileCodexPayloadBackfillsDerivedOwnershipFacts(): void
  {
    $userId = $this->insertUser('profile_codex', 'Profile Codex User');
    $this->grantUnlock($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE);
    $this->grantUnlock($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'backline_marksman_t1');
    $this->grantUnlock($userId, UserUnlockService::NAMESPACE_DIALOGUE, 'mountain_start');
    (new LineageUnlockService($this->pdo))->grant($userId, LineageUnlockService::PIG_KIN);
    $this->insertOwnedUnit($userId, 'frontline_bruiser_t1', LineageUnlockService::PIG_KIN);
    $this->grantItem($userId, 'pig_ear', 1);
    (new CodexOwnershipService($this->pdo))->grant($userId, CodexOwnershipService::TYPE_ENEMY, 'mudwrestler', 'combat_drop');
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $ownedByType = is_array($data['codex']['owned_by_type'] ?? null) ? $data['codex']['owned_by_type'] : [];

    $this->assertContains(UserUnlockService::FEATURE_WRONG_MACHINE, $ownedByType['feature'] ?? []);
    $this->assertContains('backline_marksman_t1', $ownedByType['unit_type'] ?? []);
    $this->assertContains('frontline_bruiser_t1', $ownedByType['unit_type'] ?? []);
    $this->assertContains(LineageUnlockService::PIG_KIN, $ownedByType['kin'] ?? []);
    $this->assertContains('pig_ear', $ownedByType['item'] ?? []);
    $this->assertContains('mountain_start', $ownedByType['lore'] ?? []);
    $this->assertContains('mudwrestler', $ownedByType['enemy'] ?? []);

    $detailsByType = is_array($data['codex']['details_by_type'] ?? null) ? $data['codex']['details_by_type'] : [];
    $unitDetails = is_array($detailsByType['unit_type'] ?? null) ? $detailsByType['unit_type'] : [];
    $enemyDetails = is_array($detailsByType['enemy'] ?? null) ? $detailsByType['enemy'] : [];
    $bruiserDetails = $this->detailByKey($unitDetails, 'frontline_bruiser_t1');
    $mudwrestlerDetails = $this->detailByKey($enemyDetails, 'mudwrestler');

    $this->assertSame('Bruiser', (string)($bruiserDetails['label'] ?? ''));
    $this->assertSame(1, (int)($bruiserDetails['tier'] ?? 0));
    $this->assertIsArray($bruiserDetails['stats'] ?? null);
    $this->assertArrayHasKey('attack', $bruiserDetails['stats']);
    $this->assertIsArray($bruiserDetails['growth'] ?? null);
    $this->assertArrayHasKey('max_hp', $bruiserDetails['growth']);
    $this->assertIsArray($bruiserDetails['abilities']['actives'] ?? null);

    $this->assertSame('Mudwrestler', (string)($mudwrestlerDetails['label'] ?? ''));
    $this->assertIsArray($mudwrestlerDetails['stats'] ?? null);
    $this->assertArrayHasKey('defense', $mudwrestlerDetails['stats']);
    $this->assertIsArray($mudwrestlerDetails['abilities']['actives'] ?? null);
  }

  public function testProfileReturnsImplicitAndExplicitLineageUnlocks(): void
  {
    $userId = $this->insertUser('profile_lineage', 'Profile Lineage User');
    (new LineageUnlockService($this->pdo))->grant($userId, LineageUnlockService::PIG_KIN);
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $lineages = is_array($data['lineage_unlocks'] ?? null) ? $data['lineage_unlocks'] : [];
    $bySlug = [];
    foreach ($lineages as $lineage) {
      if (is_array($lineage)) {
        $bySlug[(string)($lineage['lineage_slug'] ?? '')] = $lineage;
      }
    }

    $this->assertArrayHasKey(LineageUnlockService::BASIC_GOBLIN, $bySlug);
    $this->assertSame('Basic Goblin', (string)($bySlug[LineageUnlockService::BASIC_GOBLIN]['name'] ?? ''));
    $this->assertSame(true, (bool)($bySlug[LineageUnlockService::BASIC_GOBLIN]['is_implicit'] ?? false));
    $this->assertNull($bySlug[LineageUnlockService::BASIC_GOBLIN]['unlocked_at'] ?? null);

    $this->assertArrayHasKey(LineageUnlockService::PIG_KIN, $bySlug);
    $this->assertSame('Pig Kin', (string)($bySlug[LineageUnlockService::PIG_KIN]['name'] ?? ''));
    $this->assertSame(false, (bool)($bySlug[LineageUnlockService::PIG_KIN]['is_implicit'] ?? true));
    $this->assertIsString($bySlug[LineageUnlockService::PIG_KIN]['unlocked_at'] ?? null);
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
    $unit = [];
    foreach ($units as $candidate) {
      if (is_array($candidate) && (string)($candidate['id'] ?? '') === (string)$unitId) {
        $unit = $candidate;
        break;
      }
    }
    $this->assertNotEmpty($unit);
    $this->assertSame('basic_goblin', (string)($unit['kin_slug'] ?? ''));
    $this->assertSame('Basic Goblin', (string)($unit['kin_name'] ?? ''));
    $this->assertSame('basic_goblin', (string)($unit['splice_variant_slug'] ?? ''));
    $this->assertSame('Basic Goblin', (string)($unit['splice_variant_name'] ?? ''));
    $this->assertSame(10, (int)($unit['max_level'] ?? 0));
    $this->assertSame(6, (int)($unit['promotion_level'] ?? 0));
    $this->assertIsInt($unit['total_precision'] ?? null);
    $this->assertGreaterThan(0, (int)($unit['total_precision'] ?? 0));
    $this->assertIsInt($unit['total_resolve'] ?? null);
    $this->assertGreaterThan(0, (int)($unit['total_resolve'] ?? 0));
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
    $this->assertSame('toad_splice', (string)($unit['kin_slug'] ?? ''));
    $this->assertSame('Toad-Spliced', (string)($unit['kin_name'] ?? ''));
    $this->assertSame('+2 HP, +1 Resolve, -1 Precision.', (string)($unit['kin_passive_summary'] ?? ''));
    $this->assertSame('toad_splice', (string)($unit['splice_variant_slug'] ?? ''));
    $this->assertSame('Toad-Spliced', (string)($unit['splice_variant_name'] ?? ''));
    $this->assertSame('+2 HP, +1 Resolve, -1 Precision.', (string)($unit['splice_variant_passive_summary'] ?? ''));
    $this->assertSame(5, (int)($unit['total_precision'] ?? 0));
    $this->assertSame(5, (int)($unit['total_resolve'] ?? 0));
    $this->assertSame(20, (int)($unit['max_hp'] ?? 0));
  }

  public function testProfileObjectivesReflectDurableGameplayFacts(): void
  {
    $userId = $this->insertUser('profile_objectives', 'Profile Objectives User');
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 81828384, 'completed');
    $nodeId = $this->insertRunNode($runId, 0, 'combat', 'cleared');
    $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'victory');
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $objectives = is_array($data['objectives'] ?? null) ? $data['objectives'] : [];
    $objectiveById = [];
    foreach ($objectives as $objective) {
      if (is_array($objective) && isset($objective['id'])) {
        $objectiveById[(string)$objective['id']] = $objective;
      }
    }

    $this->assertSame('complete', (string)($objectiveById['continue-active-run']['status'] ?? ''));
    $this->assertSame(1, (int)($objectiveById['continue-active-run']['progress_current'] ?? 0));
    $this->assertSame('complete', (string)($objectiveById['claim-first-victory']['status'] ?? ''));
    $this->assertSame(1, (int)($objectiveById['claim-first-victory']['progress_current'] ?? 0));
    $this->assertSame('complete', (string)($objectiveById['complete-first-run']['status'] ?? ''));
    $this->assertSame(1, (int)($objectiveById['complete-first-run']['progress_current'] ?? 0));
  }

  public function testProfileReturnsGenericItemsWithMetadata(): void
  {
    $userId = $this->insertUser('profile_items', 'Profile Items User');
    $itemId = (int)$this->scalar('SELECT `id` FROM `items` WHERE `slug` = ? LIMIT 1', ['pig_ear']);
    $this->assertGreaterThan(0, $itemId);
    $stmt = $this->pdo?->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)');
    $stmt?->execute([$userId, $itemId, 4]);
    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->profile());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccessEnvelopeShape($response);
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $this->assertNotEmpty($items);
    $item = $items[0];
    $this->assertSame('pig_ear', (string)($item['item_slug'] ?? ''));
    $this->assertSame('lineage_material', (string)($item['category'] ?? ''));
    $this->assertSame(4, (int)($item['quantity'] ?? 0));
    $this->assertSame('the_farm', (string)($item['source_region_slug'] ?? ''));
    $this->assertSame(true, (bool)($item['is_primary_progression'] ?? false));
    $this->assertIsArray($item['meta'] ?? null);
    $this->assertSame('pig_kin', (string)($item['meta']['lineage_slug'] ?? ''));
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
    $this->assertArrayHasKey('active_run_effects', $data);
    $this->assertIsArray($data['active_run_effects']);

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

  public function testCurrentRunReturnsOnlyOngoingBattleEffectSummaries(): void
  {
    $userId = $this->insertUser('current_run_effects', 'Current Run Effects User');
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    $unitTypeId = (int)$this->scalar('SELECT `id` FROM `unit_types` ORDER BY `id` ASC LIMIT 1', []);
    $this->assertGreaterThan(0, $unitTypeId);
    $unitInsert = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, 0, 0)
    ');
    $unitInsert?->execute([$userId, $unitTypeId]);
    $unitId = (int)$this->pdo?->lastInsertId();
    $statusEffects = [[
      'type' => 'run_stat_modifier_next_combat',
      'source' => 'shrine',
      'remaining_combats' => 1,
      'stat_multipliers' => ['defense' => 1.25],
      'stat_adders' => ['resolve' => 2],
    ]];
    $stateInsert = $this->pdo?->prepare('
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, 20, 0, \'{}\', ?)
    ');
    $stateInsert?->execute([$runId, $unitId, json_encode($statusEffects, JSON_UNESCAPED_SLASHES)]);

    $nodeId = $this->insertRunNode($runId, 0, 'shrine', 'cleared');
    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'victory');
    $this->insertBattleLog($battleId, [
      'meta' => ['node_type' => 'shrine'],
      'events' => [[
        'type' => 'node_effect',
        'round' => 0,
        'tick' => 0,
        'node_type' => 'shrine',
        'label' => 'Shrine Favor Granted',
        'detail' => 'Bone Whisper grants 7 teeth.',
      ]],
    ]);
    $chaosNodeId = $this->insertRunNode($runId, 1, 'chaos', 'cleared');
    $chaosBattleId = $this->insertBattle($userId, $runId, $chaosNodeId, $teamId, 'claimed', 'victory');
    $this->insertBattleLog($chaosBattleId, [
      'meta' => [
        'node_type' => 'chaos',
        'chaos' => [
          'summary' => [
            'title' => 'Kobolds + Ambush + Teeth Rain',
            'effect' => 'A chaos roll shaped one fight.',
          ],
        ],
      ],
      'events' => [],
    ]);

    $_SESSION['user_id'] = $userId;

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->currentRun());

    $this->assertSame(200, $response['status']);
    $data = $this->assertSuccessEnvelopeShape($response);
    $effects = is_array($data['active_run_effects'] ?? null) ? $data['active_run_effects'] : [];
    $this->assertCount(1, $effects);
    $effect = is_array($effects[0] ?? null) ? $effects[0] : [];
    $this->assertSame('', (string)($effect['node_id'] ?? ''));
    $this->assertSame('shrine', (string)($effect['node_type'] ?? ''));
    $this->assertSame('Shrine Battle Effect', (string)($effect['label'] ?? ''));
    $this->assertSame('+25% Defense, +2 Resolve for 1 unit during the next combat.', (string)($effect['detail'] ?? ''));
    $this->assertSame('next combat', (string)($effect['persistence'] ?? ''));
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

  /**
   * @param list<array<string,mixed>> $details
   * @return array<string,mixed>
   */
  private function detailByKey(array $details, string $entryKey): array
  {
    foreach ($details as $detail) {
      if ((string)($detail['entry_key'] ?? '') === $entryKey) {
        return $detail;
      }
    }

    return [];
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

  private function insertBattle(
    int $userId,
    int $runId,
    int $nodeId,
    int $teamId,
    string $status,
    string $outcome
  ): int {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `battles` (
        `user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`
      ) VALUES (?, ?, ?, ?, \'combat_v1\', 424242, ?, ?, 40, 2)
    ');
    $stmt?->execute([$userId, $runId, $nodeId, $teamId, $status, $outcome]);
    return (int)$this->pdo?->lastInsertId();
  }

  /**
   * @param array<string,mixed> $log
   */
  private function insertBattleLog(int $battleId, array $log): void
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `battle_logs` (`battle_id`, `log_json`) VALUES (?, ?)');
    $stmt?->execute([$battleId, json_encode($log, JSON_UNESCAPED_SLASHES)]);
  }

  private function insertOwnedUnit(int $userId, string $unitTypeSlug, string $kinSlug): int
  {
    $unitTypeId = (int)$this->scalar('SELECT `id` FROM `unit_types` WHERE `slug` = ? LIMIT 1', [$unitTypeSlug]);
    $this->assertGreaterThan(0, $unitTypeId);

    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `splice_variant_slug`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, ?, 1, 1, 0, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId, $kinSlug]);

    return (int)$this->pdo?->lastInsertId();
  }

  private function grantItem(int $userId, string $slug, int $quantity): void
  {
    $itemId = (int)$this->scalar('SELECT `id` FROM `items` WHERE `slug` = ? LIMIT 1', [$slug]);
    $this->assertGreaterThan(0, $itemId);

    $stmt = $this->pdo?->prepare('
      INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)
    ');
    $stmt?->execute([$userId, $itemId, max(1, $quantity)]);
  }
}
