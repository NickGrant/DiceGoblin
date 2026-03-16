import { beforeEach, describe, expect, it, vi } from "vitest";

let lastDialogConfig: any = null;

vi.mock("phaser", () => {
  class FakeScene {
    registry = {};
    cameras = { main: { centerX: 480, centerY: 270 } };
    add = {
      text: vi.fn(() => ({ setOrigin: vi.fn(() => ({ destroy: vi.fn(), setText: vi.fn() })) })),
    };
    time = { delayedCall: vi.fn() };
    scale = { on: vi.fn(), off: vi.fn(), width: 960, height: 640 };
    scene = { start: vi.fn() };
  }

  class FakeContainer {
    constructor(_scene?: unknown, _x?: number, _y?: number) {}
    add() { return this; }
    setSize() { return this; }
    setInteractive() { return this; }
    setOrigin() { return this; }
    setScrollFactor() { return this; }
    setDepth() { return this; }
    destroy() {}
  }

  return {
    default: { Scene: FakeScene, GameObjects: { Container: FakeContainer } },
    Scene: FakeScene,
    GameObjects: { Container: FakeContainer },
  };
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/navigation/HomeCornerButton", () => ({ default: class { setScale() { return this; } } }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));
vi.mock("../../src/components/UnitCardGrid", () => ({ default: class { destroy() {} } }));
vi.mock("../../src/components/SquadListPanel", () => ({ default: class { destroy() {} } }));
vi.mock("../../src/components/clickable-panel/ActionButtonList", () => ({ default: class {} }));
vi.mock("../../src/components/feedback/ConfirmationDialog", () => ({
  default: class {
    constructor(cfg: unknown) {
      lastDialogConfig = cfg;
    }

    close() {}
  },
}));
vi.mock("../../src/components/UxZonePanels", () => ({ drawUxDualZones: vi.fn() }));
vi.mock("../../src/adapters/profileViewModels", () => ({ adaptUnitRecords: (units: unknown[]) => units }));

const createTeamMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    createTeam: (...args: unknown[]) => createTeamMock(...args),
  },
}));

import WarbandManagementScene from "../../src/scenes/WarbandManagementScene";

describe("WarbandManagementScene create squad flow", () => {
  beforeEach(() => {
    createTeamMock.mockReset();
    lastDialogConfig = null;
  });

  it("skips createTeam when create dialog is rejected", async () => {
    const scene = new WarbandManagementScene() as any;
    scene.showToast = vi.fn();

    await scene.createSquad();
    await lastDialogConfig.onReject();

    expect(createTeamMock).not.toHaveBeenCalled();
    expect(scene.scene.start).not.toHaveBeenCalled();
  });

  it("shows error toast when createTeam fails", async () => {
    const scene = new WarbandManagementScene() as any;
    scene.showToast = vi.fn();
    createTeamMock.mockResolvedValueOnce({ ok: false, error: { message: "bad request" } });

    await scene.createSquad();
    await lastDialogConfig.onAccept();

    expect(createTeamMock).toHaveBeenCalledWith("New Squad", false);
    expect(scene.showToast).toHaveBeenCalledWith("Create failed: bad request");
    expect(scene.scene.start).not.toHaveBeenCalled();
  });

  it("shows success toast and routes to squad details on success", async () => {
    const scene = new WarbandManagementScene() as any;
    scene.showToast = vi.fn();
    createTeamMock.mockResolvedValueOnce({ ok: true, data: { team_id: "t-100" } });

    await scene.createSquad();
    await lastDialogConfig.onAccept();

    expect(scene.showToast).toHaveBeenCalledWith("Squad created.", "#ccffcc");
    expect(scene.scene.start).toHaveBeenCalledWith("SquadDetailsScene", { squadId: "t-100" });
  });
});
