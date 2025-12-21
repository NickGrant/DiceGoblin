export type SessionResponse = {
  authenticated: boolean;
  user?: {
    id: string;
    display_name?: string | null;
  };
};

const DEFAULT_API_BASE_URL = "http://localhost:8080";

const API_BASE_URL =
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

export const apiClient = {
  async getSession(): Promise<SessionResponse> {
    return request<SessionResponse>("/api/v1/session", { method: "GET" });
  }
};
