# Angular Component and Service Inventory

Status: active  
Last Updated: 2026-05-29  
Owner: Frontend  
Depends On: `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/07-ux-rebuild/01-all-up-component-list.md`

## Purpose

This inventory describes the active Angular frontend component and service map, plus the remaining optional Phaser reintegration points.

This is an architecture inventory only. It does not require final visual styling.

## Existing Phaser Scene Disposition

| Current Phaser scene | Target owner | Angular route/component target | Phaser retained? | Notes |
|---|---|---|---|---|
| `BootScene` | Angular | startup/session initializer | No | Replace with app initialization and route guards. |
| `PreloadScene` | Angular + asset services | preload/splash state in shell | Partial | Keep Phaser asset loading only for Phaser-hosted experiences. |
| `LandingScene` | Angular | `/login`, `LandingPageComponent` | No | OAuth/login page is normal DOM UI. |
| `HomeScene` | Angular | `/home`, `HomePageComponent` | No | Navigation cards and HUD should be Angular components. |
| `RegionSelectScene` | Angular | `/regions`, `RegionsPageComponent` | No | Region cards and run start actions are DOM UI. |
| `MapExplorationScene` | Angular initially | `/run/map`, `RunMapPageComponent` | Maybe | Prefer Angular/SVG first; use Phaser only if animation/pan complexity requires it. |
| `NodeResolutionScene` | Angular shell + Phaser playback | `/run/node/:nodeId`, `RunNodePageComponent` | Yes, for battle playback | Outcome panels are Angular; battle playback can be Phaser-hosted. |
| `RestManagementScene` | Angular | `/run/rest/:nodeId`, `RunRestPageComponent` | No | Form/list/formation management should be Angular. |
| `RunEndSummaryScene` | Angular | `/run/summary`, `RunSummaryPageComponent` | No | Summary/reward results are DOM UI. |
| `WarbandManagementScene` | Angular | `/warband`, `WarbandPageComponent` | No | List/grid management should be Angular. |
| `SquadDetailsScene` | Angular | `/warband/squads/:squadId`, `SquadDetailsPageComponent` | No | Formation grid is feasible as DOM grid. |
| `UnitDetailsScene` | Angular | `/warband/units/:unitId`, `UnitDetailsPageComponent` | No | Details, promotion, equip navigation are Angular UI. |
| `DiceInventoryScene` | Angular | `/dice`, `DicePageComponent` | No | Dice grid/filter/actions are Angular UI. |
| `ShopScene` | Angular | `/shop`, `ShopPageComponent` | No | Catalog and purchase UI are Angular. |
| `DevPanelScene` | Angular | `/debug`, `DebugPageComponent` | No | Debug operator actions do not need canvas. |

## Angular Shared Layout Components

- `AppShellComponent`: global app container.
- `GameShellComponent`: authenticated game frame.
- `AuthShellComponent`: unauthenticated frame.
- `PageFrameComponent`: title, subtitle, content area, and background treatment.
- `ContentAreaFrameComponent`: Angular replacement for Phaser content area frame.
- `SectionTitleBarComponent`: title/subtitle header.
- `BottomCommandStripComponent`: persistent command region.
- `HudComponent`: top-level HUD composition.
- `HomeButtonComponent`: persistent home navigation.
- `EnergyIndicatorComponent`: energy display and regen hint.
- `CurrencyIndicatorComponent`: teeth/currency display.
- `LoadingStateComponent`: consistent pending state.
- `ErrorStateComponent`: retryable error state.
- `EmptyStateComponent`: no-data state.

## Angular Feedback Components

- `ActionButtonComponent`: generic action button.
- `AcceptButtonComponent`: accept/confirm action variant.
- `RejectButtonComponent`: reject/cancel action variant.
- `ConfirmationDialogComponent`: accept/reject confirmation pattern.
- `InputDialogComponent`: text entry modal pattern.
- `ToastComponent`: transient status feedback.
- `TooltipComponent`: contextual help.
- `StatusBannerComponent`: persistent page-level status.

## Angular List and Card Components

- `ListContainerComponent`: loading/empty/error/content shell.
- `NameLinkListComponent`: squads and similar simple row links.
- `GridListComponent`: generic card-grid shell.
- `UnitCardComponent`: unit summary card.
- `UnitGridComponent`: unit cards with selection state.
- `DiceCardComponent`: dice summary card.
- `DiceGridComponent`: dice cards with selection/filter state.
- `SquadListComponent`: squad list and active state.
- `RegionCardComponent`: region selection card.
- `ShopProductCardComponent`: shop item purchase card.

## Angular Game-Domain Components

- `HomeNavigationPanelComponent`: start/continue, warband, inventory, shop navigation.
- `RegionSelectionPanelComponent`: region availability and start-run call to action.
- `RunMapComponent`: current run graph surface.
- `RunNodeComponent`: combat/loot/rest/boss/exit node visual.
- `RunEdgeComponent`: path/unlock indicator if map is DOM/SVG.
- `RunActionListComponent`: abandon/refresh/continue actions.
- `FormationGridComponent`: 3x3 formation editor/viewer.
- `FormationCellComponent`: individual formation slot.
- `PromotionSelectionComponent`: primary/secondary promotion controls.
- `RestSummaryPanelComponent`: rest state summary.
- `RunEndSummaryPanelComponent`: end-of-run rewards/outcome.
- `BattleOutcomePanelComponent`: battle result, rewards, and claim actions.
- `BattleTimelineControlsComponent`: play/pause/step/speed/skip controls.

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

- `ApiHttpService`: shared HTTP/envelope handling.
- `SessionService`: session bootstrap and logout.
- `ProfileService`: profile retrieval, refresh, and cache invalidation.
- `RunService`: current run, create run, abandon run, exit run.
- `NodeResolutionService`: resolve non-rest nodes and normalize outcomes.
- `BattleService`: battle log and claim operations.
- `SquadService`: create, update, activate, and delete squads.
- `UnitService`: unit detail helpers and promotion commands.
- `DiceService`: inventory, equip, unequip, mutation, and sell commands.
- `ShopService`: shop catalog and purchase commands.
- `RestService`: open, update, and finalize rest state.
- `DebugService`: local debug catalog/grant/reset operations.
- `AssetUrlService`: central browser asset path handling.
- `NavigationIntentService`: maps domain events to route transitions.

## Route Facades

- `AuthFacade`
- `HomeFacade`
- `RegionSelectFacade`
- `RunMapFacade`
- `NodeResolutionFacade`
- `RestManagementFacade`
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
- `RunMapBridgeService`: optional adapter if the run map remains Phaser-rendered.

## Recommended Initial Angular Module/Folder Shape

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
    rest-management/
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

## Current Implementation Notes

Implemented now:

1. Angular app bootstraps successfully.
2. Router provides the active gameplay and management route set, not placeholder pages.
3. App shell renders the active persistent HUD and page frame.
4. API services preserve current base URL and credential behavior.
5. No Phaser-owned route exists in the active Angular app.

Still not complete:

- Full battle playback migration.
- Final state-management library decision beyond keeping services and page-owned state as the public page boundary.
