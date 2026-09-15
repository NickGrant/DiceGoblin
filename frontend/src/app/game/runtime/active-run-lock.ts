import type { GameBootstrapData } from './game-store';

/** Presentation hint only; the server remains authoritative for configuration mutations. */
export interface ActiveRunLock {
  readonly squadId: string;
  readonly unitIds: ReadonlySet<string>;
}

export function activeRunLock(bootstrap: GameBootstrapData | null): ActiveRunLock | null {
  if (!bootstrap?.active_run) return null;
  const squad = bootstrap.active_squad;
  if (!squad || squad.id !== bootstrap.active_run.squad_id
    || squad.units.length !== squad.formation.filter((id) => id !== null).length
    || squad.units.some((unit) => !squad.formation.includes(unit.id))) {
    throw new Error('Active run and participating squad state disagree. Reload authoritative state.');
  }
  return { squadId: squad.id, unitIds: new Set(squad.formation.filter((id): id is string => id !== null)) };
}
