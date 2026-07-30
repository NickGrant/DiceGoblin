<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class ShrineTuningSamplerService
{
  private const QUALITIES = ['poor', 'good', 'great'];

  public function __construct(
    private readonly EncounterPrimitiveCatalog $catalog = new EncounterPrimitiveCatalog(),
  ) {}

  /**
   * @param list<string> $regions
   * @return array<string,mixed>
   */
  public function sample(array $regions, int $samples, bool $allowDeclineable, string $seedPrefix = 'shrine-tuning'): array
  {
    $regions = $this->normalizeRegions($regions);
    $samples = max(1, $samples);
    $report = [
      'samples_per_quality' => $samples,
      'allow_declineable' => $allowDeclineable,
      'regions' => [],
    ];

    foreach ($regions as $region) {
      $qualityReports = [];
      foreach (self::QUALITIES as $quality) {
        $qualityReports[$quality] = $this->sampleQuality($region, $quality, $samples, $allowDeclineable, $seedPrefix);
      }
      $report['regions'][$region] = $qualityReports;
    }

    return $report;
  }

  /**
   * @return list<string>
   */
  private function normalizeRegions(array $regions): array
  {
    $normalized = [];
    foreach ($regions as $region) {
      $slug = trim((string)$region);
      if ($slug !== '' && !in_array($slug, $normalized, true)) {
        $normalized[] = $slug;
      }
    }

    return $normalized === [] ? ['the_farm', 'mountains', 'swamps'] : $normalized;
  }

  /**
   * @return array<string,mixed>
   */
  private function sampleQuality(string $region, string $quality, int $samples, bool $allowDeclineable, string $seedPrefix): array
  {
    $effects = [];
    $primitives = [];
    $declineableCount = 0;
    $currencyTotal = 0;

    for ($index = 0; $index < $samples; $index++) {
      $rng = $this->rng("{$seedPrefix}|{$region}|{$quality}|{$index}");
      $result = $this->catalog->resolveNodeEffect('shrine', $rng, null, [
        'region_slug' => $region,
        'quality' => $quality,
        'allow_declineable' => $allowDeclineable,
      ]);

      $slug = (string)($result['slug'] ?? 'unknown');
      $primitive = (string)($result['primitive'] ?? 'unknown');
      $effect = is_array($result['result'] ?? null) ? $result['result'] : [];
      $effects[$slug] = ($effects[$slug] ?? 0) + 1;
      $primitives[$primitive] = ($primitives[$primitive] ?? 0) + 1;
      $declineableCount += !empty($effect['declineable']) ? 1 : 0;
      $currencyTotal += max(0, (int)($result['currency_soft'] ?? 0));
    }

    ksort($effects);
    ksort($primitives);
    $primitivePercentages = [];
    foreach ($primitives as $primitive => $count) {
      $primitivePercentages[$primitive] = round(($count / $samples) * 100, 1);
    }

    return [
      'effect_counts' => $effects,
      'primitive_counts' => $primitives,
      'primitive_percentages' => $primitivePercentages,
      'declineable_count' => $declineableCount,
      'avg_currency_soft' => round($currencyTotal / $samples, 2),
    ];
  }

  private function rng(string $seed): callable
  {
    $state = (int)sprintf('%u', crc32($seed));
    return static function (int $max) use (&$state): int {
      $max = max(1, $max);
      $state = (int)(($state * 1664525 + 1013904223) & 0xffffffff);
      return $state % $max;
    };
  }
}
