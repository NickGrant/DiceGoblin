<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\BalanceSimulationService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class BalanceSimulationServiceIntegrationTest extends IntegrationTestCase
{
  public function testBattleSimulationReportsCoreMetricsAndCleansUpUsers(): void
  {
    $service = new BalanceSimulationService($this->pdo);

    $report = $service->simulateBattle('the_farm', 'combat', 3, 'qa-battle-sim');

    $this->assertTrue($report['ok']);
    $this->assertSame('battle', $report['mode']);
    $this->assertSame('the_farm', $report['region']);
    $this->assertSame(3, $report['config']['samples']);
    $this->assertArrayHasKey('node_win_rate', $report['summary']);
    $this->assertArrayHasKey('average_rounds', $report['summary']);
    $this->assertArrayHasKey('average_hp_remaining_pct', $report['summary']);
    $this->assertArrayHasKey('soft_currency_per_sample', $report['summary']);
    $this->assertCount(3, $report['samples']);
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `users` WHERE `discord_id` LIKE 'sim\\_%'", []));
  }

  public function testRunSimulationIncludesRepresentativeNodeTypes(): void
  {
    $service = new BalanceSimulationService($this->pdo);

    $report = $service->simulateRun('the_farm', 2, 'qa-run-sim');

    $this->assertTrue($report['ok']);
    $this->assertSame('run', $report['mode']);
    $this->assertSame(['combat', 'loot', 'hazard', 'shrine', 'boss'], $report['config']['node_types']);
    $this->assertArrayHasKey('completion_rate', $report['summary']);
    $this->assertArrayHasKey('average_nodes_resolved', $report['summary']);
    $this->assertArrayHasKey('unit_defeats_per_sample', $report['summary']);
    $this->assertCount(2, $report['samples']);
  }
}
