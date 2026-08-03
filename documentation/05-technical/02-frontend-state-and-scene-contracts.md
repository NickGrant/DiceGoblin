---
Title: "Frontend Route and State Contracts"
Status: Canonical
Last Updated: 2026-08-02
Owner: Engineering
Depends On:
  - frontend/src/app/app.routes.ts
  - frontend/src/app/core/services/session/session.service.ts
  - frontend/src/app/core/services/run/run.service.ts
  - frontend/src/app/core/services/wrong-machine/wrong-machine.service.ts
  - documentation/02-systems/08-dice-material-model.md
  - documentation/02-systems/09-kin-reconstruction.md
  - documentation/05-technical/03-backend-api-contracts.md
Category: 05-technical
Tags:
  - technical
---

# Frontend Route and State Contracts

## Purpose

- Describe the current Angular route surface in implementation-facing language.
- Clarify which frontend state lives at session, profile, run, reconstruction, summary, and debug levels.
- Identify target-state presentation contracts where the backend still exposes legacy dice or lineage compatibility fields.
- Keep route truth synchronized with `frontend/src/app/app.routes.ts`.

## Contract Boundary

The frontend consumes backend-authored state; it does not invent durable progression, derive hidden eligibility, or synthesize missing materials.

This document distinguishes:

- **Current route and service ownership:** what the Angular application currently ships.
- **Target presentation state:** approved semantics that may still require backend or frontend migration.

Compatibility aliases may remain in models during migration, but new UI work should use kin, recipe, and material terminology.

## Shell Model

The current frontend has two top-level experiences:

- public routes:
  - `/login`
  - `/guide`
- authenticated routes guarded under the main application shell

Authenticated routes render within a persistent shell that owns:

- top HUD and navigation
- route-framed page content
- shared motion and responsive layout behavior
- feature-gated navigation state

## Shared State Slices

### Session

Owned by `SessionService`.

Responsibilities:

- authenticated versus guest state
- current user id and display name
- CSRF token
- initial application bootstrap and refresh
- logout flow

### Profile

Owned by `SessionService` through cached profile refresh.

Current profile responsibilities include:

- energy
- soft and hard currency
- Raw Chaos
- active run id and summary-relevant run state
- units and their kin compatibility fields
- squads, active squad, formation, and unit cap
- dice inventory and equipment usage
- feature unlocks
- unit-type unlocks
- region unlocks and region catalog data
- generic items and legacy region items
- objectives
- kin or lineage discovery/eligibility compatibility state
- Codex-related inferred state where currently supplied

The profile is refreshed after most successful mutations. Components should not maintain parallel durable copies of profile-owned assets.

### Dice Presentation State

Current dice models may still include independent rarity and affix data because the runtime migration is incomplete.

Target dice presentation is material-led:

- display identity is material plus size, such as `Cardboard d4`
- rarity is read from the material
- effect summary and tags are read from the material definition
- sale and salvage values are backend-authored
- equipment location is associated with the die instance
- permanent affixes are compatibility-only and eventually disappear

The UI must not treat a missing material as neutral. Cardboard is an explicit material.

Filters and sorting should migrate from affix-oriented identity toward:

- size
- material
- material rarity
- material tags
- equipped versus unequipped state
- value or salvage value where relevant

### Kin Ownership and Eligibility State

The frontend may receive several related but distinct values:

- a unit's persisted kin
- whether the account has discovered a kin
- whether the kin may appear in ordinary unit grants or recruitment
- whether the kin Codex entry is owned

Owned units are the visible evidence of kin ownership. Discovery or eligibility flags must not be presented as though they are units.

Legacy `lineage_*` or `splice_variant_*` fields may remain in models during migration. New UI copy and state helpers should prefer `kin_*` terminology.

### Run

Owned primarily by `RunService` plus route-local page state.

Responsibilities:

- current run payload
- run creation, abandonment, and exit
- map and node state
- node resolution
- battle reward claims
- rest open/finalize actions
- chaos generation, reroll, and finalization where delegated
- dialogue and loot route transitions

### Wrong Machine

Owned by `WrongMachineService` plus route-local page state.

Current responsibilities:

- load available reconstruction previews
- submit reconstruction mutations
- refresh profile after successful mutation
- display feature-locked, available, missing-requirement, success, and error states

Target reconstruction state distinguishes:

- stable recipe identity
- output kin and unit count
- exact item and Raw Chaos costs
- owned and missing quantities
- whether the kin was previously discovered
- whether the recipe can currently be completed
- the produced unit
- whether completion caused first discovery
- newly enabled ordinary-grant eligibility
- Codex result
- retry-safe request state

The route must present the produced unit as the primary result of every successful deliberate recipe completion. First discovery is an additional milestone state, not a replacement output.

Previous kin discovery must not cause a repeatable recipe to appear permanently completed or unavailable when the player can afford another unit.

### Run Summary

Owned by `RunService.summary`.

Responsibilities:

- completed, failed, and abandoned run summary state
- rewards and progression payload
- surviving and defeated unit state
- next-action routing after terminal run outcomes

### Debug

Owned by `DebugService` and available only when runtime configuration enables it.

Responsibilities:

- grant currency
- grant units
- grant dice
- grant legacy region items where still supported
- inspect seed/catalog data
- set unit level
- reset account

Target dice grants must specify or resolve a valid size-material pair. Debug UI should not establish a permanent dependency on legacy affix creation.

## Current Route Table

### `/login`

Public landing route.

- Presents local registration and login with alternate Discord access.
- Redirects authenticated users through guards.

### `/guide`

Public guide route.

- Readable without authentication.
- Explains the game loop and player-facing rules.

### `/home`

Authenticated hub.

- Shows `Start Run` or `Continue Run` as the primary action.
- Surfaces current objective and major preparation/progression routes.
- Reflects feature-gated destinations such as Academy and Wrong Machine.

### `/codex`

Authenticated Codex route.

- Tracks account discovery across supported categories.
- Uses locked placeholders for undiscovered entries where implemented.
- Current compatibility may still include affix-oriented entries; target dice discovery uses materials.

### `/field-guide`

Legacy redirect to `/codex`.

### `/academy`

Feature-gated Academy route.

- Unlocks additional Tier-1 unit types.
- Lists promotable units and promotion destinations.
- Handles capstone requirements and inherited progression.

### `/regions`

Run-entry route.

- Displays backend-authored region state.
- Starts an unlocked region or returns an active run to `/run/map`.

### `/warband`

Roster hub.

- Displays squads and units.
- Creates and activates squads.
- Links to squad and unit detail pages.

### `/warband/squads/:squadId`

Squad editing route.

- Edits name, membership, and formation.
- Supports pointer and touch placement.
- Blocks changes while locked by an active run.

### `/warband/units/:unitId`

Unit detail route.

- Displays stats, kin, progression, capstone, and passives.
- Renames the unit.
- Edits active ability loadout.
- Assigns and clears dice on ability slots.
- Links into relevant progression flows.

### `/dice`

Dice inventory route.

- Filters and sorts owned dice.
- Shows equipment usage.
- Sells eligible dice.
- Must migrate presentation from independent rarity/affixes to size/material identity.

### `/shop`

Feature-gated economy route.

- Displays starter offers, daily deals, and feature unlocks.
- Disables unavailable, unaffordable, or already-completed purchases.
- Target dice offers display valid size-material outcomes rather than rarity-plus-affix construction.

### `/wrong-machine`

Feature-gated reconstruction route.

- Loads recipe previews from `WrongMachineService`.
- Displays item and Raw Chaos requirements.
- Submits reconstruction and refreshes profile state.
- Target success presentation shows the produced unit on every completion and separately identifies first discovery.

### `/run/map`

Active run traversal route.

- Renders the backend-authored graph and node statuses.
- Shows squad formation and run-unit condition.
- Opens node, rest, dialogue, loot, abandon, and exit flows.

### `/run/dialogue/:nodeId`

Run dialogue route.

- Presents node-bound dialogue.
- Applies completion and progression effects through backend-owned flow.
- Returns to the correct run destination.

### `/run/node/:nodeId`

Combat or general node-resolution route.

- Resolves supported node types.
- Presents outcome state and battle-log detail.
- Claims rewards and routes according to run state.

### `/run/loot/:nodeId`

Dedicated loot route.

- Presents loot-node outcome and claim state.
- Returns to the run loop safely after completion.

### `/run/rest/:nodeId`

Rest route.

- Opens backend-authored rest state.
- Shows recovery preview.
- Finalizes rest and returns to the run loop.

### `/run/summary`

Terminal run-state route.

- Displays outcome, rewards, progression, surviving units, and defeated units.
- Handles complete, failed, and abandoned runs through one shared page.

### `/debug`

Environment-gated operator route.

- Exposes testing and balancing helpers.
- Is not part of normal player progression.

## Mutation and Refresh Rules

- Profile-owned assets refresh after successful mutations.
- Route-local pending state prevents duplicate submissions while a request is in flight.
- Retry-sensitive asset creation must use backend idempotency rather than relying only on disabled buttons.
- Errors preserve enough local state for the player to understand whether anything was spent or created.
- A successful reconstruction must not be reduced to a generic toast; the produced unit and spent recipe need deliberate result presentation.
- A material migration must not cause components to independently recompute rarity, value, or effect behavior.

## Current Naming Rules

- Player-facing UI prefers `squad`; backend compatibility still uses `team` in routes and some payloads.
- Player-facing and new implementation surfaces prefer `kin`; legacy storage and payloads may still expose `lineage` or `splice_variant` compatibility fields.
- Reconstruction UI refers to recipes and produced units rather than treating lineage unlock as the repeatable action.
- Dice UI refers to explicit materials in the target model; affixes are legacy compatibility data.

## Implementation Drift to Track

- Wrong Machine frontend models currently mirror a one-time lineage-unlock response rather than the repeatable recipe result contract.
- Reconstruction requests do not yet carry a request-level idempotency key.
- Dice surfaces still depend on independent rarity and permanent affix data.
- Material-based filtering, Codex identity, and effect presentation are not yet the active runtime path.
- Some profile fields conflate kin discovery, eligibility, and ownership through legacy lineage state.

These gaps should be resolved through implementation work, not hidden by route-local inference.
