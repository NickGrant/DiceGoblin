<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Services\DevToolsService;
use DiceGoblins\Services\DiceAffixService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
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
    $service->grantUnits($userId, 'frontline_bruiser_t1', 1);
    $dice = $service->grantDice($userId, 6, 'rare', 1);
    $grantedDiceId = (int) ($dice[0]['id'] ?? 0);

    $nodeStmt = $this->pdo?->prepare(
      "INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`) VALUES (?, 1, 'combat', 'available', NULL, NULL)"
    );
    $nodeStmt?->execute([$runId]);
    $nodeId = (int) $this->pdo?->lastInsertId();

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
  }

  public function testSetUnitLevelClampsToOwnedUnitMaxLevel(): void
  {
    $userId = $this->insertUser('qa_devlevel', 'QA DevLevel');
    $service = $this->makeService();
    $grantedUnits = $service->grantUnits($userId, 'frontline_bruiser_t1', 1);
    $unitId = (int)($grantedUnits[0]['id'] ?? 0);

    $updated = $service->setUnitLevel($userId, $unitId, 99);

    $this->assertSame((string)$unitId, $updated['id']);
    $this->assertSame(6, $updated['level']);
    $this->assertSame(6, $updated['max_level']);
    $this->assertSame(6, (int)$this->scalar('SELECT `level` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  private function makeService(): DevToolsService
  {
    $playerStateRepo = new PlayerStateRepository($this->pdo);
    $bootstrapper = new PlayerBootstrapper(
      $playerStateRepo,
      new EnergyRepository($this->pdo),
      new GrantService(),
    );

    return new DevToolsService(
      $this->pdo,
      $bootstrapper,
      $playerStateRepo,
      new UnitRepository($this->pdo),
      new DiceRepository($this->pdo),
      new RegionRepository($this->pdo),
      new DiceAffixService($this->pdo),
    );
  }
}
