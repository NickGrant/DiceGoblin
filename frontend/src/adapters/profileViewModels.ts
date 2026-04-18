import type {
  AbilityCatalogEntry,
  DiceRecord,
  UnitAbilityDieRecord,
  UnitAbilityRecord,
  UnitEquippedAbilityRecord,
  UnitEquippedDie,
  UnitRecord,
  UnitUnlockedAbilityRecord,
} from "../types/ApiResponse";

export type UnitAbilityViewModel = {
  id: string;
  label: string;
  type: "active" | "passive";
  order: number;
};

export type UnitDetailsViewModel = {
  id: string;
  name: string;
  roleLabel: string;
  tier: number;
  level: number;
  xp: number;
  maxLevel: number | null;
  isMaxLevel: boolean;
  xpLabel: string;
  xpProgressRatio: number | null;
  equippedDice: Array<{ diceInstanceId: string; slotIndex: number }>;
  abilities: {
    active: UnitAbilityViewModel[];
    passive: UnitAbilityViewModel[];
  };
  unlockedAbilities: UnitAbilityViewModel[];
  equippedLoadout: UnitEquippedAbilityViewModel[];
  loadoutBudget: {
    used: number;
    max: number;
    remaining: number;
  };
};

export type UnitAbilitySlotViewModel = {
  slotIndex: number;
  diceInstanceId: string | null;
};

export type UnitEquippedAbilityViewModel = {
  abilityId: string;
  label: string;
  equipOrder: number;
  speedCost: number;
  diceCost: number;
  slots: UnitAbilitySlotViewModel[];
};

export type DiceAffixViewModel = {
  id: string;
  label: string;
  rarity: string;
  kindLabel: string;
  description: string;
  valueLabel: string;
  kind: "flat" | "percent";
  conditional: boolean;
  empty: boolean;
};

export type DiceDetailsViewModel = {
  id: string;
  displayName: string;
  sizeLabel: string;
  rarity: string;
  slotCapacity: number;
  value: number;
  sellValue: number;
  affixes: DiceAffixViewModel[];
  equipped: {
    unitId: string;
    unitName: string;
    slotIndex: number;
    abilityId?: string;
  } | null;
};

export function adaptUnitRecords(rawUnits: unknown[]): UnitRecord[] {
  return rawUnits
    .map((raw) => adaptUnitRecord(raw))
    .filter((unit): unit is UnitRecord => unit !== null);
}

export function adaptUnitDetails(rawUnits: unknown[], rawCatalog: AbilityCatalogEntry[] = []): UnitDetailsViewModel[] {
  const catalog = indexAbilityCatalog(rawCatalog);
  return adaptUnitRecords(rawUnits).map((unit) => {
    const xp = toNonNegativeInt(unit.xp, 0);
    const maxLevel = typeof unit.max_level === "number" && Number.isFinite(unit.max_level)
      ? Math.max(1, Math.floor(unit.max_level))
      : null;
    const isMaxLevel = maxLevel !== null && unit.level >= maxLevel;
    const xpLabel = isMaxLevel ? "MAX" : `${xp} XP`;

    const equippedDice = normalizeEquippedDice(unit.equipped_dice).map((entry) => ({
      diceInstanceId: entry.dice_instance_id,
      slotIndex: entry.slot_index,
    }));

    const abilityBuckets = normalizeAbilities(unit.abilities);
    const unlockedAbilities = normalizeUnlockedAbilities(unit.unlocked_abilities, catalog);
    const equippedLoadout = normalizeEquippedLoadout(unit.equipped_abilities, unit.ability_dice, catalog);
    const usedBudget = equippedLoadout.reduce((sum, entry) => sum + entry.speedCost, 0);

    return {
      id: unit.id,
      name: unit.name,
      roleLabel: nonEmptyString(unit.unit_type_name) ?? "Unknown Role",
      tier: toNonNegativeInt(unit.tier, 1),
      level: toNonNegativeInt(unit.level, 1),
      xp,
      maxLevel,
      isMaxLevel,
      xpLabel,
      xpProgressRatio: isMaxLevel ? null : normalizeXpProgress(unit),
      equippedDice,
      abilities: abilityBuckets,
      unlockedAbilities,
      equippedLoadout,
      loadoutBudget: {
        used: usedBudget,
        max: 20,
        remaining: Math.max(0, 20 - usedBudget),
      },
    };
  });
}

export function adaptDiceDetails(rawDice: unknown[], rawUnits: unknown[]): DiceDetailsViewModel[] {
  const units = adaptUnitRecords(rawUnits);
  const equippedIndex = new Map<string, { unitId: string; unitName: string; slotIndex: number; abilityId?: string }>();

  for (const unit of units) {
    for (const equipped of normalizeAbilityDiceRecords(unit.ability_dice)) {
      equippedIndex.set(equipped.dice_instance_id, {
        unitId: unit.id,
        unitName: unit.name,
        slotIndex: equipped.slot_index,
        abilityId: equipped.ability_id,
      });
    }
    for (const equipped of normalizeEquippedDice(unit.equipped_dice)) {
      if (equippedIndex.has(equipped.dice_instance_id)) continue;
      equippedIndex.set(equipped.dice_instance_id, {
        unitId: unit.id,
        unitName: unit.name,
        slotIndex: equipped.slot_index,
      });
    }
  }

  return rawDice
    .map((raw) => adaptDiceRecord(raw))
    .filter((die): die is DiceRecord => die !== null)
    .map((die) => {
      const affixes = normalizeAffixes(die);
      return {
        id: die.id,
        displayName: nonEmptyString(die.display_name) ?? `d${toNonNegativeInt(die.sides, 0)} Die`,
        sizeLabel: `d${toNonNegativeInt(die.sides, 0)}`,
        rarity: nonEmptyString(die.rarity)?.toLowerCase() ?? "common",
        slotCapacity: toNonNegativeInt(die.slot_capacity, 0),
        value: toNonNegativeInt(die.value, 0),
        sellValue: toNonNegativeInt(die.sell_value, 0),
        affixes,
        equipped: equippedIndex.get(die.id) ?? null,
      };
    });
}

function adaptUnitRecord(raw: unknown): UnitRecord | null {
  if (!isRecord(raw)) return null;
  const id = nonEmptyString(raw.id);
  if (!id) return null;

  return {
    ...raw,
    id,
    name: nonEmptyString(raw.name) ?? `Unit ${id}`,
    level: toNonNegativeInt(raw.level, 1),
    xp: toNonNegativeInt(raw.xp, 0),
    tier: toNonNegativeInt(raw.tier, 1),
    max_level: typeof raw.max_level === "number" && Number.isFinite(raw.max_level)
      ? Math.max(1, Math.floor(raw.max_level))
      : undefined,
    max_tier: typeof raw.max_tier === "number" && Number.isFinite(raw.max_tier)
      ? Math.max(1, Math.floor(raw.max_tier))
      : undefined,
    total_attack: typeof raw.total_attack === "number" && Number.isFinite(raw.total_attack)
      ? Math.max(0, Math.floor(raw.total_attack))
      : undefined,
    total_defense: typeof raw.total_defense === "number" && Number.isFinite(raw.total_defense)
      ? Math.max(0, Math.floor(raw.total_defense))
      : undefined,
    max_hp: typeof raw.max_hp === "number" && Number.isFinite(raw.max_hp)
      ? Math.max(1, Math.floor(raw.max_hp))
      : undefined,
    current_hp: typeof raw.current_hp === "number" && Number.isFinite(raw.current_hp)
      ? Math.max(0, Math.floor(raw.current_hp))
      : undefined,
    xp_to_next_level: typeof raw.xp_to_next_level === "number" && Number.isFinite(raw.xp_to_next_level)
      ? Math.max(0, Math.floor(raw.xp_to_next_level))
      : undefined,
    equipped_dice: normalizeEquippedDice(raw.equipped_dice),
    abilities: normalizeAbilityRecords(raw.abilities),
    unlocked_abilities: normalizeUnlockedAbilityRecords(raw.unlocked_abilities),
    equipped_abilities: normalizeEquippedAbilityRecords(raw.equipped_abilities),
    ability_dice: normalizeAbilityDiceRecords(raw.ability_dice),
  };
}

function adaptDiceRecord(raw: unknown): DiceRecord | null {
  if (!isRecord(raw)) return null;
  const id = nonEmptyString(raw.id);
  if (!id) return null;

  return {
    ...raw,
    id,
    display_name: nonEmptyString(raw.display_name) ?? null,
    rarity: nonEmptyString(raw.rarity) ?? "common",
    sides: toNonNegativeInt(raw.sides, 0),
    slot_capacity: toNonNegativeInt(raw.slot_capacity, 0),
    affix_slots: toNonNegativeInt(raw.affix_slots, toNonNegativeInt(raw.slot_capacity, 0)),
    value: toNonNegativeInt(raw.value, 0),
    sell_value: toNonNegativeInt(raw.sell_value, 0),
    affixes: normalizeDiceAffixRecords(raw.affixes),
  };
}

function normalizeEquippedDice(value: unknown): UnitEquippedDie[] {
  if (!Array.isArray(value)) return [];
  const out: UnitEquippedDie[] = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const diceId = nonEmptyString(item.dice_instance_id);
    if (!diceId) continue;
    out.push({
      dice_instance_id: diceId,
      slot_index: toNonNegativeInt(item.slot_index, 0),
    });
  }
  return out;
}

function normalizeAbilityRecords(value: unknown): UnitAbilityRecord[] {
  if (!Array.isArray(value)) return [];
  const out: UnitAbilityRecord[] = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const abilityId = nonEmptyString(item.ability_id);
    if (!abilityId) continue;
    out.push({
      ability_id: abilityId,
      type: nonEmptyString(item.type) ?? undefined,
      display_name: nonEmptyString(item.display_name) ?? undefined,
      order: typeof item.order === "number" ? item.order : undefined,
    });
  }
  return out;
}

function normalizeUnlockedAbilityRecords(value: unknown): UnitUnlockedAbilityRecord[] {
  if (!Array.isArray(value)) return [];
  const out: UnitUnlockedAbilityRecord[] = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const abilityId = nonEmptyString(item.ability_id);
    if (!abilityId) continue;
    out.push({ ability_id: abilityId });
  }
  return out;
}

function normalizeEquippedAbilityRecords(value: unknown): UnitEquippedAbilityRecord[] {
  if (!Array.isArray(value)) return [];
  const out: UnitEquippedAbilityRecord[] = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const abilityId = nonEmptyString(item.ability_id);
    if (!abilityId) continue;
    out.push({
      ability_id: abilityId,
      equip_order: toNonNegativeInt(item.equip_order, out.length),
      speed_cost: toNonNegativeInt(item.speed_cost, 0),
    });
  }
  return out.sort((a, b) => a.equip_order - b.equip_order || a.ability_id.localeCompare(b.ability_id));
}

function normalizeAbilityDiceRecords(value: unknown): UnitAbilityDieRecord[] {
  if (!Array.isArray(value)) return [];
  const out: UnitAbilityDieRecord[] = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const abilityId = nonEmptyString(item.ability_id);
    const diceId = nonEmptyString(item.dice_instance_id);
    if (!abilityId || !diceId) continue;
    out.push({
      ability_id: abilityId,
      slot_index: toNonNegativeInt(item.slot_index, 0),
      dice_instance_id: diceId,
    });
  }
  return out.sort((a, b) =>
    a.ability_id.localeCompare(b.ability_id)
    || a.slot_index - b.slot_index
    || a.dice_instance_id.localeCompare(b.dice_instance_id)
  );
}

function normalizeAbilities(value: unknown): { active: UnitAbilityViewModel[]; passive: UnitAbilityViewModel[] } {
  const raw = normalizeAbilityRecords(value);
  const active: UnitAbilityViewModel[] = [];
  const passive: UnitAbilityViewModel[] = [];
  let fallbackOrder = 0;

  for (const ability of raw) {
    const mapped: UnitAbilityViewModel = {
      id: ability.ability_id,
      label: nonEmptyString(ability.display_name) ?? labelFromId(ability.ability_id),
      type: ability.type === "passive" ? "passive" : "active",
      order: typeof ability.order === "number" && Number.isFinite(ability.order) ? ability.order : fallbackOrder++,
    };
    if (mapped.type === "passive") {
      passive.push(mapped);
    } else {
      active.push(mapped);
    }
  }

  active.sort((a, b) => a.order - b.order || a.label.localeCompare(b.label));
  passive.sort((a, b) => a.order - b.order || a.label.localeCompare(b.label));
  return { active, passive };
}

function normalizeUnlockedAbilities(
  value: unknown,
  catalog: Map<string, AbilityCatalogEntry>,
): UnitAbilityViewModel[] {
  const raw = normalizeUnlockedAbilityRecords(value);
  return raw
    .map((ability, index): UnitAbilityViewModel => {
      const catalogEntry = catalog.get(ability.ability_id);
      return {
        id: ability.ability_id,
        label: catalogEntry?.display_name ?? labelFromId(ability.ability_id),
        type: catalogEntry?.type === "passive" ? "passive" : "active",
        order: typeof catalogEntry?.order === "number" ? catalogEntry.order : index,
      };
    })
    .sort((a, b) => a.type.localeCompare(b.type) || a.order - b.order || a.label.localeCompare(b.label));
}

function normalizeEquippedLoadout(
  equippedAbilitiesValue: unknown,
  abilityDiceValue: unknown,
  catalog: Map<string, AbilityCatalogEntry>,
): UnitEquippedAbilityViewModel[] {
  const equippedAbilities = normalizeEquippedAbilityRecords(equippedAbilitiesValue);
  const abilityDice = normalizeAbilityDiceRecords(abilityDiceValue);
  const diceByAbility = new Map<string, Map<number, string>>();

  for (const entry of abilityDice) {
    const bySlot = diceByAbility.get(entry.ability_id) ?? new Map<number, string>();
    bySlot.set(entry.slot_index, entry.dice_instance_id);
    diceByAbility.set(entry.ability_id, bySlot);
  }

  return equippedAbilities.map((ability) => {
    const catalogEntry = catalog.get(ability.ability_id);
    const diceCost = Math.max(0, Number(catalogEntry?.dice_cost ?? 0));
    const slots: UnitAbilitySlotViewModel[] = [];
    for (let slotIndex = 0; slotIndex < diceCost; slotIndex += 1) {
      slots.push({
        slotIndex,
        diceInstanceId: diceByAbility.get(ability.ability_id)?.get(slotIndex) ?? null,
      });
    }
    return {
      abilityId: ability.ability_id,
      label: catalogEntry?.display_name ?? labelFromId(ability.ability_id),
      equipOrder: ability.equip_order,
      speedCost: ability.speed_cost,
      diceCost,
      slots,
    };
  });
}

function indexAbilityCatalog(rawCatalog: AbilityCatalogEntry[]): Map<string, AbilityCatalogEntry> {
  const map = new Map<string, AbilityCatalogEntry>();
  for (const entry of rawCatalog) {
    const abilityId = nonEmptyString(entry?.ability_id);
    if (!abilityId) continue;
    map.set(abilityId, entry);
  }
  return map;
}

function normalizeDiceAffixRecords(value: unknown): Array<{
  affix_definition_id: string;
  affix_slug?: string;
  name?: string;
  rarity?: string;
  kind?: string;
  description?: string;
  value: number;
}> {
  if (!Array.isArray(value)) return [];
  const out: Array<{
    affix_definition_id: string;
    affix_slug?: string;
    name?: string;
    rarity?: string;
    kind?: string;
    description?: string;
    value: number;
  }> = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const affixId = nonEmptyString(item.affix_definition_id);
    if (!affixId) continue;
    out.push({
      affix_definition_id: affixId,
      affix_slug: nonEmptyString(item.affix_slug) ?? undefined,
      name: nonEmptyString(item.name) ?? undefined,
      rarity: nonEmptyString(item.rarity)?.toLowerCase() ?? undefined,
      kind: nonEmptyString(item.kind)?.toLowerCase() ?? undefined,
      description: nonEmptyString(item.description) ?? undefined,
      value: typeof item.value === "number" && Number.isFinite(item.value) ? item.value : 0,
    });
  }
  return out;
}

function normalizeAffixes(die: DiceRecord): DiceAffixViewModel[] {
  const raw = normalizeDiceAffixRecords(die.affixes);
  const affixes: DiceAffixViewModel[] = raw.map((affix): DiceAffixViewModel => {
    const sourceId = affix.affix_slug ?? affix.affix_definition_id;
    const conditional = (affix.kind ?? "") === "triggered" || isConditionalAffix(sourceId);
    const percent = isPercentAffix(sourceId);
    const valueLabel = percent
      ? `${formatNumber(affix.value * 100)}%`
      : formatNumber(affix.value);

    return {
      id: affix.affix_definition_id,
      label: affix.name ?? labelFromId(sourceId),
      rarity: affix.rarity ?? "common",
      kindLabel: conditional ? "Triggered" : "Passive",
      description: affix.description ?? "No description available.",
      valueLabel,
      kind: percent ? "percent" : "flat",
      conditional,
      empty: false,
    };
  });

  const affixSlots = toNonNegativeInt((die as Record<string, unknown>).affix_slots, affixes.length);
  for (let i = affixes.length; i < affixSlots; i += 1) {
    affixes.push({
      id: `empty_${i}`,
      label: "Empty",
      rarity: "none",
      kindLabel: "Empty",
      description: "No affix assigned.",
      valueLabel: "-",
      kind: "flat",
      conditional: false,
      empty: true,
    });
  }

  return affixes;
}

function normalizeXpProgress(unit: UnitRecord): number | null {
  const xp = toNonNegativeInt(unit.xp, 0);
  const xpToNext = toNonNegativeInt((unit as Record<string, unknown>).xp_to_next_level, 0);
  if (xpToNext <= 0) return null;
  const levelSegmentTotal = xp + xpToNext;
  if (levelSegmentTotal <= 0) {
    return null;
  }
  return Math.max(0, Math.min(1, xp / levelSegmentTotal));
}

function isConditionalAffix(id: string): boolean {
  return /(conditional|_if_|^if_|_when_|^when_|on_hit|on_kill|while_)/i.test(id);
}

function isPercentAffix(id: string): boolean {
  return /(pct|percent|_mult|_ratio|_rate|precision|bulwark)/i.test(id);
}

function labelFromId(id: string): string {
  return id
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatNumber(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/\.?0+$/, "");
}

function nonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

function toNonNegativeInt(value: unknown, fallback: number): number {
  if (typeof value !== "number" || !Number.isFinite(value)) return fallback;
  return Math.max(0, Math.floor(value));
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
