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

export type RunNodeResolutionResult = CombatRunNodeResolutionResult | LootRunNodeResolutionResult | RestRunNodeResolutionResult;

const positiveIdPattern = /^[1-9][0-9]*$/;
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
  if (data['resolution_type'] === 'loot') return parseLoot(data);
  if (data['resolution_type'] === 'rest') return parseRest(data);
  throw new RunNodeResolutionContractError('Node resolution type is unsupported.');
}

function parseCombat(data: Record<string, unknown>): CombatRunNodeResolutionResult {
  exact(data, ['resolution_type', 'battle', 'node', 'newly_available_node_ids', 'terminal_player_hp', 'run', 'player_revision'], 'Combat resolution data');
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
  return Object.freeze({ resolutionType: 'combat',
    battle: Object.freeze({ id: id(battle['id'], 'Battle id'), outcome, engineVersion: 1, playbackVersion: 1,
      endingRound: integer(battle['ending_round'], 'Battle ending round'), endingTick: integer(battle['ending_tick'], 'Battle ending tick') }),
    node: base.node, newlyAvailableNodeIds: base.newlyAvailableNodeIds, terminalPlayerHp: Object.freeze(terminalPlayerHp),
    run: Object.freeze({ id: id(run['id'], 'Resolved run id'), status: run['status'], endedAt }), playerRevision: base.playerRevision });
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
