import { describe, expect, it } from "vitest";
import type { ProfileResponse } from "../../src/types/ApiResponse";
import {
  computeWarbandColumns,
  deriveWarbandHubState,
  normalizeNewSquadName,
} from "../../src/scenes/warbandManagementState";

describe("warbandManagementState", () => {
  it("derives units and squads from profile response", () => {
    const profile: ProfileResponse = {
      ok: true,
      data: {
        server_time_iso: "2026-03-07T00:00:00.000Z",
        squads: [{ id: "1", name: "Main", is_active: true, unit_ids: ["11"], formation: [] }],
        units: [{ id: "11", name: "Bruiser", level: 1 }],
        dice: [],
        currency: { soft: 0, hard: 0 },
        energy: { current: 50, max: 50, regen_rate_per_hour: 1, last_regen_at: "2026-03-07T00:00:00.000Z" },
        region_unlocks: [],
        region_items: [],
        active_run: null,
      },
    };

    const state = deriveWarbandHubState(profile);
    expect(state.units).toHaveLength(1);
    expect(state.squads).toHaveLength(1);
    expect(state.units[0]?.name).toBe("Bruiser");
    expect(state.squads[0]?.name).toBe("Main");
  });

  it("throws with API error message when profile is not ok", () => {
    const profile: ProfileResponse = {
      ok: false,
      error: { code: "server_error", message: "Failed to load profile." },
    };
    expect(() => deriveWarbandHubState(profile)).toThrow("Failed to load profile.");
  });

  it("computes deterministic two-column layout positions", () => {
    const columns = computeWarbandColumns(100, 900, 24);
    expect(columns).toEqual({
      leftX: 100,
      rightX: 562,
      columnWidth: 438,
      splitGap: 24,
    });
  });

  it("normalizes new squad name from prompt text", () => {
    expect(normalizeNewSquadName("  Alpha  ")).toBe("Alpha");
    expect(normalizeNewSquadName("   ")).toBeNull();
    expect(normalizeNewSquadName(null)).toBeNull();
  });
});
