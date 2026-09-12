<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\WarbandContentGuard;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandDiceRepository;

final class DiceCollectionQuery
{
  private readonly WarbandContentGuard $content;

  public function __construct(
    private readonly WarbandDiceRepository $dice,
    ContentRegistry $content,
  ) {
    $this->content = new WarbandContentGuard($content);
  }

  /** @return array<int,array<string,mixed>> */
  public function execute(int $userId): array
  {
    $bindingsByDie = [];
    foreach ($this->dice->listRelevantBindingsForUser($userId) as $binding) {
      if (
        (int)$binding['unit_user_id'] !== $userId
        || (int)$binding['die_user_id'] !== $userId
        || (string)$binding['unit_lifecycle_status'] !== 'active'
        || (string)$binding['die_lifecycle_status'] !== 'active'
        || (int)$binding['is_loaded'] !== 1
      ) {
        throw new WarbandIntegrityException('Persisted dice equipment ownership is invalid.');
      }

      $ability = $this->content->ability((string)$binding['ability_id']);
      $slotIndex = (int)$binding['slot_index'];
      if (($ability['kind'] ?? null) !== 'active' || $slotIndex < 0 || $slotIndex >= (int)($ability['dice_slot_count'] ?? 0)) {
        throw new WarbandIntegrityException('Persisted dice equipment slot is invalid.');
      }

      $dieId = (string)$binding['dice_instance_id'];
      $bindingsByDie[$dieId] ??= [];
      $bindingsByDie[$dieId][] = [
        'unit_id' => (string)$binding['unit_id'],
        'ability_id' => (string)$binding['ability_id'],
        'slot_index' => $slotIndex,
      ];
    }

    $result = [];
    foreach ($this->dice->listActiveForUser($userId) as $row) {
      $profileId = (string)$row['profile_id'];
      $profile = $this->content->diceProfile($profileId);
      $size = (int)$row['size'];
      $allowedSizes = array_map('intval', is_array($profile['allowed_sizes'] ?? null) ? $profile['allowed_sizes'] : []);
      if (!in_array($size, $allowedSizes, true)) {
        throw new WarbandIntegrityException('Persisted die size is invalid for its authored profile.');
      }
      $id = (string)$row['id'];
      $result[] = [
        'id' => $id,
        'size' => $size,
        'profile_id' => $profileId,
        'lifecycle_status' => (string)$row['lifecycle_status'],
        'bindings' => $bindingsByDie[$id] ?? [],
      ];
    }
    return $result;
  }
}
