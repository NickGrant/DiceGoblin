# Backend API Contracts — MVP (v1)

Status: active  
Last Updated: 2026-03-02  
Owner: Backend/API  
Depends On: `backend/public/index.php`, `backend/src/Controllers/`, `backend/src/Services/ProfileService.php`

## 0. Purpose and Scope

This document defines the **HTTP contract** between the Dice Goblins frontend (Phaser) and backend (PHP) for MVP.

In-scope:
- Discord OAuth login and cookie-backed sessions
- Fetching canonical player/run state
- Starting/resuming/abandoning a run
- Resolving nodes (including server-authoritative combat) and persisting outcomes
- Fetching battle logs for replay
- Claiming rewards idempotently
- Minimal endpoints to support warband, units, dice, and promotions required by MVP loop

Out-of-scope:
- Multiplayer/PvP endpoints
- Admin consoles beyond minimal debug hooks
- Cross-device conflict resolution beyond â€œone active runâ€

---

## 1. Conventions

### 1.1 Base URL and Versioning
- All API endpoints are versioned: `/api/v1/...`
- Non-API auth endpoints live under `/auth/...`

### 1.2 Authentication
- Authentication is **cookie-based** (server session).
- All `/api/v1/*` endpoints require an authenticated session unless explicitly documented otherwise.
- If unauthenticated:
  - Return `401 Unauthorized` (JSON error), **not** HTML redirects.

### 1.3 CSRF (Mutating Requests)
Because the API uses cookies, **all mutating endpoints** MUST require CSRF protection.

Contract:
- `GET /api/v1/session` returns a `csrf_token`
- Client sends `X-CSRF-Token: <token>` on all `POST/PUT/PATCH/DELETE`

If missing/invalid:
- Return `403 Forbidden` with error code `csrf_invalid`

### 1.4 Content Type
- Request/response JSON:
  - Request header: `Content-Type: application/json`
  - Response header: `Content-Type: application/json; charset=utf-8`

### 1.5 ID Encoding
MySQL uses `BIGINT`. JavaScript cannot safely represent all 64-bit integers.
- **All IDs are returned as strings** in JSON.
- Any request body field containing an ID must also be a string.

### 1.6 Timestamp Format
All timestamps are ISO 8601 strings in UTC:
- Example: `"2026-01-10T08:15:30.123Z"`

### 1.7 Response Envelope
All API responses use a consistent envelope:

**Success**
```json
{
  "ok": true,
  "data": { }
}
```

**Error**
```json
{
  "ok": false,
  "error": {
    "code": "string_machine_code",
    "message": "Human-readable summary",
    "details": { }
  }
}
```

### 1.8 Pagination
For list endpoints (if any in MVP):
- Query: `?limit=50&cursor=<opaque>`
- Response:
```json
{
  "ok": true,
  "data": {
    "items": [],
    "next_cursor": "opaque_or_null"
  }
}
```

### 1.9 Idempotency
For actions that must not duplicate side effects (notably â€œresolve nodeâ€ and â€œclaim rewardsâ€), the backend must be idempotent.

Contract:
- Client may send header: `Idempotency-Key: <uuid>`
- Backend must also be idempotent **without** the header when it can derive uniqueness from `(user_id, run_id, node_id)` or `battle_id`.

---

## 2. Auth Endpoints

### 2.1 Start Discord OAuth
`GET /auth/discord/start`

- Sets OAuth state in session
- Redirects user to Discord authorization URL

Response: **302 Redirect**

### 2.2 Discord Callback
`GET /auth/discord/callback?code=...&state=...`

- Validates state
- Exchanges code for token
- Fetches Discord identity
- Upserts user
- Regenerates session id
- Sets `user_id` in session
- Redirects back to frontend

Response: **302 Redirect**

### 2.3 Logout
`POST /auth/logout`

- Destroys server session (or clears session cookie)

Success:
```json
{ "ok": true, "data": {} }
```

---

## 3. Session and Profile

### 3.1 Get Session
`GET /api/v1/session`

Returns authenticated identity plus CSRF token used for mutating requests.

Success:
```json
{
  "ok": true,
  "data": {
    "user": {
      "id": "123",
      "display_name": "Nick",
      "avatar_url": "https://..."
    },
    "csrf_token": "base64_or_hex",
    "server_time_iso": "2026-01-10T08:15:30.123Z"
  }
}
```

Errors:
- `401 Unauthorized` if no session

### 3.2 Get Profile Snapshot
`GET /api/v1/profile`

Returns a single payload the client can use to hydrate most screens:
- warband/team overview
- owned units
- owned dice
- active run summary (if any)
- region unlocks / meta inventory (promotion items)

Success (shape is illustrative; keep stable keys):
```json
{
  "ok": true,
  "data": {
    "teams": [
      { "id": "10", "name": "Main", "is_active": true }
    ],
    "units": [
      {
        "id": "2001",
        "unit_type_id": "17",
        "name": "Goblin Spear",
        "level": 2,
        "xp": 40,
        "max_level": 12,
        "growth_per_ability_per_level": { "attack": 1, "defense": 1, "max_hp": 2 }
      }
    ],
    "dice": [
      {
        "id": "9001",
        "dice_definition_id": "5",
        "rarity": "uncommon",
        "sides": 6,
        "affixes": [{ "affix_definition_id": "12", "tier": 1 }]
      }
    ],
    "region_items": [
      { "region_item_id": "roc_egg", "quantity": 1 }
    ],
    "active_run": {
      "run_id": "777",
      "region_id": "2",
      "status": "active"
    }
  }
}
```

---

## 4. Regions

### 4.1 List Regions
`GET /api/v1/regions`

Success:
```json
{
  "ok": true,
  "data": {
    "regions": [
      { "id": "1", "slug": "mountains", "name": "Mountains" }
    ]
  }
}
```

### 4.2 Get Region Unlocks (Optional; can be folded into /profile)
`GET /api/v1/regions/unlocks`

Success:
```json
{
  "ok": true,
  "data": {
    "unlocks": [
      { "region_id": "1", "unlocked": true }
    ]
  }
}
```

---

## 5. Teams (Warband)

### 5.1 List Teams
`GET /api/v1/teams`

### 5.2 Create Team
`POST /api/v1/teams`
```json
{ "name": "New Team" }
```

### 5.3 Set Active Team
`POST /api/v1/teams/:teamId/activate`

Notes:
- Exactly one active team per user.

Errors:
- `404 not_found`
- `403 forbidden` if team not owned

### 5.4 Update Team Composition / Formation
`PUT /api/v1/teams/:teamId/formation`

Request:
```json
{
  "unit_ids": ["2001", "2002", "2003"],
  "formation": [
    { "unit_id": "2001", "row": 0, "col": 1 },
    { "unit_id": "2002", "row": 1, "col": 0 }
  ]
}
```
Notes
- Updates saved definition only, does not update any current run snapshots

---

## 6. Units and Promotions

### 6.1 List Unit Types (Static Catalog)
`GET /api/v1/unit-types`

### 6.2 Promote Unit
`POST /api/v1/units/:unitInstanceId/promote`

Request (promotion path is data-driven; backend validates inventory + rules):
```json
{
  "promotion_id": "tier3_roc_path"
}
```

Success:
```json
{
  "ok": true,
  "data": {
    "unit": {
      "id": "2001",
      "unit_type_id": "17",
      "level": 3,
      "xp": 0
    },
    "consumed": {
      "region_items": [{ "region_item_id": "roc_egg", "quantity": 1 }]
    }
  }
}
```

Errors:
- `409 conflict` code `promotion_requirements_not_met`
- `409 conflict` code `unit_in_active_run` (if MVP forbids promotions mid-run; if allowed, omit)

---

## 7. Dice Inventory and Equipment

### 7.1 List Dice Definitions (Static Catalog)
`GET /api/v1/dice-definitions`

### 7.2 Equip Dice to Unit
`POST /api/v1/units/:unitInstanceId/dice/equip`

Request:
```json
{
  "dice_instance_id": "9001"
}
```

Success:
```json
{
  "ok": true,
  "data": {
    "unit_id": "2001",
    "equipped_dice": ["9001", "9002"]
  }
}
```

### 7.3 Unequip Dice
`POST /api/v1/units/:unitInstanceId/dice/unequip`
```json
{ "dice_instance_id": "9001" }
```

---

## 8. Runs, Map, and Nodes

### 8.1 Start Run
`POST /api/v1/runs`

Request:
```json
{
  "region_id": "1",
  "team_id": "10"
}
```

Rules:
- User may have **only one active run** at a time.
- Run map is generated server-side (seed persisted).
- On run start, server snapshots  the team's unit membership + formation into run-scoped state.
- Team is locked for the run; editing is disallowed except for Rest nodes.
- Combat/encounters reference run-scoped snapshot.

Success:
```json
{
  "ok": true,
  "data": {
    "run_id": "777",
    "region_id": "1",
    "seed": "123456789",
    "status": "active"
  }
}
```

Errors:
- `409 conflict` code `run_already_active`

### 8.2 Get Active Run
`GET /api/v1/runs/active`

Success (if active):
```json
{
  "ok": true,
  "data": {
    "run_id": "777",
    "region_id": "1",
    "status": "active"
  }
}
```

Success (if none active):
```json
{ "ok": true, "data": { "run_id": null } }
```

### 8.3 Get Run Map State
`GET /api/v1/runs/:runId/map`

Returns node list, edges, and current node statuses.
```json
{
  "ok": true,
  "data": {
    "run_id": "777",
    "nodes": [
      {
        "id": "300",
        "type": "combat",
        "status": "available",
        "encounter_template_id": "44"
      }
    ],
    "edges": [
      { "from": "300", "to": "301" }
    ]
  }
}
```

### 8.4 Abandon Run (Quit)
`POST /api/v1/runs/:runId/abandon`

Success:
```json
{
  "ok": true,
  "data": {
    "run_id": "777",
    "status": "abandoned"
  }
}
```

### 8.5 Run Formation
`PUT /api/v1/runs/:runId/formation`

Request:
```json
{
  "unit_ids": ["2001", "2002"],
  "formation": [
    { "unit_id": "2001", "row": 0, "col": 0 },
    { "unit_id": "2002", "row": 1, "col": 1 }
  ]
}
```

Rules
- Allowed only if
  - run is active AND
  - the player is currently resolving a Rest node (or the active node type is rest) AND
  - the node is not yet finalized
- Updates run snapshot only

Errors:
- `run_not_active`
- `forbidden`
- `validation_error`

---

## 9. Node Resolution (Server-Authoritative)

### 9.1 Resolve Node
`POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

This endpoint is the core of server authority:
- For **combat nodes**: server computes battle outcome once, persists `battles` + `battle_logs`.
- For **non-combat nodes** (loot/rest/event): server applies deterministic effects and persists updated run state.

Request:
```json
{
  "team_id": "10"
}
```

Success (combat example):
```json
{
  "ok": true,
  "data": {
    "node": { "id": "300", "status": "completed" },
    "battle": {
      "battle_id": "555",
      "outcome": "victory",
      "rounds": 3,
      "ticks": 60,
      "status": "completed"
    },
    "next": {
      "unlocked_node_ids": ["301"]
    }
  }
}
```

Idempotency contract:
- If called multiple times for the same `(runId,nodeId)`:
  - Return the **existing** battle / outcome payload.
  - Do not generate a second battle or apply effects twice.

Errors:
- `409 conflict` code `node_not_available`
- `409 conflict` code `node_already_resolved` (optional; returning existing is preferred)
- `403 forbidden` if run not owned by user

---

## 10. Battles and Replay

### 10.1 Get Battle Summary (Optional; can be folded into resolve response)
`GET /api/v1/battles/:battleId`

### 10.2 Get Battle Log
`GET /api/v1/battles/:battleId/log`

Returns the exact stored log for client replay. Backend never expects the client to re-simulate.

Success:
```json
{
  "ok": true,
  "data": {
    "battle_id": "555",
    "rules_version": "combat_v1",
    "log": {
      "meta": {
        "ticksPerRound": 20,
        "rng": { "seed": 12345 },
        "createdAtIso": "2026-01-10T08:15:30.123Z",
        "version": 1
      },
      "events": [
        { "type": "phase_start", "round": 1, "tick": 1, "phase": "player_status" }
      ]
    }
  }
}
```

---

## 11. Rewards and Claiming (Idempotent)

### 11.1 Claim Battle Rewards
`POST /api/v1/battles/:battleId/claim`

Rules:
- Claim is a single step.
- Claim must be **idempotent**.
- If already claimed, return the same â€œclaimedâ€ result (or a clear status).
- `xp_total` is the XP award amount per surviving fielded unit (not split).
- Backend applies XP only to units that were fielded and not defeated.
- Units at max level do not gain XP (award is ignored for them).

Request:
```json
{}
```

Success:
```json
{
  "ok": true,
  "data": {
    "battle_id": "555",
    "status": "claimed",
    "rewards": {
      "xp_total": 20,
      "new_dice_instance_ids": ["9100"],
      "region_items": [{ "region_item_id": "roc_egg", "quantity": 1 }]
    },
    "updated_run_unit_state": [
      { "unit_instance_id": "2001", "hp": 8, "status_effects": [] }
    ],
    "xp": {
      "award_per_unit": 20,
      "applied_unit_instance_ids": ["2001", "2002"],
      "ignored_at_cap_unit_instance_ids": ["2009"]
    },
    "updated_units": [
      { "unit_instance_id": "2001", "level": 3, "xp": 5 },
      { "unit_instance_id": "2002", "level": 2, "xp": 60 }
    ]
  }
}
```

Errors:
- `409 conflict` code `battle_not_completed`
- `403 forbidden` if battle not owned by user

---

## 12. Debug Endpoints (MVP Optional)

All debug endpoints:
- MUST be disabled in production (or gated behind env flag / admin allowlist).
- MUST not mutate real economy unless explicitly desired.

Suggested minimal set:

### 12.1 Seeded Run Start
`POST /api/v1/debug/runs`
```json
{ "region_id": "1", "team_id": "10", "seed": "12345" }
```

### 12.2 Grant Items
`POST /api/v1/debug/grant`
```json
{ "region_item_id": "roc_egg", "quantity": 5 }
```

---

## 13. Error Codes (Canonical)

Use these codes consistently:

- `unauthorized`
- `forbidden`
- `not_found`
- `csrf_invalid`
- `validation_error`
- `run_already_active`
- `run_not_active`
- `node_not_available`
- `node_already_resolved`
- `battle_not_completed`
- `promotion_requirements_not_met`

Example:
```json
{
  "ok": false,
  "error": {
    "code": "run_already_active",
    "message": "User already has an active run.",
    "details": { "active_run_id": "777" }
  }
}
```

---

## 14. MVP Invariants (Backend Must Enforce)

- Exactly one active run per user
- One battle per (run_id, node_id)
- Battle logs are immutable once persisted
- Reward claim is idempotent (no double awards)
- All run state used for resume is server-canonical
- IDs are strings in API responses to avoid JS precision loss



