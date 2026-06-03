# Angular Component and Service Inventory

Status: active  
Last Updated: 2026-06-02  
Owner: Frontend  
Depends On: `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/03-ux/08-page-layout-zones.md`

## Purpose

This inventory describes the active Angular frontend component and service map, plus the optional Phaser host points that still make sense.

This is an architecture inventory only. It does not define final visual styling.

## Route Ownership Inventory

| Player Surface | Owner | Angular route/component target | Phaser retained? | Notes |
|---|---|---|---|---|
| Startup and session bootstrap | Angular | app initialization, session services, route guards | No | Startup flow is app-owned. |
| Login | Angular | `/login`, `LandingPageComponent` | No | Ordinary DOM UI. |
| Home | Angular | `/home`, `HomePageComponent` | No | Navigation cards and HUD are Angular components. |
| Region selection | Angular | `/regions`, `RegionsPageComponent` | No | Region cards and run start actions are DOM UI. |
| Run map | Angular | `/run/map`, `RunMapPageComponent` | Maybe | Prefer Angular/SVG first; use Phaser only if animation or pan complexity justifies it. |
| Node resolution | Angular shell + optional Phaser playback | `/run/node/:nodeId`, `RunNodePageComponent` | Yes, if battle playback returns | Outcome panels are Angular; battle playback can be Phaser-hosted. |
| Rest recovery | Angular | `/run/rest/:nodeId`, `RunRestPageComponent` | No | Healing and finalize controls stay Angular. |
| Run summary | Angular | `/run/summary`, `RunSummaryPageComponent` | No | Summary and reward results are DOM UI. |
| Warband hub | Angular | `/warband`, `WarbandPageComponent` | No | List and grid management stay Angular. |
| Squad details | Angular | `/warband/squads/:squadId`, `SquadDetailsPageComponent` | No | Formation grid is a DOM grid. |
| Unit details | Angular | `/warband/units/:unitId`, `UnitDetailsPageComponent` | No | Details, promotion, and equip navigation are Angular UI. |
| Dice inventory | Angular | `/dice`, `DicePageComponent` | No | Dice grid and actions are Angular UI. |
| Shop | Angular | `/shop`, `ShopPageComponent` | No | Catalog and purchase UI are Angular. |
| Debug panel | Angular | `/debug`, `DebugPageComponent` | No | Debug operator actions do not need canvas. |

## Angular Shared Layout Components

- `AppShellComponent`: global app container.
- `GameShellComponent`: authenticated game frame.
- `AuthShellComponent`: unauthenticated frame.
- `PageFrameComponent`: title, subtitle, content area, and background treatment.
- `ContentAreaFrameComponent`: shared framed content surface.
- `SectionTitleBarComponent`: title and subtitle header.
- `BottomCommandStripComponent`: persistent command region.
- `HudComponent`: top-level HUD composition.
- `HomeButtonComponent`: persistent home navigation.
- `EnergyIndicatorComponent`: energy display and regen hint.
- `CurrencyIndicatorComponent`: currency display.
- `LoadingStateComponent`: consistent pending state.
- `ErrorStateComponent`: retryable error state.
- `EmptyStateComponent`: no-data state.

## Angular Feedback Components

- `ActionButtonComponent`: generic action button.
- `AcceptButtonComponent`: accept or confirm action variant.
- `RejectButtonComponent`: reject or cancel action variant.
- `ConfirmationDialogComponent`: accept or reject confirmation pattern.
- `InputDialogComponent`: text entry modal pattern.
- `ToastComponent`: transient status feedback.
- `TooltipComponent`: contextual help.
- `StatusBannerComponent`: persistent page-level status.

## Angular List and Card Components

- `ListContainerComponent`: loading, empty, error, and content shell.
- `NameLinkListComponent`: squads and similar simple row links.
- `GridListComponent`: generic card-grid shell.
- `UnitCardComponent`: unit summary card.
- `UnitGridComponent`: unit cards with selection state.
- `DiceCardComponent`: dice summary card.
- `DiceGridComponent`: dice cards with selection or filter state.
- `SquadListComponent`: squad list and active state.
- `RegionCardComponent`: region selection card.
- `ShopProductCardComponent`: shop item purchase card.

## Angular Game-Domain Components

- `HomeNavigationPanelComponent`: start or continue, warband, inventory, and shop navigation.
- `RegionSelectionPanelComponent`: region availability and start-run call to action.
- `RunMapComponent`: current run graph surface.
- `RunNodeComponent`: combat, loot, rest, boss, and exit node visual.
- `RunEdgeComponent`: path or unlock indicator when the map is DOM or SVG.
- `RunActionListComponent`: abandon, refresh, and continue actions.
- `FormationGridComponent`: 3x3 formation editor or viewer.
- `FormationCellComponent`: individual formation slot.
- `PromotionSelectionComponent`: promotion controls.
- `RestSummaryPanelComponent`: rest recovery summary.
- `RunEndSummaryPanelComponent`: end-of-run rewards and outcome.
- `BattleOutcomePanelComponent`: battle result, rewards, and claim actions.
- `BattleTimelineControlsComponent`: play, pause, step, speed, and skip controls.

## Phaser Host Components

- `PhaserCanvasHostComponent`: low-level Angular wrapper around a Phaser game instance.
- `CombatPlaybackHostComponent`: embeds Phaser battle playback.
- `RunMapCanvasHostComponent`: optional canvas map renderer.
- `SceneCaptureHostComponent`: optional debug capture wrapper.

## Angular Page Components

- `LandingPageComponent`
- `HomePageComponent`
- `RegionsPageComponent`
- `RunMapPageComponent`
- `RunNodePageComponent`
- `RunRestPageComponent`
- `RunSummaryPageComponent`
- `WarbandPageComponent`
- `SquadDetailsPageComponent`
- `UnitDetailsPageComponent`
- `DicePageComponent`
- `ShopPageComponent`
- `DebugPageComponent`

## Core Services

- `ApiHttpService`: shared HTTP and envelope handling.
- `SessionService`: session bootstrap and logout.
- `ProfileService`: profile retrieval, refresh, and cache invalidation.
- `RunService`: current run, create run, abandon run, exit run, open rest, and finalize rest.
- `NodeResolutionService`: resolve non-rest nodes and normalize outcomes.
- `BattleService`: battle log and claim operations.
- `SquadService`: create, update, activate, and delete squads.
- `UnitService`: unit detail helpers and promotion commands.
- `DiceService`: inventory, equip, unequip, mutation, and sell commands.
- `ShopService`: shop catalog and purchase commands.
- `DebugService`: local debug catalog, grant, and reset operations.
- `AssetUrlService`: central browser asset path handling.
- `NavigationIntentService`: maps domain events to route transitions.

## Route Facades

- `AuthFacade`
- `HomeFacade`
- `RegionSelectFacade`
- `RunMapFacade`
- `NodeResolutionFacade`
- `RestRecoveryFacade`
- `RunEndSummaryFacade`
- `WarbandFacade`
- `SquadDetailsFacade`
- `UnitDetailsFacade`
- `DiceInventoryFacade`
- `ShopFacade`
- `DebugFacade`

## Phaser Bridge Services

- `PhaserGameFactoryService`: creates configured Phaser game instances for host components.
- `PhaserEventBridgeService`: typed event bridge between Angular and Phaser.
- `BattlePlaybackBridgeService`: converts battle logs into renderer-friendly playback snapshots and commands.
- `RunMapBridgeService`: optional adapter if the run map becomes Phaser-rendered.

## Recommended Initial Angular Module or Folder Shape

```text
frontend/src/app/
  core/
    api/
    models/
    state/
    routing/
  shared/
    layout/
    feedback/
    list/
    buttons/
  features/
    auth/
    home/
    regions/
    run-map/
    node-resolution/
    rest-recovery/
    run-summary/
    warband/
    dice-inventory/
    shop/
    debug/
  phaser/
    hosts/
    bridges/
    scenes/
```

## Scope Note

- The Angular route, shell, and management surfaces are the active frontend.
- Rich battle playback remains the main optional Phaser reintegration point.
