<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\WarbandContentGuard;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandUnitRepository;

final class UnitDetailQuery
{
  private readonly WarbandContentGuard $content;

  public function __construct(
    private readonly WarbandUnitRepository $units,
    ContentRegistry $content,
  ) {
    $this->content = new WarbandContentGuard($content);
  }

  /** @return array<string,mixed> */
  public function execute(int $userId, int $unitId, bool $forUpdate = false): array
  {
    $unit = $this->units->getActiveForUser($userId, $unitId, $forUpdate);
    if ($unit === null) {
      throw new UnitNotFoundException('Unit is unavailable.');
    }

    $unitTypeId = (string)$unit['unit_type_id'];
    $kinId = (string)$unit['kin_id'];
    $this->content->unitType($unitTypeId);
    $this->content->kin($kinId);

    $promotionHistory = [];
    foreach ($this->units->listPromotions($unitId, $forUpdate) as $promotion) {
      $from = (string)$promotion['from_unit_type_id'];
      $to = (string)$promotion['to_unit_type_id'];
      $this->content->unitType($from);
      $this->content->unitType($to);
      $promotionHistory[] = [
        'from_unit_type_id' => $from,
        'to_unit_type_id' => $to,
        'promoted_at' => (string)$promotion['promoted_at'],
      ];
    }

    $ownedAbilityIds = [];
    $ownedAbilitySet = [];
    foreach ($this->units->listOwnedAbilities($unitId, $forUpdate) as $owned) {
      $abilityId = (string)$owned['ability_id'];
      $this->content->ability($abilityId);
      $ownedAbilityIds[] = $abilityId;
      $ownedAbilitySet[$abilityId] = true;
    }

    $loadout = [];
    $loadoutSet = [];
    foreach ($this->units->listLoadout($unitId, $forUpdate) as $equipped) {
      $abilityId = (string)$equipped['ability_id'];
      $ability = $this->content->ability($abilityId);
      if (
        !isset($ownedAbilitySet[$abilityId])
        || isset($loadoutSet[$abilityId])
        || ($ability['kind'] ?? null) !== 'active'
        || (int)$equipped['equip_order'] !== count($loadout)
      ) {
        throw new WarbandIntegrityException('Persisted unit loadout is invalid.');
      }
      $loadout[] = [
        'ability_id' => $abilityId,
        'equip_order' => (int)$equipped['equip_order'],
      ];
      $loadoutSet[$abilityId] = true;
    }

    $bindings = [];
    $boundSlots = [];
    $boundDice = [];
    foreach ($this->units->listDiceBindings($unitId, $forUpdate) as $binding) {
      $abilityId = (string)$binding['ability_id'];
      $ability = $this->content->ability($abilityId);
      $profile = $this->content->diceProfile((string)$binding['profile_id']);
      $slotIndex = (int)$binding['slot_index'];
      $size = (int)$binding['size'];
      $allowedSizes = array_map('intval', is_array($profile['allowed_sizes'] ?? null) ? $profile['allowed_sizes'] : []);

      if (
        !isset($ownedAbilitySet[$abilityId])
        || !isset($loadoutSet[$abilityId])
        || ($ability['kind'] ?? null) !== 'active'
        || $slotIndex < 0
        || $slotIndex >= (int)($ability['dice_slot_count'] ?? 0)
        || (int)$binding['die_user_id'] !== $userId
        || (string)$binding['die_lifecycle_status'] !== 'active'
        || !in_array($size, $allowedSizes, true)
        || isset($boundSlots[$abilityId . ':' . $slotIndex])
        || isset($boundDice[(string)$binding['dice_instance_id']])
      ) {
        throw new WarbandIntegrityException('Persisted unit dice binding is invalid.');
      }

      $bindings[] = [
        'ability_id' => $abilityId,
        'slot_index' => $slotIndex,
        'dice_instance_id' => (string)$binding['dice_instance_id'],
      ];
      $boundSlots[$abilityId . ':' . $slotIndex] = true;
      $boundDice[(string)$binding['dice_instance_id']] = true;
    }

    foreach ($loadout as $equipped) {
      $abilityId = $equipped['ability_id'];
      $slotCount = (int)$this->content->ability($abilityId)['dice_slot_count'];
      for ($slotIndex = 0; $slotIndex < $slotCount; $slotIndex++) {
        if (!isset($boundSlots[$abilityId . ':' . $slotIndex])) {
          throw new WarbandIntegrityException('Persisted unit dice binding is incomplete.');
        }
      }
    }

    usort($bindings, static function(array $a, array $b) use ($loadout): int {
      $orders = [];
      foreach ($loadout as $entry) $orders[$entry['ability_id']] = $entry['equip_order'];
      return [$orders[$a['ability_id']] ?? PHP_INT_MAX, $a['slot_index']]
        <=> [$orders[$b['ability_id']] ?? PHP_INT_MAX, $b['slot_index']];
    });

    return [
      'id' => (string)$unit['id'],
      'display_name' => (string)$unit['display_name'],
      'unit_type_id' => $unitTypeId,
      'kin_id' => $kinId,
      'level' => (int)$unit['level'],
      'xp' => (int)$unit['xp'],
      'lifecycle_status' => (string)$unit['lifecycle_status'],
      'promotion_history' => $promotionHistory,
      'owned_ability_ids' => $ownedAbilityIds,
      'ability_loadout' => $loadout,
      'dice_bindings' => $bindings,
    ];
  }
}
