---
Title: "Dice Goblins vNext Game Overhaul"
Status: Working Draft
Last Updated: 2026-09-09
Owner: Product + Engineering
Depends On:
  - documentation/00-overview/00-project-overview.md
  - documentation/00-overview/01-core-gameplay-loop.md
  - documentation/02-systems/README.md
  - documentation/05-technical/00-tech-stack.md
  - documentation/05-technical/04-data-model.md
  - documentation/05-technical/08-hybrid-phaser-audio-architecture.md
Category: 07-development-path
Tags:
  - vnext
  - architecture
  - phaser
  - database
  - progression
  - overhaul
---

# Dice Goblins vNext Game Overhaul

## Purpose

Dice Goblins vNext is a deliberate implementation reset built from the game that exists today rather than from the assumptions of the original browser prototype.

The overhaul takes a small step backward in implementation maturity in exchange for a substantial step forward in product cohesion. It preserves the game's identity, core systems, authored content, and useful assets while allowing frontend architecture, backend boundaries, progression contracts, reward flow, unlock flow, and database design to be rebuilt around the game Dice Goblins has become.

The working branch is `vnext-game-overhaul`.

## Confirmed Constraints

- Existing production/runtime player data does not need to migrate into vNext.
- Backward compatibility with the current database schema is not a requirement.
- Existing migration history is design evidence, not a contract that vNext must preserve.
- Dice Goblins remains web-delivered for this overhaul.
- PHP remains the authoritative backend unless a later explicit architecture decision changes that.
- MySQL remains the persistent runtime datastore unless a later explicit architecture decision changes that.
- Angular remains available for the public website, authentication, account/platform surfaces, and hosting the game runtime.
- Phaser becomes the primary game client and owns gameplay presentation and gameplay UI.
- Mobile packaging is a future requirement to remain compatible with, not an immediate release target.
- Existing game rules should be preserved by default, but systems may be simplified, generalized, removed, or redesigned when current implementation history has produced unnecessary complexity.

## Target Client Boundary

The intended target is a website that hosts a game, rather than an Angular application that occasionally embeds Phaser.

### Angular owns

- public and marketing pages
- authentication and session entry flow
- account/platform pages that are not part of gameplay
- route protection for the game entry point
- one persistent host boundary for the Phaser runtime
- platform bootstrap and catastrophic host-level error states

### Phaser owns

- all player-facing gameplay screens
- all in-game navigation
- game HUD and menus
- camp/home presentation
- warband and squad management
- unit and dice management
- Academy, shop, Wrong Machine, Codex, and other game systems
- region selection and run presentation
- encounters, dialogue, battle playback, rewards, and run summaries
- game audio, animation, transitions, particles, camera behavior, and game-specific input
- responsive gameplay composition for desktop first and later mobile readiness

### Backend owns

- authoritative player state
- authoritative collection and progression state
- validation of all gameplay mutations
- run generation and lifecycle
- encounter and combat resolution
- reward generation and materialization
- purchases, recipes, upgrades, promotions, unlocks, and other progression transactions
- idempotency and transactional safety

Phaser should be treated as a first-class API client rather than a renderer subordinate to Angular. Angular establishes or verifies the browser session; Phaser consumes gameplay APIs using that authenticated session.

## Architecture Principles

1. **Design current-state first.** vNext models the game we intend to ship, not every state the prototype passed through.
2. **Backend authority remains explicit.** Presentation may be richer and more immediate, but player-affecting state changes are validated and committed by the server.
3. **Game presentation and game rules stay separate.** Phaser interprets authoritative state and results; it does not become a second combat or progression engine.
4. **Definitions and instances stay distinct.** Authored unit, die, item, region, encounter, reward, unlock, and recipe definitions are not player-owned records.
5. **Facts, rewards, and unlocks are different concepts.** A progression event records what happened; a reward/grant transfers assets; an unlock records persistent access or entitlement.
6. **All grants share one transactional application path.** Combat, dialogue, bounties, purchases, crafting, tutorials, and administrative grants may originate differently but should not each reinvent currency, inventory, die, unit, or unlock mutation logic.
7. **Authored content is not migration history.** Balance/content revisions should not normally require one new SQL migration per tuning change.
8. **Migration count starts over.** Once the target schema is approved and implemented, vNext should establish a clean baseline rather than replaying the prototype's historical schema evolution.
9. **Mobile readiness begins at the UI architecture.** Responsive scaling, pointer/touch semantics, safe layout regions, and asset budgets should be considered while building the new Phaser UI even though native/mobile packaging comes later.
10. **Vertical slices prove architecture.** Avoid rewriting every subsystem in parallel before one complete player loop works end to end.

## Proposed Overhaul Phases

### Phase 0 - Isolate the Rewrite

Status: Started.

- branch from `main` into `vnext-game-overhaul`
- treat current implementation and documentation as reference material
- do not preserve runtime data compatibility
- keep `main` stable while vNext design and implementation are incomplete

Exit condition: the overhaul can make breaking schema, API, and frontend decisions without destabilizing the current product branch.

### Phase 1 - Reconcile the Game Specification

Before deep implementation, inventory every active gameplay system and classify it as:

- preserve
- simplify
- redesign
- remove
- defer

Update the overview, gameplay loop, system inventory, technical architecture, and UX direction so that they describe one coherent vNext target rather than a mixture of current implementation, target-state drift, and historical MVP assumptions.

Priority missing or incomplete contracts include:

- run lifecycle
- encounter resolution
- damage/modifier/status resolution
- enemy action selection
- dice acquisition and runtime material behavior
- reward materialization
- currencies and economy
- shop
- energy
- Academy progression
- feature unlocks
- region progression

Exit condition: core gameplay and progression can be described without relying on implementation archaeology.

### Phase 2 - Redesign Progression, Rewards, and Economy

Establish distinct contracts for:

- progression events
- reward definitions/profiles
- finalized grant bundles
- grant application and idempotency
- currencies
- stackable inventory items
- owned unit instances
- owned die instances
- feature/content unlock entitlements
- region completion/progression
- Codex discovery
- recipes and crafting/reconstruction
- purchases and Academy transactions

The goal is not to force all concepts into one generic table. The goal is to centralize mutation semantics while allowing each durable domain to retain an appropriate normalized model.

Exit condition: every major way a player gains, spends, unlocks, discovers, crafts, or purchases something has an explicit and non-overlapping lifecycle.

### Phase 3 - Design the vNext Data Model

Create the database from the approved current-state domain model instead of incrementally transforming the prototype schema.

Recommended data domains:

- accounts and identities
- player profile and currencies
- authored content catalogs
- warband and unit instances
- dice instances and equipment
- stackable inventory
- squads and formations
- progression and unlocks
- region progression and Codex discovery
- runs and encounter state
- combat results/events
- transactional grant/idempotency records

Separate schema evolution from authored content revision. Establish a small vNext baseline and reserve later migrations for actual schema evolution.

Exit condition: a clean database can be created from scratch and fully support the approved vNext vertical slice without legacy compatibility columns or obsolete tables.

### Phase 4 - Establish the vNext Backend Contract

Reshape backend APIs around game use cases rather than around Angular page composition.

Key goals:

- one intentional game bootstrap contract
- stable domain-oriented DTOs
- explicit command/mutation endpoints
- reusable transaction and grant infrastructure
- deterministic/idempotent encounter and reward finalization
- no presentation-specific API coupling
- preserve deterministic combat and run-generation capabilities that remain valuable

Exit condition: a non-Angular game client can drive one complete game loop using the backend API alone.

### Phase 5 - Establish the Persistent Phaser Runtime

Replace Angular-owned gameplay routing with one persistent Phaser-hosted game application.

Initial client foundation should include:

- boot/loading flow
- game state/cache layer
- API client
- screen/navigation manager
- event/message bus where useful
- reusable game UI primitives
- responsive scale/layout rules
- input abstraction suitable for pointer and future touch
- asset registry/loading strategy
- audio ownership
- deterministic debug/screenshot hooks

Angular should mount and destroy the runtime, but should not coordinate normal in-game screen transitions.

Exit condition: authenticated entry reaches a Phaser-owned game shell that can load authoritative player state and navigate between placeholder gameplay screens without Angular gameplay routes.

### Phase 6 - Build One End-to-End Vertical Slice

Prove the new architecture before porting every subsystem.

The recommended slice is:

1. enter the game through Angular-authenticated bootstrap
2. arrive in the Phaser camp/home experience
3. inspect/edit the active squad
4. select an available combat-capable region
5. start or resume a run
6. navigate the run map
7. resolve one combat encounter on the backend
8. play the authoritative result in Phaser
9. present and claim finalized rewards
10. persist progression and return to the run/camp flow

The Farm is a strong initial combat slice because it exercises combat, units, dice, rewards, progression, and game presentation without requiring every later biome system.

Exit condition: the primary architectural path is proven from authentication through persistent reward application.

### Phase 7 - Migrate Remaining Game Systems

Move systems into the vNext contracts one domain at a time, including:

- Mystic Cave onboarding and dialogue
- full warband/unit progression
- dice inventory, materials, salvage, and loadouts
- Academy
- shop
- Wrong Machine and kin reconstruction
- Mountains and Swamps procedural runs
- rest, hazards, shrines, and chaos encounters
- Codex
- bounty/objective systems
- consumables
- run summary and failure/recovery flows

Delete obsolete compatibility surfaces instead of carrying them indefinitely.

Exit condition: vNext reaches feature parity with the intentionally preserved current game surface.

### Phase 8 - Game Feel, Release Hardening, and Mobile Readiness

Once the architecture is stable, invest in the leap-forward experience:

- camp as the persistent game-world mental model
- animated game-native navigation
- transitions and feedback for progression changes
- battle playback quality and speed controls
- richer reward presentation
- character reactions and dialogue staging
- keyboard/controller/touch strategy where appropriate
- responsive layouts and safe areas
- performance and asset-memory budgets
- future packaging evaluation for mobile and potentially desktop distribution

## Database Reset Policy

The current migration sequence will not be carried forward as the runtime installation contract for vNext.

After the vNext model is approved:

- create a clean baseline schema representing the intended starting state
- keep Git history as the archive of prototype migrations rather than copying obsolete SQL into a permanent legacy runtime folder
- reserve future numbered migrations for structural schema changes after the baseline
- keep authored content and balance data outside the schema-migration lifecycle wherever practical
- make content loading/import deterministic and validated
- make a fresh local/test database the default vNext development workflow

The exact baseline file layout and content-catalog ownership model remain architecture decisions to finalize before deleting the current migration chain from the vNext branch.

## Reward and Unlock Direction

The current reward system should be replaced by a clearer conceptual pipeline:

```text
Gameplay fact / transaction intent
            |
            v
Progression or transaction rule
            |
            +----> state transition / entitlement when applicable
            |
            v
Finalized Grant Bundle
            |
            v
Transactional Grant Application
            |
            +----> currency
            +----> items
            +----> unit instances
            +----> die instances
            +----> unlock entitlements
            +----> XP/progression effects where appropriate
```

Examples:

- A boss victory is a progression fact and reward source. It may both finalize a reward bundle and satisfy a region-progression rule.
- A region completion is persistent progression state. It may cause another region entitlement to be granted, but the completion record and the entitlement are not the same concept.
- A shop purchase is not a reward. It spends assets and uses the common grant/application path to create the purchased result.
- Wrong Machine reconstruction is not a normal loot reward. It is a recipe transaction that consumes assets, creates a unit, and may emit first-ownership progression effects.
- Codex discovery is collection/progression state and should not be overloaded into generic feature-unlock semantics unless the design explicitly treats the page itself as a granted asset.

## Content and Migration Direction

The prototype frequently uses migrations to introduce or rebalance authored content. vNext should instead distinguish:

- schema: relational structure and constraints
- code: executable behavior and handlers
- authored content: version-controlled definitions and tuning data
- runtime data: player state and generated state

A likely target is version-controlled authored content with deterministic validation/import into MySQL when relational runtime access is valuable. Final ownership rules should be decided per content category rather than requiring every piece of game content to use one storage mechanism.

## Immediate Architecture Decisions to Resolve

Before broad implementation, resolve these decision groups:

1. **Game-client state model:** Phaser navigation, cached authoritative state, invalidation/refresh rules, and API error recovery.
2. **Game bootstrap/API shape:** what Phaser receives at startup versus what remains lazy-loaded.
3. **Reward/grant model:** finalized rewards, claims, grant bundles, transactions, and idempotency.
4. **Progression/unlock model:** features, unit types, regions, kin eligibility, Codex, tutorials, objectives, and completion state.
5. **Currency/economy model:** Teeth, Raw Chaos, energy, pricing, purchases, sinks, and whether currencies share one storage model.
6. **Authored-content ownership:** which catalogs belong in version-controlled data, database tables, code registries, or presentation manifests.
7. **Baseline database layout:** domains, constraints, history/audit requirements, and the new migration convention.
8. **Backend service boundaries:** which existing services survive, which merge behind shared infrastructure, and which large orchestration services should be decomposed.
9. **Phaser UI architecture:** screen versus scene boundaries, reusable UI components, scaling, input, accessibility strategy, and deterministic visual testing.
10. **First vertical slice:** exact Farm/new-player path and the minimum systems required to prove the architecture.

## Non-Goals for the Initial Overhaul

- migrating current player/runtime data
- preserving obsolete schema/API compatibility purely for historical reasons
- converting the backend away from PHP without a separate decision
- converting the database away from MySQL without a separate decision
- rewriting the product in Godot during this initiative
- shipping native mobile clients before the web vNext architecture is stable
- porting every current Angular gameplay page one-for-one before the new Phaser game shell is proven

## Definition of Success

The overhaul is successful when Dice Goblins still contains the game players recognize, but its implementation behaves like a deliberately built game rather than a browser application that accumulated game systems over time:

- Phaser is the cohesive player-facing game runtime.
- Angular is a thin web/platform shell.
- PHP exposes a coherent authoritative game API.
- MySQL represents the current domain without prototype-era compatibility baggage.
- rewards, progression, unlocks, purchases, crafting, and ownership have explicit non-overlapping contracts.
- content revision no longer produces unnecessary schema migrations.
- one clean baseline can create the complete development database.
- future game features can be added without repeating the architectural drift that motivated vNext.
