<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Core\Env;

final class RunGeneratorVersionSelector
{
  public function generatorVersionForRegion(string $regionSlug): string
  {
    $enabledRegions = $this->enabledPatternRegions();
    return isset($enabledRegions[$regionSlug]) ? 'pattern-v1' : 'lane-v1';
  }

  /**
   * @return array<string,true>
   */
  private function enabledPatternRegions(): array
  {
    $raw = trim((string)Env::get('RUN_PATTERN_V1_REGIONS', ''));
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
