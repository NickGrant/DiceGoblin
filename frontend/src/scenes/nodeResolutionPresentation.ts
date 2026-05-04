export type BattleLog = {
  meta?: Record<string, unknown>;
  events?: Array<Record<string, unknown>>;
  [key: string]: unknown;
} | null;

export type ResolutionSummary = {
  battleId: string;
  outcome: string;
  rounds: number;
  ticks: number;
  unlockedMsg: string;
  encounterDescription?: string;
};

export type ClaimSummary = {
  rewards: string[];
  progression: string[];
};

export function buildLootReceiptLines(summary: ResolutionSummary, claimSummary: ClaimSummary): string[] {
  const lines: string[] = [];
  if (summary.encounterDescription && summary.encounterDescription.trim() !== "") {
    lines.push(`Encounter: ${summary.encounterDescription}`);
    lines.push("");
  }

  lines.push("Rewards:");
  for (const reward of claimSummary.rewards) {
    lines.push(`- ${reward.replace(/^[-\s]+/, "")}`);
  }

  lines.push("");
  lines.push("Progression:");
  if (claimSummary.progression.length === 0) {
    lines.push("- No unit progression changes");
  } else {
    for (const line of claimSummary.progression) {
      lines.push(`- ${line}`);
    }
  }

  lines.push("");
  lines.push(`Outcome: ${String(summary.outcome).toUpperCase()}`);
  return lines;
}

export function deriveDefaultTick(log: BattleLog): number {
  if (!log || !Array.isArray(log.events)) {
    return 0;
  }

  for (const event of log.events) {
    if (!event || typeof event !== "object") continue;
    const rec = event as Record<string, unknown>;
    if (rec.type !== "action") continue;
    const tick = typeof rec.tick === "number" ? rec.tick : Number(rec.tick ?? 0);
    if (Number.isFinite(tick)) {
      return Math.max(0, tick);
    }
  }

  return 0;
}

export function buildTickSummaryLines(
  summary: ResolutionSummary,
  selectedTick: number,
  tickEvents: Array<Record<string, unknown>>
): string[] {
  const lines: string[] = [];
  if (summary.encounterDescription && summary.encounterDescription.trim() !== "") {
    lines.push(`Encounter: ${summary.encounterDescription}`);
  }

  if (tickEvents.length === 0) {
    if (lines.length > 0) {
      lines.push(`Tick ${selectedTick}`);
    }
    lines.push("No events on this tick.");
    return lines;
  }

  if (lines.length > 0) {
    lines.push(`Tick ${selectedTick}`);
  }
  lines.push(`Events on tick ${selectedTick}:`);
  tickEvents.forEach((event, index) => {
    lines.push(`${index + 1}) ${formatFriendlyEvent(event)}`);
  });
  return lines;
}

export function formatFriendlyEvent(event: Record<string, unknown>): string {
  const type = String(event.type ?? "event");
  if (type === "phase_start") {
    return `Round ${event.round ?? "?"} starts.`;
  }
  if (type === "battle_start") {
    return "Battle started.";
  }
  if (type === "battle_end") {
    return `Battle ended: ${String(event.outcome ?? "unknown")}.`;
  }
  if (type !== "action") {
    return String(event.message ?? type);
  }

  const side = String(event.side ?? "unknown").toUpperCase();
  const actor = typeof event.actor_unit_instance_id === "string"
    ? `Ally ${event.actor_unit_instance_id}`
    : typeof event.actor_enemy_slug === "string"
      ? prettifyEnemySlug(event.actor_enemy_slug)
      : "Unknown actor";
  const target = typeof event.target_unit_instance_id === "string"
    ? `Ally ${event.target_unit_instance_id}`
    : typeof event.target_enemy_slug === "string"
      ? prettifyEnemySlug(event.target_enemy_slug)
      : "Unknown target";
  const ability = prettifyAbilityId(String(event.ability_id ?? "ability"));
  const diceOutcome = typeof event.dice_outcome === "string" ? event.dice_outcome : "";
  const damage = typeof event.damage === "number" ? Math.max(0, Math.floor(event.damage)) : null;
  const targetHp = typeof event.target_hp_after === "number"
    ? Math.max(0, Math.floor(event.target_hp_after))
    : null;
  const status = typeof event.status_applied === "string" && event.status_applied.trim() !== ""
    ? event.status_applied.trim()
    : null;
  const statusDuration = typeof event.status_duration_rounds === "number"
    ? Math.max(0, Math.floor(event.status_duration_rounds))
    : null;

  const parts = [`[${side}] ${actor} -> ${target} using ${ability}`];
  if (damage !== null) {
    parts.push(`DMG ${damage}`);
  }
  if (targetHp !== null) {
    parts.push(`HP ${targetHp}`);
  }
  if (status) {
    parts.push(statusDuration && statusDuration > 0 ? `Status ${status} (${statusDuration}r)` : `Status ${status}`);
  }
  if (diceOutcome) {
    parts.push(`Dice ${diceOutcome}`);
  }
  return parts.join(" | ");
}

export function prettifyAbilityId(abilityId: string): string {
  return abilityId
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function buildClaimSummary(data: Record<string, unknown>): ClaimSummary {
  const rewardsRaw = (data.rewards ?? {}) as Record<string, unknown>;
  const xpTotal = Number(rewardsRaw.xp_total ?? 0);
  const soft = Number(rewardsRaw.currency_soft ?? 0);
  const unitLabels = Array.isArray(rewardsRaw.new_unit_labels)
    ? rewardsRaw.new_unit_labels.map((label) => String(label).trim()).filter((label) => label !== "")
    : Array.isArray(rewardsRaw.new_unit_instance_ids)
      ? rewardsRaw.new_unit_instance_ids.map((id) => `#${String(id)}`)
      : [];
  const diceLabels = Array.isArray(rewardsRaw.new_dice_labels)
    ? rewardsRaw.new_dice_labels.map((label) => String(label).trim()).filter((label) => label !== "")
    : [];

  const rewardLines: string[] = [];
  if (Number.isFinite(soft) && soft > 0) {
    rewardLines.push(`Teeth +${Math.floor(soft)}`);
  }
  if (Number.isFinite(xpTotal) && xpTotal > 0) {
    rewardLines.push(`Unit XP Award +${Math.floor(xpTotal)} each`);
  }
  if (unitLabels.length > 0) {
    rewardLines.push(`New Units: ${unitLabels.join(", ")}`);
  }
  if (diceLabels.length > 0) {
    rewardLines.push(`New Dice: ${diceLabels.join(", ")}`);
  }
  if (rewardLines.length === 0) {
    rewardLines.push("- No rewards recorded");
  }

  const progressionRaw = Array.isArray(data.updated_units) ? data.updated_units : [];
  const progressionLines = progressionRaw
    .map((unit): string | null => {
      if (!unit || typeof unit !== "object") return null;
      const rec = unit as Record<string, unknown>;
      const id = typeof rec.id === "string" ? rec.id : String(rec.id ?? "");
      const level = typeof rec.level === "number" ? rec.level : Number(rec.level ?? NaN);
      const xp = typeof rec.xp === "number" ? rec.xp : Number(rec.xp ?? NaN);
      if (!id || !Number.isFinite(level) || !Number.isFinite(xp)) return null;
      const unitName = typeof rec.name === "string" && rec.name.trim() !== "" ? rec.name.trim() : `Unit ${id}`;
      return `${unitName}: L${Math.floor(level)} (${Math.floor(xp)} XP)`;
    })
    .filter((line): line is string => line !== null);

  return {
    rewards: rewardLines,
    progression: progressionLines,
  };
}

export function prettifyEnemySlug(slug: string): string {
  return String(slug)
    .split("_")
    .filter((part) => part.length > 0)
    .map((part) => part[0]?.toUpperCase() + part.slice(1))
    .join(" ");
}

export function shortenEnemyLabel(label: string): string {
  return label.length <= 14 ? label : `${label.slice(0, 12).trimEnd()}..`;
}
