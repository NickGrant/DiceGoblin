import {
  type AbandonRunResponse,
  type AbilitySlotDiceMutationResponse,
  type AbilityCatalogResponse,
  type BattleClaimResponse,
  type CreateResponse,
  type DiceSellResponse,
  type DebugCatalogResponse,
  type DebugCurrencyGrantResponse,
  type DebugGrantDieResponse,
  type DebugGrantRegionItemResponse,
  type DebugGrantUnitResponse,
  type DebugResetAccountResponse,
  type ExitRunResponse,
  type ProfileResponse,
  type PromoteUnitResponse,
  type PromotionOptionsResponse,
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

async function getCsrfToken(): Promise<string> {
  const session = await apiClient.getSession();
  return csrfFromSession(session);
}

async function requestWithCsrf<T>(
  path: string,
  method: NonNullable<RequestInit["method"]>,
  body: unknown,
): Promise<T> {
  const csrf = await getCsrfToken();

  return request<T>(path, {
    method,
    headers: new Headers([["X-CSRF-Token", csrf]]),
    body: JSON.stringify(body),
  });
}

async function mutateWithCsrf<T>(
  path: string,
  method: NonNullable<RequestInit["method"]>,
  body: unknown,
  options?: { refreshProfile?: boolean; invalidateProfile?: boolean },
): Promise<T> {
  const response = await requestWithCsrf<T>(path, method, body);

  if (options?.refreshProfile) {
    refreshProfileAfterMutation();
  } else if (options?.invalidateProfile) {
    apiClient.invalidateProfileCache();
  }

  return response;
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

  async getAbilityCatalog(): Promise<AbilityCatalogResponse> {
    return request<AbilityCatalogResponse>("/api/v1/abilities", { method: "GET" });
  },

  async createRun(regionId: number): Promise<CreateResponse> {
    const res = await mutateWithCsrf<CreateResponse>(
      "/api/v1/runs",
      "POST",
      { region_id: regionId },
      { refreshProfile: true }
    );
    // Starting a run consumes energy; purge cache and eagerly refetch profile.
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
    return requestWithCsrf<ExitRunResponse>(`/api/v1/runs/${runId}/exit`, "POST", {});
  },

  async abandonRun(runId: string): Promise<AbandonRunResponse> {
    return mutateWithCsrf<AbandonRunResponse>(`/api/v1/runs/${runId}/abandon`, "POST", {}, { refreshProfile: true });
  },

  async resolveRunNode(
    runId: string,
    nodeId: string,
    payload?: { team_id?: string }
  ): Promise<ResolveNodeResponse> {
    const res = await mutateWithCsrf<ResolveNodeResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/resolve`,
      "POST",
      payload ?? {},
      { refreshProfile: true }
    );
    // Node resolution may consume energy; keep HUD/profile energy in sync.
    return res;
  },

  async claimBattleRewards(battleId: string): Promise<BattleClaimResponse> {
    return mutateWithCsrf<BattleClaimResponse>(`/api/v1/battles/${battleId}/claim`, "POST", {}, { refreshProfile: true });
  },

  async openRest(runId: string, nodeId: string): Promise<RestOpenResponse> {
    return requestWithCsrf<RestOpenResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/open`, "POST", {});
  },

  async getShopCatalog(): Promise<ShopCatalogResponse> {
    return request<ShopCatalogResponse>("/api/v1/shop", { method: "GET" });
  },

  async purchaseShopItem(
    itemType: "basic_unit" | "basic_dice" | "daily_deal",
    productId = ""
  ): Promise<ShopPurchaseResponse> {
    return mutateWithCsrf<ShopPurchaseResponse>(
      "/api/v1/shop/purchase",
      "POST",
      { item_type: itemType, product_id: productId },
      { refreshProfile: true }
    );
  },

  async updateRestState(
    runId: string,
    nodeId: string,
    payload: { unit_ids: string[]; formation: Array<{ cell: string; unit_instance_id: string | null }> }
  ): Promise<RestStateResponse> {
    return requestWithCsrf<RestStateResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/state`, "PUT", payload);
  },

  async finalizeRest(runId: string, nodeId: string): Promise<RestFinalizeResponse> {
    return requestWithCsrf<RestFinalizeResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/finalize`, "POST", {});
  },

  async purchaseRestStoreItem(
    runId: string,
    nodeId: string,
    itemType: "basic_unit" | "basic_dice"
  ): Promise<RestStorePurchaseResponse> {
    return mutateWithCsrf<RestStorePurchaseResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/rest/store/purchase`,
      "POST",
      { item_type: itemType },
      { refreshProfile: true }
    );
  },

  async getPromotionOptions(unitId: string): Promise<PromotionOptionsResponse> {
    return request<PromotionOptionsResponse>(`/api/v1/units/${unitId}/promotion-options`, { method: "GET" });
  },

  async promoteUnit(
    primaryUnitId: string,
    secondaryUnitIds: [string, string],
    context?: { runId?: string; nodeId?: string; destinationUnitTypeId?: string }
  ): Promise<PromoteUnitResponse> {
    const body: Record<string, unknown> = {
      primary_unit_instance_id: Number(primaryUnitId),
      secondary_unit_instance_ids: secondaryUnitIds.map((id) => Number(id)),
    };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    if (context?.destinationUnitTypeId) {
      body.destination_unit_type_id = Number(context.destinationUnitTypeId);
    }
    return requestWithCsrf<PromoteUnitResponse>(`/api/v1/units/${primaryUnitId}/promote`, "POST", body);
  },

  async renameUnit(
    unitId: string,
    displayName: string
  ): Promise<RenameUnitResponse> {
    return mutateWithCsrf<RenameUnitResponse>(
      `/api/v1/units/${unitId}/name`,
      "PATCH",
      { display_name: displayName },
      { refreshProfile: true }
    );
  },

  async replaceEquippedAbilities(
    unitId: string,
    abilityIds: string[],
    context?: { runId?: string; nodeId?: string }
  ): Promise<ReplaceEquippedAbilitiesResponse> {
    const body: Record<string, unknown> = { ability_ids: abilityIds };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    return mutateWithCsrf<ReplaceEquippedAbilitiesResponse>(
      `/api/v1/units/${unitId}/loadout`,
      "PUT",
      body,
      { refreshProfile: true }
    );
  },

  async assignAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    diceId: string,
    context?: { runId?: string; nodeId?: string }
  ): Promise<AbilitySlotDiceMutationResponse> {
    const body: Record<string, unknown> = { dice_instance_id: Number(diceId) };
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    return mutateWithCsrf<AbilitySlotDiceMutationResponse>(
      `/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`,
      "PUT",
      body,
      { refreshProfile: true }
    );
  },

  async clearAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    context?: { runId?: string; nodeId?: string }
  ): Promise<AbilitySlotDiceMutationResponse> {
    const body: Record<string, unknown> = {};
    if (context?.runId && context?.nodeId) {
      body.run_id = Number(context.runId);
      body.node_id = Number(context.nodeId);
    }
    return mutateWithCsrf<AbilitySlotDiceMutationResponse>(
      `/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`,
      "DELETE",
      body,
      { refreshProfile: true }
    );
  },

  async sellDice(diceId: string): Promise<DiceSellResponse> {
    return mutateWithCsrf<DiceSellResponse>(`/api/v1/dice/${diceId}/sell`, "POST", {}, { refreshProfile: true });
  },

  // -----------------------------
  // Squads (backed by /teams routes)
  // -----------------------------

  async createTeam(
    name: string,
    makeActive = true
  ): Promise<TeamCreateResponse> {
    return mutateWithCsrf<TeamCreateResponse>(
      "/api/v1/teams",
      "POST",
      { name, make_active: makeActive },
      { invalidateProfile: true }
    );
  },

  async activateTeam(
    teamId: string
  ): Promise<TeamActivateResponse> {
    return mutateWithCsrf<TeamActivateResponse>(
      `/api/v1/teams/${teamId}/activate`,
      "POST",
      {},
      { invalidateProfile: true }
    );
  },

  async updateTeam(
    teamId: string,
    payload: TeamUpdatePayload
  ): Promise<TeamUpdateResponse> {
    return mutateWithCsrf<TeamUpdateResponse>(
      `/api/v1/teams/${teamId}`,
      "PUT",
      payload,
      { invalidateProfile: true }
    );
  },

  async deleteTeam(
    teamId: string
  ): Promise<TeamDeleteResponse> {
    return mutateWithCsrf<TeamDeleteResponse>(
      `/api/v1/teams/${teamId}`,
      "DELETE",
      {},
      { invalidateProfile: true }
    );
  },

  async getDebugCatalog(): Promise<DebugCatalogResponse> {
    return request<DebugCatalogResponse>("/api/v1/debug/catalog", { method: "GET" });
  },

  async grantDebugCurrency(soft: number, hard = 0): Promise<DebugCurrencyGrantResponse> {
    return mutateWithCsrf<DebugCurrencyGrantResponse>(
      "/api/v1/debug/grant/currency",
      "POST",
      { soft, hard },
      { refreshProfile: true }
    );
  },

  async grantDebugUnit(unitTypeSlug: string, count = 1): Promise<DebugGrantUnitResponse> {
    return mutateWithCsrf<DebugGrantUnitResponse>(
      "/api/v1/debug/grant/unit",
      "POST",
      { unit_type_slug: unitTypeSlug, count },
      { refreshProfile: true }
    );
  },

  async grantDebugDie(sides: number, rarity: string, count = 1): Promise<DebugGrantDieResponse> {
    return mutateWithCsrf<DebugGrantDieResponse>(
      "/api/v1/debug/grant/dice",
      "POST",
      { sides, rarity, count },
      { refreshProfile: true }
    );
  },

  async grantDebugRegionItem(regionItemSlug: string, quantity = 1): Promise<DebugGrantRegionItemResponse> {
    return mutateWithCsrf<DebugGrantRegionItemResponse>(
      "/api/v1/debug/grant/region-item",
      "POST",
      { region_item_slug: regionItemSlug, quantity },
      { refreshProfile: true }
    );
  },

  async resetDebugAccount(): Promise<DebugResetAccountResponse> {
    return mutateWithCsrf<DebugResetAccountResponse>(
      "/api/v1/debug/reset-account",
      "POST",
      {},
      { refreshProfile: true }
    );
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
