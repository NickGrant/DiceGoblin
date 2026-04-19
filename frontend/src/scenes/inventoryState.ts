import type { DiceDetailsViewModel } from "../adapters/profileViewModels";

export type DiceSortMode = "rarity" | "size" | "equipped";
export type DiceSizeFilter = "all" | "d4" | "d6" | "d8" | "d10" | "d12" | "d20";
export type DiceRarityFilter = "all" | "common" | "uncommon" | "rare" | "epic" | "legendary";
export type DiceEquippedFilter = "all" | "equipped" | "unequipped";

export const SORT_LABELS: Record<DiceSortMode, string> = {
  rarity: "Rarity",
  size: "Size",
  equipped: "Equipped",
};

export const SIZE_FILTER_ORDER: DiceSizeFilter[] = ["all", "d4", "d6", "d8", "d10", "d12", "d20"];
export const RARITY_FILTER_ORDER: DiceRarityFilter[] = ["all", "common", "uncommon", "rare", "epic", "legendary"];
export const EQUIPPED_FILTER_ORDER: DiceEquippedFilter[] = ["all", "equipped", "unequipped"];
export const SORT_ORDER: DiceSortMode[] = ["rarity", "size", "equipped"];

const RARITY_SORT_VALUE: Record<string, number> = {
  common: 0,
  uncommon: 1,
  rare: 2,
  epic: 3,
  legendary: 4,
};

export function getVisibleDice(
  dice: DiceDetailsViewModel[],
  filters: {
    sortMode: DiceSortMode;
    sizeFilter: DiceSizeFilter;
    rarityFilter: DiceRarityFilter;
    equippedFilter: DiceEquippedFilter;
  }
): DiceDetailsViewModel[] {
  return [...dice]
    .filter((die) => matchesFilters(die, filters))
    .sort((a, b) => compareDice(a, b, filters.sortMode));
}

export function resolveSelectedDiceId(
  dice: DiceDetailsViewModel[],
  visibleDice: DiceDetailsViewModel[],
  selectedDiceId: string | null
): string | null {
  if (visibleDice.length === 0) {
    return null;
  }

  if (selectedDiceId && visibleDice.some((die) => die.id === selectedDiceId)) {
    return selectedDiceId;
  }

  return visibleDice[0]?.id ?? dice[0]?.id ?? null;
}

export function getSelectedDie(
  dice: DiceDetailsViewModel[],
  visibleDice: DiceDetailsViewModel[],
  selectedDiceId: string | null
): DiceDetailsViewModel | null {
  if (!selectedDiceId) {
    return null;
  }

  return visibleDice.find((die) => die.id === selectedDiceId)
    ?? dice.find((die) => die.id === selectedDiceId)
    ?? null;
}

export function buildHoverDetails(die: DiceDetailsViewModel | null, hovered: boolean): string {
  if (!die) {
    return "AFFIX DETAILS\nHover a die to inspect its affixes.";
  }

  const affixLines = die.affixes.slice(0, 2).map((affix) => {
    if (affix.empty) {
      return "Empty Slot";
    }
    return `${affix.label} | ${affix.rarity.toUpperCase()}\n${affix.valueLabel}`;
  });
  if (die.affixes.length > 2) {
    affixLines.push(`+${die.affixes.length - 2} more affix entries`);
  }

  return [
    hovered ? "AFFIX DETAILS (HOVER)" : "AFFIX DETAILS",
    `${die.displayName} | ${die.sizeLabel.toUpperCase()} | ${die.rarity.toUpperCase()}`,
    `Bound: ${describeEquippedBinding(die)}`,
    ...affixLines,
  ].join("\n");
}

export function describeEquippedBinding(die: DiceDetailsViewModel | null): string {
  if (!die?.equipped) {
    return "Unequipped";
  }

  const slotLabel = `slot ${die.equipped.slotIndex + 1}`;
  if (!die.equipped.abilityId) {
    return `${die.equipped.unitName} (${slotLabel})`;
  }

  return `${die.equipped.unitName} | ${labelFromId(die.equipped.abilityId)} ${slotLabel}`;
}

export function cycleValue<T extends string>(values: readonly T[], current: T): T {
  const currentIndex = values.indexOf(current);
  const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % values.length;
  return values[nextIndex] ?? values[0]!;
}

export function labelFromId(id: string): string {
  return id
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function matchesFilters(
  die: DiceDetailsViewModel,
  filters: {
    sizeFilter: DiceSizeFilter;
    rarityFilter: DiceRarityFilter;
    equippedFilter: DiceEquippedFilter;
  }
): boolean {
  if (filters.sizeFilter !== "all" && die.sizeLabel.toLowerCase() !== filters.sizeFilter) {
    return false;
  }
  if (filters.rarityFilter !== "all" && die.rarity.toLowerCase() !== filters.rarityFilter) {
    return false;
  }
  if (filters.equippedFilter === "equipped" && !die.equipped) {
    return false;
  }
  if (filters.equippedFilter === "unequipped" && die.equipped) {
    return false;
  }
  return true;
}

function compareDice(a: DiceDetailsViewModel, b: DiceDetailsViewModel, sortMode: DiceSortMode): number {
  if (sortMode === "equipped") {
    const equippedDelta = Number(Boolean(b.equipped)) - Number(Boolean(a.equipped));
    if (equippedDelta !== 0) {
      return equippedDelta;
    }
  }
  if (sortMode === "size") {
    const sizeDelta = sizeValue(b.sizeLabel) - sizeValue(a.sizeLabel);
    if (sizeDelta !== 0) {
      return sizeDelta;
    }
  }
  if (sortMode === "rarity" || sortMode === "equipped") {
    const rarityDelta = (RARITY_SORT_VALUE[b.rarity] ?? -1) - (RARITY_SORT_VALUE[a.rarity] ?? -1);
    if (rarityDelta !== 0) {
      return rarityDelta;
    }
  }
  if (sortMode === "rarity") {
    const sizeDelta = sizeValue(b.sizeLabel) - sizeValue(a.sizeLabel);
    if (sizeDelta !== 0) {
      return sizeDelta;
    }
  }
  return a.displayName.localeCompare(b.displayName);
}

function sizeValue(sizeLabel: string): number {
  const raw = Number(sizeLabel.replace(/[^0-9]/g, ""));
  return Number.isFinite(raw) ? raw : 0;
}
