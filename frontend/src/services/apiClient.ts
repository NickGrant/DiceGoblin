import {
  type AbandonRunResponse,
  type AbilitySlotDiceMutationResponse,
  type BattleClaimResponse,
  type CreateResponse,
  type DiceSellResponse,
  type DiceMutationResponse,
  type DebugCatalogResponse,
  type DebugCurrencyGrantResponse,
  type DebugGrantDieResponse,
  type DebugGrantRegionItemResponse,
  type DebugGrantUnitResponse,
  type DebugResetAccountResponse,
  type ExitRunResponse,
  type ProfileResponse,
  type PromoteUnitResponse,
  type RenameUnitResponse,
  type ReplaceEquippedAbilitiesResponse,
  type ResolveNodeResponse,
  type RestFinalizeResponse,
  type RestOpenResponse,
  type RestStorePurchaseResponse,
  type RestStateResponse,
  type RunResponse,
  type SessionResponse,
  type ShopCatalogResponse,
  type ShopPurchaseResponse,
  type TeamActivateResponse,
  type TeamCreateResponse,
  type TeamDeleteResponse,
  type TeamUpdateResponse,
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

function refreshProfileAfterMutation(): void {
  apiClient.invalidateProfileCache();
  void apiClient.getProfile({ force: true }).catch(() => {});
}

function csrfFromSession(session: SessionResponse): string {
  return session.ok ? session.data.csrf_token : "";
}

type TeamUpdatePayload = {
  unit_ids: string[];
  formation: Array<{ cell: string; unit_instance_id: string | null }>;
  name?: string;
};

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

  async createRun(regionId: number): Promise<CreateResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";

    const res = await request<CreateResponse>("/api/v1/runs", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ region_id: regionId }),
    });
    // Starting a run consumes energy; purge cache and eagerly refetch profile.
    refreshProfileAfterMutation();
    return res;
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
    const res = await request<AbandonRunResponse>(`/api/v1/runs/${runId}/abandon`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async resolveRunNode(
    runId: string,
    nodeId: string,
    payload?: { team_id?: string }
  ): Promise<ResolveNodeResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const res = await request<ResolveNodeResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/resolve`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(payload ?? {}),
    });
    // Node resolution may consume energy; keep HUD/profile energy in sync.
    refreshProfileAfterMutation();
    return res;
  },

  async claimBattleRewards(battleId: string): Promise<BattleClaimResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const res = await request<BattleClaimResponse>(`/api/v1/battles/${battleId}/claim`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
    refreshProfileAfterMutation();
    return res;
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

  async getShopCatalog(): Promise<ShopCatalogResponse> {
    return request<ShopCatalogResponse>("/api/v1/shop", { method: "GET" });
  },

  async purchaseShopItem(
    itemType: "basic_unit" | "basic_dice" | "daily_deal",
    productId = ""
  ): Promise<ShopPurchaseResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const res = await request<ShopPurchaseResponse>("/api/v1/shop/purchase", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ item_type: itemType, product_id: productId }),
    });
    refreshProfileAfterMutation();
    return res;
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

  async purchaseRestStoreItem(
    runId: string,
    nodeId: string,
    itemType: "basic_unit" | "basic_dice"
  ): Promise<RestStorePurchaseResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const res = await request<RestStorePurchaseResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/store/purchase`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ item_type: itemType }),
    });
    refreshProfileAfterMutation();
    return res;
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

  async renameUnit(
    unitId: string,
    displayName: string
  ): Promise<RenameUnitResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const res = await request<RenameUnitResponse>(`/api/v1/units/${unitId}/name`, {
      method: "PATCH",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ display_name: displayName }),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async replaceEquippedAbilities(
    unitId: string,
    abilityIds: string[],
    context?: { runId?: string; nodeId?: string }
  ): Promise<ReplaceEquippedAbilitiesResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const body: Record<string, unknown> = { ability_ids: abilityIds };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    const res = await request<ReplaceEquippedAbilitiesResponse>(`/api/v1/units/${unitId}/loadout`, {
      method: "PUT",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(body),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async assignAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    diceId: string,
    context?: { runId?: string; nodeId?: string }
  ): Promise<AbilitySlotDiceMutationResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const body: Record<string, unknown> = { dice_instance_id: Number(diceId) };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    const res = await request<AbilitySlotDiceMutationResponse>(`/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`, {
      method: "PUT",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(body),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async clearAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    context?: { runId?: string; nodeId?: string }
  ): Promise<AbilitySlotDiceMutationResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const body: Record<string, unknown> = {};
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    const res = await request<AbilitySlotDiceMutationResponse>(`/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`, {
      method: "DELETE",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(body),
    });
    refreshProfileAfterMutation();
    return res;
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

  async sellDice(diceId: string): Promise<DiceSellResponse> {
    const session = await apiClient.getSession();
    const csrf = (session as any)?.data?.csrf_token ?? "";
    const res = await request<DiceSellResponse>(`/api/v1/dice/${diceId}/sell`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
    refreshProfileAfterMutation();
    return res;
  },

  // -----------------------------
  // Squads (backed by /teams routes)
  // -----------------------------

  async createTeam(
    name: string,
    makeActive = true
  ): Promise<TeamCreateResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);

    const res = await request<TeamCreateResponse>("/api/v1/teams", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ name, make_active: makeActive }),
    });

    apiClient.invalidateProfileCache();
    return res;
  },

  async activateTeam(
    teamId: string
  ): Promise<TeamActivateResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);

    const res = await request<TeamActivateResponse>(`/api/v1/teams/${teamId}/activate`, {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });

    apiClient.invalidateProfileCache();
    return res;
  },

  async updateTeam(
    teamId: string,
    payload: TeamUpdatePayload
  ): Promise<TeamUpdateResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);

    const res = await request<TeamUpdateResponse>(`/api/v1/teams/${teamId}`, {
      method: "PUT",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify(payload),
    });

    apiClient.invalidateProfileCache();
    return res;
  },

  async deleteTeam(
    teamId: string
  ): Promise<TeamDeleteResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);

    const res = await request<TeamDeleteResponse>(`/api/v1/teams/${teamId}`, {
      method: "DELETE",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });

    apiClient.invalidateProfileCache();
    return res;
  },

  async getDebugCatalog(): Promise<DebugCatalogResponse> {
    return request<DebugCatalogResponse>("/api/v1/debug/catalog", { method: "GET" });
  },

  async grantDebugCurrency(soft: number, hard = 0): Promise<DebugCurrencyGrantResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);
    const res = await request<DebugCurrencyGrantResponse>("/api/v1/debug/grant/currency", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ soft, hard }),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async grantDebugUnit(unitTypeSlug: string, count = 1): Promise<DebugGrantUnitResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);
    const res = await request<DebugGrantUnitResponse>("/api/v1/debug/grant/unit", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ unit_type_slug: unitTypeSlug, count }),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async grantDebugDie(sides: number, rarity: string, count = 1): Promise<DebugGrantDieResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);
    const res = await request<DebugGrantDieResponse>("/api/v1/debug/grant/dice", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ sides, rarity, count }),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async grantDebugRegionItem(regionItemSlug: string, quantity = 1): Promise<DebugGrantRegionItemResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);
    const res = await request<DebugGrantRegionItemResponse>("/api/v1/debug/grant/region-item", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({ region_item_slug: regionItemSlug, quantity }),
    });
    refreshProfileAfterMutation();
    return res;
  },

  async resetDebugAccount(): Promise<DebugResetAccountResponse> {
    const session = await apiClient.getSession();
    const csrf = csrfFromSession(session);
    const res = await request<DebugResetAccountResponse>("/api/v1/debug/reset-account", {
      method: "POST",
      headers: new Headers([["X-CSRF-Token", csrf]]),
      body: JSON.stringify({}),
    });
    refreshProfileAfterMutation();
    return res;
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
