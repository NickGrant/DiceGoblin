import { describe, expect, it } from "vitest";
import { adaptDiceDetails, adaptUnitDetails, adaptUnitRecords } from "../../src/adapters/profileViewModels";

describe("profileViewModels adapters", () => {
  it("normalizes basic unit records for scene safety", () => {
    const units = adaptUnitRecords([
      { id: "1", level: 2 },
      { id: "2", name: "Scout", level: 3, equipped_dice: [{ dice_instance_id: "9", slot_index: 0 }] },
      { bogus: true },
    ]);

    expect(units).toHaveLength(2);
    const first = units[0]!;
    const second = units[1]!;
    expect(first).toMatchObject({ id: "1", name: "Unit 1", level: 2, xp: 0, tier: 1 });
    expect(second.equipped_dice).toEqual([{ dice_instance_id: "9", slot_index: 0 }]);
  });

  it("adapts unit details with max-level and ability grouping", () => {
    const vms = adaptUnitDetails([
      {
        id: "u1",
        name: "Goblin Bruiser",
        unit_type_name: "Bruiser",
        level: 5,
        xp: 120,
        max_level: 5,
        abilities: [
          { ability_id: "guard_stance", type: "passive", order: 2 },
          { ability_id: "smash", type: "active", order: 1, display_name: "Smash" },
          { ability_id: "battle_cry", type: "active", order: 0 },
        ],
      },
    ]);

    expect(vms).toHaveLength(1);
    const vm = vms[0]!;
    expect(vm.isMaxLevel).toBe(true);
    expect(vm.xpLabel).toBe("MAX");
    expect(vm.xpProgressRatio).toBeNull();
    expect(vm.abilities.active.map((a) => a.id)).toEqual(["battle_cry", "smash"]);
    expect(vm.abilities.passive.map((a) => a.label)).toEqual(["Guard Stance"]);
  });

  it("adapts dice details with equip context, affix labels, and empty slots", () => {
    const dice = adaptDiceDetails(
      [
        {
          id: "d1",
          sides: 8,
          rarity: "Rare",
          slot_capacity: 2,
          affix_slots: 3,
          affixes: [
            { affix_definition_id: "crit_percent_if_full_hp", value: 0.15 },
          ],
        },
      ],
      [
        {
          id: "u1",
          name: "Goblin Archer",
          level: 3,
          equipped_dice: [{ dice_instance_id: "d1", slot_index: 1 }],
        },
      ]
    );

    expect(dice).toHaveLength(1);
    const first = dice[0]!;
    expect(first).toMatchObject({
      id: "d1",
      sizeLabel: "d8",
      rarity: "rare",
      slotCapacity: 2,
      equipped: {
        unitId: "u1",
        unitName: "Goblin Archer",
        slotIndex: 1,
      },
    });
    expect(first.affixes[0]!.label).toContain("(Conditional)");
    expect(first.affixes[0]!.kind).toBe("percent");
    expect(first.affixes[1]!.label).toBe("Empty");
    expect(first.affixes[2]!.label).toBe("Empty");
  });
});
