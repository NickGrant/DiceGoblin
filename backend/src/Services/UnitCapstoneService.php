<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class UnitCapstoneService
{
  public function __construct(private readonly PDO $pdo) {}

  /**
   * @param array<int,int> $unitInstanceIds
   * @return array<string, list<array{
   *   source_unit_type_id:string,
   *   source_unit_type_slug:string,
   *   source_unit_type_name:string,
   *   ability_id:string
   * }>>
   */
  public function getSelectionsForUnitIds(array $unitInstanceIds): array
  {
    if (count($unitInstanceIds) === 0) {
      return [];
    }

    $unitInstanceIds = array_values(array_unique(array_map(static fn(int $value): int => max(0, $value), $unitInstanceIds)));
    $placeholders = implode(',', array_fill(0, count($unitInstanceIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT
        uicc.`unit_instance_id`,
        uicc.`ability_id`,
        ut.`id` AS `source_unit_type_id`,
        ut.`slug` AS `source_unit_type_slug`,
        ut.`name` AS `source_unit_type_name`
      FROM `unit_instance_capstone_choices` uicc
      JOIN `unit_types` ut ON ut.`id` = uicc.`source_unit_type_id`
      WHERE uicc.`unit_instance_id` IN ($placeholders)
      ORDER BY uicc.`unit_instance_id` ASC, ut.`id` ASC
    ");
    $stmt->execute($unitInstanceIds);

    $byUnit = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $unitId = (string)$row['unit_instance_id'];
      $byUnit[$unitId] ??= [];
      $byUnit[$unitId][] = [
        'source_unit_type_id' => (string)$row['source_unit_type_id'],
        'source_unit_type_slug' => (string)$row['source_unit_type_slug'],
        'source_unit_type_name' => (string)$row['source_unit_type_name'],
        'ability_id' => (string)$row['ability_id'],
      ];
    }

    return $byUnit;
  }

  /**
   * @return array{
   *   source_unit_type_id:string,
   *   source_unit_type_slug:string,
   *   source_unit_type_name:string,
   *   ability_id:string
   * }
   */
  public function selectCapstone(int $userId, int $unitInstanceId, string $abilityId): array
  {
    $abilityId = trim($abilityId);
    if ($abilityId === '') {
      throw new RuntimeException('ability_id is required.');
    }

    $stmt = $this->pdo->prepare('
      SELECT
        ui.`id` AS `unit_instance_id`,
        ui.`unit_type_id`,
        ui.`level`,
        ut.`slug` AS `unit_type_slug`,
        ut.`name` AS `unit_type_name`,
        ut.`max_level`,
        ut.`capstone_choices_json`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`id` = ? AND ui.`user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitInstanceId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      throw new RuntimeException('Unit not found.');
    }

    $maxLevel = max(1, (int)($row['max_level'] ?? 1));
    $level = max(1, (int)($row['level'] ?? 1));
    if ($level < $maxLevel) {
      throw new RuntimeException('Capstone choice requires mastery first.');
    }

    $choices = $this->decodeCapstoneChoices($row['capstone_choices_json'] ?? null);
    if (!in_array($abilityId, $choices, true)) {
      throw new RuntimeException('Selected capstone is not valid for this unit type.');
    }

    $unitTypeId = (int)$row['unit_type_id'];
    $upsert = $this->pdo->prepare('
      INSERT INTO `unit_instance_capstone_choices` (`unit_instance_id`, `source_unit_type_id`, `ability_id`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `ability_id` = VALUES(`ability_id`),
        `updated_at` = CURRENT_TIMESTAMP
    ');
    $upsert->execute([$unitInstanceId, $unitTypeId, $abilityId]);

    $sourceTier = $this->tierFromSlug((string)($row['unit_type_slug'] ?? ''));
    $unlock = $this->pdo->prepare('
      INSERT IGNORE INTO `unit_instance_unlocked_abilities` (`unit_instance_id`, `ability_id`, `source_tier`, `source_unit_type_id`)
      VALUES (?, ?, ?, ?)
    ');
    $unlock->execute([$unitInstanceId, $abilityId, $sourceTier, $unitTypeId]);

    return [
      'source_unit_type_id' => (string)$unitTypeId,
      'source_unit_type_slug' => (string)$row['unit_type_slug'],
      'source_unit_type_name' => (string)$row['unit_type_name'],
      'ability_id' => $abilityId,
    ];
  }

  /**
   * @param mixed $choicesRaw
   * @return list<string>
   */
  public function decodeCapstoneChoices(mixed $choicesRaw): array
  {
    if (is_string($choicesRaw)) {
      $decoded = json_decode($choicesRaw, true);
      $choicesRaw = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($choicesRaw)) {
      return [];
    }

    $values = $choicesRaw['choices'] ?? [];
    if (!is_array($values)) {
      return [];
    }

    $choices = [];
    foreach ($values as $value) {
      $normalized = trim((string)$value);
      if ($normalized === '' || in_array($normalized, $choices, true)) {
        continue;
      }
      $choices[] = $normalized;
    }

    return $choices;
  }

  private function tierFromSlug(string $slug): int
  {
    if (preg_match('/_t(\d+)$/', $slug, $matches) === 1) {
      return max(1, (int)($matches[1] ?? 1));
    }

    return 1;
  }
}
