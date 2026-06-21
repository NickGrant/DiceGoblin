# Frontend Route and State Contracts

Status: active  
Last Updated: 2026-06-21  
Owner: Frontend  
Depends On: `frontend/src/app/app.routes.ts`, `frontend/src/app/core/services/session/session.service.ts`, `frontend/src/app/core/services/run/run.service.ts`

## Purpose

- Describe the current Angular route surface in implementation-facing language.
- Clarify which frontend state lives at session, profile, run, summary, and debug levels.
- Reflect the routes and behaviors the app actually ships today.

## Shell Model

The current frontend has two top-level experiences:

- public routes:
  - `/login`
  - `/guide`
- authenticated shell routes under `GameShellComponent`

Authenticated routes render inside one persistent game shell with:

- top HUD and navigation
- route-framed page content
- shared motion and responsive layout behavior

## Shared State Slices

### Session

Owned by `SessionService`.

Current responsibilities:

- authenticated vs guest state
- current user id and display name
- CSRF token
- initial app bootstrap and refresh
- logout flow

### Profile

Also owned by `SessionService` through cached profile refresh.

Current responsibilities:

- energy
- soft currency
- active run id
- units
- squads
- dice inventory
- feature unlocks
- unit-type unlocks
- region unlocks
- active squad
- squad unit cap

### Run

Owned primarily by `RunService` plus route-local page state.

Current responsibilities:

- current run payload
- run creation, abandonment, and exit
- node resolution
- battle reward claims
- rest open/finalize actions

### Run Summary

Owned by `RunService.summary`.

Current responsibilities:

- abandoned, failed, and completed run summary state
- reward and progression payload prepared for `/run/summary`

### Debug

Owned by `DebugService` and only available when runtime config enables it.

Current responsibilities:

- grant currency
- grant units
- grant dice
- grant region items
- set unit level
- reset account

## Current Route Table

### `/login`

Public landing route.

Current behavior:

- acts as guest-only entry
- presents login and guide access
- redirects authenticated users away through guards

### `/guide`

Public guide route.

Current behavior:

- readable without authentication
- doubles as the field guide content source

### `/home`

Authenticated hub route.

Current behavior:

- shows primary action as `Start Run` or `Continue Run`
- links to warband, academy, shop, inventory, and debug
- reflects whether academy and debug are available

### `/field-guide`

Authenticated copy of the guide surface.

Current behavior:

- accessible inside the authenticated shell
- reuses guide content rather than a separate guide system

### `/regions`

Run-entry route.

Current behavior:

- presents the currently surfaced region sequence
- shows locked vs unlocked regions
- starts a run for an unlocked region
- routes an already-active region back into `/run/map`

### `/warband`

Roster hub.

Current behavior:

- shows squads and units
- creates squads
- activates squads
- links into squad and unit detail pages

### `/warband/squads/:squadId`

Squad editing route.

Current behavior:

- edits squad name
- edits squad membership
- edits formation on a 3x3 grid
- supports drag/drop and tap-first placement
- blocks edits while the squad is locked by an active run

### `/warband/units/:unitId`

Unit details route.

Current behavior:

- shows stats and progression state
- allows unit rename
- exposes capstone state
- exposes inherited passives
- edits active ability loadout
- assigns and clears dice on ability slots
- links the player into academy/shop promotion flow

### `/dice`

Dice inventory route.

Current behavior:

- filters and sorts owned dice
- shows where equipped dice are in use
- sells unequipped dice

### `/shop`

Economy route.

Current behavior:

- shows starter dice and units
- shows daily deals
- shows feature unlocks
- disables unaffordable, unavailable, or already-completed purchases

### `/academy`

Feature-gated academy route.

Current behavior:

- unlocks additional Tier I unit types
- lists promotable units
- shows promotion destinations and inherited effects
- requires capstone selection when applicable before promotion

### `/run/map`

Active run traversal route.

Current behavior:

- renders node graph and node statuses from backend data
- shows squad formation and run-unit condition
- opens node, rest, abandon, and exit flows

### `/run/node/:nodeId`

Combat or loot node resolution route.

Current behavior:

- auto-resolves combat, boss, and loot nodes
- presents outcome state and battle log details
- claims rewards
- routes back to map or into summary depending on run state

### `/run/rest/:nodeId`

Rest node route.

Current behavior:

- opens rest state from the backend
- shows recovery preview
- finalizes rest and returns to the run loop

### `/run/summary`

Terminal run state route.

Current behavior:

- shows rewards, progression, survivors, and defeated units
- handles complete, failed, and abandoned runs through one shared page

### `/debug`

Environment-gated operator route.

Current behavior:

- exposes account mutation helpers for testing and balancing

## Current Naming Rules

- The player-facing UI should prefer `squad`.
- Backend compatibility still uses `team` in endpoints and some payloads.
- Route docs should describe the player-facing name first and note backend compatibility only when it matters.
