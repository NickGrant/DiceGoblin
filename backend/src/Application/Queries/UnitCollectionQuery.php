<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\WarbandContentGuard;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandUnitRepository;

final class UnitCollectionQuery
{
  private readonly WarbandContentGuard $content;

  public function __construct(
    private readonly WarbandUnitRepository $units,
    ContentRegistry $content,
  ) {
    $this->content = new WarbandContentGuard($content);
  }

  /** @return array<int,array{id:string,display_name:string,unit_type_id:string,kin_id:string,level:int,xp:int,lifecycle_status:string}> */
  public function execute(int $userId): array
  {
    $result = [];
    foreach ($this->units->listActiveForUser($userId) as $row) {
      $unitTypeId = (string)$row['unit_type_id'];
      $kinId = (string)$row['kin_id'];
      $this->content->unitType($unitTypeId);
      $this->content->kin($kinId);

      $result[] = [
        'id' => (string)$row['id'],
        'display_name' => (string)$row['display_name'],
        'unit_type_id' => $unitTypeId,
        'kin_id' => $kinId,
        'level' => (int)$row['level'],
        'xp' => (int)$row['xp'],
        'lifecycle_status' => (string)$row['lifecycle_status'],
      ];
    }
    return $result;
  }
}
