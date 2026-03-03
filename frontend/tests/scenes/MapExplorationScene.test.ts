import { describe, expect, it, vi } from "vitest";

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
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    getCurrentRun: (...args: unknown[]) => getCurrentRunMock(...args),
  },
}));

describe("MapExplorationScene transition guards", () => {
  it("does not construct NodeList when no active run exists", async () => {
    const { default: MapExplorationScene } = await import("../../src/scenes/MapExplorationScene");
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: null, map: null } });
    const scene = new MapExplorationScene() as any;
    scene.add = { existing: vi.fn() };

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(0);
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
    scene.add = { existing: vi.fn() };

    scene.create();
    await Promise.resolve();

    expect(nodeListCtor).toHaveBeenCalledTimes(1);
  });
});
