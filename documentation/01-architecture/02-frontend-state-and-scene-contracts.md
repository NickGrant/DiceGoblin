# Frontend State and Scene Contracts - MVP (Authoritative)

Status: active  
Last Updated: 2026-03-02  
Owner: Frontend  
Depends On: `frontend/src/game/config.ts`, `frontend/src/scenes/`, `frontend/src/services/apiClient.ts`

This document defines the runtime contracts for currently implemented Phaser scenes and clearly labels planned scenes that are not yet wired.

## 1. Core Principles

1. `BootScene` is the auth/session bootstrap gate.
2. Backend is source of truth for server-owned state (session, profile, run/map, node resolution, battle logs, claims).
3. Scene transitions should be explicit and deterministic.
4. Planned scenes/contracts are documented but not treated as active runtime behavior.

## 2. Implemented Scene Set

Configured in `frontend/src/game/config.ts`:

1. `BootScene`
2. `PreloadScene`
3. `LandingScene`
4. `HomeScene`
5. `RegionSelectScene`
6. `WarbandManagementScene`
7. `DiceInventoryScene`
8. `MapExplorationScene`

## 3. Planned (Not Implemented Yet)

The following scenes are documented in broader design scope but are not currently in scene config:

- `CombatScene`
- `LootScene`
- `RestScene`
- `BossScene`
- `UnitDetailsScene`
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
- `squads` (consumed as editable local team state)
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
- node interactions eventually call `POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

Output:
- currently remains in map flow; planned encounter scenes are not yet active.

### 5.7 WarbandManagementScene

Allowed side-effects:
- `GET /api/v1/profile`
- `POST /api/v1/teams`
- `POST /api/v1/teams/:teamId/activate`
- `PUT /api/v1/teams/:teamId`

Behavior:
- edits saved squad membership/formation (not run-scoped snapshot)
- supports bench membership (`unit_ids` may include unplaced units)

### 5.8 DiceInventoryScene

Current scope:
- presentation shell scene with HUD/home navigation.

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
- `DiceInventoryScene -> HomeScene` (home button)
- `MapExplorationScene -> HomeScene` (home button)

## 7. Known Gaps

- Encounter scene split (`Combat/Loot/Rest/Boss`) is planned but not wired in config.
- No centralized frontend store for run/profile; state is scene-local.
- Dedicated unit/dice details scene contracts are planned but not yet implemented.
