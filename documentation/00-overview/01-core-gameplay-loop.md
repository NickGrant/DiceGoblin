---
Title: "Core Gameplay Loop"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product
Depends On:
  - documentation/05-technical/02-frontend-state-and-scene-contracts.md
  - documentation/02-systems/mvp-reference/03-encounter-scope.md
  - documentation/02-systems/mvp-reference/06-run-resolution-scope.md
Category: 00-overview
Tags:
  - overview
---

# Core Gameplay Loop

## Purpose

- Describe the gameplay loop the current alpha launch actually supports.
- Give implementation work one reliable reference for player progression through a session.

## Current Loop

1. Authenticate or read the guide.
2. Enter the home screen.
3. Decide whether to continue an active run or start a new one.
4. If starting a new run:
   - open region selection
   - choose an unlocked region
   - spend the region's energy cost
5. Traverse the run map by selecting available nodes.
6. Resolve the selected node:
   - dialogue nodes resolve through the dialogue page or dialogue presentation on the node route
   - combat, boss, and loot nodes resolve through the node page
   - rest nodes resolve through the rest page
   - exit ends the run from the run map
7. Claim rewards or return to the map.
8. If the run ends, review the run summary and return home.
9. Between runs, use warband, dice, shop, and academy screens to prepare the next attempt.

## First-Session Region Flow

- New players begin with Mystic Cave unlocked.
- Mystic Cave costs zero energy and contains an introductory conversation with The Whim followed by an exit.
- Completing Mystic Cave unlocks The Farm.
- Completing The Farm unlocks Mountains.
- Completing Mountains unlocks Swamps.

Mystic Cave is an onboarding and narrative region. It is not part of the current combat progression loop.

## Current Between-Run Preparation

The current preparation loop includes:

- choosing and editing squads
- setting a 3x3 formation for the active squad
- renaming units
- editing equipped active abilities on units
- assigning dice to ability slots
- buying dice, units, and unlocks from the shop
- unlocking additional Tier I unit types in the academy
- promoting eligible units in the academy
- selecting capstones for mastered units when required

## Current Run Flow

During an active run:

- only one run can be active at a time
- the active squad is locked for the duration of the run
- the run map is loaded from backend state
- only available nodes can be selected
- dialogue nodes advance authored narrative and may unlock codex entries
- combat and boss encounters resolve automatically on the backend
- the player reads outcome state and battle logs rather than issuing combat commands directly
- rest restores run state through a dedicated recovery flow
- abandonment and exit both lead to the run summary route

## Current Progression Expectations

The current implementation supports these progression ideas:

- units gain XP from successful combat-related reward flow
- units can become promotion-eligible before reaching max level
- mastered units may need capstone selection before promotion
- promotion consumes secondary units and upgrades the primary unit
- feature unlocks can increase squad capacity and open additional systems
- region unlocks gate access to later content
- one-time dialogue discoveries persist for codex and onboarding state

## Current Constraints Players Feel

- energy gates how often an energy-costing run can be started
- only one squad is active at a time
- squad edits are blocked during an active run
- unit and squad content can be locked by run state
- the academy itself is feature-gated

## Notes For Future Documentation

- If current code behavior and older subsystem docs disagree, treat this file as the higher-level player-flow source of truth and update the narrower docs to match.
- Future gameplay direction is tracked in `documentation/07-development-path/00-gameplay-systems-roadmap.md`.
