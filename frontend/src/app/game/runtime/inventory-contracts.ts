import { ClientContentRegistry, ClientItemDefinition } from './client-content-registry';

export interface OwnedItemStack {
  readonly item: ClientItemDefinition;
  readonly quantity: number;
}

export class InventoryContractError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'InventoryContractError';
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function exactKeys(value: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
}

export function parseItemCollectionEnvelope(
  value: unknown,
  content: ClientContentRegistry,
): readonly OwnedItemStack[] {
  if (!isRecord(value) || !exactKeys(value, ['ok', 'data']) || value['ok'] !== true
    || !isRecord(value['data']) || !exactKeys(value['data'], ['items'])
    || !Array.isArray(value['data']['items'])) {
    throw new InventoryContractError('Inventory response must be a successful item collection envelope.');
  }
  let prior = '';
  const seen = new Set<string>();
  return Object.freeze(value['data']['items'].map((candidate): OwnedItemStack => {
    if (!isRecord(candidate) || !exactKeys(candidate, ['item_id', 'quantity'])
      || typeof candidate['item_id'] !== 'string'
      || !/^item\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(candidate['item_id'])
      || !Number.isSafeInteger(candidate['quantity']) || (candidate['quantity'] as number) <= 0
      || seen.has(candidate['item_id']) || candidate['item_id'].localeCompare(prior) <= 0) {
      throw new InventoryContractError('Inventory contains an invalid or non-deterministic item stack.');
    }
    const item = content.getItem(candidate['item_id']);
    if (!item || !item.stackable)
      throw new InventoryContractError('Inventory references unavailable or non-stackable authored content.');
    seen.add(candidate['item_id']);
    prior = candidate['item_id'];
    return Object.freeze({ item, quantity: candidate['quantity'] as number });
  }));
}
