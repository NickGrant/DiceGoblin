<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunGeneratorVersionSelector;
use PHPUnit\Framework\TestCase;

final class RunGeneratorVersionSelectorTest extends TestCase
{
  private ?string $previousEnv = null;

  protected function setUp(): void
  {
    parent::setUp();
    $value = getenv('RUN_PATTERN_V1_REGIONS');
    $this->previousEnv = $value === false ? null : $value;
  }

  protected function tearDown(): void
  {
    if ($this->previousEnv === null) {
      putenv('RUN_PATTERN_V1_REGIONS');
      unset($_ENV['RUN_PATTERN_V1_REGIONS'], $_SERVER['RUN_PATTERN_V1_REGIONS']);
    } else {
      putenv('RUN_PATTERN_V1_REGIONS=' . $this->previousEnv);
      $_ENV['RUN_PATTERN_V1_REGIONS'] = $this->previousEnv;
      $_SERVER['RUN_PATTERN_V1_REGIONS'] = $this->previousEnv;
    }

    parent::tearDown();
  }

  public function testDefaultsToLaneV1(): void
  {
    $this->setEnv('');

    $this->assertSame('lane-v1', (new RunGeneratorVersionSelector())->generatorVersionForRegion('mountains'));
  }

  public function testOptedInRegionsUsePatternV1(): void
  {
    $this->setEnv('mountains, swamps');

    $selector = new RunGeneratorVersionSelector();

    $this->assertSame('pattern-v1', $selector->generatorVersionForRegion('mountains'));
    $this->assertSame('pattern-v1', $selector->generatorVersionForRegion('swamps'));
    $this->assertSame('lane-v1', $selector->generatorVersionForRegion('the_farm'));
  }

  private function setEnv(string $value): void
  {
    putenv('RUN_PATTERN_V1_REGIONS=' . $value);
    $_ENV['RUN_PATTERN_V1_REGIONS'] = $value;
    $_SERVER['RUN_PATTERN_V1_REGIONS'] = $value;
  }
}
