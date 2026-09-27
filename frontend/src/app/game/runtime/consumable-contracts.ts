import { ClientContentRegistry } from './client-content-registry';

export interface ConsumableUsePayload { readonly item_id: string; }
export interface ConsumableEnergyView {
  readonly current: number; readonly normalMax: number; readonly regenerationPerHour: number;
  readonly regenerationIntervalSeconds: number; readonly lastRegenerationAt: string;
  readonly nextRegenerationAt: string | null; readonly fullyRegeneratedAt: string | null;
}
export interface EnergyRestoreResult {
  readonly itemId: string; readonly quantityConsumed: 1; readonly ownedQuantityAfter: number;
  readonly energy: ConsumableEnergyView; readonly playerRevision: number;
}
export interface RunUnitHealResult {
  readonly itemId: string; readonly quantityConsumed: 1; readonly ownedQuantityAfter: number;
  readonly runId: string; readonly unit: { readonly unitId: string; readonly hpBefore: number;
    readonly hpAfter: number; readonly maxHp: number }; readonly playerRevision: number;
}

export class ConsumableContractError extends Error {
  constructor(message: string) { super(message); this.name = 'ConsumableContractError'; }
}

export function parseEnergyRestoreEnvelope(
  value: unknown, request: ConsumableUsePayload, content: ClientContentRegistry,
): EnergyRestoreResult {
  const data = envelope(value);
  if (!exact(data, ['item_id', 'quantity_consumed', 'owned_quantity_after', 'energy', 'player_revision'])
    || data['item_id'] !== request.item_id || data['quantity_consumed'] !== 1
    || !safeNonNegative(data['owned_quantity_after']) || !safeNonNegative(data['player_revision'])) {
    throw new ConsumableContractError('Energy restore result is malformed.');
  }
  const item = content.getItem(request.item_id);
  if (!item || item.category !== 'consumable' || !item.stackable || item.effect?.type !== 'energy_restore') {
    throw new ConsumableContractError('Energy restore item is unavailable in authored content.');
  }
  return Object.freeze({ itemId: request.item_id, quantityConsumed: 1,
    ownedQuantityAfter: data['owned_quantity_after'], energy: parseEnergy(data['energy']),
    playerRevision: data['player_revision'] });
}

export function parseRunUnitHealEnvelope(
  value: unknown, runId: string, unitId: string, request: ConsumableUsePayload,
  content: ClientContentRegistry,
): RunUnitHealResult {
  if (!positiveId(runId) || !positiveId(unitId)) throw new ConsumableContractError('Run heal request identity is malformed.');
  const data = envelope(value);
  const unit = data['unit'];
  if (!exact(data, ['item_id', 'quantity_consumed', 'owned_quantity_after', 'run_id', 'unit', 'player_revision'])
    || data['item_id'] !== request.item_id || data['quantity_consumed'] !== 1 || data['run_id'] !== runId
    || !safeNonNegative(data['owned_quantity_after']) || !safeNonNegative(data['player_revision'])
    || !record(unit) || !exact(unit, ['unit_id', 'hp_before', 'hp_after', 'max_hp'])
    || unit['unit_id'] !== unitId || !safeNonNegative(unit['hp_before'])
    || !safePositive(unit['hp_after']) || !safePositive(unit['max_hp'])
    || unit['hp_before'] >= unit['hp_after'] || unit['hp_after'] > unit['max_hp']) {
    throw new ConsumableContractError('Run-unit heal result is malformed.');
  }
  const item = content.getItem(request.item_id);
  if (!item || item.category !== 'consumable' || !item.stackable || item.effect?.type !== 'unit_heal') {
    throw new ConsumableContractError('Run-unit healing item is unavailable in authored content.');
  }
  return Object.freeze({ itemId: request.item_id, quantityConsumed: 1,
    ownedQuantityAfter: data['owned_quantity_after'], runId,
    unit: Object.freeze({ unitId, hpBefore: unit['hp_before'], hpAfter: unit['hp_after'], maxHp: unit['max_hp'] }),
    playerRevision: data['player_revision'] });
}

function parseEnergy(value: unknown): ConsumableEnergyView {
  if (!record(value) || !exact(value, ['current', 'normal_max', 'regeneration_per_hour',
      'regeneration_interval_seconds', 'last_regeneration_at', 'next_regeneration_at', 'fully_regenerated_at'])
    || !safeNonNegative(value['current']) || !safePositive(value['normal_max'])
    || !positiveNumber(value['regeneration_per_hour']) || !positiveNumber(value['regeneration_interval_seconds'])
    || !timestamp(value['last_regeneration_at'])) {
    throw new ConsumableContractError('Energy state is malformed.');
  }
  const expectedInterval = 3600 / value['regeneration_per_hour'];
  if (Math.abs(expectedInterval - value['regeneration_interval_seconds']) > 0.000001) {
    throw new ConsumableContractError('Energy regeneration timing is incoherent.');
  }
  const capped = value['current'] >= value['normal_max'];
  const next = value['next_regeneration_at']; const full = value['fully_regenerated_at'];
  if ((capped && (next !== null || full !== null))
    || (!capped && (!timestamp(next) || !timestamp(full)))
    || (!capped && (Date.parse(next as string) <= Date.parse(value['last_regeneration_at'])
      || Date.parse(full as string) < Date.parse(next as string)))) {
    throw new ConsumableContractError('Energy regeneration timing is incoherent.');
  }
  return Object.freeze({ current: value['current'], normalMax: value['normal_max'],
    regenerationPerHour: value['regeneration_per_hour'], regenerationIntervalSeconds: value['regeneration_interval_seconds'],
    lastRegenerationAt: value['last_regeneration_at'], nextRegenerationAt: next as string | null,
    fullyRegeneratedAt: full as string | null });
}

function envelope(value: unknown): Record<string, unknown> {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data'])) {
    throw new ConsumableContractError('Consumable response envelope is malformed.');
  }
  return value['data'];
}
function record(value: unknown): value is Record<string, unknown> { return typeof value === 'object' && value !== null && !Array.isArray(value); }
function exact(value: Record<string, unknown>, keys: readonly string[]): boolean
{ return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0'); }
function safeNonNegative(value: unknown): value is number
{ return typeof value === 'number' && Number.isSafeInteger(value) && value >= 0; }
function safePositive(value: unknown): value is number { return safeNonNegative(value) && value > 0; }
function positiveNumber(value: unknown): value is number { return typeof value === 'number' && Number.isFinite(value) && value > 0; }
function positiveId(value: unknown): value is string { return typeof value === 'string' && /^[1-9][0-9]*$/.test(value); }
function timestamp(value: unknown): value is string
{
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value)) return false;
  const parsed = Date.parse(value);
  return Number.isFinite(parsed) && new Date(parsed).toISOString() === value.replace('Z', '.000Z');
}
