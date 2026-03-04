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

  it("rest workflow mutation methods include CSRF token", async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { authenticated: true, csrf_token: "csrf_rest" } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { run_id: "1", node_id: "10", status: "open", team_id: "1", unit_ids: [], formation: [], run_unit_state: [] } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { authenticated: true, csrf_token: "csrf_rest2" } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { run_id: "1", node_id: "10", status: "open", team_id: "1", unit_ids: ["11"], formation: [], run_unit_state: [] } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { authenticated: true, csrf_token: "csrf_rest3" } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { run_id: "1", node: { id: "10", status: "completed" }, next: { unlocked_node_ids: [] }, progression: [] } }));

    const { apiClient } = await import("../../src/services/apiClient");
    await apiClient.openRest("1", "10");
    await apiClient.updateRestState("1", "10", { unit_ids: ["11"], formation: [] });
    await apiClient.finalizeRest("1", "10");

    const openInit = fetchMock.mock.calls[1]?.[1] as RequestInit;
    expect(new Headers(openInit.headers).get("X-CSRF-Token")).toBe("csrf_rest");
    expect(openInit.method).toBe("POST");

    const stateInit = fetchMock.mock.calls[3]?.[1] as RequestInit;
    expect(new Headers(stateInit.headers).get("X-CSRF-Token")).toBe("csrf_rest2");
    expect(stateInit.method).toBe("PUT");

    const finalizeInit = fetchMock.mock.calls[5]?.[1] as RequestInit;
    expect(new Headers(finalizeInit.headers).get("X-CSRF-Token")).toBe("csrf_rest3");
    expect(finalizeInit.method).toBe("POST");
  });

  it("promotion and dice mutation methods include rest context when provided", async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { authenticated: true, csrf_token: "csrf_promote" } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { unit: { id: "11", tier: 2, level: 1, xp: 0 }, consumed_units: ["12", "13"] } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { authenticated: true, csrf_token: "csrf_equip" } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { unit_id: "11", equipped_dice: [] } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { authenticated: true, csrf_token: "csrf_unequip" } }))
      .mockResolvedValueOnce(jsonResponse({ ok: true, data: { unit_id: "11", equipped_dice: [] } }));

    const { apiClient } = await import("../../src/services/apiClient");
    await apiClient.promoteUnit("11", ["12", "13"], { runId: "44", nodeId: "7" });
    await apiClient.equipDice("11", "55", { runId: "44", nodeId: "7" });
    await apiClient.unequipDice("11", "55", { runId: "44", nodeId: "7" });

    const promoteInit = fetchMock.mock.calls[1]?.[1] as RequestInit;
    expect(new Headers(promoteInit.headers).get("X-CSRF-Token")).toBe("csrf_promote");
    expect(promoteInit.body).toBe(JSON.stringify({
      primary_unit_instance_id: 11,
      secondary_unit_instance_ids: [12, 13],
      run_id: 44,
      node_id: 7,
    }));

    const equipInit = fetchMock.mock.calls[3]?.[1] as RequestInit;
    expect(new Headers(equipInit.headers).get("X-CSRF-Token")).toBe("csrf_equip");
    expect(equipInit.body).toBe(JSON.stringify({ dice_instance_id: 55, run_id: 44, node_id: 7 }));

    const unequipInit = fetchMock.mock.calls[5]?.[1] as RequestInit;
    expect(new Headers(unequipInit.headers).get("X-CSRF-Token")).toBe("csrf_unequip");
    expect(unequipInit.body).toBe(JSON.stringify({ dice_instance_id: 55, run_id: 44, node_id: 7 }));
  });
});
