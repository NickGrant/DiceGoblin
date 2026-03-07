import { describe, expect, it } from "vitest";
import { computePagination, deriveListState } from "./listState";

describe("listState", () => {
  it("computes pagination bounds and window", () => {
    const page = computePagination(23, { pageIndex: 10, pageSize: 5 });
    expect(page.totalPages).toBe(5);
    expect(page.pageIndex).toBe(4);
    expect(page.start).toBe(20);
    expect(page.end).toBe(23);
    expect(page.canPrev).toBe(true);
    expect(page.canNext).toBe(false);
  });

  it("derives list lifecycle state", () => {
    expect(deriveListState("loading", 3)).toBe("loading");
    expect(deriveListState("error", 3)).toBe("error");
    expect(deriveListState("ready", 0)).toBe("empty");
    expect(deriveListState("ready", 1)).toBe("ready");
  });
});
