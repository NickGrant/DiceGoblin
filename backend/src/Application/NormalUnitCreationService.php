<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandUnitRepository;
use RuntimeException;

final class NormalUnitCreationService
{
  private const GIVEN_NAMES = [
    'Bram', 'Brik', 'Dreg', 'Fen', 'Grit', 'Hob', 'Kip', 'Mog',
    'Nix', 'Pip', 'Rusk', 'Snik', 'Tarn', 'Vex', 'Wick', 'Zog',
  ];
  private const BYNAMES = [
    'Ash', 'Briar', 'Cinder', 'Flint', 'Gorse', 'Moss', 'Pebble', 'Reed',
    'Scrap', 'Soot', 'Stone', 'Thorn', 'Twig', 'Wattle', 'Whistle', 'Wisp',
  ];

  public function __construct(
    private readonly WarbandUnitRepository $units,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{id:string,display_name:string,unit_type_id:string,kin_id:string,level:int,xp:int,lifecycle_status:string} */
  public function create(int $userId, string $unitTypeId, string $kinId): array
  {
    $unitType = $this->content->unitType($unitTypeId);
    $this->content->kin($kinId);
    $typeName = $unitType['display_name'] ?? null;
    $abilityIds = $unitType['ability_ids'] ?? null;
    if (!is_string($typeName) || trim($typeName) === '' || strlen(trim($typeName)) > 128
      || !is_array($abilityIds) || !array_is_list($abilityIds) || $abilityIds === []) {
      throw new RuntimeException('Authored normal unit configuration is invalid.');
    }
    $seen = [];
    foreach ($abilityIds as $abilityId) {
      if (!is_string($abilityId) || isset($seen[$abilityId])) throw new RuntimeException('Authored unit abilities are invalid.');
      $this->content->ability($abilityId); $seen[$abilityId] = true;
    }
    $name = self::GIVEN_NAMES[random_int(0, count(self::GIVEN_NAMES) - 1)] . ' '
      . self::BYNAMES[random_int(0, count(self::BYNAMES) - 1)];
    $unit = $this->units->createActive($userId, $unitTypeId, $kinId, $name);
    $this->units->insertOwnedAbilities((int)$unit['id'], array_values($abilityIds));
    return $unit;
  }
}
