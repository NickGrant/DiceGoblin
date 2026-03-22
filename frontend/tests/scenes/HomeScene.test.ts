import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeRectangle: true })
  );
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/BottomCommandStrip", () => ({ mountBottomCommandStrip: vi.fn() }));
vi.mock("../../src/components/clickable-panel/SharedActionButton", () => ({
  default: class {
    constructor(_cfg: unknown) {}
  },
}));

const homePanelCtor = vi.fn();
vi.mock("../../src/components/navigation/HomeNavigationPanel", () => ({
  default: class {
    constructor(cfg: unknown) {
      homePanelCtor(cfg);
    }
  },
}));

const getProfileMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    getProfile: (...args: unknown[]) => getProfileMock(...args),
  },
}));

describe("HomeScene", () => {
  beforeEach(() => {
    homePanelCtor.mockReset();
    getProfileMock.mockReset();
  });

  it("forces fresh profile fetch before selecting run panel state", async () => {
    const { default: HomeScene } = await import("../../src/scenes/HomeScene");

    getProfileMock.mockResolvedValueOnce({
      ok: true,
      data: { active_run: null },
    });

    const scene = new HomeScene() as any;
    scene.textures = { exists: vi.fn().mockReturnValue(false) };

    scene.create();
    await Promise.resolve();

    expect(getProfileMock).toHaveBeenCalledWith({ force: true, allowStaleOnError: true });
    expect(homePanelCtor).toHaveBeenCalledTimes(1);
  });
});
