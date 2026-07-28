<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;

final class RunPatternCatalogRepository
{
  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * @return array<string,mixed>|null
   */
  public function findEnabledProfile(string $regionSlug, string $generatorVersion): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        rgp.`id`,
        r.`slug` AS `region_slug`,
        rgp.`generator_version`,
        rgp.`profile_version`,
        rgp.`bounds_json`,
        rgp.`budgets_json`,
        rgp.`requirements_json`,
        rgp.`retry_policy_json`,
        rgp.`weight_policy_json`,
        rgp.`content_hash`
      FROM `run_generation_profiles` rgp
      INNER JOIN `regions` r ON r.`id` = rgp.`region_id`
      WHERE r.`slug` = ?
        AND rgp.`generator_version` = ?
        AND rgp.`enabled` = 1
      ORDER BY rgp.`profile_version` DESC
      LIMIT 1
    ');
    $stmt->execute([$regionSlug, $generatorVersion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return null;
    }

    return [
      'id' => (int)$row['id'],
      'region_slug' => (string)$row['region_slug'],
      'generator_version' => (string)$row['generator_version'],
      'profile_version' => (int)$row['profile_version'],
      'bounds' => $this->decodeJson((string)$row['bounds_json']),
      'budgets' => $this->decodeJson((string)$row['budgets_json']),
      'requirements' => $this->decodeJson((string)$row['requirements_json']),
      'retry_policy' => $this->decodeJson((string)$row['retry_policy_json']),
      'weight_policy' => $this->decodeJson((string)$row['weight_policy_json']),
      'content_hash' => (string)$row['content_hash'],
    ];
  }

  /**
   * @return list<array<string,mixed>>
   */
  public function listEnabledRules(string $regionSlug, string $generatorVersion, ?string $phase = null): array
  {
    $params = [$regionSlug, $generatorVersion];
    $phaseSql = '';
    if ($phase !== null) {
      $phaseSql = ' AND rpr.`allowed_phase` = ?';
      $params[] = $phase;
    }

    $stmt = $this->pdo->prepare("
      SELECT
        rpr.`id`,
        rpd.`slug` AS `pattern_slug`,
        rpd.`version` AS `pattern_version`,
        rpr.`base_weight`,
        rpr.`allowed_phase`,
        rpr.`min_depth`,
        rpr.`max_depth`,
        rpr.`max_per_run`,
        rpr.`cooldown_patterns`,
        rpr.`weight_modifiers_json`
      FROM `run_pattern_region_rules` rpr
      INNER JOIN `run_pattern_definitions` rpd ON rpd.`id` = rpr.`pattern_definition_id`
      INNER JOIN `regions` r ON r.`id` = rpr.`region_id`
      WHERE r.`slug` = ?
        AND rpr.`generator_version` = ?
        AND rpr.`enabled` = 1
        AND rpd.`status` = 'enabled'
        {$phaseSql}
      ORDER BY rpr.`allowed_phase` ASC, rpr.`base_weight` DESC, rpd.`slug` ASC
    ");
    $stmt->execute($params);

    $rules = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $rules[] = [
        'id' => (int)$row['id'],
        'pattern_slug' => (string)$row['pattern_slug'],
        'pattern_version' => (int)$row['pattern_version'],
        'base_weight' => (int)$row['base_weight'],
        'allowed_phase' => (string)$row['allowed_phase'],
        'min_depth' => (int)$row['min_depth'],
        'max_depth' => $row['max_depth'] === null ? null : (int)$row['max_depth'],
        'max_per_run' => $row['max_per_run'] === null ? null : (int)$row['max_per_run'],
        'cooldown_patterns' => (int)$row['cooldown_patterns'],
        'weight_modifiers' => $this->decodeJson((string)($row['weight_modifiers_json'] ?? '{}')),
      ];
    }

    return $rules;
  }

  /**
   * @return list<array<string,mixed>>
   */
  public function listEnabledPatternDefinitions(): array
  {
    $stmt = $this->pdo->query("
      SELECT `id`, `slug`, `version`, `definition_json`, `content_hash`
      FROM `run_pattern_definitions`
      WHERE `status` = 'enabled'
      ORDER BY `slug` ASC, `version` ASC
    ");

    $patterns = [];
    foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
      $patterns[] = [
        'id' => (int)$row['id'],
        'slug' => (string)$row['slug'],
        'version' => (int)$row['version'],
        'definition' => $this->decodeJson((string)$row['definition_json']),
        'content_hash' => (string)$row['content_hash'],
      ];
    }

    return $patterns;
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeJson(string $json): array
  {
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
  }
}
