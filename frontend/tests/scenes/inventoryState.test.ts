import { describe, expect, it } from "vitest";

import type { DiceDetailsViewModel } from "../../src/adapters/profileViewModels";
import {
  buildHoverDetails,
  cycleValue,
  describeEquippedBinding,
  getSelectedDie,
  getVisibleDice,
  resolveSelectedDiceId,
} from "../../src/scenes/inventoryState";

function makeDie(overrides: Partial<DiceDetailsViewModel> & Pick<DiceDetailsViewModel, "id" | "displayName">): DiceDetailsViewModel {
  return {
    id: overrides.id,
    displayName: overrides.displayName,
    sizeLabel: overrides.sizeLabel ?? "d6",
    rarity: overrides.rarity ?? "common",
    slotCapacity: overrides.slotCapacity ?? 1,
    value: overrides.value ?? 6,
    sellValue: overrides.sellValue ?? 5,
    affixes: overrides.affixes ?? [],
    equipped: overrides.equipped ?? null,
  };
}

describe("inventoryState", () => {
  it("filters and sorts dice by the requested state", () => {
    const dice = [
      makeDie({ id: "1", displayName: "Common D6", rarity: "common", sizeLabel: "d6" }),
      makeDie({
        id: "2",
        displayName: "Rare D8 Equipped",
        rarity: "rare",
        sizeLabel: "d8",
        equipped: { unitId: "u-1", unitName: "Nick", slotIndex: 0, abilityId: "fast_attack" },
      }),
      makeDie({ id: "3", displayName: "Epic D4", rarity: "epic", sizeLabel: "d4" }),
    ];

    expect(getVisibleDice(dice, {
      sortMode: "rarity",
      sizeFilter: "all",
      rarityFilter: "all",
      equippedFilter: "all",
    }).map((die) => die.id)).toEqual(["3", "2", "1"]);

    expect(getVisibleDice(dice, {
      sortMode: "equipped",
      sizeFilter: "all",
      rarityFilter: "all",
      equippedFilter: "equipped",
    }).map((die) => die.id)).toEqual(["2"]);
  });

  it("resolves selected dice ids against the visible inventory", () => {
    const dice = [
      makeDie({ id: "1", displayName: "One" }),
      makeDie({ id: "2", displayName: "Two" }),
    ];

    expect(resolveSelectedDiceId(dice, dice, "2")).toBe("2");
    expect(resolveSelectedDiceId(dice, [dice[1]!], "1")).toBe("2");
    expect(resolveSelectedDiceId(dice, [], "1")).toBeNull();
    expect(getSelectedDie(dice, [dice[1]!], "1")?.id).toBe("1");
  });

  it("builds hover details and equipped binding summaries", () => {
    const die = makeDie({
      id: "2",
      displayName: "Rare D8 Equipped",
      rarity: "rare",
      sizeLabel: "d8",
      equipped: { unitId: "u-1", unitName: "Nick", slotIndex: 1, abilityId: "fast_attack" },
      affixes: [
        {
          id: "a-1",
          rarity: "rare",
          kindLabel: "Damage",
          description: "",
          valueLabel: "+2 damage",
          kind: "flat",
          conditional: false,
          empty: false,
          label: "Jagged",
        },
        {
          id: "a-2",
          rarity: "common",
          kindLabel: "Empty",
          description: "",
          valueLabel: "",
          kind: "flat",
          conditional: false,
          empty: true,
          label: "Empty",
        },
      ],
    });

    expect(describeEquippedBinding(die)).toBe("Nick | Fast Attack slot 2");
    expect(buildHoverDetails(die, true)).toContain("AFFIX DETAILS (HOVER)");
    expect(buildHoverDetails(die, true)).toContain("Bound: Nick | Fast Attack slot 2");
    expect(buildHoverDetails(die, true)).toContain("Jagged | RARE");
    expect(buildHoverDetails(die, true)).toContain("Empty Slot");
    expect(buildHoverDetails(null, false)).toBe("AFFIX DETAILS\nHover a die to inspect its affixes.");
  });

  it("cycles list values predictably", () => {
    expect(cycleValue(["a", "b", "c"] as const, "a")).toBe("b");
    expect(cycleValue(["a", "b", "c"] as const, "c")).toBe("a");
  });
});
