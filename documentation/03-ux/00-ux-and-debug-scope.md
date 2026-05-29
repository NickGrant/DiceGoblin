# UX & Debug Scope - MVP

Status: active  
Last Updated: 2026-05-29  
Owner: UX + Frontend  
Depends On: `documentation/03-ux/02-warband-management.md`, `documentation/03-ux/03-encounter-flow-transition-matrix.md`, `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`

## Purpose

- Define the active player-facing surface area for the MVP.
- Keep the app navigation, management flows, and debug tooling aligned.
- Separate canonical gameplay UX from implementation-specific review notes.

## MVP Surface List

The active MVP includes:

- login
- home
- region selection
- warband hub
- unit details
- squad details
- dice inventory
- shop
- run map
- node resolution
- rest management
- run summary
- debug panel when environment-enabled

## Experience Goals

- The core loop should be understandable on the first session.
- Every major action should produce immediate readable feedback.
- Management and run surfaces should stay distinct so the player knows when edits are allowed.
- Debug tooling should be sufficient for local verification without leaking into normal player flow.

## Navigation Rules

- The app should use explicit route-to-route progression rather than hidden nested flows.
- Home is the primary hub outside an active run.
- Abandon run is only offered from the run map.
- Rest is the only active-run management window.
- Terminal outcomes always resolve through the shared run summary shell.

## Information Requirements

- Home shows energy, currency, and clear start-or-continue run behavior.
- Run map shows node type, node availability, unlock paths, and warband condition summary.
- Unit details show progression and equipped dice clearly.
- Dice inventory shows size, rarity, slot capacity, affixes, and equip ownership.
- Run summary shows outcome, rewards, progression, and survivor impact.

## Debug Scope

Debug tooling is environment-gated and may include:

- grant currency
- grant unit
- grant die
- grant region item
- reset account
- battle-log export when battle playback is available

## Non-Goals

- in-production cheat surfaces
- analytics dashboards
- deep accessibility redesign beyond baseline readable interaction
- modal-heavy nested navigation as a default flow pattern

## Canonical Follow-Up Docs

- Warband and inventory UX: `documentation/03-ux/02-warband-management.md`
- Run, encounter, and summary UX: `documentation/03-ux/03-encounter-flow-transition-matrix.md`
- Onboarding and first-session framing: `documentation/03-ux/09-first-session-player-journey.md`
