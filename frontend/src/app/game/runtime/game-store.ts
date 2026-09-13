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

export type WarbandDomainName = 'units' | 'dice' | 'squads';
export type WarbandDomainStatus = 'not-loaded' | 'loading' | 'fresh' | 'stale' | 'error';
export type WarbandDomainErrorKind = RuntimeApiErrorKind | 'integrity' | 'unexpected';

export interface WarbandDomainState<T> {
  readonly status: WarbandDomainStatus;
  readonly data: readonly T[] | null;
  readonly error: WarbandDomainErrorKind | null;
}

export interface WarbandCacheSnapshot {
  readonly units: WarbandDomainState<WarbandUnitSummary>;
  readonly dice: WarbandDomainState<WarbandDieSummary>;
  readonly squads: WarbandDomainState<WarbandSquadSummary>;
}

type WarbandCollectionItem = WarbandUnitSummary | WarbandDieSummary | WarbandSquadSummary;

function emptyDomain<T>(): WarbandDomainState<T> {
  return Object.freeze({ status: 'not-loaded', data: null, error: null });
}

function domainErrorKind(error: unknown): WarbandDomainErrorKind {
  if (error instanceof RuntimeApiError) return error.kind;
  if (error instanceof WarbandContractError) return 'integrity';
  return 'unexpected';
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
  private warbandCache: WarbandCacheSnapshot = this.emptyWarbandCache();
  private readonly inFlight: Partial<Record<WarbandDomainName, Promise<void>>> = {};
  private readonly listeners = new Set<(cache: WarbandCacheSnapshot) => void>();
  private cacheGeneration = 0;

  get bootstrap(): GameBootstrapData | null {
    return this.cachedBootstrap;
  }

  get playerRevision(): number | null {
    return this.cachedBootstrap?.player.player_revision ?? null;
  }

  get warband(): WarbandCacheSnapshot {
    return this.warbandCache;
  }

  hydrateBootstrap(bootstrap: GameBootstrapData): void {
    this.cachedBootstrap = bootstrap;
  }

  subscribeWarband(listener: (cache: WarbandCacheSnapshot) => void): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  loadWarbandDomains(api: RuntimeApiClient, content: ClientContentRegistry): Promise<void[]> {
    return Promise.all([
      this.loadUnits(api, content),
      this.loadDice(api, content),
      this.loadSquads(api),
    ]);
  }

  loadUnits(api: RuntimeApiClient, content: ClientContentRegistry, reload = false): Promise<void> {
    return this.loadDomain('units', () => api.getUnits().then((value) => parseUnitCollectionEnvelope(value, content)), reload);
  }

  loadDice(api: RuntimeApiClient, content: ClientContentRegistry, reload = false): Promise<void> {
    return this.loadDomain('dice', () => api.getDice().then((value) => parseDiceCollectionEnvelope(value, content)), reload);
  }

  loadSquads(api: RuntimeApiClient, reload = false): Promise<void> {
    return this.loadDomain('squads', async () => {
      const squads = parseSquadCollectionEnvelope(await api.getSquads());
      requireActiveSquadAgreement(squads, this.cachedBootstrap?.active_squad?.id ?? null);
      return squads;
    }, reload);
  }

  retryWarbandDomain(
    domain: WarbandDomainName,
    api: RuntimeApiClient,
    content: ClientContentRegistry,
  ): Promise<void> {
    if (domain === 'units') return this.loadUnits(api, content, true);
    if (domain === 'dice') return this.loadDice(api, content, true);
    return this.loadSquads(api, true);
  }

  markWarbandDomainStale(domain: WarbandDomainName): void {
    const state = this.warbandCache[domain];
    if (state.status !== 'fresh') return;
    this.setDomain(domain, { status: 'stale', data: state.data, error: null });
  }

  reconcileSquadMutation(result: SquadMutationResult, operation: 'create' | 'update' | 'activate'): void {
    try {
      const context = this.requireSquadReconciliationContext(result.playerRevision);
      const existingIndex = context.squads.findIndex((squad) => squad.id === result.squad.id);
      if ((operation === 'create' && existingIndex >= 0) || (operation !== 'create' && existingIndex < 0)) {
        throw new WarbandContractError('Affected squad does not agree with the current cache.');
      }
      const nextSquads = [...context.squads];
      if (existingIndex >= 0) nextSquads[existingIndex] = result.squad;
      else nextSquads.push(result.squad);
      this.commitSquadReconciliation(nextSquads, result.activeSquadId, result.playerRevision, context.units);
    } catch (error) {
      this.failSquadReconciliation();
      throw error;
    }
  }

  reconcileSquadDelete(result: SquadDeleteResult): void {
    try {
      const context = this.requireSquadReconciliationContext(result.playerRevision);
      if (!context.squads.some((squad) => squad.id === result.deletedSquadId)) {
        throw new WarbandContractError('Deleted squad does not agree with the current cache.');
      }
      this.commitSquadReconciliation(
        context.squads.filter((squad) => squad.id !== result.deletedSquadId),
        result.activeSquadId,
        result.playerRevision,
        context.units,
      );
    } catch (error) {
      this.failSquadReconciliation();
      throw error;
    }
  }

  clear(): void {
    this.cachedBootstrap = null;
    this.cacheGeneration += 1;
    this.warbandCache = this.emptyWarbandCache();
    for (const key of Object.keys(this.inFlight) as WarbandDomainName[]) delete this.inFlight[key];
    this.emit();
  }

  private loadDomain(
    domain: WarbandDomainName,
    request: () => Promise<readonly WarbandCollectionItem[]>,
    reload: boolean,
  ): Promise<void> {
    const state = this.warbandCache[domain];
    if (!reload && state.status === 'fresh') return Promise.resolve();
    const existing = this.inFlight[domain];
    if (existing) return existing;
    const generation = this.cacheGeneration;
    this.setDomain(domain, { status: 'loading', data: state.data, error: null });
    const promise = request()
      .then((data) => {
        if (generation === this.cacheGeneration) this.setDomain(domain, { status: 'fresh', data, error: null });
      })
      .catch((error: unknown) => {
        if (generation === this.cacheGeneration) {
          this.setDomain(domain, { status: 'error', data: state.data, error: domainErrorKind(error) });
        }
      })
      .finally(() => {
        if (this.inFlight[domain] === promise) delete this.inFlight[domain];
      });
    this.inFlight[domain] = promise;
    return promise;
  }

  private requireSquadReconciliationContext(playerRevision: number): {
    squads: readonly WarbandSquadSummary[];
    units: readonly WarbandUnitSummary[];
  } {
    if (!this.cachedBootstrap || playerRevision < this.cachedBootstrap.player.player_revision) {
      throw new WarbandContractError('Authoritative player revision regressed.');
    }
    if (this.warbandCache.squads.status !== 'fresh' || !this.warbandCache.squads.data
      || this.warbandCache.units.status !== 'fresh' || !this.warbandCache.units.data) {
      throw new WarbandContractError('Fresh squad and unit caches are required for reconciliation.');
    }
    return { squads: this.warbandCache.squads.data, units: this.warbandCache.units.data };
  }

  private commitSquadReconciliation(
    squads: readonly WarbandSquadSummary[],
    activeSquadId: string | null,
    playerRevision: number,
    units: readonly WarbandUnitSummary[],
  ): void {
    const active = activeSquadId === null ? null : squads.find((squad) => squad.id === activeSquadId);
    if (activeSquadId !== null && !active) throw new WarbandContractError('Active squad is absent from the cache.');
    const unitById = new Map(units.map((unit) => [unit.id, unit]));
    for (const squad of squads) {
      for (const unitId of squad.formation) {
        if (unitId !== null && !unitById.has(unitId)) {
          throw new WarbandContractError('Squad formation references an unavailable unit summary.');
        }
      }
    }
    const reconciled = Object.freeze(squads.map((squad) => Object.freeze({
      ...squad, isActive: squad.id === activeSquadId,
    })));
    const bootstrapActive: GameBootstrapActiveSquad | null = active ? {
      id: active.id,
      name: active.name,
      is_active: true,
      formation: Object.freeze([...active.formation]),
      units: Object.freeze(active.formation.filter((id): id is string => id !== null).map((id) => {
        const unit = unitById.get(id)!;
        return Object.freeze({
          id: unit.id, display_name: unit.displayName, unit_type_id: unit.unitType.id,
          kin_id: unit.kin.id, level: unit.level, xp: unit.xp, lifecycle_status: 'active' as const,
        });
      })),
    } : null;
    const bootstrap = this.cachedBootstrap!;
    this.cachedBootstrap = Object.freeze({
      ...bootstrap,
      player: Object.freeze({ ...bootstrap.player, player_revision: playerRevision }),
      active_squad: bootstrapActive,
    });
    this.setDomain('squads', { status: 'fresh', data: reconciled, error: null });
  }

  private failSquadReconciliation(): void {
    const state = this.warbandCache.squads;
    this.setDomain('squads', { status: 'error', data: state.data, error: 'integrity' });
  }

  private setDomain(domain: WarbandDomainName, state: WarbandDomainState<WarbandCollectionItem>): void {
    if (domain === 'units') {
      this.warbandCache = Object.freeze({
        ...this.warbandCache,
        units: Object.freeze(state as WarbandDomainState<WarbandUnitSummary>),
      });
    } else if (domain === 'dice') {
      this.warbandCache = Object.freeze({
        ...this.warbandCache,
        dice: Object.freeze(state as WarbandDomainState<WarbandDieSummary>),
      });
    } else {
      this.warbandCache = Object.freeze({
        ...this.warbandCache,
        squads: Object.freeze(state as WarbandDomainState<WarbandSquadSummary>),
      });
    }
    this.emit();
  }

  private emptyWarbandCache(): WarbandCacheSnapshot {
    return Object.freeze({
      units: emptyDomain<WarbandUnitSummary>(),
      dice: emptyDomain<WarbandDieSummary>(),
      squads: emptyDomain<WarbandSquadSummary>(),
    });
  }

  private emit(): void {
    for (const listener of this.listeners) listener(this.warbandCache);
  }
}
import { ClientContentRegistry } from './client-content-registry';
import { RuntimeApiClient, RuntimeApiError, RuntimeApiErrorKind } from './runtime-api-client';
import {
  SquadDeleteResult,
  SquadMutationResult,
  WarbandContractError,
  WarbandDieSummary,
  WarbandSquadSummary,
  WarbandUnitSummary,
  parseDiceCollectionEnvelope,
  parseSquadCollectionEnvelope,
  parseUnitCollectionEnvelope,
  requireActiveSquadAgreement,
} from './warband-contracts';
