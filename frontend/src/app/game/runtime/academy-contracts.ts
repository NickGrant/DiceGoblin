import { ClientAcademyUpgradeDefinition, ClientContentRegistry } from './client-content-registry';
import { ConsumableContractError, ConsumableEnergyView, parseEnergy } from './consumable-contracts';

export interface AcademyUpgradePayload {
  readonly upgrade_id: string;
  readonly expected_price: { readonly currency_id: 'raw_chaos'; readonly amount: number };
}
export interface AcademyUpgradeResult {
  readonly upgradeId: string;
  readonly spend: { readonly currencyId: 'raw_chaos'; readonly amount: number;
    readonly balanceBefore: number; readonly balanceAfter: number };
  readonly grant: { readonly unlockId: string };
  readonly energy: ConsumableEnergyView | null;
  readonly playerRevision: number;
}
export function canonicalAcademyUpgradePayload(value: unknown): AcademyUpgradePayload {
  if (!record(value) || !exact(value, ['upgrade_id', 'expected_price'])
    || typeof value['upgrade_id'] !== 'string'
    || !/^academy_upgrade\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(value['upgrade_id'])
    || !record(value['expected_price']) || !exact(value['expected_price'], ['currency_id', 'amount'])
    || value['expected_price']['currency_id'] !== 'raw_chaos'
    || !safeNonNegative(value['expected_price']['amount']) || value['expected_price']['amount'] === 0)
    throw new AcademyContractError('Academy upgrade request is malformed.');
  return { upgrade_id: value['upgrade_id'], expected_price: {
    currency_id: 'raw_chaos', amount: value['expected_price']['amount'],
  } };
}

export interface AcademyUpgradeReadModel {
  readonly upgrade: ClientAcademyUpgradeDefinition;
  readonly price: { readonly currencyId: 'raw_chaos'; readonly amount: number };
  readonly owned: boolean;
  readonly available: boolean;
}
export interface AcademyCatalogResult {
  readonly rawChaos: number;
  readonly playerRevision: number;
  readonly upgrades: readonly AcademyUpgradeReadModel[];
}
export class AcademyContractError extends Error {
  constructor(message: string) { super(message); this.name = 'AcademyContractError'; }
}
function record(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function exact(value: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
}
function safeNonNegative(value: unknown): value is number {
  return Number.isSafeInteger(value) && (value as number) >= 0;
}

export function parseAcademyCatalogEnvelope(value: unknown, content: ClientContentRegistry): AcademyCatalogResult {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data'])
    || !exact(value['data'], ['raw_chaos', 'player_revision', 'upgrades']))
    throw new AcademyContractError('Academy response must be a successful exact envelope.');
  const data = value['data'];
  if (!safeNonNegative(data['raw_chaos']) || !safeNonNegative(data['player_revision']) || !Array.isArray(data['upgrades']))
    throw new AcademyContractError('Academy wallet or revision is malformed.');
  let prior = '';
  const upgrades = data['upgrades'].map((candidate): AcademyUpgradeReadModel => {
    if (!record(candidate) || !exact(candidate, ['upgrade_id', 'price', 'owned', 'available'])
      || typeof candidate['upgrade_id'] !== 'string' || !/^academy_upgrade\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(candidate['upgrade_id'])
      || candidate['upgrade_id'] <= prior || !record(candidate['price'])
      || !exact(candidate['price'], ['currency_id', 'amount']) || candidate['price']['currency_id'] !== 'raw_chaos'
      || !Number.isSafeInteger(candidate['price']['amount']) || (candidate['price']['amount'] as number) <= 0
      || typeof candidate['owned'] !== 'boolean' || typeof candidate['available'] !== 'boolean'
      || (candidate['owned'] && candidate['available']))
      throw new AcademyContractError('Academy contains a malformed or non-deterministic upgrade.');
    const upgrade = content.getAcademyUpgrade(candidate['upgrade_id']);
    if (!upgrade) throw new AcademyContractError('Academy upgrade identity is unknown.');
    prior = candidate['upgrade_id'];
    return Object.freeze({ upgrade, price: Object.freeze({ currencyId: 'raw_chaos' as const,
      amount: candidate['price']['amount'] as number }), owned: candidate['owned'], available: candidate['available'] });
  });
  if (upgrades.length !== content.listAcademyUpgrades().length)
    throw new AcademyContractError('Academy response does not match projected authored content.');
  return Object.freeze({ rawChaos: data['raw_chaos'], playerRevision: data['player_revision'], upgrades: Object.freeze(upgrades) });
}

export function parseAcademyUpgradeEnvelope(value: unknown, request: AcademyUpgradePayload): AcademyUpgradeResult {
  request = canonicalAcademyUpgradePayload(request);
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data']))
    throw new AcademyContractError('Academy upgrade envelope is malformed.');
  const data = value['data'];
  const spend = data['spend']; const grant = data['grant'];
  if (!/^academy_upgrade\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(request.upgrade_id)
    || request.expected_price.currency_id !== 'raw_chaos'
    || !safeNonNegative(request.expected_price.amount) || request.expected_price.amount === 0
    || !exact(data, ['upgrade_id', 'spend', 'grant', 'energy', 'player_revision'])
    || data['upgrade_id'] !== request.upgrade_id
    || !record(spend) || !exact(spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
    || spend['currency_id'] !== 'raw_chaos' || spend['amount'] !== request.expected_price.amount
    || !safeNonNegative(spend['balance_before']) || !safeNonNegative(spend['balance_after'])
    || spend['balance_before'] - request.expected_price.amount !== spend['balance_after']
    || !record(grant) || !exact(grant, ['unlock_id'])
    || typeof grant['unlock_id'] !== 'string'
    || !/^unlock\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(grant['unlock_id'])
    || !safeNonNegative(data['player_revision']))
    throw new AcademyContractError('Academy upgrade result is malformed.');
  let energy: ConsumableEnergyView | null = null;
  if (data['energy'] !== null) {
    try { energy = parseEnergy(data['energy']); }
    catch (error) {
      if (error instanceof ConsumableContractError) throw new AcademyContractError('Academy Energy result is malformed.');
      throw error;
    }
  }
  return Object.freeze({ upgradeId: request.upgrade_id,
    spend: Object.freeze({ currencyId: 'raw_chaos' as const, amount: request.expected_price.amount,
      balanceBefore: spend['balance_before'], balanceAfter: spend['balance_after'] }),
    grant: Object.freeze({ unlockId: grant['unlock_id'] }), energy, playerRevision: data['player_revision'] });
}
