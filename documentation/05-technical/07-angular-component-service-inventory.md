---
Title: "Angular Component and Service Inventory"
Status: Canonical
Last Updated: 2026-08-01
Owner: Engineering
Depends On:
  - documentation/05-technical/05-angular-frontend-architecture-plan.md
  - documentation/05-technical/08-hybrid-phaser-audio-architecture.md
  - documentation/04-ux/08-page-layout-zones.md
  - frontend/src/app/app.routes.ts
Category: 05-technical
Tags:
  - technical
  - frontend
---

# Angular Component and Service Inventory

## Purpose

This inventory describes the current Angular frontend route, component, and service map at a source-derived level. It does not define visual styling or final UX behavior.

## Route Ownership Inventory

| Player Surface | Route | Component | Notes |
| --- | --- | --- | --- |
| Login | `/login` | `LandingPageComponent` | Guest-only public auth entry. |
| Public guide | `/guide` | `GuidePageComponent` | Public guide surface. |
| Home | `/home` | `HomePageComponent` | Authenticated hub. |
| Codex | `/codex` | `CodexPageComponent` | Authenticated discovery/reference surface. |
| Field guide redirect | `/field-guide` | redirect to `/codex` | Legacy route alias. |
| Academy | `/academy` | `AcademyPageComponent` | Feature-gated authenticated progression surface. |
| Regions | `/regions` | `RegionsPageComponent` | Run-start and region selection. |
| Warband | `/warband` | `WarbandPageComponent` | Squad/unit overview. |
| Unit details | `/warband/units/:unitId` | `UnitDetailsPageComponent` | Unit inspection and management. |
| Squad details | `/warband/squads/:squadId` | `SquadDetailsPageComponent` | Squad formation and membership. |
| Dice inventory | `/dice` | `DicePageComponent` | Dice inspection and actions. |
| Shop | `/shop` | `ShopPageComponent` | Feature-gated purchases. |
| Wrong Machine | `/wrong-machine` | `WrongMachinePageComponent` | Feature-gated reconstruction surface. |
| Run map | `/run/map` | `RunMapPageComponent` | Active run graph. |
| Run dialogue | `/run/dialogue/:nodeId` | `RunDialoguePageComponent` | Dialogue node playback. |
| Run node | `/run/node/:nodeId` | `RunNodePageComponent` | Combat, boss, shrine, hazard, and chaos resolution. |
| Run loot | `/run/loot/:nodeId` | `RunLootPageComponent` | Loot node presentation. |
| Run rest | `/run/rest/:nodeId` | `RunRestPageComponent` | Rest node recovery. |
| Run summary | `/run/summary` | `RunSummaryPageComponent` | End-of-run results. |
| Debug | `/debug` | `DebugPageComponent` | Development/operator surface. |

## Page Components

Current page components live under `frontend/src/app/pages/`:

- `AcademyPageComponent`
- `CodexPageComponent`
- `DebugPageComponent`
- `DicePageComponent`
- `GuidePageComponent`
- `HomePageComponent`
- `LandingPageComponent`
- `RegionsPageComponent`
- `RunDialoguePageComponent`
- `RunLootPageComponent`
- `RunMapPageComponent`
- `RunNodePageComponent`
- `RunRestPageComponent`
- `RunSummaryPageComponent`
- `ShopPageComponent`
- `SquadDetailsPageComponent`
- `UnitDetailsPageComponent`
- `WarbandPageComponent`
- `WrongMachinePageComponent`

## Layout Components

Current shared layout components live under `frontend/src/app/layout/`:

- `CommandControlsComponent`
- `PageFrameComponent`

## Shared UI Components And Helpers

Current shared UI components and helpers live under `frontend/src/app/shared/ui/`:

- `ConfirmModalComponent`
- `DgAlertComponent`
- `DgCommandBtnDirective`
- `DgDialogueStageComponent`
- `DiceGridObjectComponent`
- `DicePickerModalComponent`
- `FocusLayoutComponent`
- `GridObjectComponent`
- `ObjectGridComponent`
- `RunUnitFormationGridComponent`
- `ShopDiceGridObjectComponent`
- `ShopUnitGridObjectComponent`
- `TabStripComponent`
- `UnitBarComponent`
- `UnitGridObjectComponent`
- `UnitThumbnailComponent`
- `category-icons.ts`
- `dice-art.ts`
- `dice-display.utils.ts`
- `node-art.ts`
- `prototype-art.ts`
- `unit-art.ts`

## Core Services

Current services live under `frontend/src/app/core/services/`:

- `AbilityCatalogService`
- `AcademyService`
- `ApiHttpService`
- `AudioDirectorService`
- `BattlePlaybackAdapterService`
- `DebugService`
- `DialogueService`
- `DiceService`
- `ProfileService`
- `RunService`
- `SessionService`
- `ShopService`
- `SquadService`
- `UnitService`
- `ViewportOrientationService`
- `WrongMachineService`

## Supporting Core Modules

- `frontend/src/app/core/audio/`: audio manifest, route audio, and audio models.
- `frontend/src/app/core/battle-playback/`: battle playback view-model types.
- `frontend/src/app/core/dialogue/`: dialogue models and script registry.
- `frontend/src/app/core/feature-unlocks/`: feature unlock categories.
- `frontend/src/app/core/guards/`: auth and feature route guards.
- `frontend/src/app/core/models/`: API models.
- `frontend/src/app/core/regions/`: region catalog helpers.

## Phaser Status

No current page component is Phaser-owned. Battle playback is currently Angular-rendered through `RunNodePageComponent` and `BattlePlaybackAdapterService`. Phaser remains optional future infrastructure governed by `08-hybrid-phaser-audio-architecture.md`.

## Maintenance Rule

Refresh this inventory from source when any route, page component, core service, shared UI component, or Phaser host is added, removed, or renamed.
