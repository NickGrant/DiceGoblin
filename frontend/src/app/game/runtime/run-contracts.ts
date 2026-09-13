import { ClientContentRegistry } from './client-content-registry';
import type { GameBootstrapActiveRun, GameBootstrapEnergy } from './game-store';

export class RunContractError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'RunContractError';
  }
}

export interface RunStartResult {
  readonly run: GameBootstrapActiveRun;
  readonly energy: GameBootstrapEnergy;
  readonly playerRevision: number;
}

export type CurrentRunNodeStatus = 'locked' | 'available' | 'completed';

export interface CurrentRunNode {
  readonly id: string;
  readonly nodeIndex: number;
  readonly nodeTypeId: string;
  readonly status: CurrentRunNodeStatus;
  readonly completedAt: string | null;
  readonly position: { readonly column: number; readonly row: number };
}

export interface CurrentRun {
  readonly id: string;
  readonly regionId: string;
  readonly squadId: string;
  readonly status: 'active';
  readonly createdAt: string;
  readonly nodes: readonly CurrentRunNode[];
  readonly edges: readonly { readonly fromNodeId: string; readonly toNodeId: string }[];
  readonly units: readonly { readonly unitId: string; readonly currentHp: number | null }[];
}

export interface CurrentRunResult {
  readonly run: CurrentRun | null;
  readonly playerRevision: number;
}

const stableIdPattern = /^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/;
const positiveIdPattern = /^[1-9][0-9]*$/;
const utcTimestampPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;

function record(value: unknown, context: string): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value))
    throw new RunContractError(`${context} must be an object.`);
  return value as Record<string, unknown>;
}

function exact(value: Record<string, unknown>, fields: readonly string[], context: string): void {
  const actual = Object.keys(value).sort();
  const expected = [...fields].sort();
  if (actual.length !== expected.length || actual.some((field, index) => field !== expected[index]))
    throw new RunContractError(`${context} has an invalid field set.`);
}

function positiveId(value: unknown, context: string): string {
  if (typeof value !== 'string' || !positiveIdPattern.test(value))
    throw new RunContractError(`${context} must be a canonical positive ID string.`);
  return value;
}

function nonNegativeInteger(value: unknown, context: string): number {
  if (!Number.isSafeInteger(value) || (value as number) < 0)
    throw new RunContractError(`${context} must be a non-negative safe integer.`);
  return value as number;
}

function positiveNumber(value: unknown, context: string): number {
  if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0)
    throw new RunContractError(`${context} must be positive.`);
  return value;
}

function positiveInteger(value: unknown, context: string): number {
  const parsed = nonNegativeInteger(value, context);
  if (parsed === 0) throw new RunContractError(`${context} must be positive.`);
  return parsed;
}

function timestamp(value: unknown, context: string): string {
  if (typeof value !== 'string' || !utcTimestampPattern.test(value) || Number.isNaN(Date.parse(value))
    || new Date(value).toISOString() !== value.replace('Z', '.000Z'))
    throw new RunContractError(`${context} must be a UTC timestamp.`);
  return value;
}

function nullableTimestamp(value: unknown, context: string): string | null {
  return value === null ? null : timestamp(value, context);
}

function envelope(value: unknown): Record<string, unknown> {
  const outer = record(value, 'Run response');
  exact(outer, ['ok', 'data'], 'Run response');
  if (outer['ok'] !== true) throw new RunContractError('Run response is not successful.');
  return record(outer['data'], 'Run response data');
}

function regionId(value: unknown, content: ClientContentRegistry): string {
  if (typeof value !== 'string' || !stableIdPattern.test(value) || !value.startsWith('region.') || !content.getRegion(value))
    throw new RunContractError('Run region_id is not projected authored content.');
  return value;
}

function parseEnergy(value: unknown): GameBootstrapEnergy {
  const energy = record(value, 'Run Energy');
  exact(energy, ['current', 'normal_max', 'regeneration_per_hour', 'regeneration_interval_seconds', 'last_regeneration_at', 'next_regeneration_at', 'fully_regenerated_at'], 'Run Energy');
  return Object.freeze({
    current: nonNegativeInteger(energy['current'], 'Energy current'),
    normal_max: positiveInteger(energy['normal_max'], 'Energy normal_max'),
    regeneration_per_hour: positiveNumber(energy['regeneration_per_hour'], 'Energy regeneration_per_hour'),
    regeneration_interval_seconds: positiveInteger(energy['regeneration_interval_seconds'], 'Energy regeneration_interval_seconds'),
    last_regeneration_at: timestamp(energy['last_regeneration_at'], 'Energy last_regeneration_at'),
    next_regeneration_at: nullableTimestamp(energy['next_regeneration_at'], 'Energy next_regeneration_at'),
    fully_regenerated_at: nullableTimestamp(energy['fully_regenerated_at'], 'Energy fully_regenerated_at'),
  });
}

export function parseRunStartEnvelope(value: unknown, content: ClientContentRegistry): RunStartResult {
  const data = envelope(value);
  exact(data, ['run', 'energy', 'player_revision'], 'Run start data');
  const run = record(data['run'], 'Started run');
  exact(run, ['id', 'region_id', 'squad_id', 'status'], 'Started run');
  if (run['status'] !== 'active') throw new RunContractError('Started run must be active.');
  return Object.freeze({
    run: Object.freeze({
      id: positiveId(run['id'], 'Run id'),
      region_id: regionId(run['region_id'], content),
      squad_id: positiveId(run['squad_id'], 'Run squad_id'),
      status: 'active',
    }),
    energy: parseEnergy(data['energy']),
    playerRevision: nonNegativeInteger(data['player_revision'], 'Run player_revision'),
  });
}

export function parseCurrentRunEnvelope(value: unknown, content: ClientContentRegistry): CurrentRunResult {
  const data = envelope(value);
  exact(data, ['run', 'player_revision'], 'Current run data');
  const playerRevision = nonNegativeInteger(data['player_revision'], 'Run player_revision');
  if (data['run'] === null) return Object.freeze({ run: null, playerRevision });
  const raw = record(data['run'], 'Current run');
  exact(raw, ['id', 'region_id', 'squad_id', 'status', 'created_at', 'nodes', 'edges', 'units'], 'Current run');
  if (raw['status'] !== 'active' || !Array.isArray(raw['nodes']) || !Array.isArray(raw['edges']) || !Array.isArray(raw['units']))
    throw new RunContractError('Current run root is malformed.');
  if (raw['nodes'].length === 0 || raw['units'].length === 0)
    throw new RunContractError('Current run must contain nodes and participating units.');

  const nodeIds = new Set<string>();
  const nodes = raw['nodes'].map((candidate, expectedIndex): CurrentRunNode => {
    const node = record(candidate, 'Current run node');
    exact(node, ['id', 'node_index', 'node_type_id', 'status', 'completed_at', 'position'], 'Current run node');
    const id = positiveId(node['id'], 'Node id');
    if (nodeIds.has(id) || node['node_index'] !== expectedIndex) throw new RunContractError('Run node identity/order is invalid.');
    nodeIds.add(id);
    const nodeTypeId = node['node_type_id'];
    if (typeof nodeTypeId !== 'string' || !stableIdPattern.test(nodeTypeId) || !content.getRunNodeType(nodeTypeId))
      throw new RunContractError('Run node type is not projected authored content.');
    if (!['locked', 'available', 'completed'].includes(node['status'] as string)) throw new RunContractError('Run node status is invalid.');
    const completedAt = nullableTimestamp(node['completed_at'], 'Node completed_at');
    if ((node['status'] === 'completed') !== (completedAt !== null)) throw new RunContractError('Run node completion state is incoherent.');
    const position = record(node['position'], 'Run node position');
    exact(position, ['column', 'row'], 'Run node position');
    if (!Number.isSafeInteger(position['column']) || !Number.isSafeInteger(position['row'])) throw new RunContractError('Run node position is invalid.');
    return Object.freeze({ id, nodeIndex: expectedIndex, nodeTypeId, status: node['status'] as CurrentRunNodeStatus,
      completedAt, position: Object.freeze({ column: position['column'] as number, row: position['row'] as number }) });
  });

  const seenEdges = new Set<string>();
  const edges = raw['edges'].map((candidate) => {
    const edge = record(candidate, 'Current run edge');
    exact(edge, ['from_node_id', 'to_node_id'], 'Current run edge');
    const fromNodeId = positiveId(edge['from_node_id'], 'Edge from_node_id');
    const toNodeId = positiveId(edge['to_node_id'], 'Edge to_node_id');
    const key = `${fromNodeId}:${toNodeId}`;
    if (!nodeIds.has(fromNodeId) || !nodeIds.has(toNodeId) || fromNodeId === toNodeId || seenEdges.has(key))
      throw new RunContractError('Run edge is invalid.');
    seenEdges.add(key);
    return Object.freeze({ fromNodeId, toNodeId });
  });
  const reachable = new Set([nodes[0].id]);
  let changed = true;
  while (changed) {
    changed = false;
    for (const edge of edges) if (reachable.has(edge.fromNodeId) && !reachable.has(edge.toNodeId)) {
      reachable.add(edge.toNodeId); changed = true;
    }
  }
  if (reachable.size !== nodes.length) throw new RunContractError('Run graph is disconnected.');

  const unitIds = new Set<string>();
  const units = raw['units'].map((candidate) => {
    const unit = record(candidate, 'Current run unit');
    exact(unit, ['unit_id', 'current_hp'], 'Current run unit');
    const unitId = positiveId(unit['unit_id'], 'Run unit_id');
    if (unitIds.has(unitId)) throw new RunContractError('Run unit IDs must be unique.');
    unitIds.add(unitId);
    const currentHp = unit['current_hp'] === null ? null : nonNegativeInteger(unit['current_hp'], 'Run unit current_hp');
    return Object.freeze({ unitId, currentHp });
  });

  return Object.freeze({
    run: Object.freeze({ id: positiveId(raw['id'], 'Run id'), regionId: regionId(raw['region_id'], content),
      squadId: positiveId(raw['squad_id'], 'Run squad_id'), status: 'active', createdAt: timestamp(raw['created_at'], 'Run created_at'),
      nodes: Object.freeze(nodes), edges: Object.freeze(edges), units: Object.freeze(units) }),
    playerRevision,
  });
}
