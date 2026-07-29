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

    $report = $service->simulateBattle('the_farm', 'combat', 3, 'qa-battle-sim', 'basic_goblin_starter');

    $this->assertTrue($report['ok']);
    $this->assertSame('battle', $report['mode']);
    $this->assertSame('the_farm', $report['region']);
    $this->assertSame(3, $report['config']['samples']);
    $this->assertSame('basic_goblin_starter', $report['config']['profile']);
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

    $report = $service->simulateRun('the_farm', 2, 'qa-run-sim', 'pig_kin_starter');

    $this->assertTrue($report['ok']);
    $this->assertSame('run', $report['mode']);
    $this->assertSame(['combat', 'loot', 'hazard', 'shrine', 'boss'], $report['config']['node_types']);
    $this->assertSame('pig_kin_starter', $report['config']['profile']);
    $this->assertArrayHasKey('completion_rate', $report['summary']);
    $this->assertArrayHasKey('average_nodes_resolved', $report['summary']);
    $this->assertArrayHasKey('unit_defeats_per_sample', $report['summary']);
    $this->assertCount(2, $report['samples']);
  }

  public function testRunCanReturnSummaryOnlyReport(): void
  {
    $service = new BalanceSimulationService($this->pdo);

    $report = $service->run([
      'mode' => 'run',
      'region' => 'the_farm',
      'runs' => 2,
      'seed' => 'qa-summary-only-run-sim',
      'profile' => 'pig_kin_starter',
      'summary-only' => true,
    ]);

    $this->assertTrue($report['ok']);
    $this->assertSame('run', $report['mode']);
    $this->assertSame(2, $report['config']['samples']);
    $this->assertTrue($report['config']['summary_only']);
    $this->assertArrayHasKey('completion_rate', $report['summary']);
    $this->assertArrayNotHasKey('samples', $report);
  }

  public function testProfileFixtureMustBeSupported(): void
  {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unsupported simulation profile');

    (new BalanceSimulationService($this->pdo))->simulateBattle('the_farm', 'combat', 1, 'qa-profile-sim', 'bat_only');
  }
}
