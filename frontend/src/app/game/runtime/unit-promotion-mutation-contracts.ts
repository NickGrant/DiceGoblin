import { ClientContentRegistry, ClientUnitPromotionDefinition, ClientAbilityDefinition } from './client-content-registry';
import { UnitDetail, UnitDetailContractError, parseUnitDetailEnvelope } from './unit-detail-contracts';
import { WarbandDieSummary } from './warband-contracts';
import { canonicalUnitId } from './unit-promotion-contracts';

export interface UnitPromotionPayload {
  readonly promotion_id: string;
  readonly expected_price: { readonly currency_id: 'raw_chaos'; readonly amount: number };
}

export interface UnitPromotionResult {
  readonly promotion: ClientUnitPromotionDefinition;
  readonly grantedAbilities: readonly ClientAbilityDefinition[];
  readonly spend: { readonly currencyId: 'raw_chaos'; readonly amount: number;
    readonly balanceBefore: number; readonly balanceAfter: number };
  readonly unit: UnitDetail;
  readonly playerRevision: number;
}

export class UnitPromotionMutationContractError extends Error {
  constructor(message: string) { super(message); this.name = 'UnitPromotionMutationContractError'; }
}

const record = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);
const exact = (value: Record<string, unknown>, keys: readonly string[]): boolean =>
  Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
const safe = (value: unknown, minimum = 0): value is number =>
  typeof value === 'number' && Number.isSafeInteger(value) && value >= minimum;
const promotionId = (value: unknown): value is string =>
  typeof value === 'string' && /^unit_promotion\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(value);

export function canonicalUnitPromotionPayload(value: unknown): UnitPromotionPayload {
  if (!record(value) || !exact(value, ['promotion_id', 'expected_price'])
    || !promotionId(value['promotion_id']) || !record(value['expected_price'])
    || !exact(value['expected_price'], ['currency_id', 'amount'])
    || value['expected_price']['currency_id'] !== 'raw_chaos'
    || !safe(value['expected_price']['amount'], 1)) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
  return Object.freeze({ promotion_id: value['promotion_id'] as string,
    expected_price: Object.freeze({ currency_id: 'raw_chaos' as const,
      amount: (value['expected_price'] as Record<string, unknown>)['amount'] as number }) });
}

export function parseUnitPromotionMutationEnvelope(
  value: unknown, requestedUnitId: string, request: UnitPromotionPayload,
  content: ClientContentRegistry, dice: readonly WarbandDieSummary[],
): UnitPromotionResult {
  request = canonicalUnitPromotionPayload(request);
  if (!canonicalUnitId(requestedUnitId) || !record(value) || !exact(value, ['ok', 'data'])
    || value['ok'] !== true || !record(value['data'])) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
  const data = (value as Record<string, unknown>)['data'] as Record<string, unknown>;
  const promotion = data['promotion']; const spend = data['spend'];
  if (!exact(data, ['promotion', 'spend', 'unit', 'player_revision'])
    || !record(promotion) || !exact(promotion, ['promotion_id', 'from_unit_type_id', 'to_unit_type_id', 'granted_ability_ids'])
    || promotion['promotion_id'] !== request.promotion_id
    || typeof promotion['from_unit_type_id'] !== 'string' || typeof promotion['to_unit_type_id'] !== 'string'
    || !Array.isArray(promotion['granted_ability_ids'])
    || !record(spend) || !exact(spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
    || spend['currency_id'] !== 'raw_chaos' || spend['amount'] !== request.expected_price.amount
    || !safe(spend['balance_before']) || !safe(spend['balance_after'])
    || spend['balance_before'] - spend['amount'] !== spend['balance_after']
    || !safe(data['player_revision'], 1)) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
  const projected = content.getUnitPromotion(promotion['promotion_id'] as string);
  if (!projected || projected.from_unit_type_id !== promotion['from_unit_type_id']
    || projected.to_unit_type_id !== promotion['to_unit_type_id']) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
  const target = content.getUnitType(projected.to_unit_type_id);
  if (!target) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
  const grantedAbilities: ClientAbilityDefinition[] = [];
  let previous = -1;
  for (const id of promotion['granted_ability_ids'] as unknown[]) {
    if (typeof id !== 'string') throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
    const position = target!.ability_ids.indexOf(id);
    const ability = content.getAbility(id);
    if (!ability || position <= previous) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
    previous = position;
    grantedAbilities.push(ability!);
  }
  let unit: UnitDetail;
  try { unit = parseUnitDetailEnvelope({ ok: true, data: { unit: data['unit'] } }, content, dice); }
  catch (error) {
    if (error instanceof UnitDetailContractError) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
    throw error;
  }
  const last = unit.promotionHistory.at(-1);
  if (unit.id !== requestedUnitId || unit.unitType.id !== projected.to_unit_type_id
    || !last || last.fromUnitType.id !== projected.from_unit_type_id
    || last.toUnitType.id !== projected.to_unit_type_id
    || grantedAbilities.some((ability) => !unit.ownedAbilities.some((owned) => owned.id === ability.id))) throw new UnitPromotionMutationContractError('Unit promotion mutation is malformed.');
  return Object.freeze({ promotion: projected!, grantedAbilities: Object.freeze(grantedAbilities),
    spend: Object.freeze({ currencyId: 'raw_chaos' as const, amount: request.expected_price.amount,
      balanceBefore: (spend as Record<string, unknown>)['balance_before'] as number,
      balanceAfter: (spend as Record<string, unknown>)['balance_after'] as number }),
    unit, playerRevision: data['player_revision'] as number });
}
