<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\UnitSummaryAssembler;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandUnitRepository;

final class UnitCollectionQuery
{
  private readonly UnitSummaryAssembler $unitSummaries;

  public function __construct(
    private readonly WarbandUnitRepository $units,
    ContentRegistry $content,
  ) {
    $this->unitSummaries = new UnitSummaryAssembler($content);
  }

  /** @return array<int,array{id:string,display_name:string,unit_type_id:string,kin_id:string,level:int,xp:int,lifecycle_status:string}> */
  public function execute(int $userId): array
  {
    $result = [];
    foreach ($this->units->listActiveForUser($userId) as $row) {
      $result[] = $this->unitSummaries->assemble($row);
    }
    return $result;
  }
}
