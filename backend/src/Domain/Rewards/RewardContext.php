<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Rewards;

use DiceGoblins\Domain\Progression\UnitXpResolver;
use InvalidArgumentException;
use Throwable;

final class RewardContext
{
  /** @var array<string,array{unit_id:string,level:int,xp:int}> */
  private array $units = [];
  /** @var array<string,bool> */
  private array $unlocks = [];

  /** @param list<array{unit_id:int|string,level:int,xp:int}> $participatingUnits
   *  @param list<string> $ownedUnlockIds
   */
  public function __construct(
    public readonly int $teeth,
    public readonly int $rawChaos,
    array $participatingUnits,
    array $ownedUnlockIds,
  ) {
    if ($teeth < 0 || $rawChaos < 0) throw new InvalidArgumentException('Reward balances cannot be negative.');
    $xpResolver = new UnitXpResolver();
    foreach ($participatingUnits as $unit) {
      if (!is_array($unit) || array_is_list($unit) || !$this->hasExactFields($unit, ['unit_id', 'level', 'xp'])) {
        throw new InvalidArgumentException('Participating unit context is malformed.');
      }
      $id = self::canonicalId($unit['unit_id']);
      if (isset($this->units[$id]) || !is_int($unit['level']) || $unit['level'] < 1 || !is_int($unit['xp']) || $unit['xp'] < 0) {
        throw new InvalidArgumentException('Participating unit context is invalid.');
      }
      try { $threshold = $xpResolver->threshold($unit['level']); }
      catch (Throwable $e) { throw new InvalidArgumentException('Participating unit context is invalid.', 0, $e); }
      if ($unit['xp'] >= $threshold) throw new InvalidArgumentException('Participating unit XP is not normalized.');
      $this->units[$id] = ['unit_id' => $id, 'level' => $unit['level'], 'xp' => $unit['xp']];
    }
    foreach ($ownedUnlockIds as $unlockId) {
      if (!is_string($unlockId) || preg_match('/^unlock\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $unlockId) !== 1 || isset($this->unlocks[$unlockId])) {
        throw new InvalidArgumentException('Owned unlock context is invalid.');
      }
      $this->unlocks[$unlockId] = true;
    }
  }

  /** @return array<string,array{unit_id:string,level:int,xp:int}> */
  public function units(): array { return $this->units; }
  /** @return array<string,bool> */
  public function unlocks(): array { return $this->unlocks; }

  private static function canonicalId(int|string $id): string
  {
    $value = (string)$id;
    $maximum = (string)PHP_INT_MAX;
    if (preg_match('/^[1-9][0-9]*$/', $value) !== 1 || strlen($value) > strlen($maximum)
      || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
      throw new InvalidArgumentException('Unit ID is invalid.');
    }
    return $value;
  }

  /** @param array<string,mixed> $value @param list<string> $fields */
  private function hasExactFields(array $value, array $fields): bool
  {
    $actual = array_keys($value); sort($actual); sort($fields);
    return $actual === $fields;
  }
}
