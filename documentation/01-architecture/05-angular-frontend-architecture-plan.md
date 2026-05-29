# Angular Frontend Architecture Plan

Status: proposed  
Last Updated: 2026-05-28  
Owner: Frontend  
Depends On: `frontend/src/game/config.ts`, `frontend/src/scenes/`, `frontend/src/services/apiClient.ts`, `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/07-ux-rebuild/01-all-up-component-list.md`

## Purpose

This document defines the target architecture for moving Dice Goblins from a Phaser-owned UI application to an Angular-owned application shell that embeds Phaser only where canvas rendering is still the right tool.

The PHP API remains the source of truth for session, profile, run, battle, shop, squad, unit, dice, reward, and debug state.

## Current Position

The current frontend is a TypeScript, Vite, and Phaser application. Phaser currently owns application boot, scene transitions, menu screens, management screens, map presentation, node resolution, feedback UI, and debug tooling.

The target frontend should move application flow, routing, data orchestration, forms, lists, dialogs, accessibility, and persistent shell UI into Angular.

## Ownership Boundary

Angular owns:

- application routing and page navigation
- startup/session checks
- persistent app shell and page layout
- API orchestration and state facades
- menus and management views
- forms, validation, dialogs, toasts, and loading/error states
- reusable UI components
- accessible keyboard navigation and focus management
- debug/operator panels that do not require canvas rendering

Phaser owns:

- combat playback and future battle visualization
- battle event timeline animation
- sprite-heavy board or grid rendering
- optional run-map canvas if the map becomes animated, pannable, or visually dense
- deterministic screenshot/capture workflows until Angular equivalents exist

Phaser should not own ordinary page composition, CRUD-style management UI, modal forms, list/grid browsing, or primary routing.

## Proposed Angular Layers

### App Shell

The shell wraps all authenticated and unauthenticated pages.

Likely Angular pieces:

- `AppComponent`
- `AppShellComponent`
- `AuthShellComponent`
- `GameShellComponent`
- `HudComponent`
- `HomeButtonComponent`
- `EnergyIndicatorComponent`
- `CurrencyIndicatorComponent`
- `PageFrameComponent`
- `BottomCommandStripComponent`

### API and State Layer

The existing monolithic frontend API client should become smaller domain services and route-facing facades.

Core services:

- `ApiHttpService`
- `SessionService`
- `ProfileService`
- `RunService`
- `NodeResolutionService`
- `BattleService`
- `SquadService`
- `UnitService`
- `DiceService`
- `ShopService`
- `RestService`
- `DebugService`

Route-facing facades:

- `AuthFacade`
- `ProfileFacade`
- `HomeFacade`
- `RegionSelectFacade`
- `RunMapFacade`
- `NodeResolutionFacade`
- `WarbandFacade`
- `SquadDetailsFacade`
- `UnitDetailsFacade`
- `DiceInventoryFacade`
- `RestManagementFacade`
- `RunEndSummaryFacade`
- `ShopFacade`
- `DebugFacade`

Facades expose view-ready state and commands. Components should not assemble raw API payloads unless they are small leaf components.

## Routing Model

Recommended initial Angular routes:

- `/login` -> landing/login
- `/home` -> home navigation
- `/regions` -> region selection
- `/run/map` -> run map
- `/run/node/:nodeId` -> node resolution shell
- `/run/rest/:nodeId` -> rest management
- `/run/summary` -> run end summary
- `/warband` -> warband management
- `/warband/squads/:squadId` -> squad details
- `/warband/units/:unitId` -> unit details
- `/dice` -> dice inventory
- `/shop` -> shop
- `/debug` -> debug panel, environment-gated

The previous Phaser scene names remain useful as migration labels, but Angular route names should describe player intent rather than engine scenes.

## Phaser Integration Model

Angular should own the lifecycle of Phaser instances. Phaser should be mounted inside explicit Angular host components rather than instantiated as the entire app.

Recommended hosts:

- `PhaserCanvasHostComponent`
- `CombatPlaybackHostComponent`
- `RunMapCanvasHostComponent`, only if map rendering stays canvas-based
- `SceneCaptureHostComponent`, debug-only if needed

Recommended bridge services:

- `PhaserGameFactoryService`
- `PhaserEventBridgeService`
- `BattlePlaybackBridgeService`
- `RunMapBridgeService`, only if the run map remains canvas-based

The host should create a Phaser instance when mounted, destroy it when the route/component is removed, pass immutable input snapshots into Phaser, and receive Phaser events through a small bridge.

## Migration Strategy

1. Preserve the current implementation in a legacy branch.
2. Add Angular architecture and migration inventory documents.
3. Create an Angular shell in a follow-up implementation task.
4. Port non-combat Phaser UI scenes to Angular route pages.
5. Keep the backend API stable during frontend migration.
6. Keep Phaser combat and playback isolated behind Angular host components.
7. Retire Phaser-only management scenes after their Angular equivalents are validated.

## Recommended Phaser Retention Decision

Keep Phaser for:

- battle/combat playback
- battle log timeline visualization
- animated grid/unit positioning
- sprite-heavy effects
- deterministic visual capture while still useful

Evaluate Angular DOM/SVG versus Phaser for:

- run map graph surface
- encounter node visuals
- animated reward reveal moments

Move to Angular:

- landing/login
- home
- region selection
- warband management
- squad details
- unit details
- dice inventory
- rest management
- run end summary
- shop
- dev/debug panel
- modals, confirmations, toasts, list/grid shells, and command strips

## Risks

- Duplicating state between Angular and Phaser can create stale UI.
- Reusing current Phaser component names directly in Angular can preserve poor boundaries.
- A full UI rewrite can regress working flows, so migration should proceed route by route.
- Canvas-hosted views need explicit teardown to avoid duplicate Phaser games and leaked listeners.

## Open Decisions

- Whether the run map should be Angular/SVG or Phaser canvas.
- Whether battle playback is a standalone route or embedded inside node resolution.
- Whether Angular state should use RxJS services, signals, NgRx, or a facade-only pattern.
- Whether the repo remains Vite-based or moves fully to Angular CLI tooling.
- Whether screenshot capture remains Phaser-only or gains Angular route screenshot support.
