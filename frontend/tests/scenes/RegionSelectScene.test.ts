import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeContainer: true, includeRectangle: true })
  );
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/BottomCommandStrip", () => ({ mountBottomCommandStrip: vi.fn() }));
vi.mock("../../src/components/layout/ContentAreaFrame", () => ({ default: class { setDepth() { return this; } } }));
vi.mock("../../src/debug/debugHooks", () => ({ markDebugSceneReady: vi.fn() }));
vi.mock("../../src/layout/pageLayout", () => ({
  getPageLayout: () => ({
    content: { x: 20, y: 20, width: 640, height: 420 },
    buttons: { x: 700, y: 20, width: 240, height: 420 },
    bottomStrip: { x: 0, y: 560, width: 960, height: 80 },
  }),
}));

const buttonSetText = vi.fn();
const buttonSetEnabled = vi.fn();
vi.mock("../../src/components/clickable-panel/SharedActionButton", () => ({
  default: class {
    constructor(_cfg: unknown) {}
    setText(value: string) {
      buttonSetText(value);
      return this;
    }
    setEnabled(value: boolean) {
      buttonSetEnabled(value);
      return this;
    }
  },
}));

const panelSetLocked = vi.fn();
const panelSetSelected = vi.fn();
const panelSetStartable = vi.fn();
vi.mock("../../src/components/navigation/RegionSelectionPanel", () => ({
  default: class {
    constructor(_cfg: unknown) {}
    setLocked(value: boolean) {
      panelSetLocked(value);
      return this;
    }
    setSelected(value: boolean) {
      panelSetSelected(value);
      return this;
    }
    setStartable(value: boolean, label?: string) {
      panelSetStartable(value, label);
      return this;
    }
  },
}));

const getProfileMock = vi.fn();
const createRunMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    getProfile: (...args: unknown[]) => getProfileMock(...args),
    createRun: (...args: unknown[]) => createRunMock(...args),
  },
}));

function buildTextObject() {
  return {
    setOrigin: vi.fn().mockReturnThis(),
    setText: vi.fn().mockReturnThis(),
  };
}

function buildRectangleObject() {
  return {
    setOrigin: vi.fn().mockReturnThis(),
    setStrokeStyle: vi.fn().mockReturnThis(),
    setVisible: vi.fn().mockReturnThis(),
  };
}

describe("RegionSelectScene", () => {
  beforeEach(() => {
    buttonSetText.mockReset();
    buttonSetEnabled.mockReset();
    panelSetLocked.mockReset();
    panelSetSelected.mockReset();
    panelSetStartable.mockReset();
    getProfileMock.mockReset();
    createRunMock.mockReset();
  });

  it("disables run start when the selected region costs more energy than the player has", async () => {
    const { default: RegionSelectScene } = await import("../../src/scenes/RegionSelectScene");

    getProfileMock.mockResolvedValueOnce({
      ok: true,
      data: {
        energy: { current: 2, max: 50 },
        region_unlocks: [{ region_id: 1, unlocked: true }],
      },
    });

    const scene = new RegionSelectScene() as any;
    scene.add = {
      text: vi.fn(() => buildTextObject()),
      rectangle: vi.fn(() => buildRectangleObject()),
    };
    scene.scale = { width: 960, height: 640 };
    scene.scene = { start: vi.fn() };

    scene.create();
    await Promise.resolve();

    expect(getProfileMock).toHaveBeenCalledWith({ force: true, allowStaleOnError: true });
    expect(buttonSetText).toHaveBeenLastCalledWith("Need 5 Energy");
    expect(buttonSetEnabled).toHaveBeenLastCalledWith(false);
    expect(panelSetStartable).toHaveBeenCalledWith(false, "Need 5 Energy");

    await scene.startRun("mountain");
    expect(createRunMock).not.toHaveBeenCalled();
  });
});
