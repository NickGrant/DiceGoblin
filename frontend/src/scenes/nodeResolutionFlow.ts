export const NODE_RESOLUTION_TYPES = ["combat", "loot", "boss", "exit"] as const;

export type NodeResolutionType = (typeof NODE_RESOLUTION_TYPES)[number];

export function isNodeResolutionType(value: string): value is NodeResolutionType {
  return (NODE_RESOLUTION_TYPES as readonly string[]).includes(value);
}

export function deriveSummaryStatus(params: {
  nodeType: NodeResolutionType;
  outcome?: string;
  exitStatus?: string;
}): "completed" | "failed" | "abandoned" {
  const { nodeType, outcome, exitStatus } = params;

  if (nodeType === "exit") {
    if (exitStatus === "abandoned") return "abandoned";
    if (exitStatus === "failed") return "failed";
    return "completed";
  }

  if (outcome === "defeat") return "failed";
  return "completed";
}

export function formatUnlockedNodes(unlockedNodeIds: string[]): string {
  if (unlockedNodeIds.length === 0) return "No new nodes unlocked.";
  return `Unlocked nodes: ${unlockedNodeIds.join(", ")}.`;
}

