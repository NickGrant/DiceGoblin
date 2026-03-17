import { describe, expect, it } from "vitest";
import {
  validateCurrentRunResponse,
  validateProfileResponse,
  validateSessionResponse,
} from "../../src/services/apiContractValidators";

describe("API contract validators", () => {
  it("accepts valid session response payload", () => {
    const payload = {
      ok: true,
      data: {
        authenticated: true,
        csrf_token: "token_123",
        user: { id: "1", display_name: "Goblin" },
      },
    };

    expect(validateSessionResponse(payload)).toEqual(payload);
  });

  it("rejects session payload missing csrf_token", () => {
    const payload = {
      ok: true,
      data: {
        authenticated: true,
      },
    };

    expect(() => validateSessionResponse(payload)).toThrow("csrf_token");
  });

  it("rejects session payload with non token-like csrf_token", () => {
    const payload = {
      ok: true,
      data: {
        authenticated: true,
        csrf_token: "bad token",
      },
    };

    expect(() => validateSessionResponse(payload)).toThrow("token-like");
  });

  it("accepts valid profile response payload", () => {
    const payload = {
      ok: true,
      data: {
        server_time_iso: "2026-03-02T00:00:00Z",
        squads: [],
        units: [],
        dice: [],
        currency: { soft: 10, hard: 0 },
        energy: { current: 50, max: 50, regen_rate_per_hour: 12, last_regen_at: "2026-03-02T00:00:00Z" },
        region_unlocks: [],
        region_items: [],
        active_run: null,
      },
    };

    expect(validateProfileResponse(payload)).toEqual(payload);
  });

  it("rejects profile payload with teams key instead of squads", () => {
    const payload = {
      ok: true,
      data: {
        server_time_iso: "2026-03-02T00:00:00Z",
        teams: [],
        units: [],
        dice: [],
        currency: { soft: 10, hard: 0 },
        energy: { current: 50, max: 50, regen_rate_per_hour: 12, last_regen_at: "2026-03-02T00:00:00Z" },
        region_unlocks: [],
        region_items: [],
        active_run: null,
      },
    };

    expect(() => validateProfileResponse(payload)).toThrow("data.squads");
  });

  it("rejects profile payload with invalid timestamp and energy bounds", () => {
    const payload = {
      ok: true,
      data: {
        server_time_iso: "03/02/2026",
        squads: [],
        units: [],
        dice: [],
        currency: { soft: 10, hard: 0 },
        energy: { current: 60, max: 50, regen_rate_per_hour: -1, last_regen_at: "yesterday" },
        region_unlocks: [],
        region_items: [],
        active_run: null,
      },
    };

    expect(() => validateProfileResponse(payload)).toThrow("ISO-8601 UTC");
  });

  it("accepts valid current-run response payload", () => {
    const payload = {
      ok: true,
      data: {
        run: {
          run_id: "1",
          region_id: "1",
          seed: "123",
          status: "active",
          started_at: "2026-03-02T00:00:00Z",
          ended_at: null,
        },
        map: {
          nodes: [],
          edges: [],
        },
      },
    };

    expect(validateCurrentRunResponse(payload)).toEqual(payload);
  });

  it("rejects current-run payload with malformed map contract", () => {
    const payload = {
      ok: true,
      data: {
        run: null,
        map: {
          nodes: {},
          edges: [],
        },
      },
    };

    expect(() => validateCurrentRunResponse(payload)).toThrow("map.nodes/edges");
  });

  it("rejects current-run payload with invalid run timestamp and node shape", () => {
    const payload = {
      ok: true,
      data: {
        run: {
          run_id: "1",
          region_id: "1",
          seed: "123",
          status: "active",
          started_at: "2026-03-02 00:00:00",
          ended_at: null,
        },
        map: {
          nodes: [
            {
              id: "n1",
              node_type: "combat",
            },
          ],
          edges: [],
        },
      },
    };

    expect(() => validateCurrentRunResponse(payload)).toThrow("ISO-8601 UTC");
  });
});
