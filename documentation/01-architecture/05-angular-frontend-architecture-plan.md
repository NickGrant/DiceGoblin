# Angular Frontend Architecture Plan

Status: active  
Last Updated: 2026-05-29  
Owner: Frontend  
Depends On: `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`, `documentation/03-ux/08-page-layout-zones.md`

## Purpose

This document defines the active Angular frontend architecture for Dice Goblins and the narrow situations where Phaser still makes sense.

The PHP API is the source of truth for session, profile, run, battle, shop, squad, unit, dice, reward, and debug state.

## Frontend Ownership

The active frontend is the Angular application in `frontend/`.

Angular owns:

- authenticated and unauthenticated shell flow
- login, home, region, warband, dice, shop, debug, run map, node resolution, rest, and run summary routes
- routing and navigation
- forms, lists, dialogs, HUD, and ordinary page composition
- API orchestration and state facades

## Ownership Boundary

Angular owns:

- application routing and page navigation
- startup and session checks
- persistent app shell and page layout
- API orchestration and state facades
- menus and management views
- forms, validation, dialogs, toasts, and loading or error states
- reusable UI components
- accessible keyboard navigation and focus management
- debug and operator panels that do not require canvas rendering

Phaser is reserved for:

- battle playback and battle visualization if reintroduced
- sprite-heavy board or grid rendering if a canvas surface becomes justified
- deterministic screenshot or capture workflows for explicitly Phaser-hosted surfaces

Phaser does not own ordinary page composition, CRUD-style management UI, modal forms, list browsing, or primary routing.

## Angular Layers

### App Shell

The shell wraps all authenticated and unauthenticated pages.

Likely pieces:

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

The frontend uses domain services and route-facing facades instead of one oversized API client.

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

- `/login`
- `/home`
- `/regions`
- `/run/map`
- `/run/node/:nodeId`
- `/run/rest/:nodeId`
- `/run/summary`
- `/warband`
- `/warband/squads/:squadId`
- `/warband/units/:unitId`
- `/dice`
- `/shop`
- `/debug`

Angular route names describe player intent rather than engine scenes.

## Phaser Integration Model

Angular owns the lifecycle of Phaser instances. Phaser mounts inside explicit Angular host components rather than booting the entire app.

Recommended hosts:

- `PhaserCanvasHostComponent`
- `CombatPlaybackHostComponent`
- `RunMapCanvasHostComponent`, only if map rendering becomes canvas-based
- `SceneCaptureHostComponent`, debug-only if needed

Recommended bridge services:

- `PhaserGameFactoryService`
- `PhaserEventBridgeService`
- `BattlePlaybackBridgeService`
- `RunMapBridgeService`, only if the run map becomes canvas-based

The host should create a Phaser instance when mounted, destroy it when the route or component is removed, pass immutable input snapshots into Phaser, and receive Phaser events through a small bridge.

## Practical Retention Rule

Keep Phaser only when a surface benefits materially from canvas rendering.

The strongest current candidate is:

- battle playback inside node resolution

Everything else in the current app belongs in Angular first.

## Architecture Risks

- Duplicating state between Angular and Phaser can create stale UI.
- Reusing old scene vocabulary inside Angular components can preserve weak boundaries.
- Canvas-hosted views need explicit teardown to avoid duplicate Phaser instances and leaked listeners.

## Scope Note

- Angular owns the active game shell and route set today.
- Rich battle playback remains the main open presentation refinement area.
