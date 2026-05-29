# Frontend State and Scene Contracts - Legacy Phaser Current State

Status: legacy-current-state  
Last Updated: 2026-05-28  
Owner: Frontend  
Depends On: `frontend/src/game/config.ts`, `frontend/src/scenes/`, `frontend/src/services/apiClient.ts`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`

This document defines the runtime contracts for the currently implemented Phaser frontend before the Angular migration. It is no longer the target architecture for new frontend work.

Target Angular frontend ownership is defined in:

- `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
- `documentation/01-architecture/06-angular-component-service-inventory.md`

Use this document to understand existing behavior, migration parity requirements, and legacy scene responsibilities.

## 1. Core Principles

1. In the legacy Phaser frontend, `BootScene` is the auth/session bootstrap gate.
2. Backend is source of truth for server-owned state (session, profile, run/map, node resolution, battle logs, claims).
3. Legacy scene transitions should remain explicit and deterministic while the Phaser implementation is still active.
4. Planned scenes/contracts are documented but not treated as active runtime behavior.
5. User-facing terminology should prefer `squad`; API compatibility identifiers may still use `team` in route and payload keys.
6. Debug-only scene entry is allowed through `debugScene` URL params for deterministic local screenshots and review workflows.
7. For new Angular work, route/page/facade contracts supersede scene contracts except where Phaser is explicitly retained as an embedded renderer.

## 2. Implemented Legacy Scene Set

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
14. `ShopScene`
15. `DevPanelScene` (debug-only when env-enabled)

## 3. Planned Legacy Phaser Scenes (Not Implemented Yet)

The following scenes are deferred or design-only and are not currently in scene config:

- `CombatScene`
- `LootScene`
- `BossScene`

Do not add these as full application scenes as part of the Angular migration unless there is a specific decision to retain the experience in Phaser. Prefer Angular pages with Phaser host components for canvas-owned playback/rendering.

## 4. Shared State Slices (Current Legacy Phaser)

### 4.1 Session Slice

Stored in registry via `RegistrySession`.

```ts
{
  isAuthenticated: boolean,
  user?: { id: string; displayName: string; avatarUrl?: string },
  csrfToken?: string
}
```

Legacy writers:

- `BootScene` writes from `GET /api/v1/session`.

Legacy readers:

- `PreloadScene`, `LandingScene`.

Angular migration target:

- Angular session service/facade owns bootstrap and exposes session state to route components and Phaser hosts.

### 4.2 Profile Slice (Scene-local, not globally centralized)

`WarbandManagementScene` reads `GET /api/v1/profile` and derives local scene state:

- `units`
- `squads` (consumed as editable local squad state)
- active squad selection

Angular migration target:

- `ProfileService`, `WarbandFacade`, `SquadDetailsFacade`, and `UnitDetailsFacade` own profile-derived state for page components.

### 4.3 Run Slice (Scene-local)

`MapExplorationScene` reads `GET /api/v1/runs/current` and stores current run payload scene-locally for node rendering.

Angular migration target:

- `RunService`, `RunMapFacade`, `NodeResolutionFacade`, and `RestManagementFacade` own run-derived state for page components and Phaser hosts.

## 5. Implemented Legacy Scene Contracts

### 5.1 BootScene

Allowed side-effects:

- `GET /api/v1/session`
- write session state into registry

Output:

- starts `PreloadScene`.

Angular migration target:

- Replace with Angular app/session initialization and routing decisions.

### 5.2 PreloadScene

Allowed side-effects:

- asset pack loading

Input:

- reads `RegistrySession`.

Output:

- if authenticated -> `HomeScene`
- else -> `LandingScene`

Angular migration target:

- Replace ordinary app preload with Angular shell/loading state.
- Keep Phaser asset loading only inside Phaser host components that require canvas assets.

### 5.3 LandingScene

Allowed side-effects:

- OAuth redirect to `/auth/discord/start` (unauth path)

Input:

- reads `RegistrySession`.

Output:

- authenticated users can continue to `HomeScene`.

Angular migration target:

- `/login` route and `LandingPageComponent`.

### 5.4 HomeScene

Allowed side-effects:

- `POST /api/v1/auth/logout`

Output:

- navigates to `RegionSelectScene`
- navigates to `WarbandManagementScene`
- navigates to `DiceInventoryScene`
- navigates to `ShopScene`
- when dev tooling is env-enabled, exposes entry to `DevPanelScene`

Angular migration target:

- `/home` route and `HomePageComponent`.

### 5.5 RegionSelectScene

Allowed side-effects:

- via clickable region panel, starts run creation flow (`POST /api/v1/runs`) through client service

Behavior:

- presents `The Farm` as the fixed tutorial route: `combat -> loot -> rest -> boss -> exit`
- keeps formation messaging aligned with combat by treating left as `Back` and right as `Front`

Output:

- transitions to `MapExplorationScene` after run start/navigation path.

Angular migration target:

- `/regions` route and `RegionSelectPageComponent`.

### 5.6 MapExplorationScene

Allowed side-effects:

- `GET /api/v1/runs/current`
- `POST /api/v1/runs/:runId/abandon`

Output:

- transitions to `RestManagementScene` for rest nodes
- transitions to `NodeResolutionScene` for non-rest node resolution (`combat|loot|boss|exit`)
- transitions to `RunEndSummaryScene` for abandon and terminal run-end states
- renders directional unlock-path indicators from run graph edges

Angular migration target:

- `/run/map` route and `RunMapPageComponent`.
- Evaluate DOM/SVG first. Retain Phaser through `RunMapCanvasHostComponent` only if canvas rendering is justified.

### 5.7 WarbandManagementScene

Allowed side-effects:

- `GET /api/v1/profile`
- `POST /api/v1/teams`

Behavior:

- acts as hub screen with two columns:
  - units list -> opens `UnitDetailsScene`
  - squad list + actions -> opens `SquadDetailsScene`

Angular migration target:

- `/warband` route and `WarbandPageComponent`.

### 5.8 SquadDetailsScene

Allowed side-effects:

- `GET /api/v1/profile`
- `PUT /api/v1/teams/:teamId`
- `POST /api/v1/teams/:teamId/activate`

Behavior:

- edits saved squad membership/formation (not run-scoped snapshot)
- supports bench membership (`unit_ids` may include unplaced units)
- supports best-effort squad rename by passing `name` in update payload
- displays a formation guide labeling left as `Back` and right as `Front`

Angular migration target:

- `/warband/squads/:squadId` route and `SquadDetailsPageComponent`.

### 5.9 UnitDetailsScene

Allowed side-effects:

- `GET /api/v1/profile`
- `POST /api/v1/units/:unitId/promote`

Behavior:

- displays unit stats/xp and equipped dice summary
- manages promotion primary/secondary selection
- routes to `DiceInventoryScene` for dice equip/unequip flow

Angular migration target:

- `/warband/units/:unitId` route and `UnitDetailsPageComponent`.

### 5.10 DiceInventoryScene

Current scope:

- presentation shell scene with HUD/home navigation.

Planned extension:

- remains a dedicated inventory screen (not merged into unit details),
- participates in rest-management flow for allowed active-run equipment changes.

Angular migration target:

- `/dice` route and `DiceInventoryPageComponent`.

### 5.11 NodeResolutionScene

Allowed side-effects:

- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` for `combat|loot|boss`
- `POST /api/v1/runs/:runId/exit` for `exit`
- `GET /api/v1/runs/current` to decide map-return vs terminal summary

Behavior:

- shows unified node-resolution outcome surface for non-rest nodes
- exposes compact replay controls for play/pause, previous/next event, 1x/2x/4x speed, and skip-to-outcome
- in dev mode, supports copying the current battle log JSON to clipboard
- supports retry from error state
- routes to `RunEndSummaryScene` on terminal outcomes
- routes back to `MapExplorationScene` for non-terminal outcomes with resolution feedback

Angular migration target:

- `/run/node/:nodeId` route and `NodeResolutionPageComponent`.
- Keep battle playback in Phaser through `CombatPlaybackHostComponent` if canvas animation remains valuable.
- Keep result/outcome controls in Angular.

### 5.12 DevPanelScene

Allowed side-effects:

- `GET /api/v1/debug/catalog`
- `POST /api/v1/debug/grant/currency`
- `POST /api/v1/debug/grant/unit`
- `POST /api/v1/debug/grant/dice`
- `POST /api/v1/debug/grant/region-item`
- `POST /api/v1/debug/reset-account`

Behavior:

- debug-only operator surface gated by environment flag
- supports grant and reset flows for local verification/UAT
- reflects current profile counts after mutations

Angular migration target:

- `/debug` route and `DebugPageComponent`.

### 5.13 ShopScene

Allowed side-effects:

- `GET /api/v1/shop`
- `POST /api/v1/shop/purchase`

Behavior:

- exposes between-run currency spending for common d4-d10 dice and Tier 1 units
- shows a server-day daily deal that persists until the next day or until purchased
- returns to `HomeScene` through a dedicated back action

Angular migration target:

- `/shop` route and `ShopPageComponent`.

## 6. Implemented Legacy Transition Matrix

- `BootScene -> PreloadScene`
- `PreloadScene -> HomeScene` (authed)
- `PreloadScene -> LandingScene` (not authed)
- `LandingScene -> HomeScene` (continue path when authed)
- `HomeScene -> RegionSelectScene`
- `HomeScene -> WarbandManagementScene`
- `HomeScene -> DiceInventoryScene`
- `HomeScene -> ShopScene`
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
- `ShopScene -> HomeScene`
- `HomeScene -> DevPanelScene` (debug-only)

## 7. Migration Use

When implementing Angular pages, use this document to preserve legacy behavior and route parity. Do not treat Phaser scenes as the preferred implementation unit for new UI unless the Angular frontend architecture plan explicitly assigns that surface to Phaser.
