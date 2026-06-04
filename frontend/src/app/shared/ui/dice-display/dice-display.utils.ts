import { DiceRecord } from '../../../core/models/api.models';

export const DIE_RARITY_ORDER = ['common', 'uncommon', 'rare', 'epic', 'legendary'] as const;

export type DiceSortOption = 'size-asc' | 'size-desc' | 'rarity-asc' | 'rarity-desc';
export type DiceEquipFilter = 'all' | 'equipped' | 'unequipped';

export function buildDiceSizeOptions(dice: readonly DiceRecord[]): number[] {
  return [...new Set(dice.map((die) => die.sides).filter((sides): sides is number => typeof sides === 'number'))].sort(
    (left, right) => left - right,
  );
}

export function buildDiceRarityOptions(dice: readonly DiceRecord[]): string[] {
  return [...new Set(dice.map((die) => normalizeDieRarity(die.rarity)))]
    .filter((rarity) => rarity.length > 0)
    .sort((left, right) => compareRarity(left, right));
}

export function filterAndSortDice(
  dice: readonly DiceRecord[],
  options: {
    selectedSize: number | null;
    selectedRarity: string | null;
    sort: DiceSortOption;
    equipFilter?: DiceEquipFilter;
    isEquipped?: (diceId: string) => boolean;
  },
): DiceRecord[] {
  return dice
    .filter((die) => matchesSize(die, options.selectedSize))
    .filter((die) => matchesRarity(die, options.selectedRarity))
    .filter((die) => matchesEquipFilter(die, options.equipFilter ?? 'all', options.isEquipped))
    .sort((left, right) => compareDice(left, right, options.sort));
}

function matchesSize(die: DiceRecord, selectedSize: number | null): boolean {
  return selectedSize === null || die.sides === selectedSize;
}

function matchesRarity(die: DiceRecord, selectedRarity: string | null): boolean {
  return selectedRarity === null || normalizeDieRarity(die.rarity) === selectedRarity;
}

function matchesEquipFilter(
  die: DiceRecord,
  equipFilter: DiceEquipFilter,
  isEquipped: ((diceId: string) => boolean) | undefined,
): boolean {
  if (equipFilter === 'all' || !isEquipped) {
    return true;
  }

  const equipped = isEquipped(die.id);
  return equipFilter === 'equipped' ? equipped : !equipped;
}

function compareDice(left: DiceRecord, right: DiceRecord, sort: DiceSortOption): number {
  if (sort === 'size-asc') {
    return compareBySize(left, right, 'asc');
  }
  if (sort === 'size-desc') {
    return compareBySize(left, right, 'desc');
  }
  if (sort === 'rarity-asc') {
    return compareByRarity(left, right, 'asc');
  }
  return compareByRarity(left, right, 'desc');
}

function compareBySize(left: DiceRecord, right: DiceRecord, direction: 'asc' | 'desc'): number {
  const multiplier = direction === 'asc' ? 1 : -1;
  const sizeDelta = ((left.sides ?? 0) - (right.sides ?? 0)) * multiplier;
  if (sizeDelta !== 0) {
    return sizeDelta;
  }

  const rarityDelta = compareRarity(normalizeDieRarity(left.rarity), normalizeDieRarity(right.rarity));
  if (rarityDelta !== 0) {
    return rarityDelta;
  }

  return left.id.localeCompare(right.id);
}

function compareByRarity(left: DiceRecord, right: DiceRecord, direction: 'asc' | 'desc'): number {
  const multiplier = direction === 'asc' ? 1 : -1;
  const rarityDelta =
    compareRarity(normalizeDieRarity(left.rarity), normalizeDieRarity(right.rarity)) * multiplier;
  if (rarityDelta !== 0) {
    return rarityDelta;
  }

  const sizeDelta = (left.sides ?? 0) - (right.sides ?? 0);
  if (sizeDelta !== 0) {
    return sizeDelta;
  }

  return left.id.localeCompare(right.id);
}

function compareRarity(left: string, right: string): number {
  return rarityRank(left) - rarityRank(right);
}

function rarityRank(rarity: string): number {
  const index = DIE_RARITY_ORDER.indexOf(rarity as (typeof DIE_RARITY_ORDER)[number]);
  return index >= 0 ? index : DIE_RARITY_ORDER.length;
}

function normalizeDieRarity(rarity: string | null | undefined): string {
  return (rarity ?? '').trim().toLowerCase();
}
