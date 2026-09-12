<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\UnitSummaryAssembler;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Repositories\SquadRepository;

final class ActiveSquadQuery
{
  public function __construct(
    private readonly SquadRepository $squads,
    private readonly UnitSummaryAssembler $unitSummaries,
  ) {}

  /** @return array<string,mixed>|null */
  public function execute(int $userId, ?int $activeSquadId): ?array
  {
    if ($activeSquadId === null) {
      if ($this->squads->listForUser($userId) !== []) {
        throw new WarbandIntegrityException('Persisted active squad state is missing.');
      }
      return null;
    }
    $squad = $this->squads->getForUser($userId, $activeSquadId);
    if ($squad === null) throw new WarbandIntegrityException('Persisted active squad ownership is invalid.');

    $formation = array_fill(0, 9, null);
    $units = [];
    foreach ($this->squads->listFormationRowsForSquad($activeSquadId) as $row) {
      $position = (int)$row['position'];
      if ($position < 0 || $position > 8 || $formation[$position] !== null
        || (int)$row['unit_user_id'] !== $userId || (string)$row['lifecycle_status'] !== 'active') {
        throw new WarbandIntegrityException('Persisted active squad formation is invalid.');
      }
      $unitId = (string)$row['unit_id'];
      $formation[$position] = $unitId;
      $row['id'] = $row['unit_id'];
      $units[] = $this->unitSummaries->assemble($row);
    }

    return [
      'id' => (string)$squad['id'],
      'name' => $squad['name'],
      'is_active' => true,
      'formation' => $formation,
      'units' => $units,
    ];
  }
}
