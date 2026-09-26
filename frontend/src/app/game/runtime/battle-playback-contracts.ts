export class BattlePlaybackContractError extends Error {
  constructor(message: string) { super(message); this.name = 'BattlePlaybackContractError'; }
}

export type BattleOutcome = 'victory' | 'defeat' | 'stalemate';
export type BattleSide = 'player' | 'enemy';

export interface BattlePlaybackParticipant {
  readonly combatantKey: string;
  readonly side: BattleSide;
  readonly unitId: string | null;
  readonly unitTypeId: string | null;
  readonly enemyUnitTypeId: string | null;
  readonly displayName: string;
  readonly artKey: string;
  readonly position: { readonly x: number; readonly y: number };
  readonly initialHp: number;
  readonly maxHp: number;
  readonly terminalHp: number;
  readonly isDefeated: boolean;
  readonly terminalStatuses: readonly Readonly<Record<string, unknown>>[];
}

export interface BattlePlaybackEvent {
  readonly sequence: number;
  readonly type: string;
  readonly round: number;
  readonly tick: number;
  readonly facts: Readonly<Record<string, unknown>>;
}

export interface BattlePlaybackResult {
  readonly battle: {
    readonly id: string;
    readonly runId: string;
    readonly runNodeId: string;
    readonly engineVersion: 1;
    readonly playbackVersion: 1;
    readonly outcome: BattleOutcome;
    readonly endingRound: number;
    readonly endingTick: number;
    readonly participants: readonly BattlePlaybackParticipant[];
    readonly events: readonly BattlePlaybackEvent[];
  };
  readonly playerRevision: number;
}

const positiveIdPattern = /^[1-9][0-9]*$/;
const combatantKeyPattern = /^[a-z][a-z0-9_]*$/;
const playerTypePattern = /^unit_type\.[a-z0-9][a-z0-9_.-]*$/;
const enemyTypePattern = /^enemy_unit_type\.[a-z0-9][a-z0-9_.-]*$/;
const eventFacts: Readonly<Record<string, readonly string[]>> = Object.freeze({
  battle_started: ['combatant_keys'], round_started: [],
  action_started: ['actor_key', 'ability_id', 'target_key', 'target_reason'],
  action_skipped: ['actor_key', 'ability_id', 'reason'],
  dice_rolled: ['actor_key', 'ability_id', 'slot', 'die_key', 'sides', 'initial_roll', 'extra_roll', 'roll_total'],
  hit_resolved: ['actor_key', 'target_key', 'result', 'chance_percent', 'check_roll'],
  damage_dealt: ['actor_key', 'target_key', 'amount', 'hp_before', 'hp_after', 'attack_component', 'roll_total',
    'target_defense', 'conditional_multiplier', 'position_multiplier'],
  status_applied: ['target_key', 'status_id', 'source_key', 'expires_round', 'params', 'forced_target_key'],
  status_resisted: ['target_key', 'status_id', 'chance_percent', 'check_roll'],
  status_removed: ['target_key', 'status_id', 'reason'], death: ['combatant_key'], battle_ended: ['outcome'],
});
const referenceFields = ['actor_key', 'target_key', 'source_key', 'combatant_key', 'forced_target_key'] as const;

function object(value: unknown, context: string): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value))
    throw new BattlePlaybackContractError(`${context} must be an object.`);
  return value as Record<string, unknown>;
}
function exact(value: Record<string, unknown>, fields: readonly string[], context: string): void {
  const actual = Object.keys(value).sort(); const expected = [...fields].sort();
  if (actual.length !== expected.length || actual.some((key, i) => key !== expected[i]))
    throw new BattlePlaybackContractError(`${context} has an invalid field set.`);
}
function integer(value: unknown, min: number, context: string): number {
  if (!Number.isSafeInteger(value) || (value as number) < min) throw new BattlePlaybackContractError(`${context} is invalid.`);
  return value as number;
}
function id(value: unknown, context: string): string {
  if (typeof value !== 'string' || !positiveIdPattern.test(value)) throw new BattlePlaybackContractError(`${context} is invalid.`);
  return value;
}
function text(value: unknown, context: string): string {
  if (typeof value !== 'string' || value.trim() === '') throw new BattlePlaybackContractError(`${context} is invalid.`);
  return value;
}
function outcome(value: unknown): BattleOutcome {
  if (value !== 'victory' && value !== 'defeat' && value !== 'stalemate') throw new BattlePlaybackContractError('Battle outcome is invalid.');
  return value;
}
function finite(value: unknown, context: string): number {
  if (typeof value !== 'number' || !Number.isFinite(value)) throw new BattlePlaybackContractError(`${context} is invalid.`);
  return value;
}

function parseStatusSchema(
  statusIdValue: unknown,
  sourceValue: unknown,
  expiresRoundValue: unknown,
  paramsValue: unknown,
  forcedTargetValue: unknown,
  keys: ReadonlySet<string>,
): Readonly<Record<string, unknown>> {
  const statusId = text(statusIdValue, 'Status id');
  if (!['bolstered', 'sleep', 'cracked_armor', 'wrestled', 'taunting_guard', 'disarmed', 'fuse_lit',
    'shield_set', 'marked'].includes(statusId))
    throw new BattlePlaybackContractError('Status id is unsupported.');
  const source = text(sourceValue, 'Status source');
  if (!keys.has(source)) throw new BattlePlaybackContractError('Status source is unknown.');
  integer(expiresRoundValue, 1, 'Status expiration'); const params = object(paramsValue, 'Status params');
  const paramFields = statusId === 'bolstered' ? ['defense_pct']
    : statusId === 'cracked_armor' ? ['defense_reduction_flat']
    : statusId === 'taunting_guard' ? ['stack_count', 'per_stack_damage_reduction']
    : statusId === 'disarmed' ? ['attack_reduction_pct']
    : statusId === 'fuse_lit' ? ['bomb_damage']
    : statusId === 'shield_set' ? ['stacks', 'defense_flat_per_stack'] : [];
  exact(params, paramFields, 'Status params');
  if (statusId === 'bolstered' && (finite(params['defense_pct'], 'Status defense percent') < 0 || (params['defense_pct'] as number) > 1))
    throw new BattlePlaybackContractError('Status defense percent is invalid.');
  if (statusId === 'cracked_armor') integer(params['defense_reduction_flat'], 0, 'Status defense reduction');
  if (statusId === 'taunting_guard') {
    integer(params['stack_count'], 1, 'Status guard stack count');
    integer(params['per_stack_damage_reduction'], 0, 'Status guard damage reduction');
  }
  if (statusId === 'disarmed'
    && (finite(params['attack_reduction_pct'], 'Status attack reduction') < 0 || (params['attack_reduction_pct'] as number) > 1))
    throw new BattlePlaybackContractError('Status attack reduction is invalid.');
  if (statusId === 'fuse_lit') integer(params['bomb_damage'], 1, 'Status bomb damage');
  if (statusId === 'shield_set') {
    integer(params['stacks'], 1, 'Status shield stack count');
    integer(params['defense_flat_per_stack'], 0, 'Status shield defense');
  }
  if (forcedTargetValue !== null
    && (typeof forcedTargetValue !== 'string' || !keys.has(forcedTargetValue)))
    throw new BattlePlaybackContractError('Status forced target is unknown.');
  if ((statusId === 'wrestled') !== (forcedTargetValue !== null))
    throw new BattlePlaybackContractError('Status forced target is incoherent.');
  return Object.freeze({ ...params });
}

function parseStatus(value: unknown, keys: ReadonlySet<string>): Readonly<Record<string, unknown>> {
  const status = object(value, 'Terminal status');
  exact(status, ['id', 'source_key', 'expires_round', 'params', 'forced_target_key'], 'Terminal status');
  const params = parseStatusSchema(status['id'], status['source_key'], status['expires_round'], status['params'],
    status['forced_target_key'], keys);
  return Object.freeze({ ...status, params: Object.freeze({ ...params }) });
}

function parseEvent(value: unknown, index: number, keys: ReadonlySet<string>): BattlePlaybackEvent {
  const event = object(value, 'Playback event');
  exact(event, ['sequence', 'type', 'round', 'tick', 'facts'], 'Playback event');
  if (event['sequence'] !== index) throw new BattlePlaybackContractError('Playback sequence is not contiguous.');
  const type = text(event['type'], 'Playback event type');
  const expectedFacts = eventFacts[type];
  if (!expectedFacts) throw new BattlePlaybackContractError('Playback event type is unsupported.');
  const facts = object(event['facts'], 'Playback facts'); exact(facts, expectedFacts, 'Playback facts');
  for (const field of referenceFields) {
    const ref = facts[field];
    if (ref !== undefined && ref !== null && (typeof ref !== 'string' || !keys.has(ref)))
      throw new BattlePlaybackContractError('Playback event references an unknown combatant.');
  }
  for (const field of ['actor_key', 'target_key', 'source_key', 'combatant_key'])
    if (field in facts && (typeof facts[field] !== 'string' || !keys.has(facts[field] as string)))
      throw new BattlePlaybackContractError('Playback event reference is invalid.');
  for (const field of ['ability_id', 'target_reason', 'die_key', 'status_id', 'reason'])
    if (field in facts) text(facts[field], `Playback ${field}`);
  for (const field of ['slot', 'sides', 'initial_roll', 'roll_total', 'amount', 'hp_before', 'hp_after',
    'attack_component', 'target_defense', 'expires_round', 'chance_percent'])
    if (field in facts) integer(facts[field], 0, `Playback ${field}`);
  for (const field of ['extra_roll', 'check_roll'])
    if (field in facts && facts[field] !== null) integer(facts[field], 0, `Playback ${field}`);
  for (const field of ['conditional_multiplier', 'position_multiplier']) if (field in facts) finite(facts[field], `Playback ${field}`);
  if ('result' in facts && !['hit', 'miss', 'critical'].includes(facts['result'] as string)) throw new BattlePlaybackContractError('Playback hit result is invalid.');
  if ('outcome' in facts) outcome(facts['outcome']);
  const statusParams = type === 'status_applied'
    ? parseStatusSchema(facts['status_id'], facts['source_key'], facts['expires_round'], facts['params'],
      facts['forced_target_key'], keys)
    : null;
  if ('combatant_keys' in facts) {
    if (!Array.isArray(facts['combatant_keys'])) throw new BattlePlaybackContractError('Battle start keys are invalid.');
    const started = facts['combatant_keys'];
    if (started.length !== keys.size || new Set(started).size !== started.length
      || started.some((key) => typeof key !== 'string' || !keys.has(key)))
      throw new BattlePlaybackContractError('Battle start keys do not match participants.');
  }
  return Object.freeze({ sequence: index, type, round: integer(event['round'], 0, 'Event round'),
    tick: integer(event['tick'], 0, 'Event tick'),
    facts: Object.freeze(statusParams === null ? { ...facts } : { ...facts, params: statusParams }) });
}

export function parseBattlePlaybackEnvelope(value: unknown): BattlePlaybackResult {
  const envelope = object(value, 'Battle playback response'); exact(envelope, ['ok', 'data'], 'Battle playback response');
  if (envelope['ok'] !== true) throw new BattlePlaybackContractError('Battle playback response is not successful.');
  const data = object(envelope['data'], 'Battle playback data'); exact(data, ['battle', 'player_revision'], 'Battle playback data');
  const battle = object(data['battle'], 'Battle playback');
  exact(battle, ['id', 'run_id', 'run_node_id', 'engine_version', 'playback_version', 'outcome', 'ending_round',
    'ending_tick', 'participants', 'events'], 'Battle playback');
  if (battle['engine_version'] !== 1 || battle['playback_version'] !== 1) throw new BattlePlaybackContractError('Battle version is unsupported.');
  if (!Array.isArray(battle['participants']) || battle['participants'].length === 0) throw new BattlePlaybackContractError('Battle participants are invalid.');
  const rawParticipants = battle['participants'].map((candidate) => object(candidate, 'Battle participant'));
  const keys = new Set<string>(); const playerIds = new Set<string>(); const cells = new Set<string>();
  for (const participant of rawParticipants) {
    exact(participant, ['combatant_key', 'side', 'unit_id', 'unit_type_id', 'enemy_unit_type_id', 'display_name',
      'art_key', 'position', 'initial_hp', 'max_hp', 'terminal_hp', 'is_defeated', 'terminal_statuses'], 'Battle participant');
    const key = participant['combatant_key'];
    if (typeof key !== 'string' || !combatantKeyPattern.test(key) || keys.has(key)) throw new BattlePlaybackContractError('Participant key is invalid.');
    keys.add(key);
  }
  const participants = rawParticipants.map((participant): BattlePlaybackParticipant => {
    const side = participant['side']; if (side !== 'player' && side !== 'enemy') throw new BattlePlaybackContractError('Participant side is invalid.');
    const unitId = participant['unit_id'] === null ? null : id(participant['unit_id'], 'Participant unit id');
    if (unitId !== null && playerIds.has(unitId)) throw new BattlePlaybackContractError('Player unit id is duplicated.');
    if (unitId !== null) playerIds.add(unitId);
    const unitType = participant['unit_type_id']; const enemyType = participant['enemy_unit_type_id'];
    if ((side === 'player' && (unitId === null || typeof unitType !== 'string' || !playerTypePattern.test(unitType) || enemyType !== null))
      || (side === 'enemy' && (unitId !== null || unitType !== null || typeof enemyType !== 'string' || !enemyTypePattern.test(enemyType))))
      throw new BattlePlaybackContractError('Participant identity is invalid.');
    const position = object(participant['position'], 'Participant position'); exact(position, ['x', 'y'], 'Participant position');
    const x = integer(position['x'], 0, 'Participant x'); const y = integer(position['y'], 0, 'Participant y');
    if (x > 2 || y > 2) throw new BattlePlaybackContractError('Participant position is invalid.');
    const cell = `${side}:${x}:${y}`;
    if (cells.has(cell)) throw new BattlePlaybackContractError('Participant position is occupied.');
    cells.add(cell);
    const maxHp = integer(participant['max_hp'], 1, 'Participant max HP');
    const initialHp = integer(participant['initial_hp'], 0, 'Participant initial HP');
    const terminalHp = integer(participant['terminal_hp'], 0, 'Participant terminal HP');
    if (initialHp > maxHp || terminalHp > maxHp || typeof participant['is_defeated'] !== 'boolean'
      || participant['is_defeated'] !== (terminalHp === 0)) throw new BattlePlaybackContractError('Participant HP state is incoherent.');
    if (!Array.isArray(participant['terminal_statuses'])) throw new BattlePlaybackContractError('Terminal statuses are invalid.');
    return Object.freeze({ combatantKey: participant['combatant_key'] as string, side, unitId,
      unitTypeId: unitType as string | null, enemyUnitTypeId: enemyType as string | null,
      displayName: text(participant['display_name'], 'Participant display name'), artKey: text(participant['art_key'], 'Participant art key'),
      position: Object.freeze({ x, y }), initialHp, maxHp, terminalHp, isDefeated: participant['is_defeated'],
      terminalStatuses: Object.freeze(participant['terminal_statuses'].map((status) => parseStatus(status, keys))) });
  });
  if (!Array.isArray(battle['events']) || battle['events'].length < 2) throw new BattlePlaybackContractError('Playback events are invalid.');
  const events = battle['events'].map((event, index) => parseEvent(event, index, keys));
  const endingRound = integer(battle['ending_round'], 0, 'Battle ending round');
  const endingTick = integer(battle['ending_tick'], 0, 'Battle ending tick'); const result = outcome(battle['outcome']);
  const first = events[0]; const last = events[events.length - 1];
  if (first.type !== 'battle_started' || first.round !== 0 || first.tick !== 0
    || last.type !== 'battle_ended' || last.round !== endingRound || last.tick !== endingTick || last.facts['outcome'] !== result
    || events.filter((event) => event.type === 'battle_started').length !== 1
    || events.filter((event) => event.type === 'battle_ended').length !== 1
    || events.some((event, index) => index > 0 && event.tick < events[index - 1].tick))
    throw new BattlePlaybackContractError('Playback boundaries are incoherent.');
  const alive = { player: false, enemy: false };
  if (!participants.some((participant) => participant.side === 'player') || !participants.some((participant) => participant.side === 'enemy'))
    throw new BattlePlaybackContractError('Battle must contain both sides.');
  for (const participant of participants) if (!participant.isDefeated) alive[participant.side] = true;
  if ((result === 'victory' && (!alive.player || alive.enemy)) || (result === 'defeat' && (alive.player || !alive.enemy))
    || (result === 'stalemate' && (!alive.player || !alive.enemy))) throw new BattlePlaybackContractError('Battle outcome conflicts with terminal participants.');
  return Object.freeze({ battle: Object.freeze({ id: id(battle['id'], 'Battle id'), runId: id(battle['run_id'], 'Battle run id'),
    runNodeId: id(battle['run_node_id'], 'Battle node id'), engineVersion: 1, playbackVersion: 1, outcome: result,
    endingRound, endingTick, participants: Object.freeze(participants), events: Object.freeze(events) }),
    playerRevision: integer(data['player_revision'], 0, 'Battle player revision') });
}
