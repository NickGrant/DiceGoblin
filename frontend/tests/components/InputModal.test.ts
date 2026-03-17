import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  return import("../utils/phaserSceneFixtures").then(({ buildPhaserMock }) =>
    buildPhaserMock({ includeContainer: true })
  );
});

vi.mock("../../src/components/clickable-panel/SharedActionButton", () => ({
  default: class {
    constructor(_cfg: unknown) {}
  },
}));

function makeScene() {
  const keyboardOn = vi.fn();
  const keyboardOff = vi.fn();

  return {
    scale: { width: 960, height: 640 },
    add: {
      existing: vi.fn(),
      rectangle: vi.fn(() => ({
        setOrigin() {
          return this;
        },
        setStrokeStyle() {
          return this;
        },
        setInteractive() {
          return this;
        },
      })),
      text: vi.fn((_x: number, _y: number, text: string) => ({
        text,
        visible: true,
        setOrigin() {
          return this;
        },
        setText(next: string) {
          this.text = next;
          return this;
        },
        setVisible(next: boolean) {
          this.visible = next;
          return this;
        },
      })),
    },
    input: {
      keyboard: {
        on: keyboardOn,
        off: keyboardOff,
      },
    },
  } as any;
}

describe("InputModal", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("attaches and detaches keyboard listeners", async () => {
    const { default: InputModal } = await import("../../src/components/feedback/InputModal");
    const scene = makeScene();

    const modal = new InputModal({
      scene,
      title: "Create",
      message: "Enter a name",
      onAccept: vi.fn(),
      onReject: vi.fn(),
      input: {
        initialValue: "New Squad",
      },
    });

    expect(scene.input.keyboard.on).toHaveBeenCalledWith("keydown", expect.any(Function));

    const handler = scene.input.keyboard.on.mock.calls[0][1];
    modal.close();

    expect(scene.input.keyboard.off).toHaveBeenCalledWith("keydown", handler);
  });

  it("filters input characters and submits value on Enter", async () => {
    const { default: InputModal } = await import("../../src/components/feedback/InputModal");
    const scene = makeScene();
    const onAcceptInput = vi.fn();
    const onAccept = vi.fn();

    const modal = new InputModal({
      scene,
      title: "Create",
      message: "Enter a name",
      onAccept,
      onReject: vi.fn(),
      onAcceptInput,
      input: {
        initialValue: "A",
        maxLength: 3,
        allowedCharacterPattern: /[A-Z]/,
      },
    });
    (modal as any).active = true;

    const keydown = scene.input.keyboard.on.mock.calls[0][1] as (event: any) => void;
    keydown({ key: "B", preventDefault: vi.fn() });
    keydown({ key: "!", preventDefault: vi.fn() });
    keydown({ key: "C", preventDefault: vi.fn() });
    keydown({ key: "D", preventDefault: vi.fn() });
    keydown({ key: "Enter", preventDefault: vi.fn() });
    await Promise.resolve();
    await Promise.resolve();

    expect(onAcceptInput).toHaveBeenCalledWith("ABC");
    expect(onAccept).toHaveBeenCalledTimes(1);
  });

  it("cancels on Escape and closes modal", async () => {
    const { default: InputModal } = await import("../../src/components/feedback/InputModal");
    const scene = makeScene();
    const onReject = vi.fn();

    const modal = new InputModal({
      scene,
      title: "Rename",
      message: "Enter value",
      onAccept: vi.fn(),
      onReject,
      input: {
        initialValue: "X",
      },
    });
    (modal as any).active = true;

    const keydown = scene.input.keyboard.on.mock.calls[0][1] as (event: any) => void;
    keydown({ key: "Escape", preventDefault: vi.fn() });

    expect(onReject).toHaveBeenCalledTimes(1);
    expect(scene.input.keyboard.off).toHaveBeenCalledWith("keydown", keydown);
    modal.close();
  });
});
