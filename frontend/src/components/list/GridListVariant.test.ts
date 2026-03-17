import { describe, expect, it } from "vitest";
import { computeGridVisibleCapacity } from "./gridListMath";

describe("computeGridVisibleCapacity", () => {
  it("returns rows*columns based on height and card size", () => {
    const capacity = computeGridVisibleCapacity({
      height: 260,
      columns: 3,
      gapY: 8,
      cardHeight: 80,
    });

    expect(capacity).toBe(9);
  });

  it("guarantees at least one row even in tight layouts", () => {
    const capacity = computeGridVisibleCapacity({
      height: 10,
      columns: 3,
      gapY: 8,
      cardHeight: 80,
    });

    expect(capacity).toBe(3);
  });
});
