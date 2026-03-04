import { beforeEach, describe, expect, it, vi } from "vitest";

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

describe("apiClient mutation flows", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.stubGlobal("fetch", vi.fn());
  });

  it("createRun uses session CSRF token and biome-to-region mapping", async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse({
          ok: true,
          data: {
            authenticated: true,
            csrf_token: "csrf_run",
            user: { id: "1", display_name: "QA" },
          },
        })
      )
      .mockResolvedValueOnce(jsonResponse({ ok: true }));

    const { apiClient } = await import("../../src/services/apiClient");
    const response = await apiClient.createRun("mountain");
    expect(response).toEqual({ ok: true });

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      "http://localhost:8080/api/v1/session",
      expect.objectContaining({
        method: "GET",
        credentials: "include",
      })
    );

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      "http://localhost:8080/api/v1/runs",
      expect.objectContaining({
        method: "POST",
        credentials: "include",
        body: JSON.stringify({ region_id: 1 }),
      })
    );

    const secondCallInit = fetchMock.mock.calls[1]?.[1] as RequestInit;
    const headers = new Headers(secondCallInit.headers);
    expect(headers.get("X-CSRF-Token")).toBe("csrf_run");
  });

  it("createTeam sends CSRF header and mutation payload", async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse({
          ok: true,
          data: {
            authenticated: true,
            csrf_token: "csrf_team_create",
            user: { id: "2", display_name: "QA2" },
          },
        })
      )
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { team_id: "77" } }));

    const { apiClient } = await import("../../src/services/apiClient");
    const response = await apiClient.createTeam("My Squad", true);

    expect(response).toEqual({ ok: true, data: { team_id: "77" } });

    const secondCallInit = fetchMock.mock.calls[1]?.[1] as RequestInit;
    const headers = new Headers(secondCallInit.headers);
    expect(headers.get("X-CSRF-Token")).toBe("csrf_team_create");
    expect(secondCallInit.method).toBe("POST");
    expect(secondCallInit.body).toBe(JSON.stringify({ name: "My Squad", make_active: true }));
  });

  it("activateTeam and updateTeam both source CSRF from session", async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse({
          ok: true,
          data: {
            authenticated: true,
            csrf_token: "csrf_activate",
            user: { id: "3", display_name: "QA3" },
          },
        })
      )
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: {} }))
      .mockResolvedValueOnce(
        jsonResponse({
          ok: true,
          data: {
            authenticated: true,
            csrf_token: "csrf_update",
            user: { id: "3", display_name: "QA3" },
          },
        })
      )
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: {} }));

    const { apiClient } = await import("../../src/services/apiClient");
    await apiClient.activateTeam("10");
    await apiClient.updateTeam("10", {
      unit_ids: ["101", "102"],
      formation: [
        { cell: "0,0", unit_instance_id: "101" },
        { cell: "0,1", unit_instance_id: "102" },
      ],
    });

    const activateInit = fetchMock.mock.calls[1]?.[1] as RequestInit;
    const activateHeaders = new Headers(activateInit.headers);
    expect(activateHeaders.get("X-CSRF-Token")).toBe("csrf_activate");
    expect(activateInit.method).toBe("POST");

    const updateInit = fetchMock.mock.calls[3]?.[1] as RequestInit;
    const updateHeaders = new Headers(updateInit.headers);
    expect(updateHeaders.get("X-CSRF-Token")).toBe("csrf_update");
    expect(updateInit.method).toBe("PUT");
    expect(updateInit.body).toBe(
      JSON.stringify({
        unit_ids: ["101", "102"],
        formation: [
          { cell: "0,0", unit_instance_id: "101" },
          { cell: "0,1", unit_instance_id: "102" },
        ],
      })
    );
  });

  it("mutation methods propagate non-2xx response errors", async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse({
          ok: true,
          data: {
            authenticated: true,
            csrf_token: "csrf_error",
            user: { id: "4", display_name: "QA4" },
          },
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ ok: false, error: { code: "csrf_invalid" } }), {
          status: 403,
          statusText: "Forbidden",
          headers: { "Content-Type": "application/json" },
        })
      );

    const { apiClient } = await import("../../src/services/apiClient");

    await expect(apiClient.createTeam("Broken", true)).rejects.toThrow(
      "API 403 Forbidden:"
    );
  });
});
