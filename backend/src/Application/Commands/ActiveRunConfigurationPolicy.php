<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Repositories\RunPersistenceRepository;

final class ActiveRunConfigurationPolicy
{
  public function __construct(private readonly RunPersistenceRepository $runs) {}

  public function assertSquadActivationAllowed(int $userId, int $squadId): void
  {
    $active = $this->activeParticipation($userId);
    if ($active !== null && $active['squad_id'] !== $squadId) throw new ActiveRunConfigurationLockedException();
  }

  public function assertSquadFormationAllowed(int $userId, int $squadId, bool $formationChanges): void
  {
    $active = $this->activeParticipation($userId);
    if ($active !== null && $active['squad_id'] === $squadId && $formationChanges) {
      throw new ActiveRunConfigurationLockedException();
    }
  }

  public function assertSquadDeletionAllowed(int $userId, int $squadId): void
  {
    $active = $this->activeParticipation($userId);
    if ($active !== null && $active['squad_id'] === $squadId) throw new ActiveRunConfigurationLockedException();
  }

  public function assertUnitLoadoutAllowed(int $userId, int $unitId): void
  {
    $active = $this->activeParticipation($userId);
    if ($active !== null && isset($active['unit_ids'][$unitId])) throw new ActiveRunConfigurationLockedException();
  }

  /** @return array{squad_id:int,unit_ids:array<int,true>}|null */
  private function activeParticipation(int $userId): ?array
  {
    $run = $this->runs->findActiveRunForUser($userId);
    if ($run === null) return null;
    if ((int)$run['user_id'] !== $userId || $run['squad_id'] === null || (int)$run['squad_id'] <= 0
      || $run['squad_user_id'] === null || (int)$run['squad_user_id'] !== $userId) {
      throw new WarbandIntegrityException('Active run participation is invalid.');
    }
    $unitIds = [];
    foreach ($this->runs->listParticipatingUnits((int)$run['id']) as $row) {
      $unitId = (int)$row['unit_id'];
      if ((int)$row['run_id'] !== (int)$run['id'] || $unitId <= 0 || isset($unitIds[$unitId])
        || $row['unit_user_id'] === null || (int)$row['unit_user_id'] !== $userId) {
        throw new WarbandIntegrityException('Active run participation is invalid.');
      }
      $unitIds[$unitId] = true;
    }
    if ($unitIds === []) throw new WarbandIntegrityException('Active run participation is empty.');
    return ['squad_id' => (int)$run['squad_id'], 'unit_ids' => $unitIds];
  }
}
