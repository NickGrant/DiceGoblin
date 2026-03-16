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

export function formatBattleLogSummary(
  log: {
    meta?: Record<string, unknown>;
    events?: Array<Record<string, unknown>>;
    [key: string]: unknown;
  } | null
): string[] {
  if (!log) {
    return ["Battle Log: unavailable."];
  }

  const lines: string[] = [];
  const meta = typeof log.meta === "object" && log.meta !== null ? log.meta : {};
  const events = Array.isArray(log.events) ? log.events : [];
  lines.push("Battle Log:");

  const engine = typeof meta.engine === "string" ? meta.engine : null;
  if (engine) {
    lines.push(`Engine: ${engine}`);
  }

  const seedValue = (meta.rng as Record<string, unknown> | undefined)?.seed;
  if (typeof seedValue === "number" || typeof seedValue === "string") {
    lines.push(`Seed: ${seedValue}`);
  }

  lines.push(`Events: ${events.length}`);
  if (events.length === 0) {
    lines.push("(no battle events recorded)");
    return lines;
  }

  for (const event of events.slice(0, 8)) {
    const type = typeof event.type === "string" ? event.type : "event";
    lines.push(`- ${type}`);
  }
  if (events.length > 8) {
    lines.push(`...and ${events.length - 8} more.`);
  }

  return lines;
}


