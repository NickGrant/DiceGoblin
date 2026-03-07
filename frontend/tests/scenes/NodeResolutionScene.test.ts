import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  class FakeScene {
    cameras = { main: { centerX: 480, centerY: 270 } };
    scale = { width: 960, height: 640 };
    scene = { start: vi.fn() };
  }
  return {
    default: { Scene: FakeScene },
    Scene: FakeScene,
  };
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/navigation/HomeCornerButton", () => ({ default: class {} }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));
vi.mock("../../src/components/layout/ContentAreaFrame", () => ({ default: class { setDepth() { return this; } } }));

class MockActionButton {
  static instances: MockActionButton[] = [];
  public label = "";
  public enabled = false;
  private onClick?: () => void;

  constructor(cfg: { label: string; enabled?: boolean; onClick?: () => void }) {
    this.label = cfg.label;
    this.enabled = cfg.enabled ?? true;
    this.onClick = cfg.onClick;
    MockActionButton.instances.push(this);
  }

  setText(label: string): this {
    this.label = label;
    return this;
  }

  setEnabled(enabled: boolean): this {
    this.enabled = enabled;
    return this;
  }

  trigger(): void {
    this.onClick?.();
  }
}

vi.mock("../../src/components/clickable-panel/ActionButton", () => ({
  default: MockActionButton,
}));

const resolveRunNodeMock = vi.fn();
const getCurrentRunMock = vi.fn();
const exitRunMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    resolveRunNode: (...args: unknown[]) => resolveRunNodeMock(...args),
    getCurrentRun: (...args: unknown[]) => getCurrentRunMock(...args),
    exitRun: (...args: unknown[]) => exitRunMock(...args),
  },
}));

function makeSceneAdd() {
  return {
    existing: vi.fn(),
    text: vi.fn((_x: number, _y: number, _message: string) => ({
      setOrigin: vi.fn(() => ({
        setText: vi.fn(),
      })),
      setText: vi.fn(),
      destroy: vi.fn(),
    })),
  };
}

describe("NodeResolutionScene", () => {
  beforeEach(() => {
    resolveRunNodeMock.mockReset();
    getCurrentRunMock.mockReset();
    exitRunMock.mockReset();
    MockActionButton.instances = [];
  });

  it("routes missing context back to map", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({});

    scene.create();
    await Promise.resolve();

    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Back to Map");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("MapExplorationScene");
  });

  it("routes resolved non-terminal nodes back to map with payload", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    resolveRunNodeMock.mockResolvedValueOnce({
      ok: true,
      data: {
        battle: { battle_id: "b-1", outcome: "victory", rounds: 3, ticks: 12 },
        next: { unlocked_node_ids: ["n2"] },
      },
    });
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: { run_id: "run-1" } } });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-1", nodeId: "n1", nodeType: "combat" });

    scene.create();
    await Promise.resolve();
    await Promise.resolve();

    expect(resolveRunNodeMock).toHaveBeenCalledWith("run-1", "n1");
    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Back to Map");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("MapExplorationScene", expect.objectContaining({
      resolutionMessage: expect.stringContaining("Node n1 resolved (victory)."),
      resolutionColor: "#ccffcc",
    }));
  });

  it("routes resolved terminal nodes to run summary", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    resolveRunNodeMock.mockResolvedValueOnce({
      ok: true,
      data: {
        battle: { battle_id: "b-2", outcome: "defeat", rounds: 2, ticks: 8 },
        next: { unlocked_node_ids: [] },
      },
    });
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: null } });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-2", nodeId: "n9", nodeType: "boss" });

    scene.create();
    await Promise.resolve();
    await Promise.resolve();

    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Continue");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("RunEndSummaryScene", expect.objectContaining({
      status: "failed",
    }));
  });

  it("handles exit resolution and routes to run summary", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    exitRunMock.mockResolvedValueOnce({
      ok: true,
      data: { run_id: "run-3", status: "completed" },
    });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-3", nodeId: "exit-1", nodeType: "exit" });

    scene.create();
    await Promise.resolve();

    expect(exitRunMock).toHaveBeenCalledWith("run-3");
    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Continue");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("RunEndSummaryScene", expect.objectContaining({
      status: "completed",
    }));
  });
});
