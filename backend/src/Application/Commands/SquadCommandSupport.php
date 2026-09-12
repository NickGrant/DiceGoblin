<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Application\UnitSummaryAssembler;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;

final class SquadCommandSupport
{
  public function __construct(
    private readonly PlayerStateRepository $playerState,
    private readonly SquadRepository $squads,
    private readonly WarbandUnitRepository $units,
    private readonly UnitSummaryAssembler $unitSummaries,
  ) {}

  /** @return array{active_squad_id:?int,player_revision:int,squad_ids:array<int,int>} */
  public function lockPlayer(int $userId): array
  {
    $state = $this->playerState->getPlayerStateForUpdate($userId);
    if ($state === null) throw new WarbandIntegrityException('Required player state is missing.');
    $ids = $this->squads->listIdsForUserForUpdate($userId);
    $active = $state['active_squad_id'] !== null ? (int)$state['active_squad_id'] : null;
    if (($ids === []) !== ($active === null) || ($active !== null && !in_array($active, $ids, true))) {
      throw new WarbandIntegrityException('Persisted active squad state is invalid.');
    }
    if ($active !== null) $this->squadView($userId, $active, $active, true);
    return ['active_squad_id' => $active, 'player_revision' => (int)$state['player_revision'], 'squad_ids' => $ids];
  }

  public function requireOwned(int $userId, int $squadId): void
  {
    if ($this->squads->getForUser($userId, $squadId, true) === null) throw new SquadNotFoundException();
    foreach ($this->squads->listFormationRowsForSquad($squadId, true) as $row) {
      $position = (int)$row['position'];
      if ($position < 0 || $position > 8 || (int)$row['unit_user_id'] !== $userId
        || (string)$row['lifecycle_status'] !== 'active') {
        throw new WarbandIntegrityException('Persisted squad formation is invalid.');
      }
      $row['id'] = $row['unit_id'];
      $this->unitSummaries->assemble($row);
    }
  }

  public function validateUnits(int $userId, SquadConfiguration $configuration): void
  {
    $ids = $configuration->unitIds();
    $rows = $this->units->listActiveByIdsForUser($userId, $ids, true);
    if (count($rows) !== count($ids)) throw new SquadValidationException('Formation contains an unavailable unit.');
    foreach ($rows as $row) $this->unitSummaries->assemble($row);
  }

  /** @return array{id:string,name:string,is_active:bool,formation:array<int,?string>} */
  public function squadView(int $userId, int $squadId, ?int $activeSquadId, bool $forUpdate = false): array
  {
    $squad = $this->squads->getForUser($userId, $squadId);
    if ($squad === null) throw new WarbandIntegrityException('Persisted squad ownership is invalid.');
    $formation = array_fill(0, 9, null);
    foreach ($this->squads->listFormationRowsForSquad($squadId, $forUpdate) as $row) {
      $position = (int)$row['position'];
      if ($position < 0 || $position > 8 || $formation[$position] !== null
        || (int)$row['unit_user_id'] !== $userId || (string)$row['lifecycle_status'] !== 'active') {
        throw new WarbandIntegrityException('Persisted squad formation is invalid.');
      }
      $row['id'] = $row['unit_id'];
      $this->unitSummaries->assemble($row);
      $formation[$position] = (string)$row['unit_id'];
    }
    return ['id' => (string)$squadId, 'name' => $squad['name'], 'is_active' => $activeSquadId === $squadId, 'formation' => $formation];
  }
}
