# Angular Frontend Architecture Plan

Status: active  
Last Updated: 2026-05-29  
Owner: Frontend  
Depends On: `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`, `documentation/07-ux-rebuild/01-all-up-component-list.md`

## Purpose

This document defines the active Angular frontend architecture for Dice Goblins and the remaining boundaries where Phaser may still be useful.

The PHP API remains the source of truth for session, profile, run, battle, shop, squad, unit, dice, reward, and debug state.

## Current Position

The active frontend is the Angular application in `frontend/`, which owns the authenticated shell, login route, home route, region route, warband route, dice route, shop route, debug route, run map, node resolution, rest flow, and run summary.

Angular now owns application flow, routing, data orchestration, forms, lists, dialogs, accessibility, and persistent shell UI.

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

Phaser is retained for:

- future combat playback and battle visualization if reintroduced
- future sprite-heavy board or grid rendering if a canvas surface becomes justified
- deterministic screenshot/capture workflows for explicitly Phaser-hosted surfaces

Phaser does not own ordinary page composition, CRUD-style management UI, modal forms, list/grid browsing, or primary routing in the active frontend.

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

Active Angular routes:

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

Angular route names describe player intent rather than engine scenes.

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

## Current Delivery State

Completed:

1. Angular application shell and authenticated HUD layout.
2. Route coverage for login, home, regions, warband, unit details, squad details, dice, shop, run map, node resolution, rest, run summary, and debug.
3. Angular-owned services for session, profile, run, rest, shop, unit, dice, squad, and debug flows.
4. Angular DOM/SVG run-map rendering.
5. Region progression coverage for Farm, Mountains, and Swamps.

Still open:

1. Rich battle playback/presentation inside node resolution.
2. Optional Angular-specific screenshot/review workflow if additional route-level tooling is needed.

## Recommended Phaser Retention Decision

Keep Phaser for:

- battle/combat playback
- battle log timeline visualization
- animated grid/unit positioning
- sprite-heavy effects
- deterministic visual capture while still useful

Angular already owns:

- run map graph surface
- encounter node action routing
- management, inventory, and summary surfaces

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
- Canvas-hosted views need explicit teardown to avoid duplicate Phaser games and leaked listeners.

## Open Decisions

- Whether battle playback should be a standalone route, embedded node panel, or Phaser host inside the current node resolution page.
- Whether Angular state should stay on signals plus thin services or move toward fuller route facades.
- Whether screenshot capture should gain Angular route-specific support.
