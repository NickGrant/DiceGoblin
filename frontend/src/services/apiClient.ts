import {
  type AbandonRunResponse,
  type CreateResponse,
  type DiceMutationResponse,
  type ExitRunResponse,
  type ProfileResponse,
  type PromoteUnitResponse,
  type ResolveNodeResponse,
  type RestFinalizeResponse,
  type RestOpenResponse,
  type RestStateResponse,
  type RunResponse,
  type SessionResponse,
} from "../types/ApiResponse";
import {
  validateCurrentRunResponse,
  validateProfileResponse,
  validateSessionResponse,
} from "./apiContractValidators";

const DEFAULT_API_BASE_URL = "http://localhost:8080";

export const API_BASE_URL =
  (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? DEFAULT_API_BASE_URL;

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  // Normalize headers so callers can pass either:
  // - plain object: { "X-CSRF-Token": token }
  // - Headers: new Headers([["X-CSRF-Token", token]])
  const headers = new Headers(init.headers ?? undefined);

  // Ensure JSON content-type for JSON bodies (most of your API)
  if (!headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const res = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    headers,
    credentials: "include",
  });

  if (!res.ok) {
    const text = await res.text().catch(() => "");
    throw new Error(`API ${res.status} ${res.statusText}: ${text}`);
  }

  return (await res.json()) as T;
}


/**
 * Profile caching
 */
const PROFILE_TTL_MS = 30_000;

let profileCache:
  | {
      value: ProfileResponse;
      fetchedAt: number; // epoch ms
    }
  | null = null;

let inflightProfilePromise: Promise<ProfileResponse> | null = null;

function isFresh(fetchedAt: number, now = Date.now()): boolean {
  return now - fetchedAt < PROFILE_TTL_MS;
}

export const apiClient = {
  async getSession(): Promise<SessionResponse> {
    const response = await request<unknown>("/api/v1/session", { method: "GET" });
    return validateSessionResponse(response);
  },

  async logout(): Promise<{ ok: boolean }> {
    return request<{ ok: boolean }>("/api/v1/auth/logout", { method: "POST" });
  },

  async getCurrentRun(): Promise<RunResponse> {
    const response = await request<unknown>("/api/v1/runs/current", { method: "GET" });
    return validateCurrentRunResponse(response);
  },

  async createRun(biome: string): Promise<CreateResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";

    return request<CreateResponse>("/api/v1/runs", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ region_id: biome === "mountain" ? 1 : 2 }),
    });
  },

  /**
   * Raw call (no caching). Useful for tests or explicit bypass.
   */
  async getProfileRaw(): Promise<ProfileResponse> {
    const response = await request<unknown>("/api/v1/profile", { method: "GET" });
    return validateProfileResponse(response);
  },

  async exitRun(runId: string): Promise<ExitRunResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    return request<ExitRunResponse>(`/api/v1/runs/${runId}/exit`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
  },

  async abandonRun(runId: string): Promise<AbandonRunResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    return request<AbandonRunResponse>(`/api/v1/runs/${runId}/abandon`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
  },

  async resolveRunNode(
    runId: string,
    nodeId: string,
    payload?: { team_id?: string }
  ): Promise<ResolveNodeResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    return request<ResolveNodeResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/resolve`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(payload ?? {}),
    });
  },

  async openRest(runId: string, nodeId: string): Promise<RestOpenResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    return request<RestOpenResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/open`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
  },

  async updateRestState(
    runId: string,
    nodeId: string,
    payload: { unit_ids: string[]; formation: Array<{ cell: string; unit_instance_id: string | null }> }
  ): Promise<RestStateResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    return request<RestStateResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/state`, {
      method: "PUT",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(payload),
    });
  },

  async finalizeRest(runId: string, nodeId: string): Promise<RestFinalizeResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    return request<RestFinalizeResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/finalize`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
  },

  async promoteUnit(
    primaryUnitId: string,
    secondaryUnitIds: [string, string],
    context?: { runId?: string; nodeId?: string }
  ): Promise<PromoteUnitResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const body: Record<string, unknown> = {
      primary_unit_instance_id: Number(primaryUnitId),
      secondary_unit_instance_ids: secondaryUnitIds.map((id) => Number(id)),
    };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    return request<PromoteUnitResponse>(`/api/v1/units/${primaryUnitId}/promote`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(body),
    });
  },

  async equipDice(
    unitId: string,
    diceId: string,
    context?: { runId?: string; nodeId?: string }
  ): Promise<DiceMutationResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const body: Record<string, unknown> = { dice_instance_id: Number(diceId) };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    return request<DiceMutationResponse>(`/api/v1/units/${unitId}/dice/equip`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(body),
    });
  },

  async unequipDice(
    unitId: string,
    diceId: string,
    context?: { runId?: string; nodeId?: string }
  ): Promise<DiceMutationResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const body: Record<string, unknown> = { dice_instance_id: Number(diceId) };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    return request<DiceMutationResponse>(`/api/v1/units/${unitId}/dice/unequip`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(body),
    });
  },

  // -----------------------------
  // Squads (backed by /teams routes)
  // -----------------------------

  async createTeam(
    name: string,
    makeActive = true
  ): Promise<{ ok: true; data: { team_id: string } } | { ok: false; error: { code: string; message: string } }> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";

    const res = await request<{ ok: boolean; data?: any; error?: any }>("/api/v1/teams", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ name, make_active: makeActive }),
    });

    apiClient.invalidateProfileCache();
    return res as any;
  },

  async activateTeam(
    teamId: string
  ): Promise<{ ok: true; data: {} } | { ok: false; error: { code: string; message: string } }> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";

    const res = await request<{ ok: boolean; data?: any; error?: any }>(`/api/v1/teams/${teamId}/activate`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });

    apiClient.invalidateProfileCache();
    return res as any;
  },

  async updateTeam(
    teamId: string,
    payload: {
      unit_ids: string[];
      formation: Array<{ cell: string; unit_instance_id: string | null }>;
      name?: string;
    }
  ): Promise<{ ok: true; data: {} } | { ok: false; error: { code: string; message: string } }> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";

    const res = await request<{ ok: boolean; data?: any; error?: any }>(`/api/v1/teams/${teamId}`, {
      method: "PUT",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(payload),
    });

    apiClient.invalidateProfileCache();
    return res as any;
  },

  async deleteTeam(
    teamId: string
  ): Promise<{ ok: true; data: { team_id: string } } | { ok: false; error: { code: string; message: string } }> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";

    const res = await request<{ ok: boolean; data?: any; error?: any }>(`/api/v1/teams/${teamId}`, {
      method: "DELETE",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });

    apiClient.invalidateProfileCache();
    return res as any;
  },

  /**
   * Cached call:
   * - returns cached value if fetched within last 30s
   * - de-dupes concurrent callers (single in-flight request)
   * - can optionally return stale cache on error
   */
  async getProfile(options?: {
    force?: boolean; // bypass TTL
    allowStaleOnError?: boolean; // if fetch fails but we have cache, return it
  }): Promise<ProfileResponse> {
    const now = Date.now();

    if (!options?.force && profileCache && isFresh(profileCache.fetchedAt, now)) {
      return profileCache.value;
    }

    if (inflightProfilePromise) {
      return inflightProfilePromise;
    }

    inflightProfilePromise = (async () => {
      try {
        const value = await apiClient.getProfileRaw();

        profileCache = { value, fetchedAt: Date.now() };
        return value;
      } catch (err) {
        if (options?.allowStaleOnError && profileCache) {
          return profileCache.value;
        }
        throw err;
      } finally {
        inflightProfilePromise = null;
      }
    })();

    return inflightProfilePromise;
  },

  /**
   * Manual cache control
   */
  invalidateProfileCache(): void {
    profileCache = null;
  },

  peekProfileCache(): ProfileResponse | null {
    return profileCache?.value ?? null;
  },
};
