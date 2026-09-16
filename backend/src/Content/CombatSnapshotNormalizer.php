<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

use DiceGoblins\Combat\Vnext\CombatInput;
use InvalidArgumentException;

/** Resolves canonical authored facts outside the pure combat kernel. */
final class CombatSnapshotNormalizer
{
  public function __construct(private readonly ContentRegistry $content) {}

  /** @param list<array<string,mixed>> $players */
  public function forEncounter(string $seed, array $players, string $encounterId): CombatInput
  {
    return new CombatInput(['seed' => $seed, 'combatants' => [...$players, ...$this->enemyCombatants($encounterId)]]);
  }

  /** @return list<array<string,mixed>> */
  public function enemyCombatants(string $encounterId): array
  {
    $encounter = $this->content->encounter($encounterId);
    $combatants = [];
    foreach ($encounter['combatants'] as $slot) {
      $enemy = $this->content->enemyUnitType($slot['enemy_unit_type_id']);
      $virtual = $enemy['virtual_ability_dice'];
      $abilities = [];
      foreach ($enemy['active_ability_ids'] as $abilityId) {
        $definition = $this->content->ability($abilityId);
        $dice = [];
        for ($index = 0; $index < $definition['dice_slot_count']; $index++) {
          $dice[] = $this->normalizeDie($slot['key'] . '_' . $definition['handler_id'] . '_' . $index,
            $virtual['sides'], $virtual['profile_id']);
        }
        $abilities[] = $this->ability($abilityId, $dice);
      }
      $passives = [];
      foreach ($enemy['passive_ability_ids'] as $id) $passives[] = $this->passive($id);
      $combatants[] = ['key' => $slot['key'], 'side' => 'enemy', 'position' => $slot['position'],
        'stats' => $enemy['stats'], 'max_hp' => $enemy['stats']['hp'], 'current_hp' => $enemy['stats']['hp'],
        'active_abilities' => $abilities, 'passive_abilities' => $passives, 'statuses' => []];
    }
    return $combatants;
  }

  /** @param list<array<string,mixed>> $dice @return array<string,mixed> */
  public function ability(string $abilityId, array $dice): array
  {
    $definition = $this->content->ability($abilityId);
    if ($definition['kind'] !== 'active' || count($dice) !== $definition['dice_slot_count']) {
      throw new InvalidArgumentException('Active ability dice do not fill authored slots.');
    }
    return ['id' => $abilityId, 'handler_id' => $definition['handler_id'], 'target_rule' => $definition['target_rule'],
      'action_delay' => $definition['action_delay'], 'resolution_priority' => $definition['resolution_priority'],
      'dice_slot_count' => $definition['dice_slot_count'], 'config' => $definition['handler_config'], 'dice' => $dice];
  }

  /** @return array<string,mixed> */
  public function passive(string $abilityId): array
  {
    $definition = $this->content->ability($abilityId);
    if ($definition['kind'] !== 'passive') throw new InvalidArgumentException('Passive ability ID is not passive.');
    return ['id' => $abilityId, 'handler_id' => $definition['handler_id'], 'config' => $definition['handler_config']];
  }

  /** @return array<string,mixed> */
  public function normalizeDie(string $key, int $sides, string $profileId): array
  {
    $profile = $this->content->diceProfile($profileId);
    if (!in_array($sides, $profile['allowed_sizes'], true)) throw new InvalidArgumentException('Die size is not eligible for profile.');
    $effects = [];
    foreach ($profile['aspect_ids'] as $aspectId) {
      $aspect = $this->content->diceAspect($aspectId);
      $effects[] = ['id' => $aspectId, 'effect_id' => $aspect['effect_id'], 'config' => $aspect['effect_config']];
    }
    return ['key' => $key, 'sides' => $sides, 'profile_id' => $profileId, 'effects' => $effects];
  }
}
