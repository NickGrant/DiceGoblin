<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;

final class UnitSummaryAssembler
{
  private readonly WarbandContentGuard $content;

  public function __construct(ContentRegistry $content)
  {
    $this->content = new WarbandContentGuard($content);
  }

  /** @param array<string,mixed> $row
   *  @return array{id:string,display_name:string,unit_type_id:string,kin_id:string,level:int,xp:int,lifecycle_status:string}
   */
  public function assemble(array $row): array
  {
    $unitTypeId = (string)$row['unit_type_id'];
    $kinId = (string)$row['kin_id'];
    $this->content->unitType($unitTypeId);
    $this->content->kin($kinId);
    return [
      'id' => (string)$row['id'],
      'display_name' => (string)$row['display_name'],
      'unit_type_id' => $unitTypeId,
      'kin_id' => $kinId,
      'level' => (int)$row['level'],
      'xp' => (int)$row['xp'],
      'lifecycle_status' => (string)$row['lifecycle_status'],
    ];
  }
}
