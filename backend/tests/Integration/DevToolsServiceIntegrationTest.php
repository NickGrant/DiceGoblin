<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Services\DevToolsService;
use DiceGoblins\Services\LineageUnlockService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\StarterPackProvisioningService;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class DevToolsServiceIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run dev tools integration tests.';
  }

  public function testGrantActionsCreateOwnedResources(): void
  {
    $userId = $this->insertUser('qa_devtools', 'QA DevTools');
    $service = $this->makeService();

    $currency = $service->grantCurrency($userId, 250, 0);
    $this->assertSame(250, $currency['soft']);

    $baselineUnitCount = (int) $this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]);
    $baselineDiceCount = (int) $this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]);

    $units = $service->grantUnits($userId, 'frontline_bruiser_t1', 2);
    $this->assertCount(2, $units);

    $dice = $service->grantDice($userId, 6, 'rare', 1);
    $this->assertCount(1, $dice);

    $item = $service->grantItem($userId, 'pig_ear', 2);
    $this->assertSame('pig_ear', $item['item_slug']);
    $this->assertGreaterThanOrEqual(2, $item['quantity']);

    $regionItem = $service->grantRegionItem($userId, 'roc_egg', 2);
    $this->assertSame('roc_egg', $regionItem['region_item_slug']);
    $this->assertGreaterThanOrEqual(2, $regionItem['quantity']);

    $unitCount = (int) $this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]);
    $diceCount = (int) $this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]);
    $affixCount = (int) $this->scalar(
      'SELECT COUNT(*) FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` = ?',
      [$userId]
    );

    $this->assertSame($baselineUnitCount + 2, $unitCount);
    $this->assertSame($baselineDiceCount + 1, $diceCount);
    $this->assertGreaterThanOrEqual(1, $affixCount);
    $this->assertSame(2, (int)$this->scalar(
      'SELECT ui.`quantity`
       FROM `user_items` ui
       JOIN `items` i ON i.`id` = ui.`item_id`
       WHERE ui.`user_id` = ? AND i.`slug` = ?',
      [$userId, 'pig_ear']
    ));
  }

  public function testResetAccountClearsProgressAndReappliesFreshBaseline(): void
  {
    $userId = $this->insertUser('qa_devreset', 'QA DevReset');
    $service = $this->makeService();

    $regionId = $this->insertRegion(5, true, 'qa-dev-reset', 'QA Dev Reset');
    $teamId = $this->insertTeam($userId, 'Reset QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 777123, 'active');

    $this->unlockRegion($userId, $regionId);
    $this->setEnergy($userId, 9, 50);
    $service->grantCurrency($userId, 120, 0);
    $units = $service->grantUnits($userId, 'frontline_bruiser_t1', 1);
    $grantedUnitId = (int)($units[0]['id'] ?? 0);
    $grantedUnitTypeId = (int)$this->scalar('SELECT `unit_type_id` FROM `unit_instances` WHERE `id` = ? LIMIT 1', [$grantedUnitId]);
    $dice = $service->grantDice($userId, 6, 'rare', 1);
    $grantedDiceId = (int) ($dice[0]['id'] ?? 0);

    $unlockedAbilityStmt = $this->pdo?->prepare(
      "INSERT INTO `unit_instance_unlocked_abilities` (`unit_instance_id`, `ability_id`, `source_tier`, `source_unit_type_id`)
       VALUES (?, 'reset_test_active', 1, ?)"
    );
    $unlockedAbilityStmt?->execute([$grantedUnitId, $grantedUnitTypeId]);

    $equippedAbilityStmt = $this->pdo?->prepare(
      "INSERT INTO `unit_instance_equipped_abilities` (`unit_instance_id`, `ability_id`, `equip_order`, `speed_cost`)
       VALUES (?, 'reset_test_active', 99, 1)"
    );
    $equippedAbilityStmt?->execute([$grantedUnitId]);

    $capstoneStmt = $this->pdo?->prepare(
      "INSERT INTO `unit_instance_capstone_choices` (`unit_instance_id`, `source_unit_type_id`, `ability_id`)
       VALUES (?, ?, 'reset_test_capstone')"
    );
    $capstoneStmt?->execute([$grantedUnitId, $grantedUnitTypeId]);

    $codexStmt = $this->pdo?->prepare(
      "INSERT INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `metadata_json`)
       VALUES (?, 'enemy', 'mudwrestler', 'reset_test', JSON_OBJECT('qa', true))"
    );
    $codexStmt?->execute([$userId]);

    $nodeStmt = $this->pdo?->prepare(
      "INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`) VALUES (?, 1, 'combat', 'available', NULL, NULL)"
    );
    $nodeStmt?->execute([$runId]);
    $nodeId = (int) $this->pdo?->lastInsertId();

    $chaosStmt = $this->pdo?->prepare(
      "INSERT INTO `chaos_encounter_results` (`user_id`, `run_id`, `node_id`, `seed`, `reels_json`, `reward_multiplier`)
       VALUES (?, ?, ?, 99123, '{\"enemy_family\":{\"symbol\":\"farm_pigs\"},\"encounter_shape\":{\"symbol\":\"ambush\"},\"rule_reward\":{\"symbol\":\"raw_chaos_spark\"}}', 1.25)"
    );
    $chaosStmt?->execute([$userId, $runId, $nodeId]);

    $battleStmt = $this->pdo?->prepare(
      "INSERT INTO `battles` (`user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`)
       VALUES (?, ?, ?, ?, 'combat_v1', 12345, 'completed', 'victory', 5, 1)"
    );
    $battleStmt?->execute([$userId, $runId, $nodeId, $teamId]);
    $battleId = (int) $this->pdo?->lastInsertId();

    $rewardStmt = $this->pdo?->prepare(
      "INSERT INTO `battle_rewards` (`battle_id`, `xp_total`, `currency_soft`, `rewards_json`) VALUES (?, 0, 0, '{\"new_dice_instance_ids\":[],\"region_items\":[]}')"
    );
    $rewardStmt?->execute([$battleId]);

    $dealStmt = $this->pdo?->prepare(
      'INSERT INTO `shop_daily_deals` (`user_id`, `shop_date`, `dice_definition_id`, `affix_definition_id`, `affix_value`, `purchased_dice_instance_id`)
       VALUES (?, ?, ?, ?, ?, ?)'
    );
    $dealStmt?->execute([$userId, '2026-04-01', 10, 1, 1.0, $grantedDiceId]);

    $bountyDefinitionStmt = $this->pdo?->prepare(
      "INSERT INTO `bounty_definitions` (`slug`, `title`, `description`, `category`, `objective_json`, `reward_json`, `sort_order`)
       VALUES (?, 'Reset QA Bounty', 'Reset should clear accepted bounties.', 'hunting', '{\"target\":\"qa\"}', '{\"teeth\":1}', 999)"
    );
    $bountyDefinitionStmt?->execute(['qa-reset-bounty-' . $userId]);
    $bountyDefinitionId = (int) $this->pdo?->lastInsertId();
    $bountyStmt = $this->pdo?->prepare(
      "INSERT INTO `user_bounties` (`user_id`, `bounty_definition_id`, `status`, `progress_json`)
       VALUES (?, ?, 'accepted', '{\"count\":1}')"
    );
    $bountyStmt?->execute([$userId, $bountyDefinitionId]);

    $reset = $service->resetAccount($userId);

    $baselineUserId = $this->insertUser('qa_devbaseline', 'QA Dev Baseline');
    $service->grantCurrency($baselineUserId, 1, 0);
    $baselineSquads = (int) $this->scalar('SELECT COUNT(*) FROM `teams` WHERE `user_id` = ?', [$baselineUserId]);
    $baselineUnits = (int) $this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$baselineUserId]);
    $baselineDice = (int) $this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$baselineUserId]);
    $baselineUnlocks = (int) $this->scalar('SELECT COUNT(*) FROM `region_unlocks` WHERE `user_id` = ?', [$baselineUserId]);

    $this->assertSame((string) $userId, $reset['user_id']);
    $this->assertFalse($reset['active_run']);
    $this->assertSame($baselineSquads, $reset['squads']);
    $this->assertSame($baselineUnits, $reset['units']);
    $this->assertSame($baselineDice, $reset['dice']);
    $this->assertSame($baselineUnlocks, $reset['region_unlocks']);
    $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM `region_runs` WHERE `user_id` = ? AND `status` = 'active'", [$userId]));
    $this->assertSame(0, (int) $this->scalar('SELECT `currency_soft` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM `shop_daily_deals` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM `chaos_encounter_results` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM `user_bounties` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(0, (int) $this->scalar(
      'SELECT COUNT(*)
       FROM `unit_instance_unlocked_abilities` uua
       JOIN `unit_instances` ui ON ui.`id` = uua.`unit_instance_id`
       WHERE ui.`user_id` = ? AND uua.`ability_id` = ?',
      [$userId, 'reset_test_active']
    ));
    $this->assertSame(0, (int) $this->scalar(
      'SELECT COUNT(*)
       FROM `unit_instance_equipped_abilities` uea
       JOIN `unit_instances` ui ON ui.`id` = uea.`unit_instance_id`
       WHERE ui.`user_id` = ? AND uea.`ability_id` = ?',
      [$userId, 'reset_test_active']
    ));
    $this->assertSame(0, (int) $this->scalar(
      'SELECT COUNT(*)
       FROM `unit_instance_capstone_choices` ucc
       JOIN `unit_instances` ui ON ui.`id` = ucc.`unit_instance_id`
       WHERE ui.`user_id` = ? AND ucc.`ability_id` = ?',
      [$userId, 'reset_test_capstone']
    ));
    $this->assertSame(0, (int) $this->scalar(
      'SELECT COUNT(*) FROM `user_codex_entries` WHERE `user_id` = ? AND `entry_type` = ? AND `entry_key` = ?',
      [$userId, 'enemy', 'mudwrestler']
    ));
  }

  public function testSetUnitLevelClampsToOwnedUnitMaxLevel(): void
  {
    $userId = $this->insertUser('qa_devlevel', 'QA DevLevel');
    $service = $this->makeService();
    $grantedUnits = $service->grantUnits($userId, 'frontline_bruiser_t1', 1);
    $unitId = (int)($grantedUnits[0]['id'] ?? 0);

    $updated = $service->setUnitLevel($userId, $unitId, 99);

    $this->assertSame((string)$unitId, $updated['id']);
    $this->assertSame(10, $updated['level']);
    $this->assertSame(10, $updated['max_level']);
    $this->assertSame(10, (int)$this->scalar('SELECT `level` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  public function testSeedTableCatalogListsAllowlistedRowsAndDecodesJson(): void
  {
    $service = $this->makeService();

    $catalog = $service->getSeedTableCatalog('unit_types');
    $this->assertNotEmpty($catalog['tables']);
    $tableNames = array_map(static fn(array $row): string => (string)$row['name'], $catalog['tables']);
    $this->assertContains('unit_types', $tableNames);
    $this->assertContains('enemy_templates', $tableNames);
    $this->assertContains('items', $tableNames);

    $selected = $catalog['selected_table'];
    $this->assertIsArray($selected);
    $this->assertSame('unit_types', $selected['name']);
    $this->assertContains('ability_set_json', $selected['json_columns']);
    $this->assertGreaterThan(0, $selected['row_count']);
    $this->assertNotEmpty($selected['rows']);
    $firstRow = $selected['rows'][0] ?? [];
    $this->assertIsArray($firstRow);
    $this->assertArrayHasKey('slug', $firstRow);
    $this->assertIsArray($firstRow['ability_set_json']);
  }

  public function testCatalogIncludesLineageDefinitionsAndOwnedLineages(): void
  {
    $userId = $this->insertUser('qa_dev_lineages', 'QA Dev Lineages');
    $service = $this->makeService();

    $catalog = $service->getCatalog($userId);

    $lineageSlugs = array_map(static fn(array $row): string => (string)$row['lineage_slug'], $catalog['lineages']);
    $ownedLineageSlugs = array_map(static fn(array $row): string => (string)$row['lineage_slug'], $catalog['owned_lineages']);

    $this->assertContains('basic_goblin', $lineageSlugs);
    $this->assertContains('pig_kin', $lineageSlugs);
    $this->assertSame(['basic_goblin'], $ownedLineageSlugs);
  }

  public function testGrantLineageAddsExplicitOwnedLineage(): void
  {
    $userId = $this->insertUser('qa_dev_lineage_grant', 'QA Dev Lineage Grant');
    $service = $this->makeService();

    $ownedLineages = $service->grantLineage($userId, LineageUnlockService::PIG_KIN);
    $ownedLineageSlugs = array_map(static fn(array $row): string => (string)$row['lineage_slug'], $ownedLineages);

    $this->assertSame(['basic_goblin', 'pig_kin'], $ownedLineageSlugs);
    $this->assertSame('1', (string)$this->scalar(
      'SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = ? AND `unlock_key` = ?',
      [$userId, 'lineage', LineageUnlockService::PIG_KIN]
    ));
  }

  public function testSeedTableCatalogRejectsUnknownTables(): void
  {
    $service = $this->makeService();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unknown seeded table.');

    $service->getSeedTableCatalog('not_a_seed_table');
  }

  public function testGrantUnitsUsesTierEncodedInUnitTypeSlug(): void
  {
    $userId = $this->insertUser('qa_devtier', 'QA DevTier');
    $service = $this->makeService();

    $grantedUnits = $service->grantUnits($userId, 'frontline_bruiser_t2', 1);
    $unitId = (int)($grantedUnits[0]['id'] ?? 0);

    $this->assertGreaterThan(0, $unitId);
    $this->assertSame(2, (int)$this->scalar('SELECT `tier` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  private function makeService(): DevToolsService
  {
    $playerStateRepo = new PlayerStateRepository($this->pdo);
    $bootstrapper = new PlayerBootstrapper(
      $playerStateRepo,
      new EnergyRepository($this->pdo),
      new StarterPackProvisioningService(),
    );

    return new DevToolsService(
      $this->pdo,
      $bootstrapper,
      $playerStateRepo,
      new UnitRepository($this->pdo),
      new DiceRepository($this->pdo),
      new RegionRepository($this->pdo),
    );
  }
}
