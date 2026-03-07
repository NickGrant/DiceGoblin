import { describe, expect, it } from "vitest";
import { resolveContentFrameBodyRect } from "./contentAreaMath";

describe("ContentAreaFrame", () => {
  it("uses edge-to-edge body dimensions when image mode is enabled", () => {
    const rect = resolveContentFrameBodyRect({
      width: 600,
      height: 400,
      titleHeight: 56,
      marginPx: 12,
      bodyImageKey: "ux_start_run",
      useImageEdgeToEdge: true,
    });
    expect(rect).toEqual({
      x: 0,
      y: 56,
      width: 600,
      height: 344,
    });
  });

  it("uses margin spacing when body image is not used", () => {
    const rect = resolveContentFrameBodyRect({
      width: 600,
      height: 400,
      titleHeight: 56,
      marginPx: 12,
    });
    expect(rect).toEqual({
      x: 12,
      y: 68,
      width: 576,
      height: 320,
    });
  });
});
