<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Rewards;

use DiceGoblins\Content\ContentRegistry;

/** Resolves authored material drops from the completed encounter. */
final class VictoryItemDropPolicy
{
  public function __construct(private readonly ContentRegistry $content) {}

  /** @return list<array{item_id:string,quantity:int}> */
  public function grants(string $encounterId): array
  {
    $encounter = $this->content->encounter($encounterId);
    $enemySet = array_fill_keys(array_column($encounter['combatants'], 'enemy_unit_type_id'), true);
    $grants = [];
    foreach ($this->content->definitionsOfType('item') as $itemId => $item) {
      if (($item['source_region_id'] ?? null) !== $encounter['region_id']) continue;
      if (isset($item['source_encounter_id']) && $item['source_encounter_id'] !== $encounterId) continue;
      foreach ($item['victory_drops'] ?? [] as $drop) {
        if ($drop['node_type'] !== $encounter['kind']) continue;
        foreach ($drop['enemy_unit_type_ids'] as $enemyId) {
          if (!isset($enemySet[$enemyId])) continue;
          $grants[] = ['item_id' => $itemId, 'quantity' => $drop['quantity']];
          break;
        }
      }
    }
    return $grants;
  }
}
