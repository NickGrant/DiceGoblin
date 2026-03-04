import { beforeEach, describe, expect, it, vi } from "vitest";

class FakeScene {
  cameras = { main: { centerX: 480, centerY: 270 } };
  scale = { width: 960, height: 640 };
  scene = { start: vi.fn() };
}

(globalThis as any).Phaser = { Scene: FakeScene };

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/HomeButton", () => ({ default: class { setScale() { return this; } } }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));

const nodeListCtor = vi.fn();
vi.mock("../../src/components/encounter-map/NodeList", () => ({
  default: class {
    constructor(...args: unknown[]) {
      nodeListCtor(...args);
    }
  },
}));

const getCurrentRunMock = vi.fn();
const exitRunMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    getCurrentRun: (...args: unknown[]) => getCurrentRunMock(...args),
    exitRun: (...args: unknown[]) => exitRunMock(...args),
  },
}));

describe("MapExplorationScene transition guards", () => {
  beforeEach(() => {
    nodeListCtor.mockReset();
    getCurrentRunMock.mockReset();
    exitRunMock.mockReset();
  });

  it("does not construct NodeList when no active run exists", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: null, map: null } });
    const scene = new MapExplorationScene() as any;
    scene.add = { existing: vi.fn(), text: vi.fn(() => ({ setOrigin: vi.fn() })) };

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene._fallbackMessage).toBe("No active run. Start one from Regions.");
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
    scene.add = { existing: vi.fn(), text: vi.fn(() => ({ setOrigin: vi.fn() })) };

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(1);
    expect(scene._fallbackMessage).toBeNull();
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
          nodes: [
            { id: "501", run_id: "9", node_index: 1, node_type: "rest", status: "available", meta_json: '{"col":1,"row":1}' },
          ],
          edges: [],
        },
      },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = { existing: vi.fn(), text: vi.fn(() => ({ setOrigin: vi.fn() })) };
    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(1);
    const config = nodeListCtor.mock.calls[0]?.[5] as { onNodeClick?: (node: any) => void };
    expect(typeof config.onNodeClick).toBe("function");

    config.onNodeClick?.({ id: "501", node_type: "rest", status: "available" });
    expect(scene.scene.start).toHaveBeenCalledWith("RestManagementScene", { runId: "9", nodeId: "501" });
  });

  it("shows fallback when current run request throws", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockRejectedValueOnce(new Error("contract drift"));

    const scene = new MapExplorationScene() as any;
    scene.add = { existing: vi.fn(), text: vi.fn(() => ({ setOrigin: vi.fn() })) };

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene._fallbackMessage).toBe("Run data unavailable. Please retry.");
  });

  it("shows fallback when API responds with error envelope", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({
      ok: false,
      error: { code: "server_error", message: "Unexpected error." },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = { existing: vi.fn(), text: vi.fn(() => ({ setOrigin: vi.fn() })) };

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
    expect(scene._fallbackMessage).toBe("Run unavailable: Unexpected error.");
  });

  it("routes exit node clicks to RunEndSummaryScene after exit call", async () => {
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
    exitRunMock.mockResolvedValueOnce({
      ok: true,
      data: { run_id: "9", status: "completed", exit_node_id: "900" },
    });

    const scene = new MapExplorationScene() as any;
    scene.add = { existing: vi.fn(), text: vi.fn(() => ({ setOrigin: vi.fn() })) };
    scene.create();
    await Promise.resolve();

    const config = nodeListCtor.mock.calls[0]?.[5] as { onNodeClick?: (node: any) => void };
    config.onNodeClick?.({ id: "900", node_type: "exit", status: "available" });
    await Promise.resolve();

    expect(exitRunMock).toHaveBeenCalledWith("9");
    expect(scene.scene.start).toHaveBeenCalledWith("RunEndSummaryScene", expect.objectContaining({ status: "completed" }));
  });
});
