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

  for (const event of events) {
    lines.push(`- ${formatBattleEventLine(event)}`);
  }

  return lines;
}

function formatBattleEventLine(event: Record<string, unknown>): string {
  const type = typeof event.type === "string" ? event.type : "event";
  const round = typeof event.round === "number" ? event.round : null;
  const tick = typeof event.tick === "number" ? event.tick : null;
  const prefix = `${type}${round !== null ? ` r${round}` : ""}${tick !== null ? ` t${tick}` : ""}`;

  if (type === "action") {
    const side = typeof event.side === "string" ? event.side : "unknown";
    const actor = typeof event.actor_unit_instance_id === "string"
      ? `unit ${event.actor_unit_instance_id}`
      : typeof event.actor_enemy_slug === "string"
        ? `enemy ${event.actor_enemy_slug}`
        : "unknown actor";
    const target = typeof event.target_unit_instance_id === "string"
      ? `unit ${event.target_unit_instance_id}`
      : typeof event.target_enemy_slug === "string"
        ? `enemy ${event.target_enemy_slug}`
        : "unknown target";
    const ability = typeof event.ability_id === "string" ? event.ability_id : "unknown_ability";
    const abilityInstanceIndex = typeof event.ability_instance_index === "number" ? event.ability_instance_index : null;
    const loadoutSource = typeof event.loadout_source === "string" ? event.loadout_source : null;
    const damage = typeof event.damage === "number" ? event.damage : null;
    const outcome = typeof event.outcome === "string" ? event.outcome : null;
    const status = typeof event.status_applied === "string" ? event.status_applied : null;
    const statusDuration = typeof event.status_duration_rounds === "number" ? event.status_duration_rounds : null;
    const hpAfter = typeof event.target_hp_after === "number" ? event.target_hp_after : null;
    const diceOutcome = typeof event.dice_outcome === "string" ? event.dice_outcome : null;
    const slotTraceSummary = typeof event.slot_trace_summary === "string" ? event.slot_trace_summary : null;
    const abilityOutcome = typeof event.ability_outcome === "string" ? event.ability_outcome : null;
    const diceUsedSummary = formatDiceUsed(event.dice_used);

    const parts = [
      `${prefix}: [${side}] ${actor} -> ${target}`,
      `ability=${ability}`,
    ];
    if (abilityInstanceIndex !== null) parts.push(`ability_instance=${abilityInstanceIndex}`);
    if (loadoutSource) parts.push(`loadout=${loadoutSource}`);
    if (diceUsedSummary) parts.push(`dice=${diceUsedSummary}`);
    if (slotTraceSummary) parts.push(`slots=${slotTraceSummary}`);
    if (diceOutcome) parts.push(`dice_outcome=${diceOutcome}`);
    if (damage !== null) parts.push(`damage=${damage}`);
    if (outcome) parts.push(`outcome=${outcome}`);
    if (hpAfter !== null) parts.push(`hp_after=${hpAfter}`);
    if (status) {
      const statusText = statusDuration !== null ? `${status}(${statusDuration}r)` : status;
      parts.push(`status=${statusText}`);
    }
    if (abilityOutcome) parts.push(`ability_outcome=${abilityOutcome}`);
    return parts.join(" | ");
  }

  if (type === "battle_end") {
    const outcome = typeof event.outcome === "string" ? event.outcome : "unknown";
    return `${prefix}: outcome=${outcome}`;
  }

  if (type === "battle_start") {
    const playerCount = typeof event.player_unit_count === "number" ? event.player_unit_count : "?";
    const enemyCount = typeof event.enemy_unit_count === "number" ? event.enemy_unit_count : "?";
    return `${prefix}: player_units=${playerCount}, enemy_units=${enemyCount}`;
  }

  if (type === "phase_start") {
    const phase = typeof event.phase === "string" ? event.phase : "phase";
    return `${prefix}: ${phase}`;
  }

  const message = typeof event.message === "string" ? event.message : null;
  return message ? `${prefix}: ${message}` : prefix;
}

function formatDiceUsed(value: unknown): string | null {
  if (!Array.isArray(value) || value.length === 0) {
    return null;
  }

  const labels: string[] = [];
  for (const die of value) {
    if (typeof die !== "object" || die === null) continue;
    const sides = typeof (die as Record<string, unknown>).sides === "number"
      ? (die as Record<string, unknown>).sides
      : null;
    const instanceId = typeof (die as Record<string, unknown>).dice_instance_id === "string"
      ? (die as Record<string, unknown>).dice_instance_id
      : null;
    const kind = typeof (die as Record<string, unknown>).kind === "string"
      ? (die as Record<string, unknown>).kind
      : "die";

    if (sides === null) continue;
    labels.push(instanceId ? `#${instanceId}(d${sides})` : `${kind}(d${sides})`);
  }

  if (labels.length === 0) {
    return null;
  }

  return labels.join(",");
}

