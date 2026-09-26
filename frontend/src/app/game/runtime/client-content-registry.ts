import { RuntimeFetch } from './runtime-api-client';

export interface ClientStatBlock {
  readonly hp: number;
  readonly attack: number;
  readonly defense: number;
  readonly precision: number;
  readonly resolve: number;
}

export interface ClientRegionDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly art_key: string;
}

export interface ClientRunNodeTypeDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly description: string;
  readonly icon_key: string;
}

export interface ClientItemDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly description: string;
  readonly category: 'consumable' | 'material';
  readonly rarity: 'common' | 'uncommon' | 'rare' | 'epic' | 'legendary';
  readonly icon_key: string;
  readonly stackable: boolean;
  readonly effect?: {
    readonly type: 'energy_restore';
    readonly amount: number;
  };
}

export interface ClientKinDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly description: string;
  readonly art_key: string;
  readonly trait_summary: string;
  readonly stat_modifiers: ClientStatBlock;
}

export interface ClientUnitTypeDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly description: string;
  readonly art_key: string;
  readonly role: 'frontline' | 'backline' | 'support' | 'utility';
  readonly tier: number;
  readonly base_stats: ClientStatBlock;
  readonly growth_per_level: ClientStatBlock;
  readonly ability_ids: readonly string[];
}

export interface ClientAbilityDefinition {
  readonly id: string;
  readonly kind: 'active' | 'passive';
  readonly display_name: string;
  readonly description: string;
  readonly icon_key: string;
  readonly dice_slot_count: number;
}

export interface ClientDiceMaterialDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly description: string;
  readonly art_key: string;
  readonly allowed_sizes: readonly number[];
}

export interface ClientDiceAspectDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly description: string;
  readonly allowed_sizes: readonly number[];
}

export interface ClientDiceProfileDefinition {
  readonly id: string;
  readonly display_name: string;
  readonly material_id: string;
  readonly rarity: 'common' | 'uncommon' | 'rare' | 'epic' | 'legendary';
  readonly aspect_ids: readonly string[];
  readonly allowed_sizes: readonly number[];
}

export type ClientContentDefinition =
  | ClientRegionDefinition
  | ClientKinDefinition
  | ClientUnitTypeDefinition
  | ClientAbilityDefinition
  | ClientDiceMaterialDefinition
  | ClientDiceAspectDefinition
  | ClientDiceProfileDefinition
  | ClientRunNodeTypeDefinition
  | ClientItemDefinition;

export interface ClientContentProjection {
  readonly revision: string;
  readonly content: {
    readonly gameplay: {
      readonly run_energy_cost: number;
    };
    readonly regions: Readonly<Record<string, ClientRegionDefinition>>;
    readonly kin: Readonly<Record<string, ClientKinDefinition>>;
    readonly unit_types: Readonly<Record<string, ClientUnitTypeDefinition>>;
    readonly abilities: Readonly<Record<string, ClientAbilityDefinition>>;
    readonly dice_materials: Readonly<Record<string, ClientDiceMaterialDefinition>>;
    readonly dice_aspects: Readonly<Record<string, ClientDiceAspectDefinition>>;
    readonly dice_profiles: Readonly<Record<string, ClientDiceProfileDefinition>>;
    readonly run_node_types: Readonly<Record<string, ClientRunNodeTypeDefinition>>;
    readonly items: Readonly<Record<string, ClientItemDefinition>>;
  };
}

export type ClientContentLoadErrorKind = 'request' | 'malformed-response';

export class ClientContentError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ClientContentError';
  }
}

export class ClientContentLoadError extends Error {
  constructor(readonly kind: ClientContentLoadErrorKind) {
    super(`Client content load failed: ${kind}`);
    this.name = 'ClientContentLoadError';
  }
}

const stableIdPattern = /^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/;
const revisionPattern = /^[a-f0-9]{64}$/;
const supportedDieSizes = new Set([4, 6, 8, 10, 12, 20]);
const statFields = ['hp', 'attack', 'defense', 'precision', 'resolve'] as const;
const catalogFields = [
  'regions',
  'kin',
  'unit_types',
  'abilities',
  'dice_materials',
  'dice_aspects',
  'dice_profiles',
  'run_node_types',
  'items',
] as const;
const contentFields = ['gameplay', ...catalogFields] as const;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function requireExactFields(
  record: Record<string, unknown>,
  expected: readonly string[],
  context: string,
): void {
  const actual = Object.keys(record).sort();
  const required = [...expected].sort();
  if (
    actual.length !== required.length ||
    actual.some((field, index) => field !== required[index])
  ) {
    throw new ClientContentError(`${context} has an invalid field set.`);
  }
}

function requireNonEmptyString(record: Record<string, unknown>, key: string): string {
  const value = record[key];
  if (typeof value !== 'string' || value.trim() === '') {
    throw new ClientContentError(`Client content field '${key}' must be a non-empty string.`);
  }
  return value;
}

function requireInteger(
  record: Record<string, unknown>,
  key: string,
  minimum: number,
  maximum: number,
): number {
  const value = record[key];
  if (!Number.isInteger(value) || (value as number) < minimum || (value as number) > maximum) {
    throw new ClientContentError(
      `Client content field '${key}' must be an integer from ${minimum} to ${maximum}.`,
    );
  }
  return value as number;
}

function requireIdentity(
  record: Record<string, unknown>,
  catalogId: string,
  namespace: string,
  kind: string,
): string {
  const id = requireNonEmptyString(record, 'id');
  if (id !== catalogId || !stableIdPattern.test(id) || !id.startsWith(namespace)) {
    throw new ClientContentError(`Client content ${kind} '${catalogId}' has an invalid identity.`);
  }
  return id;
}

function requireStatBlock(
  value: unknown,
  context: string,
  minimum: number,
  positiveHp = false,
): ClientStatBlock {
  if (!isRecord(value)) throw new ClientContentError(`${context} must be a stat object.`);
  requireExactFields(value, statFields, context);
  return Object.freeze({
    hp: requireInteger(value, 'hp', positiveHp ? 1 : minimum, 1000000),
    attack: requireInteger(value, 'attack', minimum, 1000000),
    defense: requireInteger(value, 'defense', minimum, 1000000),
    precision: requireInteger(value, 'precision', minimum, 1000000),
    resolve: requireInteger(value, 'resolve', minimum, 1000000),
  });
}

function requireStringList(
  record: Record<string, unknown>,
  key: string,
  namespace: string,
  allowEmpty: boolean,
): readonly string[] {
  const value = record[key];
  if (!Array.isArray(value) || (!allowEmpty && value.length === 0)) {
    throw new ClientContentError(
      `Client content field '${key}' must be a ${allowEmpty ? '' : 'non-empty '}list.`,
    );
  }
  const ids: string[] = [];
  for (const candidate of value) {
    if (
      typeof candidate !== 'string' ||
      !stableIdPattern.test(candidate) ||
      !candidate.startsWith(namespace)
    ) {
      throw new ClientContentError(`Client content field '${key}' contains an invalid reference.`);
    }
    if (ids.includes(candidate))
      throw new ClientContentError(`Client content field '${key}' contains a duplicate reference.`);
    ids.push(candidate);
  }
  return Object.freeze(ids);
}

function requireDieSizes(record: Record<string, unknown>): readonly number[] {
  const value = record['allowed_sizes'];
  if (!Array.isArray(value) || value.length === 0) {
    throw new ClientContentError("Client content field 'allowed_sizes' must be a non-empty list.");
  }
  const sizes: number[] = [];
  for (const candidate of value) {
    if (!Number.isInteger(candidate) || !supportedDieSizes.has(candidate as number)) {
      throw new ClientContentError(
        "Client content field 'allowed_sizes' contains an unsupported die size.",
      );
    }
    if (sizes.includes(candidate as number))
      throw new ClientContentError(
        "Client content field 'allowed_sizes' contains a duplicate die size.",
      );
    sizes.push(candidate as number);
  }
  return Object.freeze(sizes);
}

/** Validated, indexed view of the generated browser-safe content projection. */
export class ClientContentRegistry {
  readonly revision: string;
  readonly runEnergyCost: number;
  private readonly definitionsById = new Map<string, ClientContentDefinition>();
  private readonly regions = new Map<string, ClientRegionDefinition>();
  private readonly kinDefinitions = new Map<string, ClientKinDefinition>();
  private readonly unitTypes = new Map<string, ClientUnitTypeDefinition>();
  private readonly abilities = new Map<string, ClientAbilityDefinition>();
  private readonly diceMaterials = new Map<string, ClientDiceMaterialDefinition>();
  private readonly diceAspects = new Map<string, ClientDiceAspectDefinition>();
  private readonly diceProfiles = new Map<string, ClientDiceProfileDefinition>();
  private readonly runNodeTypes = new Map<string, ClientRunNodeTypeDefinition>();
  private readonly items = new Map<string, ClientItemDefinition>();

  constructor(projection: unknown) {
    if (!isRecord(projection))
      throw new ClientContentError('Client content projection must be an object.');
    requireExactFields(projection, ['revision', 'content'], 'Client content projection');
    const revision = requireNonEmptyString(projection, 'revision');
    if (!revisionPattern.test(revision))
      throw new ClientContentError('Client content revision must be a SHA-256 hash.');

    const content = projection['content'];
    if (!isRecord(content))
      throw new ClientContentError('Client content projection must contain content catalogs.');
    requireExactFields(content, contentFields, 'Client content catalogs');
    const gameplay = content['gameplay'];
    if (!isRecord(gameplay))
      throw new ClientContentError("Client content 'gameplay' must be an object.");
    requireExactFields(gameplay, ['run_energy_cost'], 'Client gameplay presentation');
    this.runEnergyCost = requireInteger(gameplay, 'run_energy_cost', 1, 1000000);
    const catalogs = Object.fromEntries(
      catalogFields.map((name) => {
        const catalog = content[name];
        if (!isRecord(catalog))
          throw new ClientContentError(`Client content catalog '${name}' must be an object.`);
        return [name, catalog];
      }),
    ) as Record<(typeof catalogFields)[number], Record<string, unknown>>;

    this.loadCatalog(catalogs.regions, this.regions, (id, value) =>
      this.regionDefinition(id, value),
    );
    this.loadCatalog(catalogs.kin, this.kinDefinitions, (id, value) =>
      this.kinDefinition(id, value),
    );
    this.loadCatalog(catalogs.abilities, this.abilities, (id, value) =>
      this.abilityDefinition(id, value),
    );
    this.loadCatalog(catalogs.unit_types, this.unitTypes, (id, value) =>
      this.unitTypeDefinition(id, value),
    );
    this.loadCatalog(catalogs.dice_materials, this.diceMaterials, (id, value) =>
      this.diceMaterialDefinition(id, value),
    );
    this.loadCatalog(catalogs.dice_aspects, this.diceAspects, (id, value) =>
      this.diceAspectDefinition(id, value),
    );
    this.loadCatalog(catalogs.dice_profiles, this.diceProfiles, (id, value) =>
      this.diceProfileDefinition(id, value),
    );
    this.loadCatalog(catalogs.run_node_types, this.runNodeTypes, (id, value) =>
      this.runNodeTypeDefinition(id, value),
    );
    this.loadCatalog(catalogs.items, this.items, (id, value) =>
      this.itemDefinition(id, value),
    );
    this.validateReferences();
    this.revision = revision;
  }

  get(stableId: string): ClientContentDefinition | undefined {
    return this.definitionsById.get(stableId);
  }
  has(stableId: string): boolean {
    return this.definitionsById.has(stableId);
  }
  getRegion(stableId: string): ClientRegionDefinition | undefined {
    return this.regions.get(stableId);
  }
  getKin(stableId: string): ClientKinDefinition | undefined {
    return this.kinDefinitions.get(stableId);
  }
  getUnitType(stableId: string): ClientUnitTypeDefinition | undefined {
    return this.unitTypes.get(stableId);
  }
  getAbility(stableId: string): ClientAbilityDefinition | undefined {
    return this.abilities.get(stableId);
  }
  getDiceMaterial(stableId: string): ClientDiceMaterialDefinition | undefined {
    return this.diceMaterials.get(stableId);
  }
  getDiceAspect(stableId: string): ClientDiceAspectDefinition | undefined {
    return this.diceAspects.get(stableId);
  }
  getDiceProfile(stableId: string): ClientDiceProfileDefinition | undefined {
    return this.diceProfiles.get(stableId);
  }
  getRunNodeType(stableId: string): ClientRunNodeTypeDefinition | undefined {
    return this.runNodeTypes.get(stableId);
  }
  listRunNodeTypes(): readonly ClientRunNodeTypeDefinition[] {
    return Object.freeze([...this.runNodeTypes.values()]);
  }
  getItem(stableId: string): ClientItemDefinition | undefined {
    return this.items.get(stableId);
  }
  listItems(): readonly ClientItemDefinition[] {
    return Object.freeze([...this.items.values()]);
  }

  private loadCatalog<T extends ClientContentDefinition>(
    catalog: Record<string, unknown>,
    target: Map<string, T>,
    validator: (id: string, value: unknown) => T,
  ): void {
    for (const [id, candidate] of Object.entries(catalog)) {
      const definition = validator(id, candidate);
      if (this.definitionsById.has(id))
        throw new ClientContentError(`Client content contains duplicate stable ID '${id}'.`);
      target.set(id, definition);
      this.definitionsById.set(id, definition);
    }
  }

  private regionDefinition(catalogId: string, value: unknown): ClientRegionDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid region definition.');
    requireExactFields(value, ['id', 'display_name', 'art_key'], `Region '${catalogId}'`);
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'region.', 'region'),
      display_name: requireNonEmptyString(value, 'display_name'),
      art_key: requireNonEmptyString(value, 'art_key'),
    });
  }

  private kinDefinition(catalogId: string, value: unknown): ClientKinDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid kin definition.');
    requireExactFields(
      value,
      ['id', 'display_name', 'description', 'art_key', 'trait_summary', 'stat_modifiers'],
      `Kin '${catalogId}'`,
    );
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'kin.', 'kin'),
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      art_key: requireNonEmptyString(value, 'art_key'),
      trait_summary: requireNonEmptyString(value, 'trait_summary'),
      stat_modifiers: requireStatBlock(
        value['stat_modifiers'],
        `Kin '${catalogId}' stat_modifiers`,
        -1000,
      ),
    });
  }

  private unitTypeDefinition(catalogId: string, value: unknown): ClientUnitTypeDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid unit type definition.');
    requireExactFields(
      value,
      [
        'id',
        'display_name',
        'description',
        'art_key',
        'role',
        'tier',
        'base_stats',
        'growth_per_level',
        'ability_ids',
      ],
      `Unit type '${catalogId}'`,
    );
    const role = requireNonEmptyString(value, 'role');
    if (!['frontline', 'backline', 'support', 'utility'].includes(role))
      throw new ClientContentError(`Unit type '${catalogId}' has an invalid role.`);
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'unit_type.', 'unit type'),
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      art_key: requireNonEmptyString(value, 'art_key'),
      role: role as ClientUnitTypeDefinition['role'],
      tier: requireInteger(value, 'tier', 1, 100),
      base_stats: requireStatBlock(
        value['base_stats'],
        `Unit type '${catalogId}' base_stats`,
        0,
        true,
      ),
      growth_per_level: requireStatBlock(
        value['growth_per_level'],
        `Unit type '${catalogId}' growth_per_level`,
        0,
      ),
      ability_ids: requireStringList(value, 'ability_ids', 'ability.', false),
    });
  }

  private abilityDefinition(catalogId: string, value: unknown): ClientAbilityDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid ability definition.');
    requireExactFields(
      value,
      ['id', 'kind', 'display_name', 'description', 'icon_key', 'dice_slot_count'],
      `Ability '${catalogId}'`,
    );
    const kind = requireNonEmptyString(value, 'kind');
    if (kind !== 'active' && kind !== 'passive')
      throw new ClientContentError(`Ability '${catalogId}' has an invalid kind.`);
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'ability.', 'ability'),
      kind,
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      icon_key: requireNonEmptyString(value, 'icon_key'),
      dice_slot_count: requireInteger(
        value,
        'dice_slot_count',
        kind === 'active' ? 1 : 0,
        kind === 'active' ? 8 : 0,
      ),
    });
  }

  private diceMaterialDefinition(catalogId: string, value: unknown): ClientDiceMaterialDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid dice material definition.');
    requireExactFields(
      value,
      ['id', 'display_name', 'description', 'art_key', 'allowed_sizes'],
      `Dice material '${catalogId}'`,
    );
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'dice_material.', 'dice material'),
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      art_key: requireNonEmptyString(value, 'art_key'),
      allowed_sizes: requireDieSizes(value),
    });
  }

  private diceAspectDefinition(catalogId: string, value: unknown): ClientDiceAspectDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid dice aspect definition.');
    requireExactFields(
      value,
      ['id', 'display_name', 'description', 'allowed_sizes'],
      `Dice aspect '${catalogId}'`,
    );
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'dice_aspect.', 'dice aspect'),
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      allowed_sizes: requireDieSizes(value),
    });
  }

  private diceProfileDefinition(catalogId: string, value: unknown): ClientDiceProfileDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid dice profile definition.');
    requireExactFields(
      value,
      ['id', 'display_name', 'material_id', 'rarity', 'aspect_ids', 'allowed_sizes'],
      `Dice profile '${catalogId}'`,
    );
    const rarity = requireNonEmptyString(value, 'rarity');
    if (!['common', 'uncommon', 'rare', 'epic', 'legendary'].includes(rarity))
      throw new ClientContentError(`Dice profile '${catalogId}' has an invalid rarity.`);
    const materialId = value['material_id'];
    if (
      typeof materialId !== 'string' ||
      !stableIdPattern.test(materialId) ||
      !materialId.startsWith('dice_material.')
    )
      throw new ClientContentError(
        `Dice profile '${catalogId}' has an invalid material reference.`,
      );
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'dice_profile.', 'dice profile'),
      display_name: requireNonEmptyString(value, 'display_name'),
      material_id: materialId,
      rarity: rarity as ClientDiceProfileDefinition['rarity'],
      aspect_ids: requireStringList(value, 'aspect_ids', 'dice_aspect.', true),
      allowed_sizes: requireDieSizes(value),
    });
  }

  private runNodeTypeDefinition(catalogId: string, value: unknown): ClientRunNodeTypeDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid run node type definition.');
    requireExactFields(
      value,
      ['id', 'display_name', 'description', 'icon_key'],
      `Run node type '${catalogId}'`,
    );
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'run_node_type.', 'run node type'),
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      icon_key: requireNonEmptyString(value, 'icon_key'),
    });
  }

  private itemDefinition(catalogId: string, value: unknown): ClientItemDefinition {
    if (!isRecord(value))
      throw new ClientContentError('Client content contains an invalid item definition.');
    const category = requireNonEmptyString(value, 'category');
    if (category !== 'consumable' && category !== 'material')
      throw new ClientContentError(`Item '${catalogId}' has an invalid category.`);
    requireExactFields(
      value,
      category === 'consumable'
        ? ['id', 'display_name', 'description', 'category', 'rarity', 'icon_key', 'stackable', 'effect']
        : ['id', 'display_name', 'description', 'category', 'rarity', 'icon_key', 'stackable'],
      `Item '${catalogId}'`,
    );
    const rarity = requireNonEmptyString(value, 'rarity');
    if (!['common', 'uncommon', 'rare', 'epic', 'legendary'].includes(rarity))
      throw new ClientContentError(`Item '${catalogId}' has an invalid rarity.`);
    if (typeof value['stackable'] !== 'boolean')
      throw new ClientContentError(`Item '${catalogId}' has an invalid stackable flag.`);
    let effect: ClientItemDefinition['effect'];
    if (category === 'consumable') {
      const candidate = value['effect'];
      if (!isRecord(candidate)) throw new ClientContentError(`Item '${catalogId}' has an invalid effect.`);
      requireExactFields(candidate, ['type', 'amount'], `Item '${catalogId}' effect`);
      if (candidate['type'] !== 'energy_restore')
        throw new ClientContentError(`Item '${catalogId}' has an unsupported effect.`);
      effect = Object.freeze({ type: 'energy_restore', amount: requireInteger(candidate, 'amount', 1, Number.MAX_SAFE_INTEGER) });
    }
    return Object.freeze({
      id: requireIdentity(value, catalogId, 'item.', 'item'),
      display_name: requireNonEmptyString(value, 'display_name'),
      description: requireNonEmptyString(value, 'description'),
      category,
      rarity: rarity as ClientItemDefinition['rarity'],
      icon_key: requireNonEmptyString(value, 'icon_key'),
      stackable: value['stackable'],
      ...(effect ? { effect } : {}),
    });
  }

  private validateReferences(): void {
    for (const unitType of this.unitTypes.values()) {
      for (const abilityId of unitType.ability_ids) {
        if (!this.abilities.has(abilityId))
          throw new ClientContentError(
            `Unit type '${unitType.id}' references missing ability '${abilityId}'.`,
          );
      }
    }
    for (const profile of this.diceProfiles.values()) {
      const material = this.diceMaterials.get(profile.material_id);
      if (!material)
        throw new ClientContentError(
          `Dice profile '${profile.id}' references missing material '${profile.material_id}'.`,
        );
      const aspects = profile.aspect_ids.map((id) => {
        const aspect = this.diceAspects.get(id);
        if (!aspect)
          throw new ClientContentError(
            `Dice profile '${profile.id}' references missing aspect '${id}'.`,
          );
        return aspect;
      });
      for (const size of profile.allowed_sizes) {
        if (
          !material.allowed_sizes.includes(size) ||
          aspects.some((aspect) => !aspect.allowed_sizes.includes(size))
        ) {
          throw new ClientContentError(
            `Dice profile '${profile.id}' declares an incompatible die size.`,
          );
        }
      }
    }
  }
}

const browserFetch: RuntimeFetch = (input, init) => window.fetch(input, init);

export function resolveClientContentUrl(): string {
  return new URL('game-content.json', document.baseURI).toString();
}

export class ClientContentLoader {
  constructor(
    private readonly fetchRequest: RuntimeFetch = browserFetch,
    readonly contentUrl: string = resolveClientContentUrl(),
  ) {}

  async loadProjection(): Promise<unknown> {
    let response: Response;
    try {
      response = await this.fetchRequest(this.contentUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
    } catch {
      throw new ClientContentLoadError('request');
    }

    if (!response.ok) throw new ClientContentLoadError('request');

    try {
      return await response.json();
    } catch {
      throw new ClientContentLoadError('malformed-response');
    }
  }
}
