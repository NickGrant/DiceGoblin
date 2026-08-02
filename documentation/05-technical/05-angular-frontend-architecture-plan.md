---
Title: "Angular Frontend Architecture"
Status: Canonical
Last Updated: 2026-08-01
Owner: Engineering
Depends On:
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/05-technical/07-angular-component-service-inventory.md
  - documentation/05-technical/08-hybrid-phaser-audio-architecture.md
  - documentation/04-ux/08-page-layout-zones.md
  - frontend/src/app/app.routes.ts
Category: 05-technical
Tags:
  - technical
  - frontend
---

# Angular Frontend Architecture

## Purpose

This document defines the current Angular frontend architecture for Dice Goblins and the narrow situations where Phaser still makes sense.

The PHP API remains the source of truth for session, profile, run, battle, shop, squad, unit, dice, reward, academy, Wrong Machine, codex, and debug state. Angular owns route orchestration, page composition, UI state, and browser interaction.

## Ownership Boundary

Angular owns:

- authenticated and unauthenticated routing
- route guards and feature gates
- application shell layout and page frames
- forms, lists, dialogs, tabs, cards, command controls, alerts, and loading/error states
- guide, codex, academy, Wrong Machine, shop, warband, unit, dice, region, run, and debug pages
- API orchestration through domain services
- browser audio unlock, mute state, route music intents, and semantic sound playback
- responsive layout and accessibility behavior

Phaser is reserved for:

- optional battle playback rendering if canvas becomes valuable enough to reintroduce
- optional future run-map rendering only if DOM/SVG rendering stops being sufficient
- deterministic capture surfaces for explicitly canvas-hosted scenes

Phaser must not own routing, backend calls, game mutations, management UI, or ordinary page layout.

## Current Route Model

Current route definitions live in `frontend/src/app/app.routes.ts`.

| Route | Component | Access |
| --- | --- | --- |
| `/login` | `LandingPageComponent` | guest |
| `/guide` | `GuidePageComponent` | public |
| `/home` | `HomePageComponent` | authenticated |
| `/codex` | `CodexPageComponent` | authenticated |
| `/field-guide` | redirect to `/codex` | authenticated |
| `/academy` | `AcademyPageComponent` | authenticated, feature-gated |
| `/regions` | `RegionsPageComponent` | authenticated |
| `/warband` | `WarbandPageComponent` | authenticated |
| `/warband/units/:unitId` | `UnitDetailsPageComponent` | authenticated |
| `/warband/squads/:squadId` | `SquadDetailsPageComponent` | authenticated |
| `/dice` | `DicePageComponent` | authenticated |
| `/shop` | `ShopPageComponent` | authenticated, feature-gated |
| `/wrong-machine` | `WrongMachinePageComponent` | authenticated, feature-gated |
| `/run/map` | `RunMapPageComponent` | authenticated |
| `/run/dialogue/:nodeId` | `RunDialoguePageComponent` | authenticated |
| `/run/node/:nodeId` | `RunNodePageComponent` | authenticated |
| `/run/loot/:nodeId` | `RunLootPageComponent` | authenticated |
| `/run/rest/:nodeId` | `RunRestPageComponent` | authenticated |
| `/run/summary` | `RunSummaryPageComponent` | authenticated |
| `/debug` | `DebugPageComponent` | authenticated |

Unknown routes redirect to `/home`.

## Application Layers

### Shell and Layout

- `AppComponent` / `app.ts` owns the root Angular mount.
- `PageFrameComponent` provides shared page framing.
- `CommandControlsComponent` provides persistent command-control layout.
- Shared UI components under `frontend/src/app/shared/ui/` provide reusable cards, grids, modals, alerts, tabs, art helpers, and formation displays.

### API and State Services

The current frontend uses domain services instead of route-facing facade classes. Components may hold view state directly when the state is route-local, but backend state should flow through core services.

Current core services include:

- `ApiHttpService`
- `SessionService`
- `ProfileService`
- `RunService`
- `SquadService`
- `UnitService`
- `DiceService`
- `ShopService`
- `AcademyService`
- `WrongMachineService`
- `DebugService`
- `DialogueService`
- `AbilityCatalogService`
- `AudioDirectorService`
- `BattlePlaybackAdapterService`
- `ViewportOrientationService`

### Route Audio

Routes declare audio intent through route `data.audio.musicIntent`. `AudioDirectorService` and route-audio helpers own playback behavior. Route components should describe intent, not directly manage global music state.

## Battle Playback

The current battle presentation is Angular-owned. `BattlePlaybackAdapterService` converts battle logs into view-ready playback snapshots for `RunNodePageComponent`.

Phaser remains optional future infrastructure. If reintroduced, Angular should mount it through a bounded host and feed it immutable playback snapshots. Angular must keep claim, navigation, error, reward, and accessibility controls.

## Current Architecture Risks

- Large route components can accumulate too much view-model logic.
- Route-local signals are useful, but shared backend state should remain service-owned.
- Optional canvas playback needs a strict boundary if added later.
- Documentation inventories can drift quickly; `07-angular-component-service-inventory.md` should be refreshed from source when routes or services change.

## Validation Sources

- `frontend/src/app/app.routes.ts`
- `frontend/src/app/pages/`
- `frontend/src/app/core/services/`
- `frontend/src/app/shared/ui/`
- `frontend/src/app/layout/`
