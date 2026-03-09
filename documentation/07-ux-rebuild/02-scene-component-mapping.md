# UX Rebuild - Scene Component Mapping
----

Status: active  
Last Updated: 2026-03-09  
Owner: UX + Frontend  
Depends On: `documentation/07-ux-rebuild/01-all-up-component-list.md`

## Purpose
- Map component usage scene-by-scene to define required composition targets.

## Adoption Status (Milestone 16)
- Implemented: `HomeScene`, `RegionSelectScene`, `MapExplorationScene`, `NodeResolutionScene`, `WarbandManagementScene`, `SquadDetailsScene`, `UnitDetailsScene`, `DiceInventoryScene`, `RestManagementScene`, `RunEndSummaryScene`
- Implemented shared global shell: unified `BackgroundImage`, split `BottomCommandStrip`, `ContentAreaFrame`, and locked typography/palette usage.

## Scene Mapping
- `LandingScene`
  - Generic action button (login/continue)
  - Loading state panel/text
  - Error state panel/text

- `HomeScene`
  - Home navigation panel (Start Run/Continue Run instance)
  - Home navigation panel (Warband instance)
  - Home navigation panel (Inventory instance)
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `RegionSelectScene`
  - Region selection card/panel
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame
  - Error state panel/text

- `MapExplorationScene`
  - Run map graph surface
  - Run node visual
  - Run node-edge/unlock indicator
  - Run action list
  - Confirmation dialog (accept/reject pattern)
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame
  - Error state panel/text

- `NodeResolutionScene`
  - Generic action button
  - Confirmation dialog (accept/reject pattern)
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `WarbandManagementScene`
  - Grid list variant
  - Name/link list variant
  - List container
  - Generic action button
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `SquadDetailsScene`
  - Formation grid (3x3)
  - Grid list variant
  - List container
  - Generic action button
  - Confirmation dialog (accept/reject pattern)
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `UnitDetailsScene`
  - Grid list variant
  - List container
  - Promotion selection controls
  - Generic action button
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `DiceInventoryScene`
  - Grid list variant
  - List container
  - Generic action button
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `RestManagementScene`
  - Formation grid (3x3)
  - Grid list variant
  - List container
  - Promotion selection controls
  - Generic action button
  - Rest summary panel
  - Toast/status feedback message
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

- `RunEndSummaryScene`
  - End-of-run summary panel
  - Generic action button
  - Bottom command strip (split left/right)
  - Section title bar
  - Content area frame

