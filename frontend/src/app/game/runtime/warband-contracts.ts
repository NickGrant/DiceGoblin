import {
  ClientAbilityDefinition,
  ClientContentRegistry,
  ClientDiceAspectDefinition,
  ClientDiceMaterialDefinition,
  ClientDiceProfileDefinition,
  ClientKinDefinition,
  ClientUnitTypeDefinition,
} from './client-content-registry';

export interface WarbandUnitSummary {
  readonly id: string;
  readonly displayName: string;
  readonly unitType: ClientUnitTypeDefinition;
  readonly kin: ClientKinDefinition;
  readonly level: number;
  readonly xp: number;
  readonly lifecycleStatus: 'active';
}

export interface WarbandDieBinding {
  readonly unitId: string;
  readonly ability: ClientAbilityDefinition;
  readonly slotIndex: number;
}

export interface WarbandDieSummary {
  readonly id: string;
  readonly size: number;
  readonly profile: ClientDiceProfileDefinition;
  readonly material: ClientDiceMaterialDefinition;
  readonly aspects: readonly ClientDiceAspectDefinition[];
  readonly lifecycleStatus: 'active';
  readonly bindings: readonly WarbandDieBinding[];
}

export interface WarbandSquadSummary {
  readonly id: string;
  readonly name: string;
  readonly isActive: boolean;
  readonly formation: readonly (string | null)[];
}

export class WarbandContractError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'WarbandContractError';
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function hasExactKeys(record: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(record).sort().join('\0') === [...keys].sort().join('\0');
}

function collection(value: unknown, key: 'units' | 'dice' | 'squads'): readonly unknown[] {
  if (!isRecord(value) || !hasExactKeys(value, ['ok', 'data']) || value['ok'] !== true) {
    throw new WarbandContractError(`${key} response must be a successful API envelope.`);
  }
  const data = value['data'];
  if (!isRecord(data) || !hasExactKeys(data, [key]) || !Array.isArray(data[key])) {
    throw new WarbandContractError(`${key} response contains an invalid collection.`);
  }
  return data[key];
}

function positiveId(value: unknown): value is string {
  return typeof value === 'string' && /^[1-9][0-9]*$/.test(value);
}

function nonEmptyString(value: unknown, maximum = Number.POSITIVE_INFINITY): value is string {
  return typeof value === 'string' && value.trim() !== '' && Array.from(value.trim()).length <= maximum;
}

function nonNegativeInteger(value: unknown): value is number {
  return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}

export function parseUnitCollectionEnvelope(
  value: unknown,
  content: ClientContentRegistry,
): readonly WarbandUnitSummary[] {
  const ids = new Set<string>();
  return Object.freeze(collection(value, 'units').map((candidate): WarbandUnitSummary => {
    if (!isRecord(candidate) || !hasExactKeys(candidate, [
      'id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status',
    ])) throw new WarbandContractError('Unit summary is malformed.');
    const id = candidate['id'];
    const displayName = candidate['display_name'];
    const unitTypeId = candidate['unit_type_id'];
    const kinId = candidate['kin_id'];
    const level = candidate['level'];
    const xp = candidate['xp'];
    if (!positiveId(id) || ids.has(id) || !nonEmptyString(displayName, 128)
      || !nonEmptyString(unitTypeId) || !nonEmptyString(kinId)
      || !nonNegativeInteger(level) || level < 1 || !nonNegativeInteger(xp)
      || candidate['lifecycle_status'] !== 'active') {
      throw new WarbandContractError('Unit summary is malformed.');
    }
    const unitType = content.getUnitType(unitTypeId);
    const kin = content.getKin(kinId);
    if (!unitType || !kin) throw new WarbandContractError('Unit summary references unavailable authored content.');
    ids.add(id);
    return Object.freeze({ id, displayName: displayName.trim(), unitType, kin, level, xp, lifecycleStatus: 'active' });
  }));
}

export function parseDiceCollectionEnvelope(
  value: unknown,
  content: ClientContentRegistry,
): readonly WarbandDieSummary[] {
  const ids = new Set<string>();
  return Object.freeze(collection(value, 'dice').map((candidate): WarbandDieSummary => {
    if (!isRecord(candidate) || !hasExactKeys(candidate, [
      'id', 'size', 'profile_id', 'lifecycle_status', 'bindings',
    ])) throw new WarbandContractError('Die summary is malformed.');
    const id = candidate['id'];
    const size = candidate['size'];
    const profileId = candidate['profile_id'];
    if (!positiveId(id) || ids.has(id) || !nonNegativeInteger(size) || size < 1
      || !nonEmptyString(profileId) || candidate['lifecycle_status'] !== 'active'
      || !Array.isArray(candidate['bindings'])) {
      throw new WarbandContractError('Die summary is malformed.');
    }
    const profile = content.getDiceProfile(profileId);
    if (!profile || !profile.allowed_sizes.includes(size)) {
      throw new WarbandContractError('Die summary references incompatible authored content.');
    }
    const material = content.getDiceMaterial(profile.material_id);
    const aspects = profile.aspect_ids.map((aspectId) => content.getDiceAspect(aspectId));
    if (!material || !material.allowed_sizes.includes(size)
      || aspects.some((aspect) => !aspect || !aspect.allowed_sizes.includes(size))) {
      throw new WarbandContractError('Die summary references incompatible authored content.');
    }
    const bindings = candidate['bindings'].map((binding): WarbandDieBinding => {
      if (!isRecord(binding) || !hasExactKeys(binding, ['unit_id', 'ability_id', 'slot_index'])
        || !positiveId(binding['unit_id']) || !nonEmptyString(binding['ability_id'])
        || !nonNegativeInteger(binding['slot_index'])) {
        throw new WarbandContractError('Die binding summary is malformed.');
      }
      const ability = content.getAbility(binding['ability_id']);
      if (!ability || ability.kind !== 'active' || binding['slot_index'] >= ability.dice_slot_count) {
        throw new WarbandContractError('Die binding references incompatible authored content.');
      }
      return Object.freeze({ unitId: binding['unit_id'], ability, slotIndex: binding['slot_index'] });
    });
    if (bindings.length > 1) throw new WarbandContractError('One physical die has multiple bindings.');
    ids.add(id);
    return Object.freeze({ id, size, profile, material, aspects: Object.freeze(aspects as ClientDiceAspectDefinition[]), lifecycleStatus: 'active', bindings: Object.freeze(bindings) });
  }));
}

export function parseSquadCollectionEnvelope(value: unknown): readonly WarbandSquadSummary[] {
  const ids = new Set<string>();
  let activeCount = 0;
  const squads = collection(value, 'squads').map((candidate): WarbandSquadSummary => {
    if (!isRecord(candidate) || !hasExactKeys(candidate, ['id', 'name', 'is_active', 'formation'])
      || !positiveId(candidate['id']) || ids.has(candidate['id'])
      || !nonEmptyString(candidate['name'], 128) || typeof candidate['is_active'] !== 'boolean'
      || !Array.isArray(candidate['formation']) || candidate['formation'].length !== 9) {
      throw new WarbandContractError('Squad summary is malformed.');
    }
    const formationIds = new Set<string>();
    const formation = candidate['formation'].map((unitId) => {
      if (unitId === null) return null;
      if (!positiveId(unitId) || formationIds.has(unitId)) {
        throw new WarbandContractError('Squad formation is malformed.');
      }
      formationIds.add(unitId);
      return unitId;
    });
    if (candidate['is_active']) activeCount += 1;
    ids.add(candidate['id']);
    return Object.freeze({
      id: candidate['id'], name: candidate['name'].trim(), isActive: candidate['is_active'],
      formation: Object.freeze(formation),
    });
  });
  if (activeCount > 1) throw new WarbandContractError('Squad collection has multiple active squads.');
  return Object.freeze(squads);
}

export function requireActiveSquadAgreement(
  squads: readonly WarbandSquadSummary[],
  bootstrapActiveSquadId: string | null,
): void {
  const collectionActiveId = squads.find((squad) => squad.isActive)?.id ?? null;
  if (collectionActiveId !== bootstrapActiveSquadId) {
    throw new WarbandContractError('Squad collection disagrees with the current bootstrap revision.');
  }
}
