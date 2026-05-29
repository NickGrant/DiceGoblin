# Frontend Route and State Contracts

Status: active  
Last Updated: 2026-05-29  
Owner: Frontend  
Depends On: `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`

## Purpose

- Define the active frontend behavior contract in player-facing terms.
- Identify which Angular routes own each major game surface.
- Keep shared state boundaries explicit between session, profile, run, and debug concerns.

## Core Principles

1. The Angular application owns primary routing, shell UI, forms, lists, and management flows.
2. The backend is authoritative for session, profile, run state, rewards, battle outcomes, and purchases.
3. Frontend route names should describe player intent rather than implementation details.
4. User-facing copy should prefer `squad`; backend compatibility may still use `team` in endpoints and payloads.
5. Page components should consume view-ready state from services or facades instead of assembling raw API payloads inline.

## Shared State Slices

### Session

Owned by the session layer and initialized at app startup.

Includes:

- authenticated state
- player identity
- CSRF or session metadata needed for API mutations

Primary consumers:

- login route
- authenticated shell
- logout action

### Profile

Owned by profile-facing services and refreshed after major mutations.

Includes:

- currency
- energy
- units
- squads
- dice inventory
- region unlocks
- active squad selection

Primary consumers:

- home
- warband
- unit details
- squad details
- dice
- shop
- debug

### Run

Owned by run-facing services and route-local state for the active run loop.

Includes:

- active run summary
- node graph and statuses
- current node context
- run-scoped unit state
- rest summary data
- terminal summary data

Primary consumers:

- home
- regions
- run map
- node resolution
- rest management
- run summary

### Debug

Owned by debug tooling and only exposed when the environment allows it.

Includes:

- grantable currencies, dice, units, and region items
- reset account action
- dev-only inspection helpers

## Route Contracts

### `/login`

Purpose:

- unauthenticated entrypoint
- login or continue surface

Allowed behavior:

- request session state
- redirect authenticated users into the app shell
- initiate the configured login flow

### `/home`

Purpose:

- authenticated landing page
- high-level hub for run, warband, dice, shop, and debug access

Allowed behavior:

- show current energy and currency
- show start-run or continue-run affordance depending on active run state
- allow logout

### `/regions`

Purpose:

- region selection before a run

Allowed behavior:

- show unlocked and locked regions
- start a run for an unlocked region
- provide blocked feedback for unavailable regions

### `/warband`

Purpose:

- warband management hub

Allowed behavior:

- show unit list and squad list side by side
- create squads
- route into unit and squad detail flows

### `/warband/units/:unitId`

Purpose:

- unit inspection and editing surface

Allowed behavior:

- show unit identity, stats, XP, promotion state, and equipped dice
- support promotion when allowed
- route into dice management for equip or unequip actions
- show run-scoped read-only overlay when a run is active

### `/warband/squads/:squadId`

Purpose:

- saved squad editing surface

Allowed behavior:

- edit squad name
- edit membership
- edit 3x3 formation
- activate squad
- persist changes through team endpoints

### `/dice`

Purpose:

- dice inventory and management surface

Allowed behavior:

- browse owned dice
- sell unequipped dice
- equip or unequip in unit-context flows
- show where equipped dice are assigned

### `/shop`

Purpose:

- between-run economy surface

Allowed behavior:

- load shop catalog
- purchase units or dice
- show daily deal
- disable unavailable or unaffordable purchases

### `/run/map`

Purpose:

- active run traversal surface

Allowed behavior:

- load current run graph
- render node statuses and unlock paths
- allow selection of available nodes only
- abandon the run with confirmation

### `/run/node/:nodeId`

Purpose:

- unified non-rest node resolution surface

Allowed behavior:

- resolve combat, loot, boss, and exit nodes
- show immediate outcome, rewards, and next-step action
- branch back to map or into terminal summary
- present battle playback details when that surface exists

### `/run/rest/:nodeId`

Purpose:

- run-scoped rest workflow

Allowed behavior:

- open rest state
- allow the management actions supported during rest
- finalize rest and return to the map with summary feedback

### `/run/summary`

Purpose:

- terminal run summary for completed, failed, or abandoned runs

Allowed behavior:

- show outcome
- show rewards and progression
- show survivors and defeated units
- return player to home

### `/debug`

Purpose:

- environment-gated operator surface

Allowed behavior:

- grant resources and content for testing
- reset account state
- inspect available debug catalog data

## Navigation Rules

- unauthenticated users remain on `/login`
- authenticated users enter the shell and can reach `/home`
- if an active run exists, home should offer continue-run behavior
- non-rest run nodes route through `/run/node/:nodeId`
- rest nodes route through `/run/rest/:nodeId`
- terminal run outcomes route through `/run/summary`

## Current Gap

- Rich battle playback presentation is the main remaining frontend depth gap.
- Node resolution still owns the outcome contract either way, so battle playback should remain an embedded concern rather than a separate application shell.
