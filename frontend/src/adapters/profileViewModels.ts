import type { DiceRecord, UnitAbilityRecord, UnitEquippedDie, UnitRecord } from "../types/ApiResponse";

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
};

export type DiceAffixViewModel = {
  id: string;
  label: string;
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
  affixes: DiceAffixViewModel[];
  equipped: {
    unitId: string;
    unitName: string;
    slotIndex: number;
  } | null;
};

export function adaptUnitRecords(rawUnits: unknown[]): UnitRecord[] {
  return rawUnits
    .map((raw) => adaptUnitRecord(raw))
    .filter((unit): unit is UnitRecord => unit !== null);
}

export function adaptUnitDetails(rawUnits: unknown[]): UnitDetailsViewModel[] {
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
    };
  });
}

export function adaptDiceDetails(rawDice: unknown[], rawUnits: unknown[]): DiceDetailsViewModel[] {
  const units = adaptUnitRecords(rawUnits);
  const equippedIndex = new Map<string, { unitId: string; unitName: string; slotIndex: number }>();

  for (const unit of units) {
    for (const equipped of normalizeEquippedDice(unit.equipped_dice)) {
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
    equipped_dice: normalizeEquippedDice(raw.equipped_dice),
    abilities: normalizeAbilityRecords(raw.abilities),
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

function normalizeDiceAffixRecords(value: unknown): Array<{ affix_definition_id: string; value: number }> {
  if (!Array.isArray(value)) return [];
  const out: Array<{ affix_definition_id: string; value: number }> = [];
  for (const item of value) {
    if (!isRecord(item)) continue;
    const affixId = nonEmptyString(item.affix_definition_id);
    if (!affixId) continue;
    out.push({
      affix_definition_id: affixId,
      value: typeof item.value === "number" && Number.isFinite(item.value) ? item.value : 0,
    });
  }
  return out;
}

function normalizeAffixes(die: DiceRecord): DiceAffixViewModel[] {
  const raw = normalizeDiceAffixRecords(die.affixes);
  const affixes: DiceAffixViewModel[] = raw.map((affix): DiceAffixViewModel => {
    const conditional = isConditionalAffix(affix.affix_definition_id);
    const percent = isPercentAffix(affix.affix_definition_id);
    const valueLabel = percent
      ? `${formatNumber(affix.value * 100)}%`
      : formatNumber(affix.value);

    return {
      id: affix.affix_definition_id,
      label: `${labelFromId(affix.affix_definition_id)}${conditional ? " (Conditional)" : ""}`,
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
  return Math.max(0, Math.min(1, xp / xpToNext));
}

function isConditionalAffix(id: string): boolean {
  return /(conditional|_if_|^if_|_when_|^when_|on_hit|on_kill|while_)/i.test(id);
}

function isPercentAffix(id: string): boolean {
  return /(pct|percent|_mult|_ratio|_rate)/i.test(id);
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
