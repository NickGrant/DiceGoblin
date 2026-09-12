<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class UnitLoadoutConfiguration
{
  /** @param list<array{ability_id:string,dice_instance_ids:list<int>}> $abilities */
  private function __construct(public readonly array $abilities) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    if (array_keys($request) !== ['abilities']) {
      throw new UnitConfigurationValidationException('Request must contain exactly abilities.');
    }
    $abilities = $request['abilities'];
    if (!is_array($abilities) || !array_is_list($abilities) || $abilities === []) {
      throw new UnitConfigurationValidationException('Abilities must be a non-empty ordered list.');
    }

    $normalized = [];
    $seenAbilities = [];
    $seenDice = [];
    foreach ($abilities as $entry) {
      if (!is_array($entry)) {
        throw new UnitConfigurationValidationException('Each ability must contain exactly ability_id and dice_instance_ids.');
      }
      $entryKeys = array_keys($entry);
      sort($entryKeys, SORT_STRING);
      if ($entryKeys !== ['ability_id', 'dice_instance_ids']) {
        throw new UnitConfigurationValidationException('Each ability must contain exactly ability_id and dice_instance_ids.');
      }
      $abilityId = $entry['ability_id'];
      if (!is_string($abilityId) || preg_match('/^ability\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $abilityId) !== 1 || isset($seenAbilities[$abilityId])) {
        throw new UnitConfigurationValidationException('Ability IDs must be unique canonical stable IDs.');
      }
      $diceIds = $entry['dice_instance_ids'];
      if (!is_array($diceIds) || !array_is_list($diceIds)) {
        throw new UnitConfigurationValidationException('Dice instance IDs must be an ordered list.');
      }

      $normalizedDice = [];
      foreach ($diceIds as $dieId) {
        if (!is_string($dieId) || preg_match('/^[1-9][0-9]*$/D', $dieId) !== 1) {
          throw new UnitConfigurationValidationException('Dice instance IDs must be canonical positive ID strings.');
        }
        $numericId = (int)$dieId;
        if ($numericId <= 0 || (string)$numericId !== $dieId || isset($seenDice[$dieId])) {
          throw new UnitConfigurationValidationException('Dice instance IDs must be unique canonical positive ID strings.');
        }
        $seenDice[$dieId] = true;
        $normalizedDice[] = $numericId;
      }

      $seenAbilities[$abilityId] = true;
      $normalized[] = ['ability_id' => $abilityId, 'dice_instance_ids' => $normalizedDice];
    }

    return new self($normalized);
  }

  /** @return list<int> */
  public function diceIds(): array
  {
    $ids = [];
    foreach ($this->abilities as $ability) {
      foreach ($ability['dice_instance_ids'] as $dieId) $ids[] = $dieId;
    }
    return $ids;
  }
}
