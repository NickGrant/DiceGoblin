<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Application\PermanentCapabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use PHPUnit\Framework\TestCase;

final class PermanentCapabilityPolicyTest extends TestCase
{
  public function testCapabilitiesRequireExactAuthoredUnlocksAndRespectProfileSizes(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $policy = new PermanentCapabilityPolicy($content);
    $profile = ['allowed_sizes' => [4, 6, 8, 10, 12, 20]];
    $this->assertSame(50, $policy->energyNormalMaximum([]));
    $this->assertSame(8, $policy->maxAcquirableDieSize(['unlock.capability.unknown']));
    $this->assertTrue($policy->canAcquireDie(8, $profile, []));
    $this->assertFalse($policy->canAcquireDie(10, $profile, []));
    $this->assertSame(75, $policy->energyNormalMaximum(['unlock.capability.energy_max_75']));
    $this->assertSame(100, $policy->energyNormalMaximum(['unlock.capability.energy_max_100', 'unlock.capability.energy_max_75']));
    foreach ([10, 12, 20] as $size) {
      $owned = ["unlock.capability.die_size_d{$size}"];
      $this->assertSame($size, $policy->maxAcquirableDieSize($owned));
      $this->assertTrue($policy->canAcquireDie($size, $profile, $owned));
      $this->assertFalse($policy->canAcquireDie($size, ['allowed_sizes' => [4, 6, 8]], $owned));
    }
    $this->assertFalse($policy->canAcquireDie(20, $profile, ['unlock.capability.die_size_d12']));
  }
}
