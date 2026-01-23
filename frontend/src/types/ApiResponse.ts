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

export type ProfileData = {
  server_time_iso: string; // ISO timestamp
  squads: Array<unknown>;
  units: Array<unknown>;
  dice: Array<unknown>;
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
 * GET /api/v1/runs/current
 * ----------------------------------------
 */

export type RunNodeStatus = "available" | "locked" | "cleared" | string;
export type RunNodeType = "combat" | "loot" | "rest" | "boss" | string;

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
