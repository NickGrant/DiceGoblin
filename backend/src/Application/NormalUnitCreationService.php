<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandUnitRepository;
use RuntimeException;

final class NormalUnitCreationService
{
  public function __construct(
    private readonly WarbandUnitRepository $units,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{id:string,display_name:string,unit_type_id:string,kin_id:string,level:int,xp:int,lifecycle_status:string} */
  public function create(int $userId, string $unitTypeId, string $kinId): array
  {
    $unitType = $this->content->unitType($unitTypeId);
    $this->content->kin($kinId);
    $name = $unitType['display_name'] ?? null;
    $abilityIds = $unitType['ability_ids'] ?? null;
    if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 128
      || !is_array($abilityIds) || !array_is_list($abilityIds) || $abilityIds === []) {
      throw new RuntimeException('Authored normal unit configuration is invalid.');
    }
    $seen = [];
    foreach ($abilityIds as $abilityId) {
      if (!is_string($abilityId) || isset($seen[$abilityId])) throw new RuntimeException('Authored unit abilities are invalid.');
      $this->content->ability($abilityId); $seen[$abilityId] = true;
    }
    $unit = $this->units->createActive($userId, $unitTypeId, $kinId, trim($name));
    $this->units->insertOwnedAbilities((int)$unit['id'], array_values($abilityIds));
    return $unit;
  }
}
