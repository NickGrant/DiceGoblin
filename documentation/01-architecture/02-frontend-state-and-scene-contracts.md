# Frontend State and Scene Contracts - MVP (Authoritative)

Status: active  
Last Updated: 2026-03-07  
Owner: Frontend  
Depends On: `frontend/src/game/config.ts`, `frontend/src/scenes/`, `frontend/src/services/apiClient.ts`

This document defines the runtime contracts for currently implemented Phaser scenes and clearly labels planned scenes that are not yet wired.

## 1. Core Principles

1. `BootScene` is the auth/session bootstrap gate.
2. Backend is source of truth for server-owned state (session, profile, run/map, node resolution, battle logs, claims).
3. Scene transitions should be explicit and deterministic.
4. Planned scenes/contracts are documented but not treated as active runtime behavior.
5. User-facing terminology should prefer `squad`; API compatibility identifiers may still use `team` in route and payload keys.
6. Debug-only scene entry is allowed through `debugScene` URL params for deterministic local screenshots and review workflows.

## 2. Implemented Scene Set

Configured in `frontend/src/game/config.ts`:

1. `BootScene`
2. `PreloadScene`
3. `LandingScene`
4. `HomeScene`
5. `RegionSelectScene`
6. `WarbandManagementScene`
7. `SquadDetailsScene`
8. `UnitDetailsScene`
9. `DiceInventoryScene`
10. `MapExplorationScene`
11. `NodeResolutionScene`
12. `RestManagementScene`
13. `RunEndSummaryScene`

## 3. Planned (Not Implemented Yet)

The following scenes are documented in broader design scope but are not currently in scene config:

- `CombatScene`
- `LootScene`
- `BossScene`
- `DiceDetailsScene`

## 4. Shared State Slices (Current)

### 4.1 Session Slice

Stored in registry via `RegistrySession`.

```ts
{
  isAuthenticated: boolean,
  user?: { id: string; displayName: string; avatarUrl?: string },
  csrfToken?: string
}
```

Writers:
- `BootScene` writes from `GET /api/v1/session`.

Readers:
- `PreloadScene`, `LandingScene`.

### 4.2 Profile Slice (Scene-local, not globally centralized)

`WarbandManagementScene` reads `GET /api/v1/profile` and derives local scene state:
- `units`
- `squads` (consumed as editable local squad state)
- active squad selection

### 4.3 Run Slice (Scene-local)

`MapExplorationScene` reads `GET /api/v1/runs/current` and stores current run payload scene-locally for node rendering.

## 5. Implemented Scene Contracts

### 5.1 BootScene

Allowed side-effects:
- `GET /api/v1/session`
- write session state into registry

Output:
- starts `PreloadScene`.

### 5.2 PreloadScene

Allowed side-effects:
- asset pack loading

Input:
- reads `RegistrySession`.

Output:
- if authenticated -> `HomeScene`
- else -> `LandingScene`

### 5.3 LandingScene

Allowed side-effects:
- OAuth redirect to `/auth/discord/start` (unauth path)

Input:
- reads `RegistrySession`.

Output:
- authenticated users can continue to `HomeScene`.

### 5.4 HomeScene

Allowed side-effects:
- `POST /api/v1/auth/logout`

Output:
- navigates to `RegionSelectScene`
- navigates to `WarbandManagementScene`
- navigates to `DiceInventoryScene`

### 5.5 RegionSelectScene

Allowed side-effects:
- via clickable region panel, starts run creation flow (`POST /api/v1/runs`) through client service

Output:
- transitions to `MapExplorationScene` after run start/navigation path.

### 5.6 MapExplorationScene

Allowed side-effects:
- `GET /api/v1/runs/current`
- `POST /api/v1/runs/:runId/abandon`

Output:
- transitions to `RestManagementScene` for rest nodes
- transitions to `NodeResolutionScene` for non-rest node resolution (`combat|loot|boss|exit`)
- transitions to `RunEndSummaryScene` for abandon and terminal run-end states
- renders directional unlock-path indicators from run graph edges

### 5.7 WarbandManagementScene

Allowed side-effects:
- `GET /api/v1/profile`
- `POST /api/v1/teams`

Behavior:
- acts as hub screen with two columns:
  - units list -> opens `UnitDetailsScene`
  - squad list + actions -> opens `SquadDetailsScene`

### 5.8 SquadDetailsScene

Allowed side-effects:
- `GET /api/v1/profile`
- `PUT /api/v1/teams/:teamId`
- `POST /api/v1/teams/:teamId/activate`

Behavior:
- edits saved squad membership/formation (not run-scoped snapshot)
- supports bench membership (`unit_ids` may include unplaced units)
- supports best-effort squad rename by passing `name` in update payload

### 5.9 UnitDetailsScene

Allowed side-effects:
- `GET /api/v1/profile`
- `POST /api/v1/units/:unitId/promote`

Behavior:
- displays unit stats/xp and equipped dice summary
- manages promotion primary/secondary selection
- routes to `DiceInventoryScene` for dice equip/unequip flow

### 5.10 DiceInventoryScene

Current scope:
- presentation shell scene with HUD/home navigation.

Planned extension:
- remains a dedicated inventory screen (not merged into unit details),
- participates in rest-management flow for allowed active-run equipment changes.

### 5.11 NodeResolutionScene

Allowed side-effects:
- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` for `combat|loot|boss`
- `POST /api/v1/runs/:runId/exit` for `exit`
- `GET /api/v1/runs/current` to decide map-return vs terminal summary

Behavior:
- shows unified node-resolution outcome surface for non-rest nodes
- supports retry from error state
- routes to `RunEndSummaryScene` on terminal outcomes
- routes back to `MapExplorationScene` for non-terminal outcomes with resolution feedback

## 6. Implemented Transition Matrix

- `BootScene -> PreloadScene`
- `PreloadScene -> HomeScene` (authed)
- `PreloadScene -> LandingScene` (not authed)
- `LandingScene -> HomeScene` (continue path when authed)
- `HomeScene -> RegionSelectScene`
- `HomeScene -> WarbandManagementScene`
- `HomeScene -> DiceInventoryScene`
- `RegionSelectScene -> MapExplorationScene`
- `WarbandManagementScene -> HomeScene` (home button)
- `WarbandManagementScene -> UnitDetailsScene`
- `WarbandManagementScene -> SquadDetailsScene`
- `UnitDetailsScene -> DiceInventoryScene` (returnable)
- `UnitDetailsScene -> WarbandManagementScene`
- `SquadDetailsScene -> WarbandManagementScene`
- `DiceInventoryScene -> HomeScene` (home button)
- `MapExplorationScene -> HomeScene` (home button)
- `MapExplorationScene -> RestManagementScene`
- `MapExplorationScene -> NodeResolutionScene` (`combat|loot|boss|exit`)
- `MapExplorationScene -> RunEndSummaryScene` (abandon and terminal state)
- `NodeResolutionScene -> MapExplorationScene` (non-terminal outcome)
- `NodeResolutionScene -> RunEndSummaryScene` (terminal outcome)
- `RunEndSummaryScene -> HomeScene`

Planned additions:
- `RestManagementScene -> MapExplorationScene` (finalize or cancel rest)
- `RunEndSummaryScene -> HomeScene|RegionSelectScene` (continue)

## 7. Known Gaps

- Dedicated combat replay/viewer scenes are still planned but not wired in config.
- No centralized frontend store for run/profile; state is scene-local.
- Dedicated dice-details scene contract is planned but not yet implemented.

## 8. Debug Scene Loader

The frontend supports a debug-only scene override for local review workflows.

- `debugScene=<scene key or alias>` routes from `PreloadScene` directly to the requested scene.
- `debugAuth=authenticated|guest|live` controls whether boot injects a debug registry session or uses the normal backend session check.
- `debugSceneData=<json object>` passes scene init payload for scenes that require identifiers such as `unitId`, `squadId`, `runId`, or `nodeId`.
- A window-level readiness marker (`window.__DG_DEBUG__`) is updated when a scene finishes its initial render/load phase so automation can wait before capturing screenshots.
