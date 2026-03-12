import { describe, expect, it } from "vitest";
import {
  buildDebugSession,
  parseDebugSceneConfig,
  resolveDebugSceneKey,
} from "../../src/debug/debugScene";

describe("debug scene config", () => {
  it("resolves common scene aliases", () => {
    expect(resolveDebugSceneKey("home")).toBe("HomeScene");
    expect(resolveDebugSceneKey("map-exploration")).toBe("MapExplorationScene");
    expect(resolveDebugSceneKey("warband")).toBe("WarbandManagementScene");
  });

  it("parses scene selection, auth, and scene data", () => {
    const config = parseDebugSceneConfig(
      "?debugScene=unit&debugAuth=guest&debugSceneData=%7B%22unitId%22%3A%2212%22%7D&debugSettleMs=300"
    );

    expect(config.enabled).toBe(true);
    expect(config.targetSceneKey).toBe("UnitDetailsScene");
    expect(config.authMode).toBe("guest");
    expect(config.sceneData).toEqual({ unitId: "12" });
    expect(config.settleMs).toBe(300);
    expect(config.skipSessionCheck).toBe(true);
  });

  it("records errors for invalid scenes and invalid scene data", () => {
    const config = parseDebugSceneConfig("?debugScene=missing&debugSceneData=%5B1%2C2%5D");

    expect(config.enabled).toBe(false);
    expect(config.errors).toEqual([
      "Unknown debugScene 'missing'.",
      "debugSceneData must decode to a JSON object.",
    ]);
  });

  it("builds an authenticated debug session by default", () => {
    const session = buildDebugSession(parseDebugSceneConfig("?debugScene=home"));

    expect(session).toEqual({
      isAuthenticated: true,
      user: {
        id: "debug-user",
        displayName: "Debug Goblin",
        avatarUrl: "",
      },
      csrfToken: "debug-csrf-token",
    });
  });
});
