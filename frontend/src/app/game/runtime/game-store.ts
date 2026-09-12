export interface GameBootstrapAccount {
  readonly id: string;
  readonly display_name: string;
  readonly role: string;
}

export interface GameBootstrapEnergy {
  readonly current: number;
  readonly normal_max: number;
  readonly regeneration_per_hour: number;
  readonly regeneration_interval_seconds: number;
  readonly last_regeneration_at: string;
  readonly next_regeneration_at: string | null;
  readonly fully_regenerated_at: string | null;
}

export interface GameBootstrapUnitSummary {
  readonly id: string;
  readonly display_name: string;
  readonly unit_type_id: string;
  readonly kin_id: string;
  readonly level: number;
  readonly xp: number;
  readonly lifecycle_status: 'active';
}

export interface GameBootstrapActiveSquad {
  readonly id: string;
  readonly name: string;
  readonly is_active: true;
  readonly formation: readonly (string | null)[];
  readonly units: readonly GameBootstrapUnitSummary[];
}

export interface GameBootstrapData {
  readonly account: GameBootstrapAccount;
  readonly player: {
    readonly teeth: number;
    readonly raw_chaos: number;
    readonly energy: GameBootstrapEnergy;
    readonly player_revision: number;
  };
  readonly session: {
    readonly authenticated: true;
    readonly csrf_token: string;
  };
  readonly server_time: string;
  readonly content_revision: string;
  readonly progression: {
    readonly unlock_ids: readonly string[];
  };
  readonly active_squad: GameBootstrapActiveSquad | null;
  readonly active_run: null;
}

export class BootstrapContractError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'BootstrapContractError';
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function recordField(record: Record<string, unknown>, key: string): Record<string, unknown> {
  const value = record[key];
  if (!isRecord(value)) {
    throw new BootstrapContractError(`Bootstrap field '${key}' must be an object.`);
  }

  return value;
}

function stringField(record: Record<string, unknown>, key: string): string {
  const value = record[key];
  if (typeof value !== 'string' || value.trim() === '') {
    throw new BootstrapContractError(`Bootstrap field '${key}' must be a non-empty string.`);
  }

  return value;
}

function nonNegativeIntegerField(record: Record<string, unknown>, key: string): number {
  const value = record[key];
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 0) {
    throw new BootstrapContractError(`Bootstrap field '${key}' must be a non-negative integer.`);
  }

  return value;
}

function positiveNumberField(record: Record<string, unknown>, key: string): number {
  const value = record[key];
  if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) {
    throw new BootstrapContractError(`Bootstrap field '${key}' must be a positive number.`);
  }

  return value;
}

function nullableStringField(record: Record<string, unknown>, key: string): string | null {
  const value = record[key];
  if (value !== null && (typeof value !== 'string' || value.trim() === '')) {
    throw new BootstrapContractError(`Bootstrap field '${key}' must be a string or null.`);
  }

  return value;
}

function hasExactKeys(record: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(record).sort().join('\0') === [...keys].sort().join('\0');
}

function positiveId(value: unknown): value is string {
  return typeof value === 'string' && /^[1-9][0-9]*$/.test(value);
}

function parseActiveSquad(value: unknown): GameBootstrapActiveSquad | null {
  if (value === null) return null;
  if (!isRecord(value) || !hasExactKeys(value, ['id', 'name', 'is_active', 'formation', 'units'])) {
    throw new BootstrapContractError("Bootstrap field 'active_squad' is malformed.");
  }
  const formation = value['formation'];
  const units = value['units'];
  if (!positiveId(value['id']) || typeof value['name'] !== 'string' || value['name'].trim() === ''
    || Array.from(value['name'].trim()).length > 128 || value['is_active'] !== true
    || !Array.isArray(formation) || formation.length !== 9 || !Array.isArray(units)) {
    throw new BootstrapContractError("Bootstrap field 'active_squad' is malformed.");
  }
  const formationIds = new Set<string>();
  const parsedFormation = formation.map((id) => {
    if (id === null) return null;
    if (!positiveId(id) || formationIds.has(id)) throw new BootstrapContractError('Active squad formation is malformed.');
    formationIds.add(id);
    return id;
  });
  const unitIds = new Set<string>();
  const parsedUnits = units.map((unit): GameBootstrapUnitSummary => {
    if (!isRecord(unit) || !hasExactKeys(unit, ['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status'])
      || !positiveId(unit['id']) || unitIds.has(unit['id']) || !formationIds.has(unit['id'])
      || typeof unit['display_name'] !== 'string' || unit['display_name'].trim() === ''
      || typeof unit['unit_type_id'] !== 'string' || unit['unit_type_id'] === ''
      || typeof unit['kin_id'] !== 'string' || unit['kin_id'] === ''
      || typeof unit['level'] !== 'number' || !Number.isInteger(unit['level']) || unit['level'] < 1
      || typeof unit['xp'] !== 'number' || !Number.isInteger(unit['xp']) || unit['xp'] < 0
      || unit['lifecycle_status'] !== 'active') {
      throw new BootstrapContractError('Active squad unit summary is malformed.');
    }
    unitIds.add(unit['id']);
    return { id: unit['id'], display_name: unit['display_name'], unit_type_id: unit['unit_type_id'],
      kin_id: unit['kin_id'], level: unit['level'], xp: unit['xp'], lifecycle_status: 'active' };
  });
  if (unitIds.size !== formationIds.size) throw new BootstrapContractError('Active squad unit summaries are incomplete.');
  return { id: value['id'], name: value['name'].trim(), is_active: true, formation: parsedFormation, units: parsedUnits };
}

export function parseGameBootstrapEnvelope(value: unknown): GameBootstrapData {
  if (!isRecord(value) || value['ok'] !== true || !isRecord(value['data'])) {
    throw new BootstrapContractError('Bootstrap response must be a successful API envelope.');
  }

  const data = value['data'];
  const account = recordField(data, 'account');
  const player = recordField(data, 'player');
  const energy = recordField(player, 'energy');
  const session = recordField(data, 'session');
  const progression = recordField(data, 'progression');
  const unlockIds = progression['unlock_ids'];

  const parsed: GameBootstrapData = {
    account: {
      id: stringField(account, 'id'),
      display_name: stringField(account, 'display_name'),
      role: stringField(account, 'role'),
    },
    player: {
      teeth: nonNegativeIntegerField(player, 'teeth'),
      raw_chaos: nonNegativeIntegerField(player, 'raw_chaos'),
      energy: {
        current: nonNegativeIntegerField(energy, 'current'),
        normal_max: nonNegativeIntegerField(energy, 'normal_max'),
        regeneration_per_hour: positiveNumberField(energy, 'regeneration_per_hour'),
        regeneration_interval_seconds: positiveNumberField(energy, 'regeneration_interval_seconds'),
        last_regeneration_at: stringField(energy, 'last_regeneration_at'),
        next_regeneration_at: nullableStringField(energy, 'next_regeneration_at'),
        fully_regenerated_at: nullableStringField(energy, 'fully_regenerated_at'),
      },
      player_revision: nonNegativeIntegerField(player, 'player_revision'),
    },
    session: {
      authenticated: true,
      csrf_token: stringField(session, 'csrf_token'),
    },
    server_time: stringField(data, 'server_time'),
    content_revision: stringField(data, 'content_revision'),
    progression: {
      unlock_ids:
        Array.isArray(unlockIds) && unlockIds.every((id) => typeof id === 'string' && id !== '')
          ? [...unlockIds]
          : (() => {
              throw new BootstrapContractError(
                "Bootstrap field 'unlock_ids' must be a string array.",
              );
            })(),
    },
    active_squad: parseActiveSquad(data['active_squad']),
    active_run: null,
  };

  if (
    session['authenticated'] !== true ||
    data['active_run'] !== null
  ) {
    throw new BootstrapContractError(
      'Bootstrap contains invalid Milestone 1 session or active state.',
    );
  }

  return parsed;
}

/** Application-lifetime cache of the latest authoritative bootstrap response. */
export class GameStore {
  private cachedBootstrap: GameBootstrapData | null = null;

  get bootstrap(): GameBootstrapData | null {
    return this.cachedBootstrap;
  }

  get playerRevision(): number | null {
    return this.cachedBootstrap?.player.player_revision ?? null;
  }

  hydrateBootstrap(bootstrap: GameBootstrapData): void {
    this.cachedBootstrap = bootstrap;
  }

  clear(): void {
    this.cachedBootstrap = null;
  }
}
