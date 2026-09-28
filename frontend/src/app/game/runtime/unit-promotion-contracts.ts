import { ClientContentRegistry, ClientUnitPromotionDefinition, ClientUnitTypeDefinition, ClientAbilityDefinition } from './client-content-registry';

export interface UnitPromotionOption {
  readonly promotion: ClientUnitPromotionDefinition;
  readonly targetUnitType: ClientUnitTypeDefinition;
  readonly requiredLevel: number;
  readonly price: number;
  readonly levelMet: boolean;
  readonly canAfford: boolean;
  readonly available: boolean;
  readonly newAbilities: readonly ClientAbilityDefinition[];
}

export interface UnitPromotionOptionsResult {
  readonly unitId: string;
  readonly unitType: ClientUnitTypeDefinition;
  readonly level: number;
  readonly xp: number;
  readonly xpToNextLevel: number;
  readonly rawChaos: number;
  readonly playerRevision: number;
  readonly configurationLocked: boolean;
  readonly options: readonly UnitPromotionOption[];
}

export class UnitPromotionContractError extends Error {
  constructor(message: string) { super(message); this.name = 'UnitPromotionContractError'; }
}

const record = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);
const exact = (value: Record<string, unknown>, fields: readonly string[]): boolean =>
  Object.keys(value).sort().join('\0') === [...fields].sort().join('\0');
const integer = (value: unknown, minimum = 0): value is number =>
  typeof value === 'number' && Number.isSafeInteger(value) && value >= minimum;
export const canonicalUnitId = (value: unknown): value is string =>
  typeof value === 'string' && /^[1-9][0-9]*$/.test(value) && Number.isSafeInteger(Number(value));

export function parseUnitPromotionOptionsEnvelope(
  envelope: unknown, requestedUnitId: string, content: ClientContentRegistry,
): UnitPromotionOptionsResult {
  if (!canonicalUnitId(requestedUnitId) || !record(envelope) || !exact(envelope, ['ok', 'data'])
    || envelope['ok'] !== true || !record(envelope['data'])) throw new UnitPromotionContractError('Promotion options response is malformed.');
  const data = (envelope as Record<string, unknown>)['data'] as Record<string, unknown>;
  if (!exact(data, ['unit_id', 'unit_type_id', 'level', 'xp', 'xp_to_next_level', 'raw_chaos',
    'player_revision', 'configuration_locked', 'options']) || data['unit_id'] !== requestedUnitId
    || typeof data['unit_type_id'] !== 'string' || !integer(data['level'], 1)
    || !integer(data['xp']) || !integer(data['xp_to_next_level'], 1)
    || data['xp'] >= data['xp_to_next_level'] || !integer(data['raw_chaos'])
    || !integer(data['player_revision']) || typeof data['configuration_locked'] !== 'boolean'
    || !Array.isArray(data['options'])) throw new UnitPromotionContractError('Promotion options response is malformed.');
  const unitType = content.getUnitType(data['unit_type_id'] as string);
  if (!unitType) throw new UnitPromotionContractError('Promotion options response is malformed.');
  const options: UnitPromotionOption[] = [];
  let previousId = '';
  for (const candidate of data['options'] as unknown[]) {
    if (!record(candidate) || !exact(candidate, ['promotion_id', 'target_unit_type_id',
      'required_level', 'price', 'level_met', 'can_afford', 'available', 'new_ability_ids'])
      || typeof candidate['promotion_id'] !== 'string' || candidate['promotion_id'] <= previousId
      || typeof candidate['target_unit_type_id'] !== 'string'
      || !integer(candidate['required_level'], 1) || !record(candidate['price'])
      || !exact(candidate['price'], ['currency_id', 'amount'])
      || candidate['price']['currency_id'] !== 'raw_chaos'
      || !integer(candidate['price']['amount'], 1)
      || typeof candidate['level_met'] !== 'boolean' || typeof candidate['can_afford'] !== 'boolean'
      || typeof candidate['available'] !== 'boolean' || !Array.isArray(candidate['new_ability_ids'])) throw new UnitPromotionContractError('Promotion options response is malformed.');
    const promotion = content.getUnitPromotion(candidate['promotion_id'] as string);
    const target = content.getUnitType(candidate['target_unit_type_id'] as string);
    if (!promotion || !target || promotion.from_unit_type_id !== unitType!.id
      || promotion.to_unit_type_id !== target.id
      || candidate['level_met'] !== ((data['level'] as number) >= (candidate['required_level'] as number))
      || candidate['can_afford'] !== ((data['raw_chaos'] as number) >= ((candidate['price'] as Record<string, unknown>)['amount'] as number))
      || candidate['available'] !== (candidate['level_met'] && !data['configuration_locked'])) throw new UnitPromotionContractError('Promotion options response is malformed.');
    const newAbilities: ClientAbilityDefinition[] = [];
    let lastPosition = -1;
    for (const id of candidate['new_ability_ids']) {
      if (typeof id !== 'string') throw new UnitPromotionContractError('Promotion options response is malformed.');
      const position = target!.ability_ids.indexOf(id);
      const ability = content.getAbility(id);
      if (!ability || position <= lastPosition) throw new UnitPromotionContractError('Promotion options response is malformed.');
      lastPosition = position;
      newAbilities.push(ability!);
    }
    previousId = promotion!.id;
    options.push(Object.freeze({ promotion: promotion!, targetUnitType: target!,
      requiredLevel: candidate['required_level'] as number, price: (candidate['price'] as Record<string, unknown>)['amount'] as number,
      levelMet: candidate['level_met'] as boolean, canAfford: candidate['can_afford'] as boolean,
      available: candidate['available'] as boolean, newAbilities: Object.freeze(newAbilities) }));
  }
  const outgoing = content.listOutgoingUnitPromotions(unitType!.id);
  if (outgoing.length !== options.length || outgoing.some((edge, index) => edge.id !== options[index].promotion.id)) throw new UnitPromotionContractError('Promotion options response is malformed.');
  return Object.freeze({ unitId: requestedUnitId, unitType: unitType!, level: data['level'] as number, xp: data['xp'] as number,
    xpToNextLevel: data['xp_to_next_level'] as number, rawChaos: data['raw_chaos'] as number,
    playerRevision: data['player_revision'] as number, configurationLocked: data['configuration_locked'] as boolean,
    options: Object.freeze(options) });
}
