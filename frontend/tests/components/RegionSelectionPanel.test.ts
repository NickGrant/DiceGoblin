import { afterEach, describe, expect, it, vi } from "vitest";

let lastClickablePanel: {
  clickHandler?: () => void;
  alpha: number;
  setAlpha: (value: number) => unknown;
} | null = null;

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeContainer: true })
  );
});

vi.mock("../../src/components/clickable-panel/ClickablePanel", () => ({
  default: class {
    public clickHandler?: () => void;
    public alpha = 1;

    constructor(_scene: unknown, cfg: { clickHandler?: () => void }) {
      this.clickHandler = cfg.clickHandler;
      lastClickablePanel = this;
    }

    setAlpha(value: number): this {
      this.alpha = value;
      return this;
    }
  },
}));

function makeScene() {
  return {
    add: {
      existing: vi.fn(),
      rectangle: vi.fn(() => ({
        visible: true,
        setOrigin() {
          return this;
        },
        setStrokeStyle() {
          return this;
        },
        setVisible(value: boolean) {
          this.visible = value;
          return this;
        },
      })),
      text: vi.fn(() => ({
        visible: true,
        setOrigin() {
          return this;
        },
        setVisible(value: boolean) {
          this.visible = value;
          return this;
        },
      })),
    },
  } as any;
}

describe("RegionSelectionPanel", () => {
  afterEach(() => {
    lastClickablePanel = null;
    vi.restoreAllMocks();
  });

  it("routes locked selection to onLockedSelect without activating", async () => {
    const { default: RegionSelectionPanel } = await import("../../src/components/navigation/RegionSelectionPanel");

    const onSelect = vi.fn();
    const onActivate = vi.fn();
    const onLockedSelect = vi.fn();

    new RegionSelectionPanel({
      scene: makeScene(),
      x: 0,
      y: 0,
      width: 400,
      height: 240,
      regionId: "swamp",
      label: "Swamp",
      locked: true,
      onSelect,
      onActivate,
      onLockedSelect,
    });

    lastClickablePanel?.clickHandler?.();

    expect(onSelect).toHaveBeenCalledWith("swamp");
    expect(onLockedSelect).toHaveBeenCalledWith("swamp");
    expect(onActivate).not.toHaveBeenCalled();
  });

  it("requires a second click within threshold before activation", async () => {
    const { default: RegionSelectionPanel } = await import("../../src/components/navigation/RegionSelectionPanel");

    const onSelect = vi.fn();
    const onActivate = vi.fn();
    vi.spyOn(Date, "now")
      .mockReturnValueOnce(1000)
      .mockReturnValueOnce(1200);

    new RegionSelectionPanel({
      scene: makeScene(),
      x: 0,
      y: 0,
      width: 400,
      height: 240,
      regionId: "mountain",
      label: "Mountains",
      locked: false,
      onSelect,
      onActivate,
    });

    lastClickablePanel?.clickHandler?.();
    lastClickablePanel?.clickHandler?.();

    expect(onSelect).toHaveBeenCalledTimes(2);
    expect(onActivate).toHaveBeenCalledTimes(1);
    expect(onActivate).toHaveBeenCalledWith("mountain");
  });

  it("updates lock visibility and panel alpha when lock state changes", async () => {
    const { default: RegionSelectionPanel } = await import("../../src/components/navigation/RegionSelectionPanel");

    const panel = new RegionSelectionPanel({
      scene: makeScene(),
      x: 0,
      y: 0,
      width: 400,
      height: 240,
      regionId: "swamp",
      label: "Swamp",
      locked: false,
      onSelect: vi.fn(),
    }) as any;

    panel.setLocked(true);
    expect(lastClickablePanel?.alpha).toBe(0.78);
    expect(panel.lockOverlay.visible).toBe(true);
    expect(panel.lockText.visible).toBe(true);

    panel.setLocked(false);
    expect(lastClickablePanel?.alpha).toBe(1);
    expect(panel.lockOverlay.visible).toBe(false);
    expect(panel.lockText.visible).toBe(false);
  });
});
