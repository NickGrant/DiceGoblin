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

export interface GameBootstrapActiveRun {
  readonly id: string;
  readonly region_id: string;
  readonly squad_id: string;
  readonly status: 'active';
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
  readonly active_run: GameBootstrapActiveRun | null;
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

export type UnitDetailStatus = WarbandDomainStatus;

export interface UnitDetailState {
  readonly status: UnitDetailStatus;
  readonly data: UnitDetail | null;
  readonly error: WarbandDomainErrorKind | null;
}

export type CurrentRunStatus = WarbandDomainStatus;
export interface CurrentRunState {
  readonly status: CurrentRunStatus;
  readonly data: CurrentRun | null;
  readonly error: WarbandDomainErrorKind | null;
}

export interface BattleReturnIdentity {
  readonly battleId: string;
  readonly runId: string;
  readonly runNodeId: string;
  readonly minimumPlayerRevision: number;
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

function parseActiveRun(value: unknown): GameBootstrapActiveRun | null {
  if (value === null) return null;
  if (!isRecord(value) || !hasExactKeys(value, ['id', 'region_id', 'squad_id', 'status'])
    || !positiveId(value['id']) || !positiveId(value['squad_id'])
    || typeof value['region_id'] !== 'string' || !/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/.test(value['region_id'])
    || value['status'] !== 'active') {
    throw new BootstrapContractError("Bootstrap field 'active_run' is malformed.");
  }
  return { id: value['id'], region_id: value['region_id'], squad_id: value['squad_id'], status: 'active' };
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
    active_run: parseActiveRun(data['active_run']),
  };

  if (parsed.active_run && (!parsed.active_squad || parsed.active_squad.id !== parsed.active_run.squad_id)) {
    throw new BootstrapContractError('Active run and active squad disagree. Reload authoritative state.');
  }

  if (
    session['authenticated'] !== true
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
  private readonly unitDetailCache = new Map<string, UnitDetailState>();
  private readonly unitDetailInFlight = new Map<string, Promise<void>>();
  private readonly listeners = new Set<(cache: WarbandCacheSnapshot) => void>();
  private readonly runListeners = new Set<(state: CurrentRunState) => void>();
  private currentRunState: CurrentRunState = Object.freeze({ status: 'not-loaded', data: null, error: null });
  private currentRunInFlight: Promise<void> | null = null;
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

  get currentRun(): CurrentRunState {
    return this.currentRunState;
  }

  get activeRunLock(): ActiveRunLock | null {
    return activeRunLock(this.cachedBootstrap);
  }

  unitDetail(unitId: string): UnitDetailState {
    return this.unitDetailCache.get(unitId) ?? Object.freeze({ status: 'not-loaded', data: null, error: null });
  }

  hydrateBootstrap(bootstrap: GameBootstrapData): void {
    this.cachedBootstrap = bootstrap;
  }

  subscribeWarband(listener: (cache: WarbandCacheSnapshot) => void): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  subscribeCurrentRun(listener: (state: CurrentRunState) => void): () => void {
    this.runListeners.add(listener);
    return () => this.runListeners.delete(listener);
  }

  markCurrentRunStale(): void {
    this.setCurrentRun({ status: 'stale', data: this.currentRunState.data, error: null });
  }

  reconcileResolvedRunNodePlayerState(result: LootRunNodeResolutionResult | RestRunNodeResolutionResult): void {
    const bootstrap = this.cachedBootstrap;
    if (!bootstrap || result.playerRevision < bootstrap.player.player_revision)
      throw new RunContractError('Authoritative player revision regressed.');
    const activeRun = bootstrap.active_run;
    if (!activeRun || activeRun.id !== result.run.id)
      throw new RunContractError('Resolved node disagrees with the authoritative active run.');
    const current = this.currentRunState.data;
    if (!current || current.id !== result.run.id || !current.nodes.some((node) => node.id === result.node.id))
      throw new RunContractError('Resolved node disagrees with the cached current run.');
    this.cachedBootstrap = Object.freeze({
      ...bootstrap,
      player: Object.freeze({
        ...bootstrap.player,
        teeth: result.resolutionType === 'loot' ? result.wallet.teeth : bootstrap.player.teeth,
        player_revision: result.playerRevision,
      }),
    });
    this.setCurrentRun({ status: 'stale', data: current, error: null });
  }

  reconcileRunStart(result: RunStartResult): void {
    const bootstrap = this.cachedBootstrap;
    if (!bootstrap || result.playerRevision < bootstrap.player.player_revision)
      throw new RunContractError('Authoritative player revision regressed.');
    if (!bootstrap.active_squad || bootstrap.active_squad.id !== result.run.squad_id)
      throw new RunContractError('Started run squad disagrees with the authoritative active squad.');
    if (bootstrap.active_run && (bootstrap.active_run.id !== result.run.id
      || bootstrap.active_run.region_id !== result.run.region_id || bootstrap.active_run.squad_id !== result.run.squad_id))
      throw new RunContractError('Started run disagrees with the authoritative active run.');
    this.cachedBootstrap = Object.freeze({
      ...bootstrap,
      player: Object.freeze({ ...bootstrap.player, energy: result.energy, player_revision: result.playerRevision }),
      active_run: result.run,
    });
    this.setCurrentRun({ status: 'stale', data: null, error: null });
  }

  reconcileRunAbandon(result: RunAbandonResult): void {
    const bootstrap = this.cachedBootstrap;
    if (!bootstrap || result.playerRevision < bootstrap.player.player_revision)
      throw new RunContractError('Authoritative player revision regressed.');
    const summary = bootstrap.active_run;
    if (!summary || summary.id !== result.run.id || summary.region_id !== result.run.regionId
      || summary.squad_id !== result.run.squadId)
      throw new RunContractError('Abandoned run disagrees with the authoritative active run.');
    if (bootstrap.active_squad && bootstrap.active_squad.id !== result.run.squadId)
      throw new RunContractError('Abandoned run squad disagrees with the authoritative active squad.');
    const current = this.currentRunState.data;
    if (current && (current.id !== result.run.id || current.regionId !== result.run.regionId
      || current.squadId !== result.run.squadId))
      throw new RunContractError('Abandoned run disagrees with the cached current run.');
    this.cachedBootstrap = Object.freeze({ ...bootstrap,
      player: Object.freeze({ ...bootstrap.player, player_revision: result.playerRevision }), active_run: null });
    this.setCurrentRun({ status: 'fresh', data: null, error: null });
  }

  loadCurrentRun(api: RuntimeApiClient, content: ClientContentRegistry, reload = false): Promise<void> {
    if (!reload && this.currentRunState.status === 'fresh') return Promise.resolve();
    if (this.currentRunInFlight) return this.currentRunInFlight;
    const generation = this.cacheGeneration;
    const prior = this.currentRunState;
    this.setCurrentRun({ status: 'loading', data: prior.data, error: null });
    const promise = api.getCurrentRun(content)
      .then((result) => {
        if (generation !== this.cacheGeneration) return;
        this.reconcileCurrentRun(result);
      })
      .catch((error: unknown) => {
        if (generation === this.cacheGeneration)
          this.setCurrentRun({ status: 'error', data: prior.data, error: runErrorKind(error) });
      })
      .finally(() => { if (this.currentRunInFlight === promise) this.currentRunInFlight = null; });
    this.currentRunInFlight = promise;
    return promise;
  }

  retryCurrentRun(api: RuntimeApiClient, content: ClientContentRegistry): Promise<void> {
    return this.loadCurrentRun(api, content, true);
  }

  reconcileBattleReturn(result: CurrentRunResult, battle: BattleReturnIdentity): CurrentRun | null {
    const prior = this.currentRunState;
    try {
      if (result.playerRevision < battle.minimumPlayerRevision)
        throw new RunContractError('Authoritative player revision regressed behind the retained battle.');
      if (result.run?.id === battle.runId) {
        const node = result.run.nodes.find((candidate) => candidate.id === battle.runNodeId);
        if (!node || (node.nodeTypeId !== 'run_node_type.combat' && node.nodeTypeId !== 'run_node_type.boss') || node.status !== 'completed'
          || node.battleId !== battle.battleId)
          throw new RunContractError('Current run contradicts the retained battle relationship.');
      }
      this.reconcileCurrentRun(result);
      return this.currentRunState.data;
    } catch (error) {
      this.setCurrentRun({ status: 'error', data: prior.data, error: runErrorKind(error) });
      throw error;
    }
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

  loadUnitDetail(
    unitId: string,
    api: RuntimeApiClient,
    content: ClientContentRegistry,
    reload = false,
  ): Promise<void> {
    const state = this.unitDetail(unitId);
    if (!reload && state.status === 'fresh') return Promise.resolve();
    const existing = this.unitDetailInFlight.get(unitId);
    if (existing) return existing;
    const units = this.warbandCache.units;
    const dice = this.warbandCache.dice;
    const summary = units.status === 'fresh' ? units.data?.find((unit) => unit.id === unitId) : undefined;
    if (!summary || dice.status !== 'fresh' || !dice.data) {
      this.setUnitDetail(unitId, { status: 'error', data: state.data, error: 'integrity' });
      return Promise.resolve();
    }
    const generation = this.cacheGeneration;
    this.setUnitDetail(unitId, { status: 'loading', data: state.data, error: null });
    const promise = api.getUnitDetail(unitId)
      .then((value) => parseUnitDetailEnvelope(value, content, dice.data!, summary))
      .then((data) => {
        if (generation === this.cacheGeneration) this.setUnitDetail(unitId, { status: 'fresh', data, error: null });
      })
      .catch((error: unknown) => {
        if (generation === this.cacheGeneration) {
          this.setUnitDetail(unitId, { status: 'error', data: state.data, error: unitDetailErrorKind(error) });
        }
      })
      .finally(() => {
        if (this.unitDetailInFlight.get(unitId) === promise) this.unitDetailInFlight.delete(unitId);
      });
    this.unitDetailInFlight.set(unitId, promise);
    return promise;
  }

  retryUnitDetail(unitId: string, api: RuntimeApiClient, content: ClientContentRegistry): Promise<void> {
    return this.loadUnitDetail(unitId, api, content, true);
  }

  markUnitDetailStale(unitId: string): void {
    const state = this.unitDetail(unitId);
    if (state.status === 'fresh') this.setUnitDetail(unitId, { status: 'stale', data: state.data, error: null });
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

  reconcileUnitRename(result: UnitMutationResult): void {
    const current = this.requireUnitReconciliationContext(result, 'rename');
    try {
      if (!sameUnitState(current.detail, result.unit, false, true)) {
        throw new UnitDetailContractError('Rename response changed unrelated unit state.');
      }
      const nextUnits = Object.freeze(current.units.map((unit) => unit.id === result.unit.id
        ? Object.freeze({ ...unit, displayName: result.unit.displayName }) : unit));
      const bootstrap = this.cachedBootstrap!;
      const activeSquad = bootstrap.active_squad ? Object.freeze({
        ...bootstrap.active_squad,
        units: Object.freeze(bootstrap.active_squad.units.map((unit) => unit.id === result.unit.id
          ? Object.freeze({ ...unit, display_name: result.unit.displayName }) : unit)),
      }) : null;
      this.cachedBootstrap = Object.freeze({
        ...bootstrap,
        player: Object.freeze({ ...bootstrap.player, player_revision: result.playerRevision }),
        active_squad: activeSquad,
      });
      this.warbandCache = Object.freeze({
        ...this.warbandCache,
        units: Object.freeze({ status: 'fresh', data: nextUnits, error: null }),
      });
      this.unitDetailCache.set(result.unit.id, Object.freeze({ status: 'fresh', data: result.unit, error: null }));
      this.emit();
    } catch (error) {
      this.failUnitReconciliation(result.unit.id, 'rename');
      throw error;
    }
  }

  reconcileUnitLoadout(result: UnitMutationResult): void {
    const current = this.requireUnitReconciliationContext(result, 'loadout');
    try {
      if (!sameUnitState(current.detail, result.unit, true, false)) {
        throw new UnitDetailContractError('Loadout response changed unrelated unit state.');
      }
      const newBindings = new Map(result.unit.diceBindings.map((binding) => [binding.die.id, binding]));
      const nextDice = Object.freeze(current.dice.map((die) => {
        const replacement = newBindings.get(die.id);
        if (replacement) {
          const otherBinding = die.bindings[0];
          if (otherBinding && otherBinding.unitId !== result.unit.id) {
            throw new UnitDetailContractError('Loadout response conflicts with another unit binding.');
          }
          return Object.freeze({
            ...die,
            bindings: Object.freeze([Object.freeze({
              unitId: result.unit.id, ability: replacement.ability, slotIndex: replacement.slotIndex,
            })]),
          });
        }
        if (die.bindings[0]?.unitId === result.unit.id) {
          return Object.freeze({ ...die, bindings: Object.freeze([]) });
        }
        return die;
      }));
      if (newBindings.size !== result.unit.diceBindings.length
        || result.unit.diceBindings.some((binding) => !current.dice.some((die) => die.id === binding.die.id))) {
        throw new UnitDetailContractError('Loadout response references unavailable dice.');
      }
      const nextDiceById = new Map(nextDice.map((die) => [die.id, die]));
      const reconciledDetail = Object.freeze({
        ...result.unit,
        diceBindings: Object.freeze(result.unit.diceBindings.map((binding) => Object.freeze({
          ...binding, die: nextDiceById.get(binding.die.id)!,
        }))),
      });
      const bootstrap = this.cachedBootstrap!;
      this.cachedBootstrap = Object.freeze({
        ...bootstrap,
        player: Object.freeze({ ...bootstrap.player, player_revision: result.playerRevision }),
      });
      this.warbandCache = Object.freeze({
        ...this.warbandCache,
        dice: Object.freeze({ status: 'fresh', data: nextDice, error: null }),
      });
      this.unitDetailCache.set(result.unit.id, Object.freeze({ status: 'fresh', data: reconciledDetail, error: null }));
      this.emit();
    } catch (error) {
      this.failUnitReconciliation(result.unit.id, 'loadout');
      throw error;
    }
  }

  clear(): void {
    this.cachedBootstrap = null;
    this.cacheGeneration += 1;
    this.warbandCache = this.emptyWarbandCache();
    this.unitDetailCache.clear();
    this.unitDetailInFlight.clear();
    this.currentRunState = Object.freeze({ status: 'not-loaded', data: null, error: null });
    this.currentRunInFlight = null;
    for (const key of Object.keys(this.inFlight) as WarbandDomainName[]) delete this.inFlight[key];
    this.emit();
    this.emitRun();
  }

  private reconcileCurrentRun(result: CurrentRunResult): void {
    const bootstrap = this.cachedBootstrap;
    if (!bootstrap || result.playerRevision < bootstrap.player.player_revision)
      throw new RunContractError('Authoritative player revision regressed.');
    const summary = bootstrap.active_run;
    if (result.run === null) {
      if (summary && result.playerRevision === bootstrap.player.player_revision)
        throw new RunContractError('Equal-revision current run contradicts bootstrap.');
      this.cachedBootstrap = Object.freeze({ ...bootstrap,
        player: Object.freeze({ ...bootstrap.player, player_revision: result.playerRevision }), active_run: null });
      this.setCurrentRun({ status: 'fresh', data: null, error: null });
      return;
    }
    if (bootstrap.active_squad && bootstrap.active_squad.id !== result.run.squadId)
      throw new RunContractError('Current run squad disagrees with the authoritative active squad.');
    const returnedSummary: GameBootstrapActiveRun = Object.freeze({ id: result.run.id,
      region_id: result.run.regionId, squad_id: result.run.squadId, status: 'active' });
    if (!summary && result.playerRevision === bootstrap.player.player_revision)
      throw new RunContractError('Equal-revision current run contradicts bootstrap.');
    if (summary && result.playerRevision === bootstrap.player.player_revision
      && (summary.id !== returnedSummary.id || summary.region_id !== returnedSummary.region_id
        || summary.squad_id !== returnedSummary.squad_id))
      throw new RunContractError('Equal-revision current run contradicts bootstrap.');
    this.cachedBootstrap = Object.freeze({ ...bootstrap,
      player: Object.freeze({ ...bootstrap.player, player_revision: result.playerRevision }), active_run: returnedSummary });
    this.setCurrentRun({ status: 'fresh', data: result.run, error: null });
  }

  private setCurrentRun(state: CurrentRunState): void {
    this.currentRunState = Object.freeze(state);
    this.emitRun();
  }

  private emitRun(): void {
    for (const listener of this.runListeners) listener(this.currentRunState);
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

  private requireUnitReconciliationContext(
    result: UnitMutationResult,
    operation: 'rename' | 'loadout',
  ): { detail: UnitDetail; units: readonly WarbandUnitSummary[]; dice: readonly WarbandDieSummary[] } {
    try {
      if (!this.cachedBootstrap || result.playerRevision < this.cachedBootstrap.player.player_revision) {
        throw new UnitDetailContractError('Authoritative player revision regressed.');
      }
      const detail = this.unitDetail(result.unit.id);
      if (detail.status !== 'fresh' || !detail.data || this.warbandCache.units.status !== 'fresh'
        || !this.warbandCache.units.data || this.warbandCache.dice.status !== 'fresh' || !this.warbandCache.dice.data) {
        throw new UnitDetailContractError('Fresh unit detail, roster, and dice caches are required.');
      }
      if (!this.warbandCache.units.data.some((unit) => unit.id === result.unit.id)) {
        throw new UnitDetailContractError('Unit is absent from the roster cache.');
      }
      return { detail: detail.data, units: this.warbandCache.units.data, dice: this.warbandCache.dice.data };
    } catch (error) {
      this.failUnitReconciliation(result.unit.id, operation);
      throw error;
    }
  }

  private failUnitReconciliation(unitId: string, operation: 'rename' | 'loadout'): void {
    const detail = this.unitDetail(unitId);
    this.unitDetailCache.set(unitId, Object.freeze({ status: 'error', data: detail.data, error: 'integrity' }));
    if (operation === 'rename') {
      const units = this.warbandCache.units;
      this.warbandCache = Object.freeze({ ...this.warbandCache, units: Object.freeze({ status: 'stale', data: units.data, error: 'integrity' }) });
    } else {
      const dice = this.warbandCache.dice;
      this.warbandCache = Object.freeze({ ...this.warbandCache, dice: Object.freeze({ status: 'stale', data: dice.data, error: 'integrity' }) });
    }
    this.emit();
  }

  private setUnitDetail(unitId: string, state: UnitDetailState): void {
    this.unitDetailCache.set(unitId, Object.freeze(state));
    this.emit();
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
import {
  UnitDetail,
  UnitDetailContractError,
  UnitMutationResult,
  parseUnitDetailEnvelope,
} from './unit-detail-contracts';
import { CurrentRun, CurrentRunResult, RunAbandonResult, RunContractError, RunStartResult } from './run-contracts';
import { activeRunLock, ActiveRunLock } from './active-run-lock';
import { LootRunNodeResolutionResult, RestRunNodeResolutionResult } from './run-node-resolution-contracts';

function unitDetailErrorKind(error: unknown): WarbandDomainErrorKind {
  if (error instanceof RuntimeApiError) return error.kind;
  if (error instanceof UnitDetailContractError) return 'integrity';
  return 'unexpected';
}

function runErrorKind(error: unknown): WarbandDomainErrorKind {
  if (error instanceof RuntimeApiError) return error.kind;
  if (error instanceof RunContractError) return 'integrity';
  return 'unexpected';
}

function sameUnitState(
  current: UnitDetail,
  next: UnitDetail,
  allowLoadoutChange: boolean,
  allowNameChange: boolean,
): boolean {
  if (current.id !== next.id || (!allowNameChange && current.displayName !== next.displayName)
    || current.unitType.id !== next.unitType.id || current.kin.id !== next.kin.id
    || current.level !== next.level || current.xp !== next.xp || current.lifecycleStatus !== next.lifecycleStatus
    || current.ownedAbilities.map((ability) => ability.id).join('\0') !== next.ownedAbilities.map((ability) => ability.id).join('\0')
    || JSON.stringify(current.promotionHistory.map((entry) => [entry.fromUnitType.id, entry.toUnitType.id, entry.promotedAt]))
      !== JSON.stringify(next.promotionHistory.map((entry) => [entry.fromUnitType.id, entry.toUnitType.id, entry.promotedAt]))) {
    return false;
  }
  if (allowLoadoutChange) return true;
  return JSON.stringify(current.abilityLoadout.map((entry) => entry.ability.id))
      === JSON.stringify(next.abilityLoadout.map((entry) => entry.ability.id))
    && JSON.stringify(current.diceBindings.map((entry) => [entry.ability.id, entry.slotIndex, entry.die.id]))
      === JSON.stringify(next.diceBindings.map((entry) => [entry.ability.id, entry.slotIndex, entry.die.id]));
}
