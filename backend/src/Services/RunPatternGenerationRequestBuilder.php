<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use RuntimeException;

final class RunPatternGenerationRequestBuilder
{
  public function __construct(
    private readonly RunPatternCatalogRepository $catalog,
    private readonly RunPatternVariantCompiler $variantCompiler = new RunPatternVariantCompiler(),
    private readonly RunPatternV2GridCompiler $v2GridCompiler = new RunPatternV2GridCompiler()
  ) {
  }

  /**
   * @return array<string,mixed>
   */
  public function build(string $regionSlug, string $seed, string $generatorVersion = 'pattern-v1'): array
  {
    $profile = $this->catalog->findEnabledProfile($regionSlug, $generatorVersion);
    if ($profile === null) {
      throw new RuntimeException("No enabled {$generatorVersion} generation profile exists for {$regionSlug}.");
    }

    $rules = $this->catalog->listEnabledRules($regionSlug, $generatorVersion);
    if ($rules === []) {
      throw new RuntimeException("No enabled {$generatorVersion} pattern rules exist for {$regionSlug}.");
    }

    $patterns = $this->patternsByKey($this->catalog->listEnabledPatternDefinitions(), $rules);
    $variants = $generatorVersion === 'pattern-v2' ? [] : $this->compileVariants($patterns);
    $tiles = $generatorVersion === 'pattern-v2' ? $this->compileV2Tiles($patterns) : [];

    return [
      'region_slug' => $regionSlug,
      'seed' => $seed,
      'generator_version' => $generatorVersion,
      'profile_version' => (int)$profile['profile_version'],
      'catalog_hash' => $this->catalogHash($profile, $rules, $patterns),
      'profile' => $profile,
      'rules_by_phase' => $this->rulesByPhase($rules),
      'patterns_by_key' => $patterns,
      'variants_by_pattern_key' => $variants,
      'tiles_by_pattern_key' => $tiles,
      'story_placement_requests' => [],
    ];
  }

  /**
   * @param array<string,array<string,mixed>> $patterns
   * @return array<string,list<array<string,mixed>>>
   */
  private function compileVariants(array $patterns): array
  {
    $variants = [];
    foreach ($patterns as $patternKey => $pattern) {
      $variants[$patternKey] = $this->variantCompiler->compile($pattern['definition']);
    }

    return $variants;
  }

  /**
   * @param array<string,array<string,mixed>> $patterns
   * @return array<string,array<string,mixed>>
   */
  private function compileV2Tiles(array $patterns): array
  {
    $tiles = [];
    foreach ($patterns as $patternKey => $pattern) {
      $tiles[$patternKey] = $this->v2GridCompiler->compile($pattern['definition']);
    }

    return $tiles;
  }

  /**
   * @param list<array<string,mixed>> $definitions
   * @param list<array<string,mixed>> $rules
   * @return array<string,array<string,mixed>>
   */
  private function patternsByKey(array $definitions, array $rules): array
  {
    $required = [];
    foreach ($rules as $rule) {
      $required[$this->patternKey($rule)] = true;
    }

    $patterns = [];
    foreach ($definitions as $definition) {
      $key = $this->patternKey($definition);
      if (isset($required[$key])) {
        $patterns[$key] = $definition;
      }
    }

    $missing = array_values(array_diff(array_keys($required), array_keys($patterns)));
    if ($missing !== []) {
      throw new RuntimeException('Missing enabled pattern definitions for rules: ' . implode(', ', $missing));
    }

    ksort($patterns);
    return $patterns;
  }

  /**
   * @param list<array<string,mixed>> $rules
   * @return array<string,list<array<string,mixed>>>
   */
  private function rulesByPhase(array $rules): array
  {
    $grouped = [];
    foreach ($rules as $rule) {
      $phase = (string)$rule['allowed_phase'];
      $grouped[$phase] ??= [];
      $grouped[$phase][] = $rule;
    }

    ksort($grouped);
    return $grouped;
  }

  /**
   * @param array<string,mixed> $row
   */
  private function patternKey(array $row): string
  {
    $slug = (string)($row['pattern_slug'] ?? $row['slug'] ?? '');
    $version = (int)($row['pattern_version'] ?? $row['version'] ?? 0);
    return "{$slug}@{$version}";
  }

  /**
   * @param array<string,mixed> $profile
   * @param list<array<string,mixed>> $rules
   * @param array<string,array<string,mixed>> $patterns
   */
  private function catalogHash(array $profile, array $rules, array $patterns): string
  {
    $ruleHashes = array_map(
      static fn(array $rule): array => [
        'pattern' => (string)$rule['pattern_slug'] . '@' . (int)$rule['pattern_version'],
        'phase' => (string)$rule['allowed_phase'],
        'weight' => (int)$rule['base_weight'],
        'min_depth' => (int)$rule['min_depth'],
        'max_depth' => $rule['max_depth'],
        'max_per_run' => $rule['max_per_run'],
        'cooldown_patterns' => (int)$rule['cooldown_patterns'],
        'weight_modifiers' => is_array($rule['weight_modifiers'] ?? null) ? $rule['weight_modifiers'] : [],
      ],
      $rules
    );
    usort($ruleHashes, static fn(array $a, array $b): int => strcmp((string)$a['pattern'] . ':' . (string)$a['phase'], (string)$b['pattern'] . ':' . (string)$b['phase']));

    $patternHashes = [];
    foreach ($patterns as $key => $pattern) {
      $patternHashes[$key] = (string)$pattern['content_hash'];
    }
    ksort($patternHashes);

    $json = json_encode([
      'profile' => (string)$profile['content_hash'],
      'rules' => $ruleHashes,
      'patterns' => $patternHashes,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
      throw new RuntimeException('Unable to hash run pattern generation request.');
    }

    return hash('sha256', $json);
  }
}
