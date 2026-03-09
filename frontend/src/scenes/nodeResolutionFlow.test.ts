import { describe, expect, it } from "vitest";
import {
  deriveSummaryStatus,
  formatUnlockedNodes,
  isNodeResolutionType,
} from "./nodeResolutionFlow";

describe("nodeResolutionFlow", () => {
  it("validates supported node-resolution types", () => {
    expect(isNodeResolutionType("combat")).toBe(true);
    expect(isNodeResolutionType("loot")).toBe(true);
    expect(isNodeResolutionType("boss")).toBe(true);
    expect(isNodeResolutionType("exit")).toBe(true);
    expect(isNodeResolutionType("rest")).toBe(false);
  });

  it("derives summary status for combat outcomes", () => {
    expect(deriveSummaryStatus({ nodeType: "combat", outcome: "victory" })).toBe("completed");
    expect(deriveSummaryStatus({ nodeType: "boss", outcome: "defeat" })).toBe("failed");
  });

  it("derives summary status for exit outcomes", () => {
    expect(deriveSummaryStatus({ nodeType: "exit", exitStatus: "abandoned" })).toBe("abandoned");
    expect(deriveSummaryStatus({ nodeType: "exit", exitStatus: "failed" })).toBe("failed");
    expect(deriveSummaryStatus({ nodeType: "exit", exitStatus: "completed" })).toBe("completed");
  });

  it("formats unlocked node summaries", () => {
    expect(formatUnlockedNodes([])).toBe("No new nodes unlocked.");
    expect(formatUnlockedNodes(["n2", "n3"])).toBe("Unlocked nodes: n2, n3.");
  });
});


