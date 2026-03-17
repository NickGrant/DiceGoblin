import { describe, expect, it } from "vitest";
import {
  deriveSummaryStatus,
  formatBattleLogSummary,
  formatUnlockedNodes,
  isNodeResolutionType,
} from "./nodeResolutionFlow";

describe("nodeResolutionFlow", () => {
  it("validates supported node-resolution types", () => {
    expect(isNodeResolutionType("combat")).toBe(true);
    expect(isNodeResolutionType("loot")).toBe(true);
    expect(isNodeResolutionType("boss")).toBe(true);
    expect(isNodeResolutionType("exit")).toBe(true);
    expect(isNodeResolutionType("rest")).toBe(false);
  });

  it("derives summary status for combat outcomes", () => {
    expect(deriveSummaryStatus({ nodeType: "combat", outcome: "victory" })).toBe("completed");
    expect(deriveSummaryStatus({ nodeType: "boss", outcome: "defeat" })).toBe("failed");
  });

  it("derives summary status for exit outcomes", () => {
    expect(deriveSummaryStatus({ nodeType: "exit", exitStatus: "abandoned" })).toBe("abandoned");
    expect(deriveSummaryStatus({ nodeType: "exit", exitStatus: "failed" })).toBe("failed");
    expect(deriveSummaryStatus({ nodeType: "exit", exitStatus: "completed" })).toBe("completed");
  });

  it("formats unlocked node summaries", () => {
    expect(formatUnlockedNodes([])).toBe("No new nodes unlocked.");
    expect(formatUnlockedNodes(["n2", "n3"])).toBe("Unlocked nodes: n2, n3.");
  });

  it("formats full battle events with action outcome detail", () => {
    const lines = formatBattleLogSummary({
      meta: { engine: "deterministic_v1", rng: { seed: 1234 } },
      events: [
        { type: "battle_start", round: 0, tick: 0, player_unit_count: 4, enemy_unit_count: 3 },
        {
          type: "action",
          round: 1,
          tick: 5,
          side: "player",
          actor_unit_instance_id: "1",
          target_enemy_slug: "goblin_archer",
          ability_id: "poison_stab",
          damage: 6,
          outcome: "hit",
          target_hp_after: 11,
          status_applied: "poison",
        },
        { type: "battle_end", round: 3, tick: 60, outcome: "victory" },
      ],
    });

    expect(lines).toContain("Events: 3");
    expect(lines.some((line) => line.includes("ability=poison_stab"))).toBe(true);
    expect(lines.some((line) => line.includes("damage=6"))).toBe(true);
    expect(lines.some((line) => line.includes("status=poison"))).toBe(true);
    expect(lines.some((line) => line.includes("outcome=victory"))).toBe(true);
  });
});


