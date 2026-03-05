# Warband UX Flows and Screen Contracts
----

Status: active  
Last Updated: 2026-03-05  
Owner: UX + Frontend  
Depends On: `frontend/src/scenes/WarbandManagementScene.ts`, `frontend/src/scenes/UnitDetailsScene.ts`, `frontend/src/scenes/SquadDetailsScene.ts`

## Purpose
- Define the split warband UX model as two explicit user flows.
- Keep squad composition and unit progression concerns separate.

## Flow Split
- Flow A: `Unit Details`
  - entry from Warband hub unit list
  - shows unit stats/xp, equipped dice summary, and promotion controls
  - links to `DiceInventoryScene` for equip/unequip actions
- Flow B: `Squad Details`
  - entry from Warband hub squad list
  - edits squad membership + 3x3 formation
  - supports squad activation and rename attempt

## Scene Responsibilities

### `WarbandManagementScene` (Hub)
- Two columns:
  - left: units list (click opens `UnitDetailsScene`)
  - right: squads list (click opens `SquadDetailsScene`) + action list
- Required squad actions:
  - create new squad (`New Squad`)

### `UnitDetailsScene`
- Shows selected unit:
  - name
  - level/max-level
  - xp
  - tier
  - equipped dice summary
- Promotion controls:
  - selected unit is primary
  - choose two compatible secondary units
  - promotion blocked when active run exists
- Dice flow:
  - `Manage Dice` routes to `DiceInventoryScene` with return context

### `SquadDetailsScene`
- Shows one selected squad:
  - editable membership + 3x3 formation
  - clear selected cell
  - save squad state
  - set active squad
  - rename via prompt + team update payload
- Navigation:
  - back to Warband hub

## Data Contract Notes
- Squad persistence uses `PUT /api/v1/teams/:teamId` with:
  - `unit_ids`
  - `formation`
  - optional `name` (best-effort; backend support may vary)
- Squad activation uses `POST /api/v1/teams/:teamId/activate`.
- Squad deletion uses `DELETE /api/v1/teams/:teamId` with backend safety gates.
- Unit promotion uses `POST /api/v1/units/:unitId/promote`.
- Dice equip/unequip remains in `DiceInventoryScene`.

## Button Composition Rule
- Any screen-side action controls should be rendered through `ActionButtonList` when practical.
- Single standalone actions may still use `ActionButton`.
