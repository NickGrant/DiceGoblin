# Backend API Contracts - Current Alpha Surface

Status: active  
Last Updated: 2026-06-21  
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
- `POST /api/v1/auth/logout`
- `GET /api/v1/session`

Current behavior:

- session bootstrap tells the frontend whether the user is authenticated
- session payload also supplies the CSRF token used by mutating requests
- logout should clear local shell state even if the backend request fails

## Profile And Shared Player State

Current routes:

- `GET /api/v1/profile`
- `GET /api/v1/health`
- `GET /api/v1/abilities`

Current behavior:

- profile is the main shared data payload for the authenticated shell
- it contains energy, currency, active run, squads, units, dice, unlocks, and region access data
- the frontend refreshes profile after most successful mutations

## Shop And Academy

Current routes:

- `GET /api/v1/shop`
- `POST /api/v1/shop/purchase`
- `GET /api/v1/academy`
- `POST /api/v1/academy/unlock-unit-type`

Current behavior:

- shop returns starter inventory, daily deals, and feature unlocks
- academy returns unit-type unlock catalog
- purchases and unlocks are backend-authoritative and refresh profile state after success

## Run Flow

Current routes:

- `GET /api/v1/runs/current`
- `POST /api/v1/runs`
- `POST /api/v1/runs/:runId/abandon`
- `POST /api/v1/runs/:runId/exit`
- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve`
- `POST /api/v1/runs/:runId/nodes/:nodeId/rest/open`
- `POST /api/v1/runs/:runId/nodes/:nodeId/rest/finalize`

Current behavior:

- only one active run is supported at a time
- region start consumes energy through run creation
- node resolution is backend-authoritative
- rest has explicit open and finalize steps
- abandon and exit both produce summary-relevant run state

## Battles

Current routes:

- `GET /api/v1/battles/:battleId/log`
- `POST /api/v1/battles/:battleId/claim`

Current behavior:

- battle logs are fetched and rendered after node resolution
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
- `POST /api/v1/units/:unitInstanceId/dice/equip`
- `POST /api/v1/units/:unitInstanceId/dice/unequip`
- `POST /api/v1/dice/:diceInstanceId/sell`

Current behavior:

- promotion options are fetched separately from the shared profile
- promotion, capstone choice, rename, loadout edits, and slot-dice changes all refresh profile state afterward
- dice selling is a direct mutation and also refreshes profile state

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
- `POST /api/v1/debug/grant/currency`
- `POST /api/v1/debug/grant/unit`
- `POST /api/v1/debug/grant/dice`
- `POST /api/v1/debug/grant/region-item`
- `POST /api/v1/debug/units/set-level`
- `POST /api/v1/debug/reset-account`

Current behavior:

- these routes are intended for non-production testing workflows
- frontend access is gated by runtime config, not by a separate public product flow

## Documentation Rule

- If endpoint names in this file ever disagree with `backend/public/index.php`, treat the router as source of truth and update this file immediately.
