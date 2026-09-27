export interface DiceSellResult {
  readonly diceId: string;
  readonly lifecycleStatus: 'sold';
  readonly teethAwarded: number;
  readonly teeth: number;
  readonly playerRevision: number;
}

export interface DiceSalvageResult {
  readonly diceId: string;
  readonly lifecycleStatus: 'salvaged';
  readonly rawChaosAwarded: number;
  readonly rawChaos: number;
  readonly playerRevision: number;
}

export class DiceLifecycleContractError extends Error {
  constructor(message: string) { super(message); this.name = 'DiceLifecycleContractError'; }
}

export function parseDiceSellEnvelope(value: unknown, diceId: string): DiceSellResult {
  const data = lifecycleData(value, diceId, 'sold', 'teeth_awarded', 'teeth');
  return Object.freeze({ diceId, lifecycleStatus: 'sold', teethAwarded: data.award,
    teeth: data.balance, playerRevision: data.playerRevision });
}

export function parseDiceSalvageEnvelope(value: unknown, diceId: string): DiceSalvageResult {
  const data = lifecycleData(value, diceId, 'salvaged', 'raw_chaos_awarded', 'raw_chaos');
  return Object.freeze({ diceId, lifecycleStatus: 'salvaged', rawChaosAwarded: data.award,
    rawChaos: data.balance, playerRevision: data.playerRevision });
}

function lifecycleData(
  value: unknown,
  diceId: string,
  status: 'sold' | 'salvaged',
  awardField: 'teeth_awarded' | 'raw_chaos_awarded',
  balanceField: 'teeth' | 'raw_chaos',
): { award: number; balance: number; playerRevision: number } {
  if (!positiveId(diceId) || !record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true
    || !record(value['data'])) throw new DiceLifecycleContractError('Die lifecycle response envelope is malformed.');
  const data = value['data'];
  if (!exact(data, ['dice_id', 'lifecycle_status', awardField, balanceField, 'player_revision'])
    || data['dice_id'] !== diceId || data['lifecycle_status'] !== status || !safePositive(data[awardField])
    || !safeNonNegative(data[balanceField]) || (data[balanceField] as number) < (data[awardField] as number)
    || !safeNonNegative(data['player_revision'])) {
    throw new DiceLifecycleContractError('Die lifecycle result is malformed.');
  }
  return { award: data[awardField] as number, balance: data[balanceField] as number,
    playerRevision: data['player_revision'] as number };
}

function record(value: unknown): value is Record<string, unknown>
{ return typeof value === 'object' && value !== null && !Array.isArray(value); }
function exact(value: Record<string, unknown>, keys: readonly string[]): boolean
{ return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0'); }
function safeNonNegative(value: unknown): value is number
{ return typeof value === 'number' && Number.isSafeInteger(value) && value >= 0; }
function safePositive(value: unknown): value is number { return safeNonNegative(value) && value > 0; }
function positiveId(value: unknown): value is string { return typeof value === 'string' && /^[1-9][0-9]*$/.test(value); }
