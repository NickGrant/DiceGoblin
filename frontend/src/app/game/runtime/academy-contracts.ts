import { ClientAcademyUpgradeDefinition, ClientContentRegistry } from './client-content-registry';

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
