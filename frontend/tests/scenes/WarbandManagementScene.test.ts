import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  const FakeScene = class {
    registry = {};
    cameras = { main: { centerX: 480, centerY: 270 } };
    add = {
      text: () => ({
        setOrigin: () => ({
          destroy: () => {},
          setText: () => {},
        }),
      }),
    };
    time = { delayedCall: (_ms: number, cb: () => void) => cb() };
    scale = { on: () => {}, off: () => {}, width: 960, height: 640 };
    scene = { start: () => {} };
  };

  return {
    default: { Scene: FakeScene },
    Scene: FakeScene,
  };
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/HomeButton", () => ({ default: class { setScale() { return this; } } }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));
vi.mock("../../src/components/Button", () => ({ default: class { setEnabled() {} destroy() {} } }));
vi.mock("../../src/components/FormationGrid3x3", () => ({
  default: class {
    getSelectedCell() { return null; }
    setFormation() {}
    destroy() {}
  },
}));
vi.mock("../../src/components/UnitListPanel", () => ({
  default: class {
    refreshRowStates() {}
    destroy() {}
  },
}));

const updateTeamMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    updateTeam: (...args: unknown[]) => updateTeamMock(...args),
  },
}));

import WarbandManagementScene from "../../src/scenes/WarbandManagementScene";

describe("WarbandManagementScene interactions", () => {
  beforeEach(() => {
    updateTeamMock.mockReset();
  });

  it("moves a unit from prior cell when placing into a new cell", () => {
    const scene = new WarbandManagementScene() as any;
    scene.editFormation = {
      A1: "u1", B1: null, C1: null,
      A2: null, B2: null, C2: null,
      A3: null, B3: null, C3: null,
    };
    scene.editUnitIds = new Set<string>();

    scene.placeUnitIntoCell("u1", "B2");

    expect(scene.editFormation.A1).toBeNull();
    expect(scene.editFormation.B2).toBe("u1");
    expect(scene.editUnitIds.has("u1")).toBe(true);
  });

  it("handles cell click by placing selected unit and clearing selection", () => {
    const scene = new WarbandManagementScene() as any;
    scene.selectedUnitId = "u3";
    scene.editFormation = {
      A1: null, B1: null, C1: null,
      A2: null, B2: null, C2: null,
      A3: null, B3: null, C3: null,
    };
    scene.editUnitIds = new Set<string>();
    scene.refreshDerivedUiState = vi.fn();

    scene.handleCellClick("C3");

    expect(scene.editFormation.C3).toBe("u3");
    expect(scene.selectedUnitId).toBeNull();
    expect(scene.refreshDerivedUiState).toHaveBeenCalledTimes(1);
  });

  it("clears selected occupied cell", () => {
    const scene = new WarbandManagementScene() as any;
    scene.editFormation = {
      A1: "u9", B1: null, C1: null,
      A2: null, B2: null, C2: null,
      A3: null, B3: null, C3: null,
    };
    scene.grid = { getSelectedCell: () => "A1" };
    scene.refreshDerivedUiState = vi.fn();

    scene.clearSelectedCell();

    expect(scene.editFormation.A1).toBeNull();
    expect(scene.refreshDerivedUiState).toHaveBeenCalledTimes(1);
  });

  it("shows error toast when save fails", async () => {
    const scene = new WarbandManagementScene() as any;
    scene.activeTeam = { id: "11", name: "Alpha", is_active: true, unit_ids: [], formation: [] };
    scene.editUnitIds = new Set<string>(["u1"]);
    scene.editFormation = {
      A1: "u1", B1: null, C1: null,
      A2: null, B2: null, C2: null,
      A3: null, B3: null, C3: null,
    };
    scene.showToast = vi.fn();
    scene.loadData = vi.fn();
    updateTeamMock.mockResolvedValue({ ok: false, error: { message: "bad request" } });

    await scene.saveTeam();

    expect(updateTeamMock).toHaveBeenCalledTimes(1);
    expect(scene.showToast).toHaveBeenCalledWith("Save failed: bad request");
    expect(scene.loadData).not.toHaveBeenCalled();
  });

  it("shows success toast and reloads after save success", async () => {
    const scene = new WarbandManagementScene() as any;
    scene.activeTeam = { id: "12", name: "Beta", is_active: true, unit_ids: [], formation: [] };
    scene.editUnitIds = new Set<string>(["u2"]);
    scene.editFormation = {
      A1: "u2", B1: null, C1: null,
      A2: null, B2: null, C2: null,
      A3: null, B3: null, C3: null,
    };
    scene.showToast = vi.fn();
    scene.loadData = vi.fn().mockResolvedValue(undefined);
    updateTeamMock.mockResolvedValue({ ok: true, data: {} });

    await scene.saveTeam();

    expect(updateTeamMock).toHaveBeenCalledTimes(1);
    expect(scene.showToast).toHaveBeenCalledWith("Saved!", "#ccffcc");
    expect(scene.loadData).toHaveBeenCalledTimes(1);
  });
});
