<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;

final class UnitConfigurationSupport
{
  public function __construct(
    private readonly PlayerStateRepository $playerState,
    private readonly WarbandDiceRepository $dice,
    private readonly UnitDetailQuery $unitDetails,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{player_revision:int} */
  public function lockPlayer(int $userId): array
  {
    $state = $this->playerState->getPlayerStateForUpdate($userId);
    if ($state === null) throw new WarbandIntegrityException('Required player state is missing.');
    return ['player_revision' => (int)$state['player_revision']];
  }

  /** @return array<string,mixed> */
  public function lockValidUnit(int $userId, int $unitId): array
  {
    $unit = $this->unitDetails->execute($userId, $unitId, true);
    $diceIds = array_map('intval', array_column($unit['dice_bindings'], 'dice_instance_id'));
    if ($diceIds !== []) {
      $counts = [];
      foreach ($this->dice->listBindingsForDiceIds($diceIds, true) as $binding) {
        $dieId = (int)$binding['dice_instance_id'];
        $counts[$dieId] = ($counts[$dieId] ?? 0) + 1;
        if ((int)$binding['unit_id'] !== $unitId) {
          throw new WarbandIntegrityException('Persisted physical die binding is duplicated across units.');
        }
      }
      foreach ($diceIds as $dieId) {
        if (($counts[$dieId] ?? 0) !== 1) {
          throw new WarbandIntegrityException('Persisted physical die binding is invalid.');
        }
      }
    }
    return $unit;
  }

  public function validateProposal(int $userId, int $unitId, array $currentUnit, UnitLoadoutConfiguration $configuration): void
  {
    $owned = array_fill_keys($currentUnit['owned_ability_ids'], true);
    foreach ($configuration->abilities as $entry) {
      try {
        $ability = $this->content->ability($entry['ability_id']);
      } catch (ContentValidationException) {
        throw new UnitConfigurationValidationException('Requested ability is unavailable.');
      }
      if (!isset($owned[$entry['ability_id']]) || ($ability['kind'] ?? null) !== 'active') {
        throw new UnitConfigurationValidationException('Requested ability is unavailable.');
      }
      if (count($entry['dice_instance_ids']) !== (int)($ability['dice_slot_count'] ?? -1)) {
        throw new UnitConfigurationValidationException('Requested ability dice slots are invalid.');
      }
    }

    $diceIds = $configuration->diceIds();
    $rows = $this->dice->listActiveByIdsForUser($userId, $diceIds, true);
    if (count($rows) !== count($diceIds)) {
      throw new UnitConfigurationValidationException('Requested dice configuration is unavailable.');
    }
    foreach ($rows as $row) {
      try {
        $profile = $this->content->diceProfile((string)$row['profile_id']);
      } catch (ContentValidationException) {
        throw new UnitConfigurationValidationException('Requested dice configuration is unavailable.');
      }
      $allowedSizes = array_map('intval', is_array($profile['allowed_sizes'] ?? null) ? $profile['allowed_sizes'] : []);
      if (!in_array((int)$row['size'], $allowedSizes, true)) {
        throw new UnitConfigurationValidationException('Requested dice configuration is unavailable.');
      }
    }

    foreach ($this->dice->listBindingsForDiceIds($diceIds, true) as $binding) {
      if ((int)$binding['unit_id'] !== $unitId) {
        throw new UnitConfigurationValidationException('Requested dice configuration is unavailable.');
      }
    }
  }

  public function isIdentical(array $currentUnit, UnitLoadoutConfiguration $configuration): bool
  {
    $current = [];
    $bindings = [];
    foreach ($currentUnit['dice_bindings'] as $binding) {
      $bindings[$binding['ability_id']][(int)$binding['slot_index']] = (int)$binding['dice_instance_id'];
    }
    foreach ($currentUnit['ability_loadout'] as $ability) {
      $abilityId = $ability['ability_id'];
      $diceIds = $bindings[$abilityId] ?? [];
      ksort($diceIds, SORT_NUMERIC);
      $current[] = ['ability_id' => $abilityId, 'dice_instance_ids' => array_values($diceIds)];
    }
    return $current === $configuration->abilities;
  }

  /** @return array<string,mixed> */
  public function detail(int $userId, int $unitId): array
  {
    return $this->unitDetails->execute($userId, $unitId);
  }
}
