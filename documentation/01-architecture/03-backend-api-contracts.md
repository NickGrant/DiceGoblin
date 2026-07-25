# Backend API Contracts - Current Alpha Surface

Status: active  
Last Updated: 2026-07-25
Owner: Backend/API  
Depends On: `backend/public/index.php`, `backend/src/Controllers/`, `frontend/src/app/core/services/api-http/api-http.service.ts`

## Purpose

- Summarize the backend routes the current frontend actually depends on.
- Describe the current contract style without pretending every endpoint is a frozen public API.
- Replace older speculative API descriptions with the implemented alpha surface.

## Core Contract Rules

- API routes live under `/api/v1/...`
- OAuth entry routes live under `/auth/...`
- authentication is cookie/session based
- mutating requests use CSRF protection
- the backend is authoritative for session, profile, purchases, runs, battles, progression, and rewards
- JSON ids should be treated as strings by the frontend even when backed by numeric database ids

## Authentication And Session

Current routes:

- `GET /auth/discord/start`
- `GET /auth/discord/callback`
- `POST /api/v1/auth/local/register`
- `POST /api/v1/auth/local/login`
- `POST /api/v1/auth/local/password-reset/request`
- `POST /api/v1/auth/local/password-reset/confirm`
- `POST /api/v1/auth/logout`
- `GET /api/v1/session`

Current behavior:

- local registration accepts email, password, and display name, then establishes the normal session
- local login accepts email and password, then establishes the normal session
- password reset request accepts email and returns a generic success payload without revealing whether an account exists
- password reset confirm accepts a reset token and new password, updates the hash, consumes the token, and establishes the normal session
- session bootstrap tells the frontend whether the user is authenticated
- session payload also supplies the CSRF token used by mutating requests
- logout should clear local shell state even if the backend request fails
- auth responses never expose password hashes or local credential internals
- non-production reset requests may include a raw reset token for local Docker/dev workflows until email delivery exists

## Profile And Shared Player State

Current routes:

- `GET /api/v1/profile`
- `GET /api/v1/health`
- `GET /api/v1/abilities`

Current behavior:

- profile is the main shared data payload for the authenticated shell
- it contains energy, currency, active run, squads, units, dice, unlocks, and region access data
- `currency` includes `soft`, `hard`, and `raw_chaos`
- profile now includes a backend-authored `regions` catalog with unlock and inferred completion state for each enabled biome
- profile includes `items` for generic account inventory such as lineage materials and boss catalysts; `region_items` remains a legacy compatibility payload
- unit records include `kin_*` aliases plus legacy `splice_variant_*` fields; clients should prefer `kin_*` and treat splice fields as compatibility-only
- `active_run` includes region metadata such as slug and theme so the frontend does not need to infer biome presentation from unlock arrays
- `objectives` contains backend-derived passive guidance records with id, status, priority, progress, route, and optional metadata
- the frontend refreshes profile after most successful mutations

### Kin compatibility plan

The current unit, shop, and reward payloads expose `kin_*` aliases while still carrying legacy `splice_variant_*` fields because those names match current storage. New endpoints and new response objects should use `kin_*` and `lineage_*` names instead.

A later compatibility migration should:

- rename services and frontend helpers to kin/lineage terms first, leaving database columns untouched
- add a forward migration for storage/table names only after the API aliases are deployed
- remove legacy response fields in a separate cleanup after clients have moved to the kin names

## Shop And Academy

Current routes:

- `GET /api/v1/shop`
- `POST /api/v1/shop/purchase`
- `GET /api/v1/academy`
- `POST /api/v1/academy/unlock-unit-type`

Current behavior:

- shop returns starter inventory, daily deals, and feature unlocks
- academy returns unit-type unlock catalog entries with backend-authored `is_available` and `requirements` fields
- purchases and unlocks are backend-authoritative and refresh profile state after success

## Bounty Board

Current routes:

- `GET /api/v1/bounties`
- `POST /api/v1/bounties/accept`
- `POST /api/v1/bounties/sync`
- `POST /api/v1/bounties/:userBountyId/claim`

Current behavior:

- the board seeds a small backend-authored bounty definition set into the existing bounty tables
- players may accept up to three active or completed-but-unclaimed bounties
- progress is derived idempotently from durable gameplay rows such as completed runs and claimed victorious battles
- claiming a completed bounty grants backend-authored rewards and returns the updated board state

## Run Flow

Current routes:

- `GET /api/v1/runs/current`
- `POST /api/v1/runs`
- `POST /api/v1/runs/:runId/abandon`
- `POST /api/v1/runs/:runId/exit`
- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve`
- `POST /api/v1/runs/:runId/nodes/:nodeId/rest/open`
- `POST /api/v1/runs/:runId/nodes/:nodeId/rest/finalize`
- `POST /api/v1/runs/:runId/nodes/:nodeId/chaos/generate`
- `POST /api/v1/runs/:runId/nodes/:nodeId/chaos/reroll`
- `POST /api/v1/runs/:runId/nodes/:nodeId/chaos/finalize`

Current behavior:

- only one active run is supported at a time
- region start consumes energy through run creation
- current run payloads include region metadata such as `region_slug`, `region_name`, `region_theme`, `recommended_level`, and `energy_cost`
- node resolution is backend-authoritative
- non-combat `hazard` and `shrine` nodes resolve through the same node-resolution endpoint and persist their generated results in the battle log/reward rows
- `chaos` nodes use dedicated slot-style reel endpoints:
  - generate creates or returns the persisted reel result
  - reroll changes one reel before completion when the reroll is still available
  - finalize completes the node, applies the bounded persisted-result reward, stores the reward payload, and returns the same payout on retry
- rest has explicit open and finalize steps
- abandon and exit both produce summary-relevant run state

## Battles

Current routes:

- `GET /api/v1/battles/:battleId/log`
- `POST /api/v1/battles/:battleId/claim`

Current behavior:

- node resolution responses may already include the battle log used by the current frontend
- `GET /api/v1/battles/:battleId/log` remains available as a direct backend route, but it is not the primary path used by the Angular run-node screen today
- claim finalizes rewards and may also finalize run summary state

## Units And Progression

Current routes:

- `GET /api/v1/units/:unitInstanceId/promotion-options`
- `POST /api/v1/units/:unitInstanceId/promote`
- `PUT /api/v1/units/:unitInstanceId/capstone`
- `PATCH /api/v1/units/:unitInstanceId/name`
- `PUT /api/v1/units/:unitInstanceId/loadout`
- `PUT /api/v1/units/:unitInstanceId/abilities/:abilityId/slots/:slotIndex/dice`
- `DELETE /api/v1/units/:unitInstanceId/abilities/:abilityId/slots/:slotIndex/dice`
- `POST /api/v1/dice/:diceInstanceId/sell`
- `POST /api/v1/dice/:diceInstanceId/salvage`

Current behavior:

- promotion options are fetched separately from the shared profile
- promotion, capstone choice, rename, loadout edits, and slot-dice changes all refresh profile state afterward
- dice selling is a direct mutation and also refreshes profile state
- dice salvage deletes an unequipped owned die, awards backend-calculated Raw Chaos, blocks equipped dice, and refreshes profile state

## Squads / Teams

Current routes:

- `POST /api/v1/teams`
- `POST /api/v1/teams/:teamId/activate`
- `PUT /api/v1/teams/:teamId`
- `DELETE /api/v1/teams/:teamId`

Current behavior:

- backend route names remain `teams` for compatibility
- the frontend should continue presenting this surface as `squads`

## Debug Surface

Current routes:

- `GET /api/v1/debug/catalog`
- `GET /api/v1/debug/seed-tables`
- `POST /api/v1/debug/grant/currency`
- `POST /api/v1/debug/grant/unit`
- `POST /api/v1/debug/grant/dice`
- `POST /api/v1/debug/grant/region-item`
- `POST /api/v1/debug/units/set-level`
- `POST /api/v1/debug/reset-account`

Current behavior:

- these routes are intended for non-production testing workflows
- frontend access is gated by runtime config, not by a separate public product flow
- seed-table browsing is read-only, allowlisted, and intended for inspecting authored catalog data without direct SQL access

## Documentation Rule

- If endpoint names in this file ever disagree with `backend/public/index.php`, treat the router as source of truth and update this file immediately.
