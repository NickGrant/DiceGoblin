import { describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeRectangle: true })
  );
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/layout/ContentAreaFrame", () => ({ default: class { setDepth() { return this; } } }));
vi.mock("../../src/components/clickable-panel/SharedActionButton", () => ({ default: class { setEnabled() { return this; } } }));
vi.mock("../../src/components/FormationGrid3x3", () => ({ default: class {}, __esModule: true }));
vi.mock("../../src/components/UnitCardGrid", () => ({ default: class {}, __esModule: true }));
vi.mock("../../src/components/BottomCommandStrip", () => ({ mountBottomCommandStrip: vi.fn() }));

const finalizeRestMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    finalizeRest: (...args: unknown[]) => finalizeRestMock(...args),
  },
}));

describe("RestManagementScene", () => {
  it("resets finalized state when reopened with new rest context", async () => {
    const { default: RestManagementScene } = await import("../../src/scenes/RestManagementScene");

    const scene = new RestManagementScene() as any;
    scene.finalized = true;
    scene.selectedUnitId = "u-old";
    scene.editUnitIds = new Set(["u-old"]);
    scene.editFormation = { A1: "u-old" };
    scene.runUnitState = [{ unit_instance_id: "u-old", hp: 1 }];
    scene.baselineRunUnitHp = new Map([["u-old", 1]]);

    scene.init({ runId: "10", nodeId: "700" });

    expect(scene.runId).toBe("10");
    expect(scene.nodeId).toBe("700");
    expect(scene.finalized).toBe(false);
    expect(scene.selectedUnitId).toBeNull();
    expect(scene.editUnitIds.size).toBe(0);
    expect(scene.editFormation).toEqual({
      A1: null, B1: null, C1: null,
      A2: null, B2: null, C2: null,
      A3: null, B3: null, C3: null,
    });
    expect(scene.runUnitState).toEqual([]);
    expect(scene.baselineRunUnitHp.size).toBe(0);
  });

  it("returns to map immediately after finalize rest", async () => {
    const { default: RestManagementScene } = await import("../../src/scenes/RestManagementScene");

    finalizeRestMock.mockResolvedValueOnce({
      ok: true,
      data: {
        progression: [{ unit_instance_id: "u1", level_before: 1, level_after: 2 }],
      },
    });

    const scene = new RestManagementScene() as any;
    scene.runId = "9";
    scene.nodeId = "501";
    scene.runUnitState = [{ unit_instance_id: "u1", hp: 20 }];
    scene.baselineRunUnitHp = new Map([["u1", 10]]);
    scene.scene = { start: vi.fn() };
    scene.refreshUi = vi.fn();
    scene.applyRestState = vi.fn().mockResolvedValue(true);

    await scene.finalizeRest();

    expect(scene.scene.start).toHaveBeenCalledWith("MapExplorationScene", {
      resolutionMessage: "Rest finalized. - 1 unit healed - 1 progression update",
      resolutionColor: "#ccffcc",
    });
  }, 10000);
});
