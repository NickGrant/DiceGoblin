import type { SessionState } from "../state/RegistrySession";

export const DEBUG_SCENE_KEYS = [
  "BootScene",
  "PreloadScene",
  "LandingScene",
  "HomeScene",
  "RegionSelectScene",
  "WarbandManagementScene",
  "SquadDetailsScene",
  "UnitDetailsScene",
  "DiceInventoryScene",
  "MapExplorationScene",
  "NodeResolutionScene",
  "RestManagementScene",
  "RunEndSummaryScene",
] as const;

export type DebugSceneKey = (typeof DEBUG_SCENE_KEYS)[number];
export type DebugAuthMode = "authenticated" | "guest" | "live";

export type DebugSceneConfig = {
  enabled: boolean;
  targetSceneKey: DebugSceneKey | null;
  sceneData: Record<string, unknown>;
  authMode: DebugAuthMode;
  displayName: string;
  userId: string;
  skipSessionCheck: boolean;
  settleMs: number;
  errors: string[];
};

const DEFAULT_DISPLAY_NAME = "Debug Goblin";
const DEFAULT_USER_ID = "debug-user";

function normalizeSceneValue(value: string): string {
  return value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
}

const SCENE_ALIAS_MAP = new Map<string, DebugSceneKey>();

for (const key of DEBUG_SCENE_KEYS) {
  const withoutSuffix = key.replace(/Scene$/, "");
  for (const alias of [key, withoutSuffix]) {
    SCENE_ALIAS_MAP.set(normalizeSceneValue(alias), key);
  }
}

for (const [alias, key] of [
  ["region", "RegionSelectScene"],
  ["regions", "RegionSelectScene"],
  ["warband", "WarbandManagementScene"],
  ["squad", "SquadDetailsScene"],
  ["unit", "UnitDetailsScene"],
  ["dice", "DiceInventoryScene"],
  ["map", "MapExplorationScene"],
  ["node", "NodeResolutionScene"],
  ["rest", "RestManagementScene"],
  ["summary", "RunEndSummaryScene"],
  ["runsummary", "RunEndSummaryScene"],
] as const) {
  SCENE_ALIAS_MAP.set(alias, key);
}

function parseAuthMode(value: string | null): DebugAuthMode {
  if (value === "guest" || value === "live") {
    return value;
  }

  return "authenticated";
}

function parseBooleanFlag(value: string | null): boolean {
  return value === "1" || value === "true" || value === "yes";
}

function parsePositiveInt(value: string | null, fallback: number): number {
  const parsed = Number.parseInt(value ?? "", 10);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
}

export function resolveDebugSceneKey(value: string | null | undefined): DebugSceneKey | null {
  if (!value) {
    return null;
  }

  return SCENE_ALIAS_MAP.get(normalizeSceneValue(value)) ?? null;
}

export function parseDebugSceneData(value: string | null): Record<string, unknown> {
  if (!value) {
    return {};
  }

  const parsed = JSON.parse(value) as unknown;
  if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
    throw new Error("debugSceneData must decode to a JSON object.");
  }

  return parsed as Record<string, unknown>;
}

export function parseDebugSceneConfig(search?: string): DebugSceneConfig {
  const params = new URLSearchParams(
    search ?? (typeof window !== "undefined" ? window.location.search : "")
  );
  const rawScene = params.get("debugScene");
  const targetSceneKey = resolveDebugSceneKey(rawScene);
  const errors: string[] = [];

  if (rawScene && !targetSceneKey) {
    errors.push(`Unknown debugScene '${rawScene}'.`);
  }

  let sceneData: Record<string, unknown> = {};
  try {
    sceneData = parseDebugSceneData(params.get("debugSceneData"));
  } catch (error) {
    errors.push(error instanceof Error ? error.message : "Invalid debugSceneData.");
  }

  const authMode = parseAuthMode(params.get("debugAuth"));
  const enabled = targetSceneKey !== null;

  return {
    enabled,
    targetSceneKey,
    sceneData,
    authMode,
    displayName: params.get("debugDisplayName")?.trim() || DEFAULT_DISPLAY_NAME,
    userId: params.get("debugUserId")?.trim() || DEFAULT_USER_ID,
    skipSessionCheck:
      enabled && (authMode !== "live" || parseBooleanFlag(params.get("debugSkipSession"))),
    settleMs: parsePositiveInt(params.get("debugSettleMs"), 0),
    errors,
  };
}

const cachedDebugSceneConfig = parseDebugSceneConfig();

export function getDebugSceneConfig(): DebugSceneConfig {
  return cachedDebugSceneConfig;
}

export function buildDebugSession(config: DebugSceneConfig): SessionState | null {
  if (!config.enabled || config.authMode === "live") {
    return null;
  }

  if (config.authMode === "guest") {
    return { isAuthenticated: false };
  }

  return {
    isAuthenticated: true,
    user: {
      id: config.userId,
      displayName: config.displayName,
      avatarUrl: "",
    },
    csrfToken: "debug-csrf-token",
  };
}
