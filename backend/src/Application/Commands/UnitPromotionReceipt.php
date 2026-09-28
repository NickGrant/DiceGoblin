<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Support\ClientSafeInteger;

/** Validates a finalized receipt without consulting mutable unit state or current authored prices. */
final class UnitPromotionReceipt
{
  /** @param array<string,mixed> $result */
  public static function validate(array $result, UnitPromotionRequest $request): void
  {
    $promotion = $result['promotion'] ?? null;
    $spend = $result['spend'] ?? null;
    $unit = $result['unit'] ?? null;
    if (!self::exact($result, ['promotion', 'spend', 'unit', 'player_revision'])
      || !self::object($promotion) || !self::exact($promotion, ['promotion_id', 'from_unit_type_id', 'to_unit_type_id', 'granted_ability_ids'])
      || $promotion['promotion_id'] !== $request->promotionId
      || !self::stable($promotion['from_unit_type_id'], 'unit_type.')
      || !self::stable($promotion['to_unit_type_id'], 'unit_type.')
      || $promotion['from_unit_type_id'] === $promotion['to_unit_type_id']
      || !self::object($spend) || !self::exact($spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
      || $spend['currency_id'] !== 'raw_chaos' || $spend['amount'] !== $request->expectedAmount
      || !self::safe($spend['balance_before']) || !self::safe($spend['balance_after'])
      || $spend['balance_before'] - $spend['amount'] !== $spend['balance_after']
      || !self::safe($result['player_revision']) || $result['player_revision'] < 1
      || !self::object($unit) || !self::exact($unit, ['id', 'display_name', 'unit_type_id', 'kin_id',
        'level', 'xp', 'xp_to_next_level', 'lifecycle_status', 'promotion_history', 'owned_ability_ids',
        'ability_loadout', 'dice_bindings'])
      || $unit['id'] !== (string)$request->unitId || $unit['unit_type_id'] !== $promotion['to_unit_type_id']
      || !is_string($unit['display_name']) || trim($unit['display_name']) === ''
      || !self::stable($unit['kin_id'], 'kin.') || $unit['lifecycle_status'] !== 'active'
      || !self::safe($unit['level']) || $unit['level'] < 1
      || !self::safe($unit['xp']) || !self::safe($unit['xp_to_next_level'])
      || $unit['xp_to_next_level'] < 1 || $unit['xp'] >= $unit['xp_to_next_level']) self::invalid();

    if (!self::list($unit['promotion_history']) || $unit['promotion_history'] === []) self::invalid();
    $prior = null;
    foreach ($unit['promotion_history'] as $row) {
      if (!self::object($row) || !self::exact($row, ['from_unit_type_id', 'to_unit_type_id', 'promoted_at'])
        || !self::stable($row['from_unit_type_id'], 'unit_type.') || !self::stable($row['to_unit_type_id'], 'unit_type.')
        || !is_string($row['promoted_at']) || trim($row['promoted_at']) === ''
        || ($prior !== null && $row['from_unit_type_id'] !== $prior)) self::invalid();
      $prior = $row['to_unit_type_id'];
    }
    $last = $unit['promotion_history'][count($unit['promotion_history']) - 1];
    if ($last['from_unit_type_id'] !== $promotion['from_unit_type_id']
      || $last['to_unit_type_id'] !== $promotion['to_unit_type_id']) self::invalid();

    if (!self::idList($unit['owned_ability_ids'], 'ability.')
      || !self::idList($promotion['granted_ability_ids'], 'ability.')) self::invalid();
    $owned = array_fill_keys($unit['owned_ability_ids'], true);
    foreach ($promotion['granted_ability_ids'] as $id) if (!isset($owned[$id])) self::invalid();

    if (!self::list($unit['ability_loadout']) || !self::list($unit['dice_bindings'])) self::invalid();
    $equipped = [];
    foreach ($unit['ability_loadout'] as $order => $row) {
      if (!self::object($row) || !self::exact($row, ['ability_id', 'equip_order'])
        || !self::stable($row['ability_id'], 'ability.') || !isset($owned[$row['ability_id']])
        || isset($equipped[$row['ability_id']]) || $row['equip_order'] !== $order) self::invalid();
      $equipped[$row['ability_id']] = true;
    }
    $dice = []; $slots = [];
    foreach ($unit['dice_bindings'] as $row) {
      if (!self::object($row) || !self::exact($row, ['ability_id', 'slot_index', 'dice_instance_id'])
        || !is_string($row['ability_id']) || !isset($equipped[$row['ability_id']])
        || !self::safe($row['slot_index']) || !self::positiveId($row['dice_instance_id'])
        || isset($dice[$row['dice_instance_id']]) || isset($slots[$row['ability_id'] . ':' . $row['slot_index']])) self::invalid();
      $dice[$row['dice_instance_id']] = true; $slots[$row['ability_id'] . ':' . $row['slot_index']] = true;
    }
  }

  private static function invalid(): never { throw new WarbandIntegrityException('Persisted unit promotion receipt is invalid.'); }
  private static function object(mixed $value): bool { return is_array($value) && !array_is_list($value); }
  private static function list(mixed $value): bool { return is_array($value) && array_is_list($value); }
  private static function safe(mixed $value): bool { return is_int($value) && $value >= 0 && $value <= ClientSafeInteger::MAXIMUM; }
  private static function positiveId(mixed $value): bool
  { return is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1; }
  private static function stable(mixed $value, string $prefix): bool
  { return is_string($value) && str_starts_with($value, $prefix)
      && preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D', $value) === 1; }
  private static function idList(mixed $value, string $prefix): bool
  {
    if (!self::list($value)) return false;
    $ids = [];
    foreach ($value as $id) {
      if (!self::stable($id, $prefix) || isset($ids[$id])) return false;
      $ids[$id] = true;
    }
    return true;
  }
  /** @param array<string,mixed> $value @param list<string> $fields */
  private static function exact(array $value, array $fields): bool
  { $actual = array_keys($value); sort($actual, SORT_STRING); sort($fields, SORT_STRING); return $actual === $fields; }
}
