/**
 * Generic API envelope used across endpoints.
 * - On success: { ok: true, data: T }
 * - On error:   { ok: false, error: { code, message, details? } }
 */
export type ApiError = {
  code: string;
  message: string;
  details?: Record<string, unknown>;
};

export type ApiOk<T> = { ok: true; data: T };
export type ApiErr = { ok: false; error: ApiError };
export type ApiResponse<T> = ApiOk<T> | ApiErr;

/**
 * ----------------------------------------
 * GET /api/v1/session
 * ----------------------------------------
 */

export type SessionData = {
  authenticated: boolean;
  csrf_token: string;
  user?: {
    id: string;
    display_name?: string | null;
    avatar_url?: string | null;
  };
};

export type SessionResponse = ApiResponse<SessionData>;

/**
 * ----------------------------------------
 * GET /api/v1/profile
 * ----------------------------------------
 */

export type ProfileActiveRun = {
  run_id: string;
  region_id: string;
  seed: string;
  status: string;
  started_at: string;
  ended_at: string | null;
};

// ----------------------------------------
// /profile payload helpers
// ----------------------------------------

export type UnitEquippedDie = {
  dice_instance_id: string;
  slot_index: number;
};

export type UnitAbilityRecord = {
  ability_id: string;
  type?: "active" | "passive" | string;
  display_name?: string;
  order?: number;
};

export type UnitRecord = {
  // Minimum fields used by UnitListPanel + Warband screen
  id: string;
  name: string;
  level: number;

  // Optional (safe while backend stabilizes)
  unit_type_id?: string;
  unit_type_name?: string;
  tier?: number;
  xp?: number;
  max_level?: number;
  locked?: boolean;
  equipped_dice?: UnitEquippedDie[];
  abilities?: UnitAbilityRecord[];

  [key: string]: unknown;
};

export type TeamFormationCell = {
  cell: string; // "A1".."C3"
  unit_instance_id: string | null;
};

export type TeamRecord = {
  id: string;
  name: string;
  is_active: boolean;
  unit_ids: string[];
  formation: TeamFormationCell[];

  [key: string]: unknown;
};

export type DiceAffixRecord = {
  affix_definition_id: string;
  value: number;
};

export type DiceRecord = {
  id: string;
  dice_definition_id?: string;
  display_name?: string | null;
  rarity?: string;
  sides?: number;
  slot_capacity?: number;
  affixes?: DiceAffixRecord[];

  [key: string]: unknown;
};

export type ProfileData = {
  server_time_iso: string; // ISO timestamp
  squads: TeamRecord[];
  units: UnitRecord[];
  dice: DiceRecord[];
  currency: {
    soft: number;
    hard: number;
  };
  energy: {
    current: number;
    max: number;
    regen_rate_per_hour: number;
    last_regen_at: string; // ISO timestamp
  };
  region_unlocks: Array<unknown>;
  region_items: Array<unknown>;
  active_run: ProfileActiveRun | null;
};

export type ProfileResponse = ApiResponse<ProfileData>;

/**
 * ----------------------------------------
 * GET /api/v1/abilities
 * ----------------------------------------
 *
 * Canonical ability catalog payload (from backend AbilityRegistry::toCatalogPayload()).
 */

export type AbilityCatalogEntry = {
  ability_id: string;
  type: "active" | "passive" | string;

  display_name: string;
  short_desc: string;
  icon_key: string;

  tags: string[];
  default_params: Record<string, unknown>;

  /**
   * Present for both active and passive (passives include it for stable sorting).
   */
  order: number;

  /**
   * Active-only fields (backend includes these only when type === "active").
   */
  speed?: number;
  dice_cost?: number;
  default_target?: string | null;
};

export type AbilityCatalogData = {
  catalog_version: number;
  abilities: AbilityCatalogEntry[];
};

export type AbilityCatalogResponse = ApiResponse<AbilityCatalogData>;

/**
 * ----------------------------------------
 * GET /api/v1/runs/current
 * ----------------------------------------
 */

export type RunNodeStatus = "available" | "locked" | "cleared" | string;
export type RunNodeType = "combat" | "loot" | "rest" | "boss" | "exit" | string;

export type CurrentRunRecord = {
  run_id: string;
  region_id: string;
  seed: string;
  status: string;
  started_at: string;
  ended_at: string | null;
};

export type CurrentRunNode = {
  id: string;
  run_id: string;
  node_index: number;
  node_type: RunNodeType;
  status: RunNodeStatus;

  // DB field
  meta_json?: string | null;

  // Controller convenience field (decoded from meta_json)
  meta?: Record<string, unknown> | null;
};

export type CurrentRunEdge = {
  edge_id: string;
  run_id: string;
  from_node_id: string;
  to_node_id: string;
};

export type CurrentRunMap = {
  nodes: CurrentRunNode[];
  edges: CurrentRunEdge[];
};

export type CurrentRunData = {
  run: CurrentRunRecord | null;
  map: CurrentRunMap | null;
};

export type RunResponse = ApiResponse<CurrentRunData>;

export type RestRunUnitState = {
  unit_instance_id: string;
  hp: number;
  is_defeated: boolean;
  status_effects: unknown[];
};

export type RestOpenData = {
  run_id: string;
  node_id: string;
  status: "open" | string;
  team_id: string;
  unit_ids: string[];
  formation: TeamFormationCell[];
  run_unit_state: RestRunUnitState[];
};

export type RestFinalizeProgression = {
  id: string;
  from_level: number;
  to_level: number;
  from_xp: number;
  to_xp: number;
};

export type RestFinalizeData = {
  run_id: string;
  node: { id: string; status: string };
  next: { unlocked_node_ids: string[] };
  progression: RestFinalizeProgression[];
};

export type RestOpenResponse = ApiResponse<RestOpenData>;
export type RestStateResponse = ApiResponse<RestOpenData>;
export type RestFinalizeResponse = ApiResponse<RestFinalizeData>;

export type ExitRunData = {
  run_id: string;
  status: string;
  exit_node_id: string;
};

export type ExitRunResponse = ApiResponse<ExitRunData>;

export type AbandonRunData = {
  run_id: string;
  status: string;
};

export type AbandonRunResponse = ApiResponse<AbandonRunData>;

export type ResolveNodeData = {
  node: {
    id: string;
    status: string;
  };
  battle: {
    battle_id: string;
    outcome: string;
    rounds: number;
    ticks: number;
    status: string;
  };
  next: {
    unlocked_node_ids: string[];
  };
};

export type ResolveNodeResponse = ApiResponse<ResolveNodeData>;

export type PromoteUnitData = {
  unit: { id: string; tier: number; level: number; xp: number };
  consumed_units: string[];
};

export type PromoteUnitResponse = ApiResponse<PromoteUnitData>;

export type DiceMutationData = {
  unit_id: string;
  equipped_dice: UnitEquippedDie[];
};

export type DiceMutationResponse = ApiResponse<DiceMutationData>;

export type TeamCreateData = {
  team_id: string;
};

export type TeamMutationData = Record<string, never>;

export type TeamCreateResponse = ApiResponse<TeamCreateData>;
export type TeamActivateResponse = ApiResponse<TeamMutationData>;
export type TeamUpdateResponse = ApiResponse<TeamMutationData>;
export type TeamDeleteResponse = ApiResponse<TeamCreateData>;

/**
 * ----------------------------------------
 * POST /api/v1/runs
 * ----------------------------------------
 *
 * Success: { ok: true, data: {} } OR in some implementations { ok: true }.
 * To keep the client strictly typed, treat success as an empty object payload.
 */
export type CreateRunData = Record<string, never>;
export type CreateResponse = ApiResponse<CreateRunData>;
