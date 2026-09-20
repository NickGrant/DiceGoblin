export class RunNodeResolutionContractError extends Error {
  constructor(message: string) { super(message); this.name = 'RunNodeResolutionContractError'; }
}

export type ResolvedBattleOutcome = 'victory' | 'defeat' | 'stalemate';
export interface CompletedRunNode { readonly id: string; readonly status: 'completed'; readonly completedAt: string }
export interface ResolvedActiveRun { readonly id: string; readonly status: 'active'; readonly endedAt: null }

interface ResolutionBase {
  readonly node: CompletedRunNode;
  readonly newlyAvailableNodeIds: readonly string[];
  readonly playerRevision: number;
}

export interface CombatRunNodeResolutionResult extends ResolutionBase {
  readonly resolutionType: 'combat';
  readonly battle: { readonly id: string; readonly outcome: ResolvedBattleOutcome; readonly engineVersion: 1;
    readonly playbackVersion: 1; readonly endingRound: number; readonly endingTick: number };
  readonly terminalPlayerHp: Readonly<Record<string, number>>;
  readonly run: { readonly id: string; readonly status: 'active' | 'failed'; readonly endedAt: string | null };
}

export interface BossRunNodeResolutionResult extends ResolutionBase {
  readonly resolutionType: 'boss';
  readonly battle: CombatRunNodeResolutionResult['battle'];
  readonly terminalPlayerHp: Readonly<Record<string, number>>;
  readonly run: CombatRunNodeResolutionResult['run'];
  readonly rewards: null | {
    readonly unitXp: readonly { readonly unitId: string; readonly amount: number; readonly levelBefore: number;
      readonly xpBefore: number; readonly levelAfter: number; readonly xpAfter: number }[];
    readonly unlocks: readonly { readonly unlockId: string; readonly outcome: 'granted' | 'already_owned' }[];
  };
}

export interface LootRunNodeResolutionResult extends ResolutionBase {
  readonly resolutionType: 'loot';
  readonly wallet: { readonly teeth: number };
  readonly grantedRewards: readonly [{ readonly rewardType: 'currency'; readonly currencyId: 'teeth'; readonly amount: number }];
  readonly run: ResolvedActiveRun;
}

export interface RestRunNodeResolutionResult extends ResolutionBase {
  readonly resolutionType: 'rest';
  readonly healing: readonly { readonly unitId: string; readonly hpBefore: number; readonly hpAfter: number; readonly maxHp: number }[];
  readonly run: ResolvedActiveRun;
}

export interface ExitRunNodeResolutionResult extends ResolutionBase {
  readonly resolutionType: 'exit';
  readonly newlyAvailableNodeIds: readonly [];
  readonly run: { readonly id: string; readonly status: 'completed'; readonly endedAt: string };
}

export type BattleRunNodeResolutionResult = CombatRunNodeResolutionResult | BossRunNodeResolutionResult;
export type RunNodeResolutionResult = BattleRunNodeResolutionResult | LootRunNodeResolutionResult | RestRunNodeResolutionResult
  | ExitRunNodeResolutionResult;

const positiveIdPattern = /^[1-9][0-9]*$/;
const unlockIdPattern = /^unlock\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const utcTimestampPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;

function object(value: unknown, context: string): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) throw new RunNodeResolutionContractError(`${context} must be an object.`);
  return value as Record<string, unknown>;
}
function exact(value: Record<string, unknown>, fields: readonly string[], context: string): void {
  const actual = Object.keys(value).sort(); const expected = [...fields].sort();
  if (actual.length !== expected.length || actual.some((field, index) => field !== expected[index]))
    throw new RunNodeResolutionContractError(`${context} has an invalid field set.`);
}
function id(value: unknown, context: string): string {
  if (typeof value !== 'string' || !positiveIdPattern.test(value)) throw new RunNodeResolutionContractError(`${context} is invalid.`);
  return value;
}
function integer(value: unknown, context: string): number {
  if (!Number.isSafeInteger(value) || (value as number) < 0) throw new RunNodeResolutionContractError(`${context} is invalid.`);
  return value as number;
}
function positiveInteger(value: unknown, context: string): number {
  const result = integer(value, context);
  if (result < 1) throw new RunNodeResolutionContractError(`${context} is invalid.`);
  return result;
}
function unlockId(value: unknown): string {
  if (typeof value !== 'string' || !unlockIdPattern.test(value)) throw new RunNodeResolutionContractError('Boss unlock ID is invalid.');
  return value;
}
function validXpTransition(levelBefore: number, xpBefore: number, amount: number, levelAfter: number, xpAfter: number): boolean {
  if (levelBefore < 1 || levelAfter < levelBefore || xpBefore >= levelBefore * 100 || xpAfter >= levelAfter * 100) return false;
  const beforeTotal = xpBefore + ((levelBefore - 1) * levelBefore * 50);
  const afterTotal = xpAfter + ((levelAfter - 1) * levelAfter * 50);
  return Number.isSafeInteger(beforeTotal) && Number.isSafeInteger(afterTotal) && Number.isSafeInteger(beforeTotal + amount)
    && beforeTotal + amount === afterTotal;
}
function timestamp(value: unknown, context: string): string {
  if (typeof value !== 'string' || !utcTimestampPattern.test(value) || Number.isNaN(Date.parse(value)))
    throw new RunNodeResolutionContractError(`${context} is invalid.`);
  return value;
}
function common(data: Record<string, unknown>): ResolutionBase & { readonly runValue: Record<string, unknown> } {
  const node = object(data['node'], 'Resolved node'); exact(node, ['id', 'status', 'completed_at'], 'Resolved node');
  if (node['status'] !== 'completed') throw new RunNodeResolutionContractError('Resolved node must be completed.');
  if (!Array.isArray(data['newly_available_node_ids'])) throw new RunNodeResolutionContractError('Newly available node IDs must be a list.');
  const newlyAvailableNodeIds = data['newly_available_node_ids'].map((candidate) => id(candidate, 'Newly available node id'));
  if (new Set(newlyAvailableNodeIds).size !== newlyAvailableNodeIds.length) throw new RunNodeResolutionContractError('Newly available node IDs must be unique.');
  return { node: Object.freeze({ id: id(node['id'], 'Resolved node id'), status: 'completed',
      completedAt: timestamp(node['completed_at'], 'Resolved node timestamp') }),
    newlyAvailableNodeIds: Object.freeze(newlyAvailableNodeIds),
    playerRevision: integer(data['player_revision'], 'Node resolution player revision'),
    runValue: object(data['run'], 'Resolved run') };
}
function activeRun(value: Record<string, unknown>): ResolvedActiveRun {
  exact(value, ['id', 'status', 'ended_at'], 'Resolved run');
  if (value['status'] !== 'active' || value['ended_at'] !== null) throw new RunNodeResolutionContractError('Resolved run must remain active.');
  return Object.freeze({ id: id(value['id'], 'Resolved run id'), status: 'active', endedAt: null });
}

export function parseRunNodeResolutionEnvelope(value: unknown): RunNodeResolutionResult {
  const envelope = object(value, 'Node resolution response'); exact(envelope, ['ok', 'data'], 'Node resolution response');
  if (envelope['ok'] !== true) throw new RunNodeResolutionContractError('Node resolution response is not successful.');
  const data = object(envelope['data'], 'Node resolution data');
  if (data['resolution_type'] === 'combat') return parseCombat(data);
  if (data['resolution_type'] === 'boss') return parseBoss(data);
  if (data['resolution_type'] === 'loot') return parseLoot(data);
  if (data['resolution_type'] === 'rest') return parseRest(data);
  if (data['resolution_type'] === 'exit') return parseExit(data);
  throw new RunNodeResolutionContractError('Node resolution type is unsupported.');
}

function parseBattleFacts(data: Record<string, unknown>): Omit<CombatRunNodeResolutionResult, 'resolutionType'> {
  const base = common(data); const battle = object(data['battle'], 'Resolved battle');
  exact(battle, ['id', 'outcome', 'engine_version', 'playback_version', 'ending_round', 'ending_tick'], 'Resolved battle');
  if (battle['engine_version'] !== 1 || battle['playback_version'] !== 1) throw new RunNodeResolutionContractError('Resolved battle version is unsupported.');
  const outcome = battle['outcome'];
  if (outcome !== 'victory' && outcome !== 'defeat' && outcome !== 'stalemate') throw new RunNodeResolutionContractError('Resolved battle outcome is invalid.');
  const hp = object(data['terminal_player_hp'], 'Terminal player HP'); const terminalPlayerHp: Record<string, number> = {};
  for (const [unitId, currentHp] of Object.entries(hp)) terminalPlayerHp[id(unitId, 'Terminal player unit id')] = integer(currentHp, 'Terminal player HP');
  const run = base.runValue; exact(run, ['id', 'status', 'ended_at'], 'Resolved run');
  if (run['status'] !== 'active' && run['status'] !== 'failed') throw new RunNodeResolutionContractError('Resolved run status is invalid.');
  const endedAt = run['ended_at'] === null ? null : timestamp(run['ended_at'], 'Resolved run ended_at');
  if ((run['status'] === 'active') !== (endedAt === null)) throw new RunNodeResolutionContractError('Resolved run lifecycle is incoherent.');
  return { battle: Object.freeze({ id: id(battle['id'], 'Battle id'), outcome, engineVersion: 1, playbackVersion: 1,
      endingRound: integer(battle['ending_round'], 'Battle ending round'), endingTick: integer(battle['ending_tick'], 'Battle ending tick') }),
    node: base.node, newlyAvailableNodeIds: base.newlyAvailableNodeIds, terminalPlayerHp: Object.freeze(terminalPlayerHp),
    run: Object.freeze({ id: id(run['id'], 'Resolved run id'), status: run['status'], endedAt }), playerRevision: base.playerRevision };
}

function parseCombat(data: Record<string, unknown>): CombatRunNodeResolutionResult {
  exact(data, ['resolution_type', 'battle', 'node', 'newly_available_node_ids', 'terminal_player_hp', 'run', 'player_revision'], 'Combat resolution data');
  return Object.freeze({ resolutionType: 'combat', ...parseBattleFacts(data) });
}

function parseBoss(data: Record<string, unknown>): BossRunNodeResolutionResult {
  exact(data, ['resolution_type', 'battle', 'node', 'newly_available_node_ids', 'terminal_player_hp', 'rewards', 'run', 'player_revision'], 'Boss resolution data');
  const facts = parseBattleFacts(data);
  if (data['rewards'] === null) {
    if (facts.battle.outcome === 'victory') throw new RunNodeResolutionContractError('Boss victory rewards are missing.');
    return Object.freeze({ resolutionType: 'boss', ...facts, rewards: null });
  }
  if (facts.battle.outcome !== 'victory') throw new RunNodeResolutionContractError('Failed Boss battle cannot grant rewards.');
  const rewards = object(data['rewards'], 'Boss rewards'); exact(rewards, ['unit_xp', 'unlocks'], 'Boss rewards');
  if (!Array.isArray(rewards['unit_xp']) || rewards['unit_xp'].length === 0) throw new RunNodeResolutionContractError('Boss XP rewards are invalid.');
  let priorUnitId: string | null = null;
  const unitXp = rewards['unit_xp'].map((value) => {
    const row = object(value, 'Boss XP transition');
    exact(row, ['unit_id', 'amount', 'level_before', 'xp_before', 'level_after', 'xp_after'], 'Boss XP transition');
    const unitId = id(row['unit_id'], 'Boss XP unit id');
    if (priorUnitId !== null && (unitId.length < priorUnitId.length || (unitId.length === priorUnitId.length && unitId <= priorUnitId)))
      throw new RunNodeResolutionContractError('Boss XP unit IDs must be uniquely ordered.');
    priorUnitId = unitId;
    const amount = positiveInteger(row['amount'], 'Boss XP amount');
    const levelBefore = positiveInteger(row['level_before'], 'Boss level before');
    const xpBefore = integer(row['xp_before'], 'Boss XP before');
    const levelAfter = positiveInteger(row['level_after'], 'Boss level after');
    const xpAfter = integer(row['xp_after'], 'Boss XP after');
    if (!validXpTransition(levelBefore, xpBefore, amount, levelAfter, xpAfter))
      throw new RunNodeResolutionContractError('Boss XP transition is incoherent.');
    return Object.freeze({ unitId, amount, levelBefore, xpBefore, levelAfter, xpAfter });
  });
  if (!Array.isArray(rewards['unlocks'])) throw new RunNodeResolutionContractError('Boss unlock rewards are invalid.');
  let priorUnlockId: string | null = null;
  const unlocks = rewards['unlocks'].map((value) => {
    const row = object(value, 'Boss unlock reward'); exact(row, ['unlock_id', 'outcome'], 'Boss unlock reward');
    const currentUnlockId = unlockId(row['unlock_id']);
    if (priorUnlockId !== null && currentUnlockId <= priorUnlockId)
      throw new RunNodeResolutionContractError('Boss unlock IDs must be uniquely ordered.');
    if (row['outcome'] !== 'granted' && row['outcome'] !== 'already_owned')
      throw new RunNodeResolutionContractError('Boss unlock outcome is invalid.');
    priorUnlockId = currentUnlockId;
    return Object.freeze({ unlockId: currentUnlockId, outcome: row['outcome'] });
  });
  return Object.freeze({ resolutionType: 'boss', ...facts, rewards: Object.freeze({ unitXp: Object.freeze(unitXp),
    unlocks: Object.freeze(unlocks) }) });
}

function parseLoot(data: Record<string, unknown>): LootRunNodeResolutionResult {
  exact(data, ['resolution_type', 'wallet', 'granted_rewards', 'node', 'newly_available_node_ids', 'run', 'player_revision'], 'Loot resolution data');
  const base = common(data); const wallet = object(data['wallet'], 'Loot wallet'); exact(wallet, ['teeth'], 'Loot wallet');
  const rewards = data['granted_rewards'];
  if (!Array.isArray(rewards) || rewards.length !== 1) throw new RunNodeResolutionContractError('Loot rewards are invalid.');
  const reward = object(rewards[0], 'Loot reward'); exact(reward, ['reward_type', 'currency_id', 'amount'], 'Loot reward');
  if (reward['reward_type'] !== 'currency' || reward['currency_id'] !== 'teeth') throw new RunNodeResolutionContractError('Loot reward is unsupported.');
  const grantedRewards: LootRunNodeResolutionResult['grantedRewards'] = Object.freeze([Object.freeze({
    rewardType: 'currency' as const, currencyId: 'teeth' as const,
    amount: integer(reward['amount'], 'Loot reward amount'),
  })]);
  return Object.freeze({ resolutionType: 'loot', node: base.node, newlyAvailableNodeIds: base.newlyAvailableNodeIds,
    wallet: Object.freeze({ teeth: integer(wallet['teeth'], 'Loot Teeth balance') }),
    grantedRewards, run: activeRun(base.runValue), playerRevision: base.playerRevision });
}

function parseRest(data: Record<string, unknown>): RestRunNodeResolutionResult {
  exact(data, ['resolution_type', 'healing', 'node', 'newly_available_node_ids', 'run', 'player_revision'], 'Rest resolution data');
  const base = common(data); const raw = data['healing'];
  if (!Array.isArray(raw) || raw.length === 0) throw new RunNodeResolutionContractError('Rest healing is invalid.');
  let prior: string | null = null;
  const healing = raw.map((value) => {
    const row = object(value, 'Rest healing transition'); exact(row, ['unit_id', 'hp_before', 'hp_after', 'max_hp'], 'Rest healing transition');
    const unitId = id(row['unit_id'], 'Rest unit id');
    if (prior !== null && (unitId.length < prior.length || (unitId.length === prior.length && unitId <= prior)))
      throw new RunNodeResolutionContractError('Rest unit IDs must be uniquely ordered.');
    prior = unitId;
    const hpBefore = integer(row['hp_before'], 'Rest HP before'); const hpAfter = integer(row['hp_after'], 'Rest HP after');
    const maxHp = integer(row['max_hp'], 'Rest max HP');
    if (hpAfter !== maxHp || hpBefore > maxHp || maxHp < 1) throw new RunNodeResolutionContractError('Rest HP transition is incoherent.');
    return Object.freeze({ unitId, hpBefore, hpAfter, maxHp });
  });
  return Object.freeze({ resolutionType: 'rest', node: base.node, newlyAvailableNodeIds: base.newlyAvailableNodeIds,
    healing: Object.freeze(healing), run: activeRun(base.runValue), playerRevision: base.playerRevision });
}

function parseExit(data: Record<string, unknown>): ExitRunNodeResolutionResult {
  exact(data, ['resolution_type', 'node', 'newly_available_node_ids', 'run', 'player_revision'], 'Exit resolution data');
  const base = common(data);
  if (base.newlyAvailableNodeIds.length !== 0) throw new RunNodeResolutionContractError('Exit cannot unlock another run node.');
  const run = base.runValue; exact(run, ['id', 'status', 'ended_at'], 'Resolved run');
  if (run['status'] !== 'completed') throw new RunNodeResolutionContractError('Exit must complete the run.');
  return Object.freeze({ resolutionType: 'exit', node: base.node, newlyAvailableNodeIds: Object.freeze([] as const),
    run: Object.freeze({ id: id(run['id'], 'Resolved run id'), status: 'completed',
      endedAt: timestamp(run['ended_at'], 'Resolved run ended_at') }), playerRevision: base.playerRevision });
}
