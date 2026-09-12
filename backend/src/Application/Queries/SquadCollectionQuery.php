<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Repositories\SquadRepository;

final class SquadCollectionQuery
{
  public function __construct(private readonly SquadRepository $squads) {}

  /** @return array<int,array{id:string,name:string,is_active:bool,formation:array<int,?string>}> */
  public function execute(int $userId): array
  {
    $rows = $this->squads->listForUser($userId);
    $activeSquadId = $this->squads->activeSquadIdForUser($userId);
    if ($activeSquadId !== null && $this->squads->squadOwnerId($activeSquadId) !== $userId) {
      throw new WarbandIntegrityException('Persisted active squad ownership is invalid.');
    }

    $formationBySquad = [];
    foreach ($rows as $row) $formationBySquad[(string)$row['id']] = array_fill(0, 9, null);

    foreach ($this->squads->listFormationRowsForUser($userId) as $row) {
      $squadId = (string)$row['squad_id'];
      $position = (int)$row['position'];
      if (
        !isset($formationBySquad[$squadId])
        || $position < 0
        || $position > 8
        || (int)$row['unit_user_id'] !== $userId
        || (string)$row['lifecycle_status'] !== 'active'
      ) {
        throw new WarbandIntegrityException('Persisted squad formation is invalid.');
      }
      $formationBySquad[$squadId][$position] = (string)$row['unit_id'];
    }

    $result = [];
    foreach ($rows as $row) {
      $id = (string)$row['id'];
      $result[] = [
        'id' => $id,
        'name' => (string)$row['name'],
        'is_active' => $activeSquadId !== null && (string)$activeSquadId === $id,
        'formation' => $formationBySquad[$id],
      ];
    }
    return $result;
  }
}
