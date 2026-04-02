import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeRectangle: true })
  );
});

vi.mock("../../src/components/BackgroundImage", () => ({ default: class {} }));
vi.mock("../../src/components/BottomCommandStrip", () => ({ mountBottomCommandStrip: vi.fn() }));

const getProfileMock = vi.fn();
vi.mock("../../src/services/apiClient", () => ({
  apiClient: {
    getProfile: (...args: unknown[]) => getProfileMock(...args),
  },
}));

describe("HomeScene", () => {
  beforeEach(() => {
    getProfileMock.mockReset();
  });

  it("forces fresh profile fetch before selecting run panel state", async () => {
    const { default: HomeScene } = await import("../../src/scenes/HomeScene");

    getProfileMock.mockResolvedValueOnce({
      ok: true,
      data: { active_run: null },
    });

    const scene = new HomeScene() as any;
    scene.add = {
      rectangle: vi.fn(() => ({
        setOrigin: vi.fn().mockReturnThis(),
        setAlpha: vi.fn().mockReturnThis(),
      })),
      text: vi.fn(() => ({
        setOrigin: vi.fn().mockReturnThis(),
        setVisible: vi.fn().mockReturnThis(),
      })),
      zone: vi.fn(() => ({
        setOrigin: vi.fn().mockReturnThis(),
        setInteractive: vi.fn().mockReturnThis(),
        on: vi.fn().mockReturnThis(),
      })),
    };
    scene.scene = { start: vi.fn() };

    scene.create();
    await Promise.resolve();

    expect(getProfileMock).toHaveBeenCalledWith({ force: true, allowStaleOnError: true });
    expect(scene.add.rectangle).toHaveBeenCalled();
    expect(scene.add.zone).toHaveBeenCalledTimes(5);
  });
});
