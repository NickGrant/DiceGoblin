<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class RunPatternCatalogSyncService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly ?RunPatternCatalogValidator $validator = null
  ) {
  }

  /**
   * @return array{patterns:int,rules:int,profiles:int,catalog_hash:string}
   */
  public function syncDefaultCatalog(): array
  {
    return $this->syncDirectory(dirname(__DIR__, 2) . '/data/run-patterns');
  }

  /**
   * @return array{patterns:int,rules:int,profiles:int,catalog_hash:string}
   */
  public function syncDirectory(string $catalogRoot): array
  {
    $validator = $this->validator ?? new RunPatternCatalogValidator();
    $validation = $validator->validateDirectory($catalogRoot);
    if (!$validation['valid']) {
      throw new RuntimeException('run_pattern_catalog_invalid: ' . implode('; ', $validation['errors']));
    }

    $catalog = $this->loadCatalog($catalogRoot);
    $this->pdo->beginTransaction();
    try {
      $patternIds = $this->syncPatterns($catalog['patterns']);
      $regionIds = $this->regionIdsBySlug();
      $rules = $this->syncRules($catalog['rules'], $patternIds, $regionIds);
      $profiles = $this->syncProfiles($catalog['profiles'], $regionIds);
      $this->pdo->commit();
    } catch (\Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }

    return [
      'patterns' => count($patternIds),
      'rules' => $rules,
      'profiles' => $profiles,
      'catalog_hash' => (string)$validation['catalog_hash'],
    ];
  }

  /**
   * @param list<array<string,mixed>> $patterns
   * @return array<string,int>
   */
  private function syncPatterns(array $patterns): array
  {
    $ids = [];
    $stmt = $this->pdo->prepare('
      INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `status` = VALUES(`status`),
        `definition_json` = VALUES(`definition_json`),
        `content_hash` = VALUES(`content_hash`)
    ');

    foreach ($patterns as $pattern) {
      $slug = (string)$pattern['slug'];
      $version = (int)$pattern['version'];
      $json = $this->encodeJson($pattern);
      $stmt->execute([$slug, $version, (string)$pattern['status'], $json, hash('sha256', $json)]);
      $ids["{$slug}@{$version}"] = $this->patternDefinitionId($slug, $version);
    }

    return $ids;
  }

  /**
   * @param list<array<string,mixed>> $rules
   * @param array<string,int> $patternIds
   * @param array<string,int> $regionIds
   */
  private function syncRules(array $rules, array $patternIds, array $regionIds): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `run_pattern_region_rules` (
        `pattern_definition_id`,
        `region_id`,
        `generator_version`,
        `base_weight`,
        `allowed_phase`,
        `min_depth`,
        `max_depth`,
        `max_per_run`,
        `cooldown_patterns`,
        `enabled`,
        `weight_modifiers_json`
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `base_weight` = VALUES(`base_weight`),
        `max_depth` = VALUES(`max_depth`),
        `max_per_run` = VALUES(`max_per_run`),
        `cooldown_patterns` = VALUES(`cooldown_patterns`),
        `enabled` = VALUES(`enabled`),
        `weight_modifiers_json` = VALUES(`weight_modifiers_json`)
    ');

    $count = 0;
    foreach ($rules as $rule) {
      $patternKey = (string)$rule['pattern_slug'] . '@' . (int)$rule['pattern_version'];
      $regionSlug = (string)$rule['region_slug'];
      $stmt->execute([
        $patternIds[$patternKey] ?? throw new RuntimeException("Missing pattern {$patternKey}."),
        $regionIds[$regionSlug] ?? throw new RuntimeException("Missing region {$regionSlug}."),
        (string)$rule['generator_version'],
        (int)$rule['base_weight'],
        (string)$rule['allowed_phase'],
        (int)$rule['min_depth'],
        $rule['max_depth'] === null ? null : (int)$rule['max_depth'],
        $rule['max_per_run'] === null ? null : (int)$rule['max_per_run'],
        (int)$rule['cooldown_patterns'],
        (bool)$rule['enabled'] ? 1 : 0,
        $this->encodeJson(is_array($rule['weight_modifiers'] ?? null) ? $rule['weight_modifiers'] : []),
      ]);
      $count++;
    }

    return $count;
  }

  /**
   * @param list<array<string,mixed>> $profiles
   * @param array<string,int> $regionIds
   */
  private function syncProfiles(array $profiles, array $regionIds): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `run_generation_profiles` (
        `region_id`,
        `generator_version`,
        `profile_version`,
        `enabled`,
        `bounds_json`,
        `budgets_json`,
        `requirements_json`,
        `retry_policy_json`,
        `weight_policy_json`,
        `content_hash`
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `enabled` = VALUES(`enabled`),
        `bounds_json` = VALUES(`bounds_json`),
        `budgets_json` = VALUES(`budgets_json`),
        `requirements_json` = VALUES(`requirements_json`),
        `retry_policy_json` = VALUES(`retry_policy_json`),
        `weight_policy_json` = VALUES(`weight_policy_json`),
        `content_hash` = VALUES(`content_hash`)
    ');

    $count = 0;
    foreach ($profiles as $profile) {
      $regionSlug = (string)$profile['region_slug'];
      $profileJson = $this->encodeJson($profile);
      $stmt->execute([
        $regionIds[$regionSlug] ?? throw new RuntimeException("Missing region {$regionSlug}."),
        (string)$profile['generator_version'],
        (int)$profile['profile_version'],
        (bool)$profile['enabled'] ? 1 : 0,
        $this->encodeJson(is_array($profile['bounds'] ?? null) ? $profile['bounds'] : []),
        $this->encodeJson(is_array($profile['budgets'] ?? null) ? $profile['budgets'] : []),
        $this->encodeJson(is_array($profile['requirements'] ?? null) ? $profile['requirements'] : []),
        $this->encodeJson(is_array($profile['retry_policy'] ?? null) ? $profile['retry_policy'] : []),
        $this->encodeJson(is_array($profile['weight_policy'] ?? null) ? $profile['weight_policy'] : []),
        hash('sha256', $profileJson),
      ]);
      $count++;
    }

    return $count;
  }

  /**
   * @return array<string,int>
   */
  private function regionIdsBySlug(): array
  {
    $stmt = $this->pdo->query('SELECT `id`, `slug` FROM `regions`');
    $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $ids = [];
    foreach ($rows as $row) {
      $ids[(string)$row['slug']] = (int)$row['id'];
    }
    return $ids;
  }

  private function patternDefinitionId(string $slug, int $version): int
  {
    $stmt = $this->pdo->prepare('SELECT `id` FROM `run_pattern_definitions` WHERE `slug` = ? AND `version` = ?');
    $stmt->execute([$slug, $version]);
    return (int)$stmt->fetchColumn();
  }

  /**
   * @return array{patterns:list<array<string,mixed>>,rules:list<array<string,mixed>>,profiles:list<array<string,mixed>>}
   */
  private function loadCatalog(string $catalogRoot): array
  {
    $patternData = $this->decodeJsonFile($catalogRoot . '/shared/patterns.json');
    $rules = [];
    foreach (glob($catalogRoot . '/*/rules.json') ?: [] as $path) {
      $data = $this->decodeJsonFile($path);
      foreach (is_array($data['rules'] ?? null) ? $data['rules'] : [] as $rule) {
        if (is_array($rule)) {
          $rules[] = [
            ...$rule,
            'region_slug' => (string)($data['region_slug'] ?? ''),
            'generator_version' => (string)($data['generator_version'] ?? ''),
          ];
        }
      }
    }

    $profiles = [];
    foreach (glob($catalogRoot . '/profiles/*.json') ?: [] as $path) {
      $data = $this->decodeJsonFile($path);
      if (is_array($data)) {
        $profiles[] = $data;
      }
    }

    return [
      'patterns' => array_values(array_filter(is_array($patternData['patterns'] ?? null) ? $patternData['patterns'] : [], 'is_array')),
      'rules' => $rules,
      'profiles' => $profiles,
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeJsonFile(string $path): array
  {
    $raw = file_get_contents($path);
    if ($raw === false) {
      throw new RuntimeException("Unable to read {$path}.");
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      throw new RuntimeException("Invalid JSON in {$path}.");
    }
    return $data;
  }

  /**
   * @param array<mixed> $value
   */
  private function encodeJson(array $value): string
  {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      throw new RuntimeException('Unable to encode run pattern catalog JSON.');
    }
    return $json;
  }
}
