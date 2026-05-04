import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) => buildPhaserMock());
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/navigation/HomeCornerButton", () => ({ default: class {} }));
vi.mock("../../src/components/HudPanel", () => ({ default: class {} }));
vi.mock("../../src/components/layout/ContentAreaFrame", () => ({ default: class { setDepth() { return this; } } }));
vi.mock("../../src/components/FormationGrid3x3", () => ({
  default: class {
    constructor(_cfg: unknown) {}
    destroy() {}
  },
}));

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

vi.mock("../../src/components/clickable-panel/SharedActionButton", () => ({
  default: MockActionButton,
}));

const resolveRunNodeMock = vi.fn();
const getCurrentRunMock = vi.fn();
const exitRunMock = vi.fn();
const claimBattleRewardsMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    resolveRunNode: (...args: unknown[]) => resolveRunNodeMock(...args),
    getCurrentRun: (...args: unknown[]) => getCurrentRunMock(...args),
    exitRun: (...args: unknown[]) => exitRunMock(...args),
    claimBattleRewards: (...args: unknown[]) => claimBattleRewardsMock(...args),
  },
}));

function makeSceneAdd() {
  return {
    existing: vi.fn(),
    rectangle: vi.fn(() => {
      const rectObj = {
        setOrigin: vi.fn(() => rectObj),
        setStrokeStyle: vi.fn(() => rectObj),
        destroy: vi.fn(),
      };
      return rectObj;
    }),
    graphics: vi.fn(() => {
      const graphicsObj = {
        fillStyle: vi.fn(() => graphicsObj),
        fillRect: vi.fn(() => graphicsObj),
        createGeometryMask: vi.fn(() => ({})),
        destroy: vi.fn(),
        visible: true,
      };
      return graphicsObj;
    }),
    text: vi.fn((_x: number, _y: number, _message: string) => {
      const textObj = {
        setOrigin: vi.fn(() => textObj),
        setText: vi.fn(() => textObj),
        setMask: vi.fn(() => textObj),
        setY: vi.fn(() => textObj),
        getBounds: vi.fn(() => ({ height: 120 })),
        destroy: vi.fn(),
      };
      return textObj;
    }),
  };
}

function expectObjectPayloadKeys(value: unknown, keys: string[]): void {
  expect(value).toBeTypeOf("object");
  expect(value).not.toBeNull();
  const payload = value as Record<string, unknown>;
  for (const key of keys) {
    expect(payload).toHaveProperty(key);
  }
}

async function flushSceneTasks(ticks = 5): Promise<void> {
  for (let i = 0; i < ticks; i += 1) {
    await Promise.resolve();
  }
}

async function waitForActionLabel(label: string, maxAttempts = 60): Promise<void> {
  for (let i = 0; i < maxAttempts; i += 1) {
    if (MockActionButton.instances[0]?.label === label) {
      return;
    }
    await Promise.resolve();
  }
}

describe("NodeResolutionScene", () => {
  beforeEach(() => {
    resolveRunNodeMock.mockReset();
    getCurrentRunMock.mockReset();
    exitRunMock.mockReset();
    claimBattleRewardsMock.mockReset();
    claimBattleRewardsMock.mockResolvedValue({ ok: true, data: { battle_id: "b-claim", status: "claimed", rewards: {} } });
    MockActionButton.instances = [];
  });

  it("routes missing context back to map", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({});

    scene.create();
    await flushSceneTasks();

    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Back to Map");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("MapExplorationScene");
  }, 10000);

  it("routes resolved non-terminal nodes back to map with payload", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    resolveRunNodeMock.mockResolvedValueOnce({
      ok: true,
      data: {
        battle: { battle_id: "b-1", outcome: "victory", rounds: 3, ticks: 12, status: "complete", log: null },
        next: { unlocked_node_ids: ["n2"] },
      },
    });
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: { run_id: "run-1" } } });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-1", nodeId: "n1", nodeType: "combat" });

    scene.create();
    await flushSceneTasks();
    await waitForActionLabel("Back to Map");

    expect(resolveRunNodeMock).toHaveBeenCalledWith("run-1", "n1");
    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Back to Map");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("MapExplorationScene", expect.any(Object));
    const routePayload = scene.scene.start.mock.calls.at(-1)?.[1];
    expectObjectPayloadKeys(routePayload, ["resolutionMessage", "resolutionColor"]);
    expect((routePayload as Record<string, unknown>).resolutionMessage).toEqual(expect.stringContaining("Node n1 resolved (victory)."));
    expect((routePayload as Record<string, unknown>).resolutionColor).toBe("#ccffcc");
  });

  it("routes resolved terminal nodes to run summary", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    claimBattleRewardsMock.mockResolvedValueOnce({
      ok: true,
      data: {
        battle_id: "b-claim",
        status: "claimed",
        rewards: {},
        run_summary: {
          rewards: ["Teeth +22", "New Dice: bone d6"],
          progression: ["Knuts +20 XP"],
          survivors: ["Knuts"],
          defeated: [],
        },
      },
    });
    resolveRunNodeMock.mockResolvedValueOnce({
      ok: true,
      data: {
        battle: { battle_id: "b-2", outcome: "defeat", rounds: 2, ticks: 8, status: "complete", log: null },
        next: { unlocked_node_ids: [] },
      },
    });
    getCurrentRunMock.mockResolvedValueOnce({ ok: true, data: { run: null } });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-2", nodeId: "n9", nodeType: "boss" });

    scene.create();
    await flushSceneTasks();
    await waitForActionLabel("Continue");

    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Continue");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("RunEndSummaryScene", expect.any(Object));
    const summaryPayload = scene.scene.start.mock.calls.at(-1)?.[1];
    expectObjectPayloadKeys(summaryPayload, ["status", "rewards", "progression", "survivors", "defeated"]);
    expect((summaryPayload as Record<string, unknown>).status).toBe("failed");
    expect((summaryPayload as Record<string, unknown>).rewards).toEqual(["Teeth +22", "New Dice: bone d6"]);
  });

  it("handles exit resolution and routes to run summary", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    exitRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run_id: "run-3",
        status: "completed",
        run_summary: {
          rewards: ["Teeth +31"],
          progression: ["Knuts +30 XP"],
          survivors: ["Knuts", "Mallow"],
          defeated: [],
        },
      },
    });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-3", nodeId: "exit-1", nodeType: "exit" });

    scene.create();
    await flushSceneTasks();

    expect(exitRunMock).toHaveBeenCalledWith("run-3");
    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Continue");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("RunEndSummaryScene", expect.any(Object));
    const summaryPayload = scene.scene.start.mock.calls.at(-1)?.[1];
    expectObjectPayloadKeys(summaryPayload, ["status", "rewards", "progression", "survivors", "defeated"]);
    expect((summaryPayload as Record<string, unknown>).status).toBe("completed");
    expect((summaryPayload as Record<string, unknown>).progression).toEqual(["Knuts +30 XP"]);
  });

  it("marks no-enemies resolution and routes back to map with reason", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    resolveRunNodeMock.mockResolvedValueOnce({
      ok: false,
      error: { message: "combat_no_enemies" },
    });
    getCurrentRunMock.mockResolvedValueOnce({
      ok: true,
      data: {
        run: { run_id: "run-4" },
        map: {
          nodes: [{ id: "n10", status: "cleared" }],
        },
      },
    });

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-4", nodeId: "n10", nodeType: "combat" });

    scene.create();
    await flushSceneTasks();

    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Back to Map");
    action?.trigger();
    expect(scene.scene.start).toHaveBeenCalledWith("MapExplorationScene", {
      resolutionMessage: "Node n10 resolved: combat_no_enemies",
      resolutionColor: "#ffd89e",
    });
  });

  it("recovers from resolve timeout without leaving the action button stuck", async () => {
    const { default: NodeResolutionScene } = await import("../../src/scenes/NodeResolutionScene");
    resolveRunNodeMock.mockImplementationOnce(() => new Promise(() => {}));

    const scene = new NodeResolutionScene() as any;
    scene.add = makeSceneAdd();
    scene.init({ runId: "run-5", nodeId: "n11", nodeType: "combat" });
    scene.resolveTimeoutMs = 5;

    scene.create();
    await new Promise((resolve) => setTimeout(resolve, 25));

    const action = MockActionButton.instances[0];
    expect(action?.label).toBe("Retry Resolve");
    expect(action?.enabled).toBe(true);
  });
});
