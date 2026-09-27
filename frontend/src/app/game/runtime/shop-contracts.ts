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
export interface ShopPurchasePayload {
  readonly offer_id: string;
  readonly expected_price: { readonly currency_id: 'teeth'; readonly amount: number };
}
export type ShopPurchaseOutput = {
  readonly type: 'item'; readonly itemId: string; readonly quantityGranted: number; readonly ownedQuantityAfter: number;
} | {
  readonly type: 'die'; readonly die: { readonly id: string; readonly size: 4 | 6 | 8; readonly profileId: string; readonly lifecycleStatus: 'active' };
};
export interface ShopPurchaseResult {
  readonly offerId: string;
  readonly spend: { readonly currencyId: 'teeth'; readonly amount: number; readonly balanceBefore: number; readonly balanceAfter: number };
  readonly playerRevision: number;
  readonly output: ShopPurchaseOutput;
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

export function parseShopPurchaseEnvelope(value: unknown, content: ClientContentRegistry): ShopPurchaseResult {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data'])
    || !exact(value['data'], ['offer_id', 'spend', 'player_revision', 'output'])) {
    throw new ShopContractError('Shop purchase response must be a successful exact envelope.');
  }
  const data = value['data']; const offerId = data['offer_id']; const spend = data['spend']; const output = data['output'];
  if (typeof offerId !== 'string' || !record(spend) || !exact(spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
    || spend['currency_id'] !== 'teeth' || !Number.isSafeInteger(spend['amount']) || (spend['amount'] as number) <= 0
    || !safeNonNegative(spend['balance_before']) || !safeNonNegative(spend['balance_after'])
    || (spend['balance_before'] as number) - (spend['amount'] as number) !== spend['balance_after']
    || !safeNonNegative(data['player_revision']) || !record(output)) {
    throw new ShopContractError('Shop purchase spend or revision is malformed.');
  }
  const offer = content.getShopOffer(offerId);
  if (!offer) throw new ShopContractError('Shop purchase offer is unknown.');
  let parsedOutput: ShopPurchaseOutput;
  if (output['type'] === 'item') {
    if (!exact(output, ['type', 'item_id', 'quantity_granted', 'owned_quantity_after']) || offer.grant.type !== 'item'
      || output['item_id'] !== offer.grant.item_id || output['quantity_granted'] !== offer.grant.quantity
      || !Number.isSafeInteger(output['quantity_granted']) || (output['quantity_granted'] as number) <= 0
      || !safeNonNegative(output['owned_quantity_after'])
      || (output['owned_quantity_after'] as number) < (output['quantity_granted'] as number)) {
      throw new ShopContractError('Shop item output is incoherent.');
    }
    parsedOutput = Object.freeze({ type: 'item', itemId: output['item_id'] as string,
      quantityGranted: output['quantity_granted'] as number, ownedQuantityAfter: output['owned_quantity_after'] as number });
  } else if (output['type'] === 'die') {
    const die = output['die'];
    if (!exact(output, ['type', 'die']) || offer.grant.type !== 'die' || !record(die)
      || !exact(die, ['id', 'size', 'profile_id', 'lifecycle_status'])
      || typeof die['id'] !== 'string' || !/^[1-9][0-9]*$/.test(die['id'])
      || ![4, 6, 8].includes(die['size'] as number) || die['size'] !== offer.grant.size
      || die['profile_id'] !== offer.grant.dice_profile_id || die['lifecycle_status'] !== 'active') {
      throw new ShopContractError('Shop die output is incoherent.');
    }
    parsedOutput = Object.freeze({ type: 'die', die: Object.freeze({ id: die['id'], size: die['size'] as 4 | 6 | 8,
      profileId: die['profile_id'] as string, lifecycleStatus: 'active' }) });
  } else {
    throw new ShopContractError('Shop purchase output type is invalid.');
  }
  return Object.freeze({ offerId, spend: Object.freeze({ currencyId: 'teeth', amount: spend['amount'] as number,
    balanceBefore: spend['balance_before'] as number, balanceAfter: spend['balance_after'] as number }),
    playerRevision: data['player_revision'], output: parsedOutput });
}
