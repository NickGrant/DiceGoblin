<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class RunCombatModifierService
{
  private const MODIFIABLE_STATS = ['attack', 'defense', 'precision', 'resolve'];
  private const DAMAGE_KEY = 'damage';

  /**
   * @param array<string,mixed> $unit
   * @return array<string,mixed>
   */
  public function applyModifiersToUnit(array $unit): array
  {
    $effects = $this->normalizeEffects($unit['run_status_effects'] ?? []);
    if ($effects === []) {
      $unit['run_combat_modifiers'] = [];
      return $unit;
    }

    $applied = [];
    foreach ($effects as $effect) {
      $statMultipliers = $effect['stat_multipliers'];
      $statAdders = $effect['stat_adders'];
      foreach (self::MODIFIABLE_STATS as $stat) {
        $base = max($stat === 'defense' ? 0 : 1, (int)($unit[$stat] ?? ($stat === 'defense' ? 0 : 1)));
        $added = $base + (int)($statAdders[$stat] ?? 0);
        $multiplier = (float)($statMultipliers[$stat] ?? 1.0);
        $unit[$stat] = max($stat === 'defense' ? 0 : 1, (int)floor($added * max(0.1, $multiplier)));
      }

      $damageMultiplier = (float)($statMultipliers[self::DAMAGE_KEY] ?? 1.0);
      if (abs($damageMultiplier - 1.0) > 0.0001) {
        $combatAffixes = is_array($unit['combat_affixes'] ?? null) ? $unit['combat_affixes'] : [];
        $combatAffixes['run_damage_multiplier'] = (float)($combatAffixes['run_damage_multiplier'] ?? 1.0) * $damageMultiplier;
        $unit['combat_affixes'] = $combatAffixes;
      }

      $applied[] = [
        'type' => $effect['type'],
        'source' => $effect['source'],
        'remaining_combats' => $effect['remaining_combats'],
        'stat_multipliers' => $statMultipliers,
        'stat_adders' => $statAdders,
      ];
    }

    $unit['run_combat_modifiers'] = $applied;
    return $unit;
  }

  /**
   * @param array<int,int|string> $unitIds
   * @return list<array{unit_instance_id:string,type:string,remaining_combats:int}>
   */
  public function consumeNextCombatModifiers(PDO $pdo, int $runId, array $unitIds): array
  {
    $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds), static fn(int $id): bool => $id > 0)));
    if ($runId <= 0 || $unitIds === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $stmt = $pdo->prepare("
      SELECT `unit_instance_id`, `status_effects_json`
      FROM `run_unit_state`
      WHERE `run_id` = ? AND `unit_instance_id` IN ($placeholders)
      FOR UPDATE
    ");
    $stmt->execute(array_merge([$runId], $unitIds));

    $consumed = [];
    $update = $pdo->prepare('
      UPDATE `run_unit_state`
      SET `status_effects_json` = ?
      WHERE `run_id` = ? AND `unit_instance_id` = ?
    ');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $unitId = (int)($row['unit_instance_id'] ?? 0);
      $rawEffects = json_decode((string)($row['status_effects_json'] ?? ''), true);
      $rawEffects = is_array($rawEffects) ? $rawEffects : [];
      $nextEffects = [];
      $changed = false;

      foreach ($rawEffects as $rawEffect) {
        if (!is_array($rawEffect)) {
          continue;
        }
        $normalized = $this->normalizeEffect($rawEffect);
        if ($normalized === null) {
          $nextEffects[] = $rawEffect;
          continue;
        }

        $remaining = max(0, $normalized['remaining_combats'] - 1);
        $changed = true;
        $consumed[] = [
          'unit_instance_id' => (string)$unitId,
          'type' => $normalized['type'],
          'remaining_combats' => $remaining,
        ];
        if ($remaining > 0) {
          $rawEffect['remaining_combats'] = $remaining;
          $nextEffects[] = $rawEffect;
        }
      }

      if ($changed) {
        $update->execute([json_encode(array_values($nextEffects), JSON_UNESCAPED_SLASHES), $runId, $unitId]);
      }
    }

    return $consumed;
  }

  /**
   * @param mixed $rawEffects
   * @return list<array{type:string,source:string,remaining_combats:int,stat_multipliers:array<string,float>,stat_adders:array<string,int>}>
   */
  private function normalizeEffects(mixed $rawEffects): array
  {
    if (is_string($rawEffects)) {
      $decoded = json_decode($rawEffects, true);
      $rawEffects = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($rawEffects)) {
      return [];
    }

    $effects = [];
    foreach ($rawEffects as $rawEffect) {
      if (!is_array($rawEffect)) {
        continue;
      }
      $effect = $this->normalizeEffect($rawEffect);
      if ($effect !== null) {
        $effects[] = $effect;
      }
    }

    return $effects;
  }

  /**
   * @param array<string,mixed> $rawEffect
   * @return array{type:string,source:string,remaining_combats:int,stat_multipliers:array<string,float>,stat_adders:array<string,int>}|null
   */
  private function normalizeEffect(array $rawEffect): ?array
  {
    $type = trim((string)($rawEffect['type'] ?? ''));
    if (!in_array($type, ['squad_damage_next_combat', 'stat_modifier_next_combat', 'squad_stat_modifier_next_combat', 'run_stat_modifier_next_combat'], true)) {
      return null;
    }

    $remainingCombats = max(1, (int)($rawEffect['remaining_combats'] ?? 1));
    $statMultipliers = $this->normalizeFloatMap($rawEffect['stat_multipliers'] ?? []);
    $statAdders = $this->normalizeIntMap($rawEffect['stat_adders'] ?? []);

    if ($type === 'squad_damage_next_combat') {
      $damageMultiplier = (float)($rawEffect['damage_multiplier'] ?? 1.0);
      if ($damageMultiplier > 0.0) {
        $statMultipliers[self::DAMAGE_KEY] = $damageMultiplier;
      }
    }

    $statMultipliers = array_intersect_key($statMultipliers, array_flip(array_merge(self::MODIFIABLE_STATS, [self::DAMAGE_KEY])));
    $statAdders = array_intersect_key($statAdders, array_flip(self::MODIFIABLE_STATS));
    if ($statMultipliers === [] && $statAdders === []) {
      return null;
    }

    return [
      'type' => $type,
      'source' => trim((string)($rawEffect['source'] ?? 'run')),
      'remaining_combats' => $remainingCombats,
      'stat_multipliers' => $statMultipliers,
      'stat_adders' => $statAdders,
    ];
  }

  /**
   * @return array<string,float>
   */
  private function normalizeFloatMap(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $key => $raw) {
      if (!is_numeric($raw)) {
        continue;
      }
      $out[(string)$key] = (float)$raw;
    }

    return $out;
  }

  /**
   * @return array<string,int>
   */
  private function normalizeIntMap(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $key => $raw) {
      if (!is_numeric($raw)) {
        continue;
      }
      $out[(string)$key] = (int)$raw;
    }

    return $out;
  }
}
