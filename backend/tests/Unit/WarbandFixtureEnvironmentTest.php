<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Application\Commands\WarbandFixtureEnvironment;
use PHPUnit\Framework\TestCase;

final class WarbandFixtureEnvironmentTest extends TestCase
{
  public function testOnlyExplicitSafeEnvironmentNamesEnableFixtures(): void
  {
    foreach (['dev', 'test', 'uat'] as $environment) {
      $this->assertTrue(WarbandFixtureEnvironment::isEnabled($environment, '1'));
    }
    foreach ([null, '', 'prod', 'production', 'Dev', 'TEST', 'unexpected'] as $environment) {
      $this->assertFalse(WarbandFixtureEnvironment::isEnabled($environment, '1'));
    }
    foreach ([null, '', '0', 'true', 'yes', '01'] as $optIn) {
      $this->assertFalse(WarbandFixtureEnvironment::isEnabled('dev', $optIn));
    }
  }
}
