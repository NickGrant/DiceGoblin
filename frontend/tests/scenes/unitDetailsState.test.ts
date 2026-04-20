import { describe, expect, it } from "vitest";
import type { DiceDetailsViewModel, UnitDetailsViewModel } from "../../src/adapters/profileViewModels";
import type { PromotionOptionRecord, UnitRecord } from "../../src/types/ApiResponse";
import {
  assignFusionSecondarySelection,
  buildUnitSummaryText,
  clearFusionSelections,
  getSelectableDice,
  isPromotionCompatible,
  resolveUnitDetailsSelections,
} from "../../src/scenes/unitDetailsState";

function makeDie(id: string, unitId?: string): DiceDetailsViewModel {
  return {
    id,
    displayName: id,
    sizeLabel: "d4",
    rarity: "common",
    slotCapacity: 1,
    value: 1,
    sellValue: 1,
    affixes: [],
    equipped: unitId ? { unitId, unitName: `Unit ${unitId}`, slotIndex: 0 } : null,
  };
}

function makeUnitDetails(overrides: Partial<UnitDetailsViewModel> = {}): UnitDetailsViewModel {
  return {
    id: "u1",
    name: "Mudjaw",
    roleLabel: "Bruiser",
    tier: 1,
    level: 2,
    xp: 25,
    maxLevel: 6,
    isMaxLevel: false,
    xpLabel: "25 XP",
    xpProgressRatio: 0.2,
    equippedDice: [],
    abilities: { active: [], passive: [] },
    unlockedAbilities: [
      { id: "a1", label: "Strike", type: "active", order: 1, speedCost: 6, diceCost: 2 },
      { id: "p1", label: "Guard", type: "passive", order: 2, speedCost: 0, diceCost: 0 },
    ],
    equippedLoadout: [
      {
        abilityId: "a1",
        label: "Strike",
        equipOrder: 0,
        speedCost: 6,
        diceCost: 2,
        slots: [{ slotIndex: 0, diceInstanceId: null }, { slotIndex: 1, diceInstanceId: null }],
      },
    ],
    loadoutBudget: { used: 6, max: 20, remaining: 14 },
    ...overrides,
  };
}

function makeRawUnit(overrides: Partial<UnitRecord> = {}): UnitRecord {
  return {
    id: "u1",
    name: "Mudjaw",
    level: 2,
    xp: 25,
    tier: 1,
    unit_type_id: "frontline_bruiser_t1",
    max_level: 6,
    total_attack: 8,
    total_defense: 3,
    max_hp: 20,
    current_hp: 18,
    xp_to_next_level: 75,
    ...overrides,
  };
}

describe("unitDetailsState", () => {
  it("filters selectable dice to empty dice or dice already equipped by the viewed unit", () => {
    const dice = [makeDie("d1"), makeDie("d2", "u1"), makeDie("d3", "u2")];
    expect(getSelectableDice(dice, "u1").map((die) => die.id)).toEqual(["d1", "d2"]);
  });

  it("normalizes loadout, dice, fusion, and promotion selections together", () => {
    const unit = makeUnitDetails({
      equippedLoadout: [
        {
          abilityId: "a1",
          label: "Strike",
          equipOrder: 0,
          speedCost: 6,
          diceCost: 1,
          slots: [{ slotIndex: 0, diceInstanceId: null }],
        },
      ],
    });
    const rawUnits = [makeRawUnit(), makeRawUnit({ id: "u2", name: "Second" })];
    const promotionOptions: PromotionOptionRecord[] = [{
      branch_unit_type_id: "b1",
      branch_unit_type_slug: "frontline_bruiser_t1",
      branch_unit_type_name: "Bruiser",
      target_unit_type_id: "t2",
      target_unit_type_slug: "frontline_enforcer_t2",
      target_unit_type_name: "Enforcer",
      target_tier: 2,
      mode: "chain",
    }];

    const normalized = resolveUnitDetailsSelections({
      unit,
      rawUnits,
      dice: [makeDie("d1"), makeDie("d2", "u2")],
      unitId: "u1",
      promotionOptions,
      state: {
        selectedLoadoutIndex: 9,
        selectedAbilitySlotIndex: 4,
        selectedDiceId: "missing",
        fusionSecondaryIds: ["u2", "gone"],
        selectedFusionSlotIndex: 9,
        selectedPromotionOptionIndex: 5,
      },
    });

    expect(normalized.selectedLoadoutIndex).toBe(0);
    expect(normalized.selectedAbilitySlotIndex).toBe(0);
    expect(normalized.selectedDiceId).toBe("d1");
    expect(normalized.fusionSecondaryIds).toEqual(["u2", null]);
    expect(normalized.selectedFusionSlotIndex).toBe(1);
    expect(normalized.selectedPromotionOptionIndex).toBe(0);
  });

  it("checks promotion compatibility by type, tier, and max level", () => {
    const units: UnitRecord[] = [
      makeRawUnit({ id: "u1", unit_type_id: "frontline_bruiser_t1", tier: 1, level: 6, max_level: 6 }),
      makeRawUnit({ id: "u2", unit_type_id: "frontline_bruiser_t1", tier: 1, level: 6, max_level: 6 }),
      makeRawUnit({ id: "u3", unit_type_id: "frontline_bruiser_t1", tier: 2, level: 6, max_level: 6 }),
      makeRawUnit({ id: "u4", unit_type_id: "frontline_marksman_t1", tier: 1, level: 6, max_level: 6 }),
      makeRawUnit({ id: "u5", unit_type_id: "frontline_bruiser_t1", tier: 1, level: 4, max_level: 6 }),
    ];

    expect(isPromotionCompatible(units, "u1", "u2")).toBe(true);
    expect(isPromotionCompatible(units, "u1", "u3")).toBe(false);
    expect(isPromotionCompatible(units, "u1", "u4")).toBe(false);
    expect(isPromotionCompatible(units, "u1", "u5")).toBe(false);
  });

  it("assigns and clears fusion selections predictably", () => {
    expect(assignFusionSecondarySelection([null, null], 0, "u2")).toEqual({
      fusionSecondaryIds: ["u2", null],
      selectedFusionSlotIndex: 1,
    });

    expect(assignFusionSecondarySelection(["u2", null], 1, "u2")).toEqual({
      fusionSecondaryIds: [null, null],
      selectedFusionSlotIndex: 0,
    });

    expect(clearFusionSelections()).toEqual({
      fusionSecondaryIds: [null, null],
      selectedFusionSlotIndex: 0,
    });
  });

  it("builds a readable unit summary from the unit and raw stat record", () => {
    const summary = buildUnitSummaryText(makeUnitDetails(), makeRawUnit());
    expect(summary).toContain("Mudjaw");
    expect(summary).toContain("Bruiser  T1  LV 2/6");
    expect(summary).toContain("HP 18/20  ATK 8  DEF 3");
    expect(summary).toContain("Loadout 6/20 pts");
    expect(summary).toContain("Abilities 1 active / 1 passive");
    expect(summary).toContain("XP 25 (75 to next)");
  });
});
