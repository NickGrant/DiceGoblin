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

  it("adapts equipped loadout budget and ability-slot dice using catalog metadata", () => {
    const vms = adaptUnitDetails(
      [
        {
          id: "u7",
          name: "Mudjaw",
          unit_type_name: "Bruiser",
          level: 2,
          xp: 10,
          max_level: 5,
          unlocked_abilities: [
            { ability_id: "basic_attack_melee" },
            { ability_id: "heavy_strike" },
            { ability_id: "guard_stance" },
          ],
          equipped_abilities: [
            { ability_id: "basic_attack_melee", equip_order: 0, speed_cost: 4 },
            { ability_id: "heavy_strike", equip_order: 1, speed_cost: 8 },
          ],
          ability_dice: [
            { ability_id: "heavy_strike", slot_index: 0, dice_instance_id: "d9" },
          ],
        },
      ],
      [
        { ability_id: "basic_attack_melee", type: "active", display_name: "Basic Attack", order: 10, speed: 4, dice_cost: 0, short_desc: "", icon_key: "", tags: [], default_params: {} },
        { ability_id: "heavy_strike", type: "active", display_name: "Heavy Strike", order: 20, speed: 8, dice_cost: 1, short_desc: "", icon_key: "", tags: [], default_params: {} },
        { ability_id: "guard_stance", type: "passive", display_name: "Guard Stance", order: 30, short_desc: "", icon_key: "", tags: [], default_params: {} },
      ]
    );

    const vm = vms[0]!;
    expect(vm.loadoutBudget).toEqual({ used: 12, max: 20, remaining: 8 });
    expect(vm.unlockedAbilities.map((ability) => ability.id)).toEqual([
      "basic_attack_melee",
      "heavy_strike",
      "guard_stance",
    ]);
    expect(vm.equippedLoadout).toHaveLength(2);
    expect(vm.equippedLoadout[1]).toMatchObject({
      abilityId: "heavy_strike",
      speedCost: 8,
      diceCost: 1,
      slots: [{ slotIndex: 0, diceInstanceId: "d9" }],
    });
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
          value: 88,
          sell_value: 44,
          affixes: [
            {
              affix_definition_id: "crit_percent_if_full_hp",
              affix_slug: "explode_once",
              name: "Explode",
              rarity: "rare",
              kind: "triggered",
              description: "Roll again once on max and combine.",
              value: 0.15,
            },
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
      value: 88,
      sellValue: 44,
      equipped: {
        unitId: "u1",
        unitName: "Goblin Archer",
        slotIndex: 1,
      },
    });
    expect(first.affixes[0]!.label).toBe("Explode");
    expect(first.affixes[0]!.rarity).toBe("rare");
    expect(first.affixes[0]!.kind).toBe("flat");
    expect(first.affixes[0]!.kindLabel).toBe("Triggered");
    expect(first.affixes[0]!.description).toContain("Roll again once");
    expect(first.affixes[1]!.label).toBe("Empty");
    expect(first.affixes[2]!.label).toBe("Empty");
  });

  it("prefers ability-slot equip context over legacy unit-pool context", () => {
    const dice = adaptDiceDetails(
      [
        { id: "d2", sides: 4, rarity: "common", slot_capacity: 0, affix_slots: 0, affixes: [] },
      ],
      [
        {
          id: "u2",
          name: "Bogblade",
          level: 1,
          equipped_dice: [{ dice_instance_id: "d2", slot_index: 0 }],
          ability_dice: [{ ability_id: "heavy_strike", slot_index: 0, dice_instance_id: "d2" }],
        },
      ]
    );

    expect(dice[0]?.equipped).toMatchObject({
      unitId: "u2",
      unitName: "Bogblade",
      slotIndex: 0,
      abilityId: "heavy_strike",
    });
  });

  it("sanitizes malformed numeric and nested equipped-dice data", () => {
    const units = adaptUnitRecords([
      {
        id: "u99",
        level: -4,
        xp: -10,
        tier: -2,
        equipped_dice: [
          { dice_instance_id: "", slot_index: 1 },
          { dice_instance_id: "d5", slot_index: -8 },
          { bad: true },
        ],
      },
    ]);

    expect(units).toHaveLength(1);
    expect(units[0]).toMatchObject({
      id: "u99",
      level: 0,
      xp: 0,
      tier: 0,
      equipped_dice: [{ dice_instance_id: "d5", slot_index: 0 }],
    });
  });
});
