export class RunNodeResolutionContractError extends Error {
  constructor(message: string) { super(message); this.name = 'RunNodeResolutionContractError'; }
}

export type ResolvedBattleOutcome = 'victory' | 'defeat' | 'stalemate';

export interface RunNodeResolutionResult {
  readonly battle: { readonly id: string; readonly outcome: ResolvedBattleOutcome; readonly engineVersion: 1;
    readonly playbackVersion: 1; readonly endingRound: number; readonly endingTick: number };
  readonly node: { readonly id: string; readonly status: 'completed'; readonly completedAt: string };
  readonly newlyAvailableNodeIds: readonly string[];
  readonly terminalPlayerHp: Readonly<Record<string, number>>;
  readonly run: { readonly id: string; readonly status: 'active' | 'failed'; readonly endedAt: string | null };
  readonly playerRevision: number;
}

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

export function parseRunNodeResolutionEnvelope(value: unknown): RunNodeResolutionResult {
  const envelope = object(value, 'Node resolution response'); exact(envelope, ['ok', 'data'], 'Node resolution response');
  if (envelope['ok'] !== true) throw new RunNodeResolutionContractError('Node resolution response is not successful.');
  const data = object(envelope['data'], 'Node resolution data');
  exact(data, ['battle', 'node', 'newly_available_node_ids', 'terminal_player_hp', 'run', 'player_revision'], 'Node resolution data');
  const battle = object(data['battle'], 'Resolved battle');
  exact(battle, ['id', 'outcome', 'engine_version', 'playback_version', 'ending_round', 'ending_tick'], 'Resolved battle');
  if (battle['engine_version'] !== 1 || battle['playback_version'] !== 1) throw new RunNodeResolutionContractError('Resolved battle version is unsupported.');
  const outcome = battle['outcome'];
  if (outcome !== 'victory' && outcome !== 'defeat' && outcome !== 'stalemate') throw new RunNodeResolutionContractError('Resolved battle outcome is invalid.');
  const node = object(data['node'], 'Resolved node'); exact(node, ['id', 'status', 'completed_at'], 'Resolved node');
  if (node['status'] !== 'completed') throw new RunNodeResolutionContractError('Resolved node must be completed.');
  if (!Array.isArray(data['newly_available_node_ids'])) throw new RunNodeResolutionContractError('Newly available node IDs must be a list.');
  const newlyAvailableNodeIds = data['newly_available_node_ids'].map((candidate) => id(candidate, 'Newly available node id'));
  if (new Set(newlyAvailableNodeIds).size !== newlyAvailableNodeIds.length) throw new RunNodeResolutionContractError('Newly available node IDs must be unique.');
  const hp = object(data['terminal_player_hp'], 'Terminal player HP'); const terminalPlayerHp: Record<string, number> = {};
  for (const [unitId, currentHp] of Object.entries(hp)) terminalPlayerHp[id(unitId, 'Terminal player unit id')] = integer(currentHp, 'Terminal player HP');
  const run = object(data['run'], 'Resolved run'); exact(run, ['id', 'status', 'ended_at'], 'Resolved run');
  if (run['status'] !== 'active' && run['status'] !== 'failed') throw new RunNodeResolutionContractError('Resolved run status is invalid.');
  const endedAt = run['ended_at'] === null ? null : timestamp(run['ended_at'], 'Resolved run ended_at');
  if ((run['status'] === 'active') !== (endedAt === null)) throw new RunNodeResolutionContractError('Resolved run lifecycle is incoherent.');
  return Object.freeze({
    battle: Object.freeze({ id: id(battle['id'], 'Battle id'), outcome, engineVersion: 1, playbackVersion: 1,
      endingRound: integer(battle['ending_round'], 'Battle ending round'), endingTick: integer(battle['ending_tick'], 'Battle ending tick') }),
    node: Object.freeze({ id: id(node['id'], 'Resolved node id'), status: 'completed', completedAt: timestamp(node['completed_at'], 'Resolved node timestamp') }),
    newlyAvailableNodeIds: Object.freeze(newlyAvailableNodeIds), terminalPlayerHp: Object.freeze(terminalPlayerHp),
    run: Object.freeze({ id: id(run['id'], 'Resolved run id'), status: run['status'], endedAt }),
    playerRevision: integer(data['player_revision'], 'Node resolution player revision'),
  });
}
