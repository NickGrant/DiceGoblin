import { describe, expect, it } from "vitest";

import {
  buildClaimSummary,
  buildLootReceiptLines,
  buildTickSummaryLines,
  deriveDefaultTick,
  formatFriendlyEvent,
  prettifyAbilityId,
  prettifyEnemySlug,
  shortenEnemyLabel,
  type ResolutionSummary,
} from "../../src/scenes/nodeResolutionPresentation";

const baseSummary: ResolutionSummary = {
  battleId: "battle-1",
  outcome: "victory",
  rounds: 2,
  ticks: 14,
  unlockedMsg: "Unlocked next node",
  encounterDescription: "Farm ambush",
};

describe("nodeResolutionPresentation", () => {
  it("builds claim summaries from reward and progression payloads", () => {
    expect(buildClaimSummary({
      rewards: {
        currency_soft: 15,
        xp_total: 9,
        new_unit_instance_ids: ["u-2"],
        new_dice_instance_ids: ["d-4", "d-7"],
      },
      updated_units: [
        { id: "u-1", level: 3, xp: 18 },
        { id: "u-2", level: 2, xp: 7 },
      ],
    })).toEqual({
      rewards: [
        "Teeth +15",
        "Unit XP Award +9 each",
        "New Units: #u-2",
        "New Dice: #d-4, #d-7",
      ],
      progression: [
        "Unit u-1: L3 (18 XP)",
        "Unit u-2: L2 (7 XP)",
      ],
    });
  });

  it("uses a fallback reward line when no rewards are recorded", () => {
    expect(buildClaimSummary({ rewards: {}, updated_units: [] })).toEqual({
      rewards: ["- No rewards recorded"],
      progression: [],
    });
  });

  it("builds receipt lines with cleaned reward bullets and progression fallback", () => {
    expect(buildLootReceiptLines(baseSummary, {
      rewards: ["- Teeth +15", "Unit XP Award +9 each"],
      progression: [],
    })).toEqual([
      "Encounter: Farm ambush",
      "",
      "Rewards:",
      "- Teeth +15",
      "- Unit XP Award +9 each",
      "",
      "Progression:",
      "- No unit progression changes",
      "",
      "Outcome: VICTORY",
    ]);
  });

  it("derives the first actionable tick from the battle log", () => {
    expect(deriveDefaultTick({
      events: [
        { type: "battle_start" },
        { type: "action", tick: "6" },
        { type: "action", tick: 12 },
      ],
    })).toBe(6);
    expect(deriveDefaultTick({ events: [{ type: "phase_start", round: 1 }] })).toBe(0);
    expect(deriveDefaultTick(null)).toBe(0);
  });

  it("formats friendly event lines for action and non-action records", () => {
    expect(formatFriendlyEvent({
      type: "action",
      side: "enemy",
      actor_enemy_slug: "bog_lurker",
      target_unit_instance_id: "u-9",
      ability_id: "toxic_jab",
      damage: 5,
      target_hp_after: 11,
      status_applied: "poison",
      status_duration_rounds: 2,
      dice_outcome: "[4,1]",
    })).toBe("[ENEMY] Bog Lurker -> Ally u-9 using Toxic Jab | DMG 5 | HP 11 | Status poison (2r) | Dice [4,1]");

    expect(formatFriendlyEvent({ type: "battle_end", outcome: "victory" })).toBe("Battle ended: victory.");
    expect(formatFriendlyEvent({ type: "note", message: "Rewards claimed." })).toBe("Rewards claimed.");
  });

  it("builds tick summary lines from the selected tick and events", () => {
    expect(buildTickSummaryLines(baseSummary, 6, [
      {
        type: "action",
        side: "player",
        actor_unit_instance_id: "u-1",
        target_enemy_slug: "bog_lurker",
        ability_id: "fast_attack",
        damage: 3,
      },
    ])).toEqual([
      "Encounter: Farm ambush",
      "Tick 6",
      "Events on tick 6:",
      "1) [PLAYER] Ally u-1 -> Bog Lurker using Fast Attack | DMG 3",
    ]);

    expect(buildTickSummaryLines(baseSummary, 10, [])).toEqual([
      "Encounter: Farm ambush",
      "Tick 10",
      "No events on this tick.",
    ]);
  });

  it("prettifies ids and shortens enemy labels consistently", () => {
    expect(prettifyAbilityId("slow_attack")).toBe("Slow Attack");
    expect(prettifyEnemySlug("swamp_viper")).toBe("Swamp Viper");
    expect(shortenEnemyLabel("Swamp Viper Champion")).toBe("Swamp Viper..");
    expect(shortenEnemyLabel("Pig Raider")).toBe("Pig Raider");
  });
});
