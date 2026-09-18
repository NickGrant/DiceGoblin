<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Rewards;

use DiceGoblins\Domain\Progression\UnitXpResolver;
use JsonException;

final class FinalizedRewardResult
{
  public const VERSION = 1;

  /** @param array<string,mixed> $data */
  private function __construct(private readonly array $data) {}

  /** @param array<string,mixed> $data */
  public static function fromArray(array $data): self
  {
    self::fields($data, ['version', 'event_id', 'reward_definition_id', 'source_type', 'source_id', 'entries'], 'result');
    if (($data['version'] ?? null) !== self::VERSION) throw new RewardResultException('Unsupported finalized reward result version.');
    self::stableId($data['event_id'] ?? null, 'event.', 'event_id');
    self::stableId($data['reward_definition_id'] ?? null, 'reward_definition.', 'reward_definition_id');
    if (!is_string($data['source_type']) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $data['source_type']) !== 1) {
      throw new RewardResultException('Finalized reward source_type is invalid.');
    }
    if (!is_string($data['source_id']) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/', $data['source_id']) !== 1) {
      throw new RewardResultException('Finalized reward source_id is invalid.');
    }
    if (!is_array($data['entries']) || !array_is_list($data['entries'])) throw new RewardResultException('Finalized reward entries must be an ordered list.');

    $keys = $currencyState = $unitState = $unlockState = [];
    $xpUnitSet = null;
    $xpResolver = new UnitXpResolver();
    foreach ($data['entries'] as $index => $entry) {
      if (!is_array($entry) || array_is_list($entry)) throw new RewardResultException('Finalized reward entry must be an object.');
      self::fields($entry, ['entry_index', 'key', 'reward_type', 'probability_basis_points', 'roll', 'outcome', 'grant'], "entry {$index}");
      if (($entry['entry_index'] ?? null) !== $index) throw new RewardResultException('Finalized reward entry order is invalid.');
      $key = $entry['key'] ?? null;
      if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1 || isset($keys[$key])) {
        throw new RewardResultException('Finalized reward entry key is invalid or duplicated.');
      }
      $keys[$key] = true;
      $type = $entry['reward_type'] ?? null;
      if (!in_array($type, ['currency', 'unit_xp', 'unlock'], true)) throw new RewardResultException('Finalized reward type is unsupported.');
      $probability = self::integer($entry['probability_basis_points'] ?? null, 1, 10000, 'probability');
      $roll = self::integer($entry['roll'] ?? null, 1, 10000, 'roll');
      $outcome = $entry['outcome'] ?? null;
      if (!in_array($outcome, ['not_rolled', 'granted', 'already_owned'], true)) throw new RewardResultException('Finalized reward outcome is unsupported.');
      $rolled = $roll <= $probability;
      if (($outcome === 'not_rolled') === $rolled) throw new RewardResultException('Finalized reward roll and outcome are inconsistent.');
      if ($outcome === 'already_owned' && $type !== 'unlock') throw new RewardResultException('Only unlock rewards may be already owned.');
      if ($outcome === 'not_rolled') {
        if ($entry['grant'] !== null) throw new RewardResultException('A non-rolled reward must not contain grant facts.');
        continue;
      }
      if (!is_array($entry['grant']) || array_is_list($entry['grant'])) throw new RewardResultException('Rolled reward grant facts are malformed.');
      if ($type === 'currency') self::validateCurrencyGrant($entry['grant'], $currencyState);
      elseif ($type === 'unit_xp') self::validateXpGrant($entry['grant'], $unitState, $xpUnitSet, $xpResolver);
      else self::validateUnlockGrant($entry['grant'], $outcome, $unlockState);
    }
    return new self($data);
  }

  public static function fromJson(string $json): self
  {
    try {
      $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      throw new RewardResultException('Finalized reward JSON is invalid.', 0, $e);
    }
    if (!is_array($data) || array_is_list($data)) throw new RewardResultException('Finalized reward JSON must contain an object.');
    return self::fromArray($data);
  }

  /** @return array<string,mixed> */
  public function toArray(): array { return $this->data; }

  public function toJson(): string
  {
    try {
      return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $e) {
      throw new RewardResultException('Finalized reward result could not be encoded.', 0, $e);
    }
  }

  public function eventId(): string { return (string)$this->data['event_id']; }
  public function rewardDefinitionId(): string { return (string)$this->data['reward_definition_id']; }
  public function sourceType(): string { return (string)$this->data['source_type']; }
  public function sourceId(): string { return (string)$this->data['source_id']; }
  /** @return list<array<string,mixed>> */
  public function entries(): array { return $this->data['entries']; }

  /** @param array<string,mixed> $grant @param array<string,int> $state */
  private static function validateCurrencyGrant(array $grant, array &$state): void
  {
    self::fields($grant, ['currency_id', 'amount', 'balance_before', 'balance_after'], 'currency grant');
    $currency = $grant['currency_id'] ?? null;
    if (!in_array($currency, ['teeth', 'raw_chaos'], true)) throw new RewardResultException('Finalized currency ID is invalid.');
    $amount = self::integer($grant['amount'] ?? null, 1, PHP_INT_MAX, 'currency amount');
    $before = self::integer($grant['balance_before'] ?? null, 0, PHP_INT_MAX, 'currency balance');
    $after = self::integer($grant['balance_after'] ?? null, 0, PHP_INT_MAX, 'currency balance');
    if ($amount > PHP_INT_MAX - $before || $after !== $before + $amount) throw new RewardResultException('Finalized currency arithmetic is invalid.');
    if (isset($state[$currency]) && $state[$currency] !== $before) throw new RewardResultException('Finalized currency entries do not chain.');
    $state[$currency] = $after;
  }

  /** @param array<string,mixed> $grant
   *  @param array<string,array{level:int,xp:int}> $state
   *  @param list<string>|null $expectedUnits
   */
  private static function validateXpGrant(array $grant, array &$state, ?array &$expectedUnits, UnitXpResolver $resolver): void
  {
    self::fields($grant, ['target_scope', 'amount_per_unit', 'units'], 'XP grant');
    if (($grant['target_scope'] ?? null) !== 'participating_units') throw new RewardResultException('Finalized XP target scope is invalid.');
    $amount = self::integer($grant['amount_per_unit'] ?? null, 1, PHP_INT_MAX, 'XP amount');
    if (!is_array($grant['units']) || !array_is_list($grant['units'])) throw new RewardResultException('Finalized XP units must be an ordered list.');
    $ids = [];
    foreach ($grant['units'] as $offset => $unit) {
      if (!is_array($unit) || array_is_list($unit)) throw new RewardResultException('Finalized XP unit transition is malformed.');
      self::fields($unit, ['unit_id', 'level_before', 'xp_before', 'level_after', 'xp_after'], "XP unit {$offset}");
      $id = self::unitId($unit['unit_id'] ?? null);
      if (isset($ids[$id])) throw new RewardResultException('Finalized XP unit IDs are duplicated.');
      if ($ids !== [] && self::compareIds(array_key_last($ids), $id) >= 0) throw new RewardResultException('Finalized XP unit IDs are not canonically ordered.');
      $ids[$id] = true;
      $beforeLevel = self::integer($unit['level_before'] ?? null, 1, PHP_INT_MAX, 'unit level');
      $beforeXp = self::integer($unit['xp_before'] ?? null, 0, PHP_INT_MAX, 'unit XP');
      $afterLevel = self::integer($unit['level_after'] ?? null, 1, PHP_INT_MAX, 'unit level');
      $afterXp = self::integer($unit['xp_after'] ?? null, 0, PHP_INT_MAX, 'unit XP');
      if (isset($state[$id]) && ($state[$id]['level'] !== $beforeLevel || $state[$id]['xp'] !== $beforeXp)) {
        throw new RewardResultException('Finalized XP entries do not chain.');
      }
      try { $expected = $resolver->apply($beforeLevel, $beforeXp, $amount); }
      catch (\Throwable $e) { throw new RewardResultException('Finalized XP transition is invalid.', 0, $e); }
      if ($expected['level'] !== $afterLevel || $expected['xp'] !== $afterXp) throw new RewardResultException('Finalized XP transition does not match the canonical resolver.');
      $state[$id] = ['level' => $afterLevel, 'xp' => $afterXp];
    }
    $unitIds = array_keys($ids);
    if ($expectedUnits !== null && $expectedUnits !== $unitIds) throw new RewardResultException('Finalized XP participant sets are inconsistent.');
    $expectedUnits ??= $unitIds;
  }

  /** @param array<string,mixed> $grant @param array<string,string> $state */
  private static function validateUnlockGrant(array $grant, string $outcome, array &$state): void
  {
    self::fields($grant, ['unlock_id'], 'unlock grant');
    $id = self::stableId($grant['unlock_id'] ?? null, 'unlock.', 'unlock_id');
    if (isset($state[$id]) && $outcome !== 'already_owned') throw new RewardResultException('Repeated finalized unlock must be already owned.');
    $state[$id] = $outcome;
  }

  /** @param array<string,mixed> $value @param list<string> $expected */
  private static function fields(array $value, array $expected, string $context): void
  {
    $actual = array_keys($value); sort($actual); sort($expected);
    if ($actual !== $expected) throw new RewardResultException("Finalized reward {$context} has an invalid field set.");
  }

  private static function stableId(mixed $value, string $namespace, string $field): string
  {
    if (!is_string($value) || preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $value) !== 1 || !str_starts_with($value, $namespace)) {
      throw new RewardResultException("Finalized reward {$field} is invalid.");
    }
    return $value;
  }

  private static function unitId(mixed $value): string
  {
    $maximum = (string)PHP_INT_MAX;
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1 || strlen($value) > strlen($maximum)
      || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
      throw new RewardResultException('Finalized XP unit ID is invalid.');
    }
    return $value;
  }

  private static function integer(mixed $value, int $minimum, int $maximum, string $field): int
  {
    if (!is_int($value) || $value < $minimum || $value > $maximum) throw new RewardResultException("Finalized reward {$field} is invalid.");
    return $value;
  }

  public static function compareIds(int|string $left, int|string $right): int
  {
    $left = (string)$left;
    $right = (string)$right;
    return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
  }
}
