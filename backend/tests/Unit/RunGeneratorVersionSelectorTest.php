<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunGeneratorVersionSelector;
use PHPUnit\Framework\TestCase;

final class RunGeneratorVersionSelectorTest extends TestCase
{
  private ?string $previousPatternV1Env = null;
  private ?string $previousPatternV2Env = null;

  protected function setUp(): void
  {
    parent::setUp();
    $patternV1 = getenv('RUN_PATTERN_V1_REGIONS');
    $patternV2 = getenv('RUN_PATTERN_V2_REGIONS');
    $this->previousPatternV1Env = $patternV1 === false ? null : $patternV1;
    $this->previousPatternV2Env = $patternV2 === false ? null : $patternV2;
  }

  protected function tearDown(): void
  {
    $this->restoreEnv('RUN_PATTERN_V1_REGIONS', $this->previousPatternV1Env);
    $this->restoreEnv('RUN_PATTERN_V2_REGIONS', $this->previousPatternV2Env);

    parent::tearDown();
  }

  public function testDefaultsToLaneV1(): void
  {
    $this->setEnv('');
    $this->setEnv('', 'RUN_PATTERN_V2_REGIONS');

    $this->assertSame('lane-v1', (new RunGeneratorVersionSelector())->generatorVersionForRegion('mountains'));
  }

  public function testOptedInRegionsUsePatternV1(): void
  {
    $this->setEnv('mountains, swamps');
    $this->setEnv('', 'RUN_PATTERN_V2_REGIONS');

    $selector = new RunGeneratorVersionSelector();

    $this->assertSame('pattern-v1', $selector->generatorVersionForRegion('mountains'));
    $this->assertSame('pattern-v1', $selector->generatorVersionForRegion('swamps'));
    $this->assertSame('lane-v1', $selector->generatorVersionForRegion('the_farm'));
  }

  public function testOptedInPatternV2RegionsUsePatternV2(): void
  {
    $this->setEnv('swamps');
    $this->setEnv('mountains, swamps', 'RUN_PATTERN_V2_REGIONS');

    $selector = new RunGeneratorVersionSelector();

    $this->assertSame('pattern-v2', $selector->generatorVersionForRegion('mountains'));
    $this->assertSame('pattern-v2', $selector->generatorVersionForRegion('swamps'));
    $this->assertSame('lane-v1', $selector->generatorVersionForRegion('the_farm'));
  }

  private function setEnv(string $value, string $key = 'RUN_PATTERN_V1_REGIONS'): void
  {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }

  private function restoreEnv(string $key, ?string $value): void
  {
    if ($value === null) {
      putenv($key);
      unset($_ENV[$key], $_SERVER[$key]);
      return;
    }

    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }
}
