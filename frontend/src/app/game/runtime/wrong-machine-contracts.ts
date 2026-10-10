import { ClientContentRegistry, ClientItemDefinition, ClientKinDefinition, ClientUnitTypeDefinition } from './client-content-registry';

const recipeId = /^reconstruction_recipe\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const itemId = /^item\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const unitTypeId = /^unit_type\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const unlockId = /^unlock\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const kinId = /^kin\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const record = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);
const exact = (value: Record<string, unknown>, keys: readonly string[]): boolean =>
  Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
const safe = (value: unknown): value is number => Number.isSafeInteger(value) && (value as number) >= 0;
const positive = (value: unknown): value is number => safe(value) && value > 0;

export class WrongMachineContractError extends Error {
  constructor(message: string) { super(message); this.name = 'WrongMachineContractError'; }
}

export interface WrongMachineIngredient {
  readonly item: ClientItemDefinition;
  readonly quantity: number;
  readonly owned: number;
}
export interface WrongMachineRecipe {
  readonly recipeId: string;
  readonly displayName: string;
  readonly description: string;
  readonly kin: ClientKinDefinition;
  readonly kinRestored: boolean;
  readonly mode: 'first_restoration' | 'repeat_reconstruction';
  readonly unitTypeSelection: 'random_unlocked' | 'chosen_unlocked';
  readonly eligibleUnitTypes: readonly ClientUnitTypeDefinition[];
  readonly prerequisites: readonly { readonly unlockId: string; readonly owned: boolean }[];
  readonly prerequisitesMet: boolean;
  readonly price: { readonly currencyId: 'raw_chaos'; readonly amount: number };
  readonly ingredients: readonly WrongMachineIngredient[];
  readonly reconstructable: boolean;
}
export interface WrongMachineCatalog {
  readonly rawChaos: number;
  readonly playerRevision: number;
  readonly recipes: readonly WrongMachineRecipe[];
}
export interface ReconstructionPayload {
  readonly recipe_id: string;
  readonly expected_mode: 'first_restoration' | 'repeat_reconstruction';
  readonly expected_price: { readonly currency_id: 'raw_chaos'; readonly amount: number };
  readonly expected_ingredients: readonly { readonly item_id: string; readonly quantity: number }[];
  readonly unit_type_id?: string;
}
export interface ReconstructionResult {
  readonly recipeId: string;
  readonly mode: 'first_restoration' | 'repeat_reconstruction';
  readonly unit: { readonly id: string; readonly displayName: string; readonly unitTypeId: string; readonly kinId: string;
    readonly level: 1; readonly xp: 0; readonly lifecycleStatus: 'active' };
  readonly spend: { readonly currencyId: 'raw_chaos'; readonly amount: number;
    readonly balanceBefore: number; readonly balanceAfter: number };
  readonly consumedItems: readonly { readonly itemId: string; readonly quantity: number; readonly ownedAfter: number }[];
  readonly kinRestoration: { readonly kinId: string; readonly unlockId: string; readonly outcome: 'granted' | 'already_owned' };
  readonly playerRevision: number;
}

export function reconstructionPayload(recipe: WrongMachineRecipe, selectedUnitTypeId: string | null): ReconstructionPayload {
  if (recipe.mode === 'repeat_reconstruction' && (!selectedUnitTypeId
    || !recipe.eligibleUnitTypes.some((type) => type.id === selectedUnitTypeId)))
    throw new WrongMachineContractError('An eligible unit type must be selected.');
  const request: ReconstructionPayload = {
    recipe_id: recipe.recipeId, expected_mode: recipe.mode,
    expected_price: { currency_id: 'raw_chaos', amount: recipe.price.amount },
    expected_ingredients: recipe.ingredients.map((ingredient) => ({ item_id: ingredient.item.id, quantity: ingredient.quantity }))
      .sort((a, b) => a.item_id.localeCompare(b.item_id)),
    ...(recipe.mode === 'repeat_reconstruction' ? { unit_type_id: selectedUnitTypeId! } : {}),
  };
  return canonicalReconstructionPayload(request);
}

export function canonicalReconstructionPayload(value: unknown): ReconstructionPayload {
  if (!record(value) || !['first_restoration', 'repeat_reconstruction'].includes(value['expected_mode'] as string)
    || !exact(value, value['expected_mode'] === 'repeat_reconstruction'
      ? ['recipe_id', 'expected_mode', 'expected_price', 'expected_ingredients', 'unit_type_id']
      : ['recipe_id', 'expected_mode', 'expected_price', 'expected_ingredients'])
    || typeof value['recipe_id'] !== 'string' || !recipeId.test(value['recipe_id'])
    || !record(value['expected_price']) || !exact(value['expected_price'], ['currency_id', 'amount'])
    || value['expected_price']['currency_id'] !== 'raw_chaos' || !positive(value['expected_price']['amount'])
    || !Array.isArray(value['expected_ingredients']) || value['expected_ingredients'].length === 0
    || (value['expected_mode'] === 'repeat_reconstruction'
      && (typeof value['unit_type_id'] !== 'string' || !unitTypeId.test(value['unit_type_id']))))
    throw new WrongMachineContractError('Reconstruction request is malformed.');
  const ingredients = value['expected_ingredients']; let prior = '';
  for (const ingredient of ingredients) {
    if (!record(ingredient) || !exact(ingredient, ['item_id', 'quantity'])
      || typeof ingredient['item_id'] !== 'string' || !itemId.test(ingredient['item_id'])
      || ingredient['item_id'] <= prior || !positive(ingredient['quantity']))
      throw new WrongMachineContractError('Reconstruction ingredients are malformed.');
    prior = ingredient['item_id'];
  }
  return value as unknown as ReconstructionPayload;
}

export function parseWrongMachineCatalogEnvelope(value: unknown, content: ClientContentRegistry): WrongMachineCatalog {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data'])
    || !exact(value['data'], ['raw_chaos', 'player_revision', 'recipes'])
    || !safe(value['data']['raw_chaos']) || !safe(value['data']['player_revision'])
    || !Array.isArray(value['data']['recipes'])) throw new WrongMachineContractError('Wrong Machine read is malformed.');
  const data = value['data']; let prior = '';
  const recipes = (data['recipes'] as unknown[]).map((candidate): WrongMachineRecipe => {
    if (!record(candidate) || !exact(candidate, ['recipe_id', 'display_name', 'description', 'kin_id', 'kin_restored',
      'mode', 'unit_type_selection', 'eligible_unit_type_ids', 'prerequisites', 'prerequisites_met', 'price', 'ingredients', 'reconstructable'])
      || typeof candidate['recipe_id'] !== 'string' || !recipeId.test(candidate['recipe_id']) || candidate['recipe_id'] <= prior
      || typeof candidate['display_name'] !== 'string' || candidate['display_name'].trim() === ''
      || typeof candidate['description'] !== 'string' || candidate['description'].trim() === ''
      || typeof candidate['kin_id'] !== 'string' || !kinId.test(candidate['kin_id'])
      || typeof candidate['kin_restored'] !== 'boolean'
      || candidate['mode'] !== (candidate['kin_restored'] ? 'repeat_reconstruction' : 'first_restoration')
      || candidate['unit_type_selection'] !== (candidate['kin_restored'] ? 'chosen_unlocked' : 'random_unlocked')
      || !Array.isArray(candidate['eligible_unit_type_ids']) || !Array.isArray(candidate['prerequisites'])
      || !Array.isArray(candidate['ingredients']) || candidate['ingredients'].length === 0
      || typeof candidate['prerequisites_met'] !== 'boolean' || typeof candidate['reconstructable'] !== 'boolean'
      || !record(candidate['price']) || !exact(candidate['price'], ['currency_id', 'amount'])
      || candidate['price']['currency_id'] !== 'raw_chaos' || !positive(candidate['price']['amount']))
      throw new WrongMachineContractError('Wrong Machine recipe is malformed.');
    const kin = content.getKin(candidate['kin_id']);
    if (!kin) throw new WrongMachineContractError('Wrong Machine Kin is absent from client content.');
    let priorType = '';
    const eligibleUnitTypes = candidate['eligible_unit_type_ids'].map((id: unknown): ClientUnitTypeDefinition => {
      if (typeof id !== 'string' || !unitTypeId.test(id) || id <= priorType)
        throw new WrongMachineContractError('Eligible unit types are malformed.');
      priorType = id;
      const type = content.getUnitType(id);
      if (!type) throw new WrongMachineContractError('Eligible unit type is absent from client content.');
      return type;
    });
    let priorPrerequisite = '';
    const prerequisites = candidate['prerequisites'].map((row: unknown) => {
      if (!record(row) || !exact(row, ['unlock_id', 'owned']) || typeof row['unlock_id'] !== 'string'
        || !unlockId.test(row['unlock_id']) || row['unlock_id'] <= priorPrerequisite || typeof row['owned'] !== 'boolean')
        throw new WrongMachineContractError('Wrong Machine prerequisites are malformed.');
      priorPrerequisite = row['unlock_id'];
      return Object.freeze({ unlockId: row['unlock_id'], owned: row['owned'] });
    });
    const seenItems = new Set<string>();
    const ingredients = candidate['ingredients'].map((row: unknown): WrongMachineIngredient => {
      if (!record(row) || !exact(row, ['item_id', 'quantity', 'owned']) || typeof row['item_id'] !== 'string'
        || !itemId.test(row['item_id']) || seenItems.has(row['item_id']) || !positive(row['quantity']) || !safe(row['owned']))
        throw new WrongMachineContractError('Wrong Machine ingredients are malformed.');
      seenItems.add(row['item_id']);
      const item = content.getItem(row['item_id']);
      if (!item || !item.stackable) throw new WrongMachineContractError('Wrong Machine item is absent from client content.');
      return Object.freeze({ item, quantity: row['quantity'], owned: row['owned'] });
    });
    const prerequisitesMet = prerequisites.every((row) => row.owned);
    const reconstructable = prerequisitesMet && eligibleUnitTypes.length > 0
      && (data['raw_chaos'] as number) >= (candidate['price']['amount'] as number)
      && ingredients.every((row) => row.owned >= row.quantity);
    if (candidate['prerequisites_met'] !== prerequisitesMet || candidate['reconstructable'] !== reconstructable)
      throw new WrongMachineContractError('Wrong Machine availability is incoherent.');
    prior = candidate['recipe_id'];
    return Object.freeze({ recipeId: candidate['recipe_id'], displayName: candidate['display_name'],
      description: candidate['description'], kin, kinRestored: candidate['kin_restored'],
      mode: candidate['mode'] as WrongMachineRecipe['mode'],
      unitTypeSelection: candidate['unit_type_selection'] as WrongMachineRecipe['unitTypeSelection'],
      eligibleUnitTypes: Object.freeze(eligibleUnitTypes), prerequisites: Object.freeze(prerequisites),
      prerequisitesMet, price: Object.freeze({ currencyId: 'raw_chaos', amount: candidate['price']['amount'] as number }),
      ingredients: Object.freeze(ingredients), reconstructable });
  });
  return Object.freeze({ rawChaos: data['raw_chaos'] as number, playerRevision: data['player_revision'] as number,
    recipes: Object.freeze(recipes) });
}

export function parseReconstructionEnvelope(value: unknown, request: ReconstructionPayload,
  content: ClientContentRegistry): ReconstructionResult {
  request = canonicalReconstructionPayload(request);
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true || !record(value['data'])
    || !exact(value['data'], ['recipe_id', 'mode', 'unit', 'spend', 'consumed_items', 'kin_restoration', 'player_revision']))
    throw new WrongMachineContractError('Reconstruction receipt is malformed.');
  const data = value['data']; const unit = data['unit']; const spend = data['spend'];
  const restoration = data['kin_restoration']; const items = data['consumed_items'];
  if (data['recipe_id'] !== request.recipe_id || data['mode'] !== request.expected_mode
    || !record(unit) || !exact(unit, ['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status'])
    || typeof unit['id'] !== 'string' || !/^[1-9][0-9]*$/.test(unit['id'])
    || typeof unit['display_name'] !== 'string' || unit['display_name'].trim() === ''
    || typeof unit['unit_type_id'] !== 'string' || !unitTypeId.test(unit['unit_type_id'])
    || typeof unit['kin_id'] !== 'string' || !kinId.test(unit['kin_id'])
    || !content.getKin(unit['kin_id']) || !content.getUnitType(unit['unit_type_id'])
    || (request.unit_type_id !== undefined && unit['unit_type_id'] !== request.unit_type_id)
    || unit['level'] !== 1 || unit['xp'] !== 0 || unit['lifecycle_status'] !== 'active'
    || !record(spend) || !exact(spend, ['currency_id', 'amount', 'balance_before', 'balance_after'])
    || spend['currency_id'] !== 'raw_chaos' || spend['amount'] !== request.expected_price.amount
    || !safe(spend['balance_before']) || !safe(spend['balance_after'])
    || spend['balance_before'] - spend['amount'] !== spend['balance_after']
    || !record(restoration) || !exact(restoration, ['kin_id', 'unlock_id', 'outcome'])
    || restoration['kin_id'] !== unit['kin_id'] || typeof restoration['unlock_id'] !== 'string'
    || !unlockId.test(restoration['unlock_id'])
    || restoration['outcome'] !== (request.expected_mode === 'first_restoration' ? 'granted' : 'already_owned')
    || !Array.isArray(items) || items.length !== request.expected_ingredients.length
    || !safe(data['player_revision'])) throw new WrongMachineContractError('Reconstruction receipt is incoherent.');
  const consumedItems = items.map((row: unknown, index: number) => {
    const expected = request.expected_ingredients[index];
    if (!record(row) || !exact(row, ['item_id', 'quantity', 'owned_after'])
      || row['item_id'] !== expected.item_id || row['quantity'] !== expected.quantity || !safe(row['owned_after']))
      throw new WrongMachineContractError('Reconstruction consumption is malformed.');
    return Object.freeze({ itemId: expected.item_id, quantity: expected.quantity, ownedAfter: row['owned_after'] });
  });
  return Object.freeze({ recipeId: request.recipe_id, mode: request.expected_mode,
    unit: Object.freeze({ id: unit['id'], displayName: unit['display_name'], unitTypeId: unit['unit_type_id'],
      kinId: unit['kin_id'], level: 1, xp: 0, lifecycleStatus: 'active' }),
    spend: Object.freeze({ currencyId: 'raw_chaos', amount: spend['amount'], balanceBefore: spend['balance_before'],
      balanceAfter: spend['balance_after'] }), consumedItems: Object.freeze(consumedItems),
    kinRestoration: Object.freeze({ kinId: restoration['kin_id'], unlockId: restoration['unlock_id'],
      outcome: restoration['outcome'] as 'granted' | 'already_owned' }), playerRevision: data['player_revision'] });
}
