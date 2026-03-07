import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  class FakeScene {
    cameras = { main: { centerX: 480, centerY: 270 } };
    scale = { width: 960, height: 640 };
    scene = { start: vi.fn() };
  }
  class Rectangle {
    constructor(public x: number, public y: number, public width: number, public height: number) {}
  }
  return {
    default: { Scene: FakeScene, Geom: { Rectangle } },
    Scene: FakeScene,
    Geom: { Rectangle },
  };
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/navigation/HomeCornerButton", () => ({ default: class {} }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));
vi.mock("../../src/components/layout/ContentAreaFrame", () => ({ default: class { setDepth() { return this; } } }));
vi.mock("../../src/components/clickable-panel/ActionButtonList", () => ({ default: class {} }));
vi.mock("../../src/components/feedback/ToastMessage", () => ({ default: class { destroy() {} } }));
vi.mock("../../src/components/feedback/ConfirmationDialog", () => ({
  default: class {
    constructor(_cfg: unknown) {}
    close() {}
  },
}));

const nodeListCtor = vi.fn();
vi.mock("../../src/components/encounter-map/NodeList", () => ({
  default: class {
    destroy() {}
    constructor(...args: unknown[]) {
      nodeListCtor(...args);
    }
  },
}));

const getCurrentRunMock = vi.fn();
const abandonRunMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    getCurrentRun: (...args: unknown[]) => getCurrentRunMock(...args),
    abandonRun: (...args: unknown[]) => abandonRunMock(...args),
  },
}));

function makeSceneAdd() {
  return {
    existing: vi.fn(),
    text: vi.fn((x: number, y: number, message: string) => ({
      x,
      y,
      message,
      setOrigin: vi.fn(() => ({ x, y, message })),
      destroy: vi.fn(),
    })),
  };
}

describe("MapExplorationScene transition guards", () => {
  beforeEach(() => {
    nodeListCtor.mockReset();
    getCurrentRunMock.mockReset();
    abandonRunMock.mockReset();
  });

  it("does not construct NodeList when no active run exists", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: null, map: null } });
    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene.add.text).toHaveBeenCalledWith(
      expect.any(Number),
      expect.any(Number),
      "No active run. Start one from Regions.",
      expect.any(Object)
    );
  });

  it("constructs NodeList when current run payload is valid", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run: {
          run_id: "1",
          region_id: "1",
          seed: "123",
          status: "active",
          started_at: "2026-03-03T00:00:00Z",
          ended_at: null,
        },
        map: {
          nodes: [
            {
              id: "100",
              run_id: "1",
              node_index: 0,
              node_type: "combat",
              status: "available",
              meta_json: '{"col":0,"row":1}',
            },
          ],
          edges: [],
        },
      },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(1);
  });

  it("routes rest node clicks to RestManagementScene", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run: {
          run_id: "9",
          region_id: "1",
          seed: "rest-seed",
          status: "active",
          started_at: "2026-03-04T00:00:00Z",
          ended_at: null,
        },
        map: {
          nodes: [{ id: "501", run_id: "9", node_index: 1, node_type: "rest", status: "available", meta_json: '{"col":1,"row":1}' }],
          edges: [],
        },
      },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();
    scene.create();
    await Promise.resolve();

    const config = nodeListCtor.mock.calls[0]?.[6] as { onNodeClick?: (node: any) => Promise<void> };
    await config.onNodeClick?.({ id: "501", node_type: "rest", status: "available" });
    expect(scene.scene.start).toHaveBeenCalledWith("RestManagementScene", { runId: "9", nodeId: "501" });
  });

  it("shows fallback when current run request throws", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockRejectedValueOnce(new Error("contract drift"));

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene.add.text).toHaveBeenCalledWith(
      expect.any(Number),
      expect.any(Number),
      "Run data unavailable. Please retry.",
      expect.any(Object)
    );
  });

  it("shows fallback when API responds with error envelope", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: false,
      error: { code: "server_error", message: "Unexpected error." },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene.add.text).toHaveBeenCalledWith(
      expect.any(Number),
      expect.any(Number),
      "Run unavailable: Unexpected error.",
      expect.any(Object)
    );
  });

  it("routes exit node clicks to NodeResolutionScene", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run: {
          run_id: "9",
          region_id: "1",
          seed: "exit-seed",
          status: "active",
          started_at: "2026-03-04T00:00:00Z",
          ended_at: null,
        },
        map: {
          nodes: [{ id: "900", run_id: "9", node_index: 9, node_type: "exit", status: "available", meta_json: "{}" }],
          edges: [],
        },
      },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();
    scene.create();
    await Promise.resolve();

    const config = nodeListCtor.mock.calls[0]?.[6] as { onNodeClick?: (node: any) => Promise<void> };
    await config.onNodeClick?.({ id: "900", node_type: "exit", status: "available" });
    expect(scene.scene.start).toHaveBeenCalledWith("NodeResolutionScene", {
      runId: "9",
      nodeId: "900",
      nodeType: "exit",
    });
  });
});
