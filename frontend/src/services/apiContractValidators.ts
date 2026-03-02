import type { ProfileResponse, RunResponse, SessionResponse } from "../types/ApiResponse";

type RecordValue = Record<string, unknown>;

function isRecord(value: unknown): value is RecordValue {
  return typeof value === "object" && value !== null;
}

function isApiErrorShape(value: unknown): boolean {
  if (!isRecord(value)) return false;
  if (value.ok !== false) return false;
  if (!isRecord(value.error)) return false;
  return typeof value.error.code === "string" && typeof value.error.message === "string";
}

function assertValidErrorOrOkEnvelope(value: unknown, endpoint: string): asserts value is RecordValue {
  if (!isRecord(value)) {
    throw new Error(`Invalid ${endpoint} response: expected object envelope.`);
  }

  if (value.ok === false) {
    if (!isApiErrorShape(value)) {
      throw new Error(`Invalid ${endpoint} response: malformed error envelope.`);
    }
    return;
  }

  if (value.ok !== true || !isRecord(value.data)) {
    throw new Error(`Invalid ${endpoint} response: expected ok/data envelope.`);
  }
}

export function validateSessionResponse(value: unknown): SessionResponse {
  assertValidErrorOrOkEnvelope(value, "/api/v1/session");
  if (value.ok === false) return value as SessionResponse;

  const data = value.data;
  if (!isRecord(data)) {
    throw new Error("Invalid /api/v1/session response: data must be object.");
  }
  if (typeof data.authenticated !== "boolean") {
    throw new Error("Invalid /api/v1/session response: data.authenticated must be boolean.");
  }
  if (typeof data.csrf_token !== "string") {
    throw new Error("Invalid /api/v1/session response: data.csrf_token must be string.");
  }

  return value as SessionResponse;
}

export function validateProfileResponse(value: unknown): ProfileResponse {
  assertValidErrorOrOkEnvelope(value, "/api/v1/profile");
  if (value.ok === false) return value as ProfileResponse;

  const data = value.data;
  if (!isRecord(data)) {
    throw new Error("Invalid /api/v1/profile response: data must be object.");
  }
  if (typeof data.server_time_iso !== "string") {
    throw new Error("Invalid /api/v1/profile response: data.server_time_iso must be string.");
  }
  if (!Array.isArray(data.squads)) {
    throw new Error("Invalid /api/v1/profile response: data.squads must be array.");
  }
  if (!Array.isArray(data.units) || !Array.isArray(data.dice)) {
    throw new Error("Invalid /api/v1/profile response: data.units and data.dice must be arrays.");
  }
  if (!isRecord(data.currency) || typeof data.currency.soft !== "number" || typeof data.currency.hard !== "number") {
    throw new Error("Invalid /api/v1/profile response: data.currency.soft/hard must be numbers.");
  }
  if (
    !isRecord(data.energy)
    || typeof data.energy.current !== "number"
    || typeof data.energy.max !== "number"
    || typeof data.energy.regen_rate_per_hour !== "number"
    || typeof data.energy.last_regen_at !== "string"
  ) {
    throw new Error("Invalid /api/v1/profile response: malformed energy contract.");
  }
  if (!Array.isArray(data.region_unlocks) || !Array.isArray(data.region_items)) {
    throw new Error("Invalid /api/v1/profile response: region arrays are required.");
  }
  if (data.active_run !== null && !isRecord(data.active_run)) {
    throw new Error("Invalid /api/v1/profile response: data.active_run must be object|null.");
  }

  return value as ProfileResponse;
}

export function validateCurrentRunResponse(value: unknown): RunResponse {
  assertValidErrorOrOkEnvelope(value, "/api/v1/runs/current");
  if (value.ok === false) return value as RunResponse;

  const data = value.data;
  if (!isRecord(data)) {
    throw new Error("Invalid /api/v1/runs/current response: data must be object.");
  }
  if (!("run" in data) || !("map" in data)) {
    throw new Error("Invalid /api/v1/runs/current response: data.run and data.map are required.");
  }

  if (data.run !== null && !isRecord(data.run)) {
    throw new Error("Invalid /api/v1/runs/current response: data.run must be object|null.");
  }

  if (data.map !== null) {
    if (!isRecord(data.map)) {
      throw new Error("Invalid /api/v1/runs/current response: data.map must be object|null.");
    }
    if (!Array.isArray(data.map.nodes) || !Array.isArray(data.map.edges)) {
      throw new Error("Invalid /api/v1/runs/current response: map.nodes/edges must be arrays.");
    }
  }

  return value as RunResponse;
}
