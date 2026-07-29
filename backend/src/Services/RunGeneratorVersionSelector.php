<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Core\Env;

final class RunGeneratorVersionSelector
{
  public function generatorVersionForRegion(string $regionSlug): string
  {
    $enabledPatternV2Regions = $this->enabledPatternRegions('RUN_PATTERN_V2_REGIONS');
    if (isset($enabledPatternV2Regions[$regionSlug])) {
      return 'pattern-v2';
    }

    $enabledPatternV1Regions = $this->enabledPatternRegions('RUN_PATTERN_V1_REGIONS');
    return isset($enabledPatternV1Regions[$regionSlug]) ? 'pattern-v1' : 'lane-v1';
  }

  /**
   * @return array<string,true>
   */
  private function enabledPatternRegions(string $envKey): array
  {
    $raw = trim((string)Env::get($envKey, ''));
    if ($raw === '') {
      return [];
    }

    $regions = [];
    foreach (explode(',', $raw) as $region) {
      $region = trim($region);
      if ($region !== '') {
        $regions[$region] = true;
      }
    }

    return $regions;
  }
}
