import { ClientContentRegistry, ClientShopOfferDefinition } from './client-content-registry';

export interface ShopOfferReadModel {
  readonly offer: ClientShopOfferDefinition;
  readonly price: { readonly currencyId: 'teeth'; readonly amount: number };
  readonly available: boolean;
  readonly canAfford: boolean;
}
export interface ShopCatalogResult {
  readonly teeth: number;
  readonly playerRevision: number;
  readonly offers: readonly ShopOfferReadModel[];
}
export class ShopContractError extends Error {
  constructor(message: string) { super(message); this.name = 'ShopContractError'; }
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

export function parseShopCatalogEnvelope(value: unknown, content: ClientContentRegistry): ShopCatalogResult {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data'])
    || !exact(value['data'], ['teeth', 'player_revision', 'offers'])) {
    throw new ShopContractError('Shop response must be a successful exact envelope.');
  }
  const data = value['data'];
  if (!safeNonNegative(data['teeth']) || !safeNonNegative(data['player_revision']) || !Array.isArray(data['offers']))
    throw new ShopContractError('Shop wallet or revision is malformed.');
  const teeth = data['teeth']; let prior = ''; const seen = new Set<string>();
  const offers = data['offers'].map((candidate): ShopOfferReadModel => {
    if (!record(candidate) || !exact(candidate, ['offer_id', 'price', 'available', 'can_afford'])
      || typeof candidate['offer_id'] !== 'string' || !/^shop_offer\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(candidate['offer_id'])
      || candidate['offer_id'] <= prior || seen.has(candidate['offer_id']) || !record(candidate['price'])
      || !exact(candidate['price'], ['currency_id', 'amount']) || candidate['price']['currency_id'] !== 'teeth'
      || !Number.isSafeInteger(candidate['price']['amount']) || (candidate['price']['amount'] as number) <= 0
      || typeof candidate['available'] !== 'boolean' || typeof candidate['can_afford'] !== 'boolean') {
      throw new ShopContractError('Shop contains a malformed or non-deterministic offer.');
    }
    const offer = content.getShopOffer(candidate['offer_id']);
    if (!offer || candidate['can_afford'] !== (teeth >= (candidate['price']['amount'] as number)))
      throw new ShopContractError('Shop offer identity or affordability is incoherent.');
    seen.add(candidate['offer_id']); prior = candidate['offer_id'];
    return Object.freeze({ offer, price: Object.freeze({ currencyId: 'teeth' as const,
      amount: candidate['price']['amount'] as number }), available: candidate['available'], canAfford: candidate['can_afford'] });
  });
  if (offers.length !== content.listShopOffers().length)
    throw new ShopContractError('Shop response does not match the projected authored catalog.');
  return Object.freeze({ teeth, playerRevision: data['player_revision'], offers: Object.freeze(offers) });
}
