import { type CreateResponse, type ProfileResponse, type RunResponse, type SessionResponse } from "../types/ApiResponse";


const DEFAULT_API_BASE_URL = "http://localhost:8080";

export const API_BASE_URL =
  (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? DEFAULT_API_BASE_URL;

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(init.headers ?? {})
    },
    credentials: "include"
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
    return request<SessionResponse>("/api/v1/session", { method: "GET" });
  },

  async logout(): Promise<{ ok: boolean }> {
    return request<{ ok: boolean }>("/api/v1/auth/logout", { method: "POST" });
  },

  async getCurrentRun(): Promise<RunResponse> {
    return request<RunResponse>("/api/v1/runs/current", { method: "GET"});
  },

  async createRun(biome: string): Promise<CreateResponse> {
    this.getSession().then((session) => {
      console.log('session', session)
      return request<CreateResponse>("/api/v1/runs", { 
        method: "POST", 
        headers: new Headers([['HTTP_X_CSRF_TOKEN', session.data?.csrf_token]]),
        body: JSON.stringify({region_id: biome === 'mountain' ? 1 : 2})
      });
    })
    
  },


  /**
   * Raw call (no caching). Useful for tests or explicit bypass.
   */
  async getProfileRaw(): Promise<ProfileResponse> {
    return request<ProfileResponse>("/api/v1/profile", { method: "GET" });
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
  }
};
