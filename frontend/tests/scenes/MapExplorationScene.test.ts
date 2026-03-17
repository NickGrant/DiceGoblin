import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeRectangle: true })
  );
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/navigation/HomeCornerButton", () => ({ default: class {} }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));
vi.mock("../../src/components/layout/ContentAreaFrame", () => ({ default: class { setDepth() { return this; } } }));
vi.mock("../../src/components/clickable-panel/UnifiedButtonList", () => ({ default: class {} }));
vi.mock("../../src/components/feedback/ToastMessage", () => ({ default: class { destroy() {} } }));
let confirmModalConfig: any;
vi.mock("../../src/components/feedback/ConfirmModal", () => ({
  default: class {
    constructor(cfg: unknown) {
      confirmModalConfig = cfg;
    }
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

async function flushSceneTasks(ticks = 4): Promise<void> {
  for (let i = 0; i < ticks; i += 1) {
    await Promise.resolve();
  }
}

describe("MapExplorationScene transition guards", () => {
  beforeEach(() => {
    nodeListCtor.mockReset();
    getCurrentRunMock.mockReset();
    abandonRunMock.mockReset();
    confirmModalConfig = undefined;
  });

  it("does not construct NodeList when no active run exists", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: null, map: null } });
    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();

    scene.create();
    await flushSceneTasks();

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
    await flushSceneTasks();

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
    await flushSceneTasks();

    const config = nodeListCtor.mock.calls[0]?.[6] as { onNodeClick?: (node: any) => Promise<void> };
    await config.onNodeClick?.({ id: "501", node_type: "rest", status: "available" });
    expect(scene.scene.start).toHaveBeenCalledWith("RestManagementScene", { runId: "9", nodeId: "501" });
  });

  it("shows fallback when current run request throws", async () => {
    const cases: Array<{
      label: string;
      arrange: () => void;
      expectedMessage: string;
    }> = [
      {
        label: "request throws",
        arrange: () => getCurrentRunMock.mockRejectedValueOnce(new Error("contract drift")),
        expectedMessage: "Run data unavailable. Please retry.",
      },
      {
        label: "error envelope",
        arrange: () =>
          getCurrentRunMock.mockResolvedValueOnce({
            ok: false,
            error: { code: "server_error", message: "Unexpected error." },
          }),
        expectedMessage: "Run unavailable: Unexpected error.",
      },
    ];

    for (const testCase of cases) {
      nodeListCtor.mockReset();
      getCurrentRunMock.mockReset();
      testCase.arrange();

      const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
      const scene = new MapExplorationScene() as any;
      scene.add = makeSceneAdd();

      scene.create();
      await flushSceneTasks();

      expect(nodeListCtor, testCase.label).toHaveBeenCalledTimes(0);
      expect(scene.add.text, testCase.label).toHaveBeenCalledWith(
        expect.any(Number),
        expect.any(Number),
        testCase.expectedMessage,
        expect.any(Object)
      );
    }
  });

  it("shows fallback when map node payload contains unsupported node states/types", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run: {
          run_id: "12",
          region_id: "1",
          seed: "invalid-map-seed",
          status: "active",
          started_at: "2026-03-04T00:00:00Z",
          ended_at: null,
        },
        map: {
          nodes: [{ id: "x1", run_id: "12", node_index: 0, node_type: "unknown", status: "broken", meta_json: "{}" }],
          edges: [],
        },
      },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();

    scene.create();
    await flushSceneTasks();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene.add.text).toHaveBeenCalledWith(
      expect.any(Number),
      expect.any(Number),
      "Run map payload is invalid. Please refresh or restart run.",
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
    await flushSceneTasks();

    const config = nodeListCtor.mock.calls[0]?.[6] as { onNodeClick?: (node: any) => Promise<void> };
    await config.onNodeClick?.({ id: "900", node_type: "exit", status: "available" });
    expect(scene.scene.start).toHaveBeenCalledWith("NodeResolutionScene", {
      runId: "9",
      nodeId: "900",
      nodeType: "exit",
    });
  });

  it("does not route locked node clicks and shows explanatory fallback", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run: {
          run_id: "9",
          region_id: "1",
          seed: "locked-seed",
          status: "active",
          started_at: "2026-03-04T00:00:00Z",
          ended_at: null,
        },
        map: {
          nodes: [{ id: "x9", run_id: "9", node_index: 4, node_type: "combat", status: "locked", meta_json: "{}" }],
          edges: [],
        },
      },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = makeSceneAdd();
    scene.create();
    await flushSceneTasks();

    const config = nodeListCtor.mock.calls[0]?.[6] as { onNodeClick?: (node: any) => Promise<void> };
    await config.onNodeClick?.({ id: "x9", node_type: "combat", status: "locked" });

    expect(scene.scene.start).not.toHaveBeenCalledWith("NodeResolutionScene", expect.anything());
    expect(scene.add.text).toHaveBeenCalledWith(
      expect.any(Number),
      expect.any(Number),
      "Node 'x9' is locked and cannot be selected.",
      expect.any(Object)
    );
  });

  it("uses abandon and stay labels in abandon confirmation", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    const scene = new MapExplorationScene() as any;

    scene.runEnvelope = {
      ok: true,
      data: {
        run: {
          run_id: "9",
          region_id: "1",
          seed: "s",
          status: "active",
          started_at: "2026-03-04T00:00:00Z",
          ended_at: null,
        },
      },
    };

    await scene.confirmAbandonRun();

    expect(confirmModalConfig).toMatchObject({
      title: "ABANDON RUN?",
      width: 640,
      height: 320,
      acceptLabel: "Abandon",
      rejectLabel: "Stay",
    });
  });
});
