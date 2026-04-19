import type { DiceDetailsViewModel, UnitDetailsViewModel, UnitEquippedAbilityViewModel } from "../adapters/profileViewModels";
import type { PromotionOptionRecord, UnitRecord } from "../types/ApiResponse";

export const REQUIRED_FUSION_UNITS = 2;

export type UnitDetailsSelectionState = {
  selectedLoadoutIndex: number;
  selectedAbilitySlotIndex: number;
  selectedDiceId: string | null;
  fusionSecondaryIds: Array<string | null>;
  selectedFusionSlotIndex: number;
  selectedPromotionOptionIndex: number;
};

export function getSelectableDice(
  dice: DiceDetailsViewModel[],
  unitId: string,
): DiceDetailsViewModel[] {
  return dice.filter((die) => !die.equipped || die.equipped.unitId === unitId);
}

export function getFusionCandidates(units: UnitRecord[], unitId: string): UnitRecord[] {
  return units.filter((unit) => unit.id !== unitId);
}

export function isPromotionCompatible(
  units: UnitRecord[],
  primaryId: string,
  secondaryId: string,
): boolean {
  const primary = units.find((unit) => unit.id === primaryId);
  const secondary = units.find((unit) => unit.id === secondaryId);
  if (!primary || !secondary) return false;
  if (primary.id === secondary.id) return false;
  if ((primary.unit_type_id ?? "") !== (secondary.unit_type_id ?? "")) return false;
  if ((primary.tier ?? 1) !== (secondary.tier ?? 1)) return false;

  const primaryMaxLevel = typeof primary.max_level === "number" ? primary.max_level : null;
  const secondaryMaxLevel = typeof secondary.max_level === "number" ? secondary.max_level : null;
  if (primaryMaxLevel !== null && primary.level < primaryMaxLevel) return false;
  if (secondaryMaxLevel !== null && secondary.level < secondaryMaxLevel) return false;
  return true;
}

export function assignFusionSecondarySelection(
  currentSelections: Array<string | null>,
  selectedFusionSlotIndex: number,
  unitId: string,
): { fusionSecondaryIds: Array<string | null>; selectedFusionSlotIndex: number } {
  const nextSelections = [...currentSelections];
  const existingIndex = nextSelections.findIndex((id) => id === unitId);
  if (existingIndex >= 0) {
    nextSelections[existingIndex] = null;
    return {
      fusionSecondaryIds: nextSelections,
      selectedFusionSlotIndex: existingIndex,
    };
  }

  const targetIndex = nextSelections.findIndex((id) => id === null);
  const safeIndex = targetIndex >= 0 ? targetIndex : selectedFusionSlotIndex;
  nextSelections[safeIndex] = unitId;
  return {
    fusionSecondaryIds: nextSelections,
    selectedFusionSlotIndex: Math.min(REQUIRED_FUSION_UNITS - 1, safeIndex + 1),
  };
}

export function clearFusionSelections(): { fusionSecondaryIds: Array<string | null>; selectedFusionSlotIndex: number } {
  return {
    fusionSecondaryIds: Array(REQUIRED_FUSION_UNITS).fill(null),
    selectedFusionSlotIndex: 0,
  };
}

export function resolveUnitDetailsSelections(args: {
  unit: UnitDetailsViewModel | null;
  rawUnits: UnitRecord[];
  dice: DiceDetailsViewModel[];
  unitId: string;
  promotionOptions: PromotionOptionRecord[];
  state: UnitDetailsSelectionState;
}): UnitDetailsSelectionState {
  const { unit, rawUnits, dice, unitId, promotionOptions, state } = args;
  if (!unit) {
    return {
      ...state,
      selectedLoadoutIndex: 0,
      selectedAbilitySlotIndex: 0,
      selectedDiceId: null,
      fusionSecondaryIds: Array(REQUIRED_FUSION_UNITS).fill(null),
      selectedFusionSlotIndex: 0,
      selectedPromotionOptionIndex: 0,
    };
  }

  const loadoutLength = unit.equippedLoadout.length;
  const selectedLoadoutIndex = loadoutLength === 0
    ? 0
    : Math.max(0, Math.min(state.selectedLoadoutIndex, loadoutLength - 1));
  const selectedAbility = unit.equippedLoadout[selectedLoadoutIndex];
  const slotCount = selectedAbility?.diceCost ?? 0;
  const selectedAbilitySlotIndex = slotCount > 0
    ? Math.max(0, Math.min(state.selectedAbilitySlotIndex, slotCount - 1))
    : 0;

  const selectableDice = getSelectableDice(dice, unitId);
  const selectedDiceId = state.selectedDiceId && selectableDice.some((die) => die.id === state.selectedDiceId)
    ? state.selectedDiceId
    : selectableDice[0]?.id ?? null;

  const compatibleIds = new Set(getFusionCandidates(rawUnits, unitId).map((candidate) => candidate.id));
  const fusionSecondaryIds = state.fusionSecondaryIds.map((candidateId) =>
    candidateId && compatibleIds.has(candidateId) ? candidateId : null
  );

  return {
    selectedLoadoutIndex,
    selectedAbilitySlotIndex,
    selectedDiceId,
    fusionSecondaryIds,
    selectedFusionSlotIndex: Math.max(0, Math.min(state.selectedFusionSlotIndex, REQUIRED_FUSION_UNITS - 1)),
    selectedPromotionOptionIndex: promotionOptions.length === 0
      ? 0
      : Math.max(0, Math.min(state.selectedPromotionOptionIndex, promotionOptions.length - 1)),
  };
}

export function buildUnitSummaryText(
  unit: UnitDetailsViewModel | null,
  rawUnit: UnitRecord | null,
): string {
  if (!unit || !rawUnit) return "";

  const activeAbilities = unit.unlockedAbilities.filter((ability) => ability.type === "active").length;
  const passiveAbilities = unit.unlockedAbilities.filter((ability) => ability.type === "passive").length;
  const totalAttack = typeof rawUnit.total_attack === "number" ? rawUnit.total_attack : "?";
  const totalDefense = typeof rawUnit.total_defense === "number" ? rawUnit.total_defense : "?";
  const hpCurrent = typeof rawUnit.current_hp === "number" ? rawUnit.current_hp : "?";
  const hpMax = typeof rawUnit.max_hp === "number" ? rawUnit.max_hp : "?";
  const xpToNext = typeof rawUnit.xp_to_next_level === "number" ? rawUnit.xp_to_next_level : null;

  return [
    unit.name,
    `${unit.roleLabel}  T${unit.tier}  LV ${unit.level}${unit.maxLevel ? `/${unit.maxLevel}` : ""}`,
    `HP ${hpCurrent}/${hpMax}  ATK ${totalAttack}  DEF ${totalDefense}`,
    `Loadout ${unit.loadoutBudget.used}/${unit.loadoutBudget.max} pts`,
    `Abilities ${activeAbilities} active / ${passiveAbilities} passive`,
    unit.isMaxLevel ? "XP MAX" : `XP ${unit.xp}${xpToNext !== null ? ` (${xpToNext} to next)` : ""}`,
  ].join("\n");
}

export function getSelectedPromotionOption(
  promotionOptions: PromotionOptionRecord[],
  selectedPromotionOptionIndex: number,
): PromotionOptionRecord | null {
  return promotionOptions[selectedPromotionOptionIndex] ?? null;
}

export function getSelectedLoadoutEntry(
  unit: UnitDetailsViewModel | null,
  selectedLoadoutIndex: number,
): UnitEquippedAbilityViewModel | null {
  if (!unit) return null;
  return unit.equippedLoadout[selectedLoadoutIndex] ?? null;
}
