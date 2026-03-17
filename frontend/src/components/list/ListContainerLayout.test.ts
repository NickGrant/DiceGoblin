import { describe, expect, it } from "vitest";
import { resolveListContentLayout } from "./listContainerLayout";

describe("resolveListContentLayout", () => {
  it("reserves title lane while keeping pagination lane stable", () => {
    const withTitle = resolveListContentLayout({
      width: 320,
      height: 260,
      hasTitle: true,
    });
    const withoutTitle = resolveListContentLayout({
      width: 320,
      height: 260,
      hasTitle: false,
    });

    expect(withTitle.contentX).toBe(12);
    expect(withTitle.contentY).toBe(44);
    expect(withTitle.contentWidth).toBe(296);
    expect(withTitle.contentHeight).toBe(174);

    expect(withoutTitle.contentY).toBe(12);
    expect(withoutTitle.contentHeight).toBe(206);

    // Bottom edge of content remains stable so pager lane does not overlap content.
    expect(withTitle.contentY + withTitle.contentHeight).toBe(withoutTitle.contentY + withoutTitle.contentHeight);
  });

  it("clamps negative dimensions to zero", () => {
    const layout = resolveListContentLayout({
      width: 8,
      height: 24,
      hasTitle: true,
    });

    expect(layout.contentWidth).toBe(0);
    expect(layout.contentHeight).toBe(0);
  });
});
