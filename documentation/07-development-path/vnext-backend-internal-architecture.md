---
Title: "vNext Backend Internal Architecture"
Status: Accepted
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-api-contract-model.md
  - documentation/07-development-path/vnext-endpoint-inventory.md
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
Category: 07-development-path
Tags:
  - vnext
  - backend
  - architecture
  - php
  - services
  - repositories
  - content-registry
---

# vNext Backend Internal Architecture

## Decision

vNext remains a single deployable PHP backend backed by one MySQL database. The overhaul will not introduce microservices, asynchronous messaging, full event sourcing, or other distributed-system machinery that is not justified by the game's scale.

The backend will instead be reorganized around explicit architectural responsibilities:

```text
HTTP
Controllers
    ↓
Application Commands / Queries
    ↓
Domain Rules + Gameplay Engines
    ↓
Repositories      Content Registry
    ↓                   ↓
MySQL             Authored JSON
```

Cross-cutting infrastructure provides authentication, session management, CSRF, transactions, idempotency, logging, clocks/randomness where useful, and `player_revision` handling.

The important rule is dependency direction, not exact folder names:

> Controllers depend on application operations; application operations coordinate domain rules, engines, repositories, and authored content. Persistence and HTTP concerns do not leak into computational game rules.

## Why vNext Changes the Current Shape

The prototype proved useful game systems, but `Services/` became a broad catch-all containing orchestration, calculations, SQL access, transaction ownership, response construction, and large gameplay workflows.

vNext should preserve valuable algorithms while separating those responsibilities so future features have an obvious architectural home.

## Controllers

Controllers are HTTP adapters only.

A controller may:

- authenticate the request;
- enforce CSRF requirements for mutations;
- parse and perform basic HTTP/input-shape validation;
- construct or invoke the relevant application command/query;
- map application results and known failures to HTTP responses.

A controller should not:

- contain gameplay rules;
- decide reward outcomes;
- directly perform multi-step persistence workflows;
- own business transaction sequencing;
- calculate node availability, progression, squad validity, or combat outcomes.

Controller boundaries should follow coherent API domains, such as Account, Game Bootstrap, Units, Dice, Squads, Shop, Academy, Wrong Machine, Objectives, Runs, Run Nodes, and Battles.

A generic catch-all `GameplayController` should not remain the normal home for unrelated game mutations.

## Application Layer

The application layer owns use cases and complete player intentions.

Examples include:

- purchase a Shop offer;
- promote a unit;
- replace a unit loadout;
- replace a squad configuration;
- activate a squad;
- start a run;
- resolve a run node;
- abandon a run;
- reconstruct a goblin through the Wrong Machine;
- unlock an Academy upgrade.

The exact implementation does not require one PHP class per endpoint. Closely related use cases may share an application service when that remains cohesive.

The governing rule is:

> One application operation represents one complete authoritative player intention and owns the transaction boundary for that intention.

Application operations coordinate lower-level rules and persistence but should not contain reusable game formulas that belong in domain services or gameplay engines.

## Transaction Ownership

vNext uses a single transaction owner per mutating application operation.

Conceptually:

```text
HTTP mutation
    ↓
Application command
    ↓
Begin transaction
    ↓
Validate authoritative state
Apply costs / effects
Resolve gameplay
Resolve rewards
Persist resulting state
Increment player_revision once
    ↓
Commit
```

Nested repositories, domain services, reward handlers, and gameplay engines must not independently commit the parent operation.

A lightweight transaction abstraction may be introduced so application operations can execute work atomically without duplicating low-level PDO transaction boilerplate.

`player_revision` increments once for a successfully committed persistent player-state mutation, even when that command changes several tables.

## Idempotency

Retry-sensitive commands use a shared idempotency convention.

Commands that spend resources, create durable assets, roll randomness, or resolve gameplay must be protected by an idempotency boundary.

The idempotency mechanism belongs at the application/infrastructure boundary rather than being independently reinvented by Shop, Wrong Machine, Runs, rewards, and other domains.

Repeating the same finalized command identity must return the previously finalized result rather than spending, rolling, or granting again.

Temporary idempotency/event-resolution records may be removed later by the accepted scheduled cleanup policy.

## Repositories

Repositories are deliberately narrow persistence components.

Repositories know:

- SQL;
- MySQL row shape;
- relational queries;
- persistence operations;
- database locking where required.

Repositories do not know:

- authored gameplay rules;
- why a region or unit type should unlock;
- how a die material/aspect profile behaves;
- Shop or Academy eligibility semantics;
- reward probabilities;
- combat resolution rules;
- presentation/API response shape.

Expected vNext repository domains include, as needed:

```text
UserRepository
UserStateRepository
UnitRepository
DiceRepository
SquadRepository
InventoryRepository
UnlockRepository
CodexRepository
ObjectiveRepository
RunRepository
RunNodeRepository
RunEdgeRepository
RunModifierRepository
BattleRepository
ResolvedEventRepository
AuthenticationIdentityRepository
```

This list is conceptual rather than a mandate for one repository class per table.

Repositories exist for mutable/runtime MySQL data only. Authored catalogs such as regions, unit types, die profiles, materials, aspects, rewards, objectives, Shop offers, Academy upgrades, dialogue, and Wrong Machine recipes do not receive catalog repositories backed by MySQL.

## Authored Content Registry

The authored-content model requires a first-class runtime registry.

`ContentRegistry` represents the normalized in-memory view of the Git-tracked JSON game definitions.

Conceptually it provides domain-oriented access to definitions such as:

```text
units
abilities
kin
dice profiles
materials
aspects
regions
nodes
rewards
modifiers
objectives
dialogue
Shop offers
Academy upgrades
Wrong Machine recipes
```

The exact public API may be split into domain views rather than one giant class. All such views belong to one coherent authored-content subsystem.

The registry:

- loads authored JSON;
- indexes definitions by stable ID;
- resolves authored references;
- exposes normalized definitions to application/domain code;
- avoids repeated ad hoc file loading throughout the backend.

The registry is not a second database and does not duplicate mutable player state.

## Content Validation

Content consumption and content validation are separate responsibilities.

CI performs the heavy structural and semantic validation established by the authored-content contract, including duplicate IDs, broken references, invalid reward probabilities, invalid recipes, invalid die profiles, and aspect/size compatibility.

Runtime code consumes known-good content through the registry and should fail safely when impossible references are encountered, but it should not rebuild the complete validation graph for every HTTP request.

## Domain Rules

Reusable deterministic game rules should live outside controllers and repositories.

Examples include:

```text
Energy calculation
Squad validation
Promotion rules
Unit progression
Dice valuation
Run modifier behavior
Target resolution
Reward rolling
```

These components should accept explicit state/definitions and return decisions or calculations. Where practical they should not know about HTTP, sessions, or direct SQL access.

This is the preferred location for reusable rule logic that must be tested independently from persistence.

## Combat Engine

Combat remains a distinct computational subsystem.

The existing conceptual separation between combat abilities and the combat engine is retained.

Combat should operate approximately as:

```text
CombatInput
    ↓
CombatEngine
    ↓
CombatResult
```

A combat result may contain:

- outcome;
- resulting unit HP/status state;
- playback events;
- progression facts such as XP inputs;
- semantic gameplay facts that can advance objectives or trigger rewards.

The combat engine should not directly write MySQL or construct HTTP responses.

A node-resolution application operation loads authoritative state, constructs combat input, invokes the engine, persists the result/playback, applies progression and rewards, updates the run/node state, increments `player_revision`, and commits the transaction.

This separation allows combat and balance simulation to run without requiring a live database mutation workflow.

## Run Generation

Run generation is treated similarly to combat: computational generation is separate from starting/persisting a run.

Conceptually:

```text
Authored region/pattern configuration
    ↓
RunGenerator
    ↓
GeneratedRunGraph
```

The Start Run application operation remains responsible for:

- validating region access;
- validating/locking the active squad configuration;
- spending Energy;
- invoking run generation;
- persisting the run, nodes, edges, modifiers, and run-unit state;
- incrementing `player_revision`;
- committing atomically.

Existing run-generation, pattern-compilation, and graph-validation algorithms should be evaluated for reuse/refactoring rather than discarded solely because persistence is being rebuilt.

## Reward Resolution Pipeline

Rewards are a first-class internal subsystem because they are used by combat, bosses, dialogue, objectives, Shop/Academy/World interactions, and other successful events.

Conceptually:

```text
Semantic Event
    ↓
Event Resolver
    ↓
Reward Roller
    ↓
Finalized Reward Result
    ↓
Grant Applicator
```

Grant application may dispatch to domain-specific handlers for:

- currency;
- stackable items;
- units;
- dice;
- unlocks;
- Codex entries;
- XP/additive progression.

Reward resolution remains synchronous inside the parent application transaction.

The use of the word "event" does not imply a message bus or asynchronous/event-sourced architecture. It is a gameplay abstraction for a semantic occurrence whose authored rewards must resolve exactly once.

Duplicate unique rewards continue to resolve to nothing rather than rerolling.

## Semantic Gameplay Facts and Objectives

Gameplay engines/application operations may produce internal semantic facts such as:

```text
enemy_defeated
node_completed
run_completed
unit_promoted
```

These facts may be passed synchronously to objective progression so bounty/objective counters are updated as authoritative gameplay occurs.

No infrastructure message bus is required for this mechanism.

## Queries and Read Models

Read/query paths may be optimized for the API surface rather than forced through the full mutation-domain model.

Examples:

- unit collection query returns compact `UnitSummary` records;
- unit detail query assembles complete mutable unit state for the individual unit screen;
- bootstrap query composes the account/user state, active squad, unlock summary, and active-run summary;
- current-run query assembles the full persisted run aggregate.

Purpose-built query services may join several runtime repositories and authored content views when needed.

This is a lightweight command/query separation, not a requirement to introduce a formal CQRS framework.

## DTO and Boundary Types

Associative arrays remain acceptable for local/simple calculations, but important architectural boundaries should use explicit typed DTO/value objects where they improve correctness.

Likely candidates include:

```text
UnitSummary
UnitDetail
CombatInput
CombatResult
RunAggregate
StartRunResult
RewardResult
```

The project should avoid creating ceremony-heavy DTO classes for every trivial three-field structure.

## Authentication, Sessions, and Security

Authentication/session infrastructure remains separate from gameplay domains.

The backend continues to support cookie/session authentication, CSRF protection, direct credentials, Discord OAuth, and linked authentication identities as defined by the storage/API contracts.

A user's game/application role belongs to the account, not to an individual login identity.

## Suggested Source Organization

The exact directory layout may evolve during implementation, but a target conceptual organization is:

```text
backend/src/
  Controllers/
  Application/
    Commands/
    Queries/
  Domain/
    Accounts/
    Economy/
    Units/
    Dice/
    Squads/
    Progression/
    Objectives/
    Rewards/
    Runs/
  Combat/
    Engine/
    Abilities/
  RunGeneration/
  Content/
  Repositories/
  Infrastructure/
  Http/
```

Do not preserve a generic `Services/` directory as the default destination for new logic merely because the prototype used it that way. New code should have an intentional architectural home.

## What vNext Explicitly Avoids

The baseline does not introduce:

- microservices;
- asynchronous service-to-service messaging;
- a distributed event bus;
- full event sourcing;
- a heavyweight CQRS framework;
- an ORM solely to abstract PDO;
- repository interfaces for every repository purely for ceremony;
- a heavyweight dependency-injection framework without a demonstrated need.

These may only be reconsidered if future requirements create a concrete problem that they solve.

## Guidance for Future Features

When adding a future feature, use these questions to determine where it belongs:

1. Is it authored/static game definition data? Put it in the Git-tracked JSON content model and expose it through the Content Registry.
2. Is it mutable player/runtime state? Persist it through the appropriate runtime repository/MySQL domain.
3. Is it a player intention that changes state? Model it as an application command with one authoritative transaction boundary.
4. Is it a read surface? Build a purpose-appropriate query/read model rather than returning unrelated account state.
5. Is it a reusable deterministic gameplay rule? Keep it in a domain rule/service or gameplay engine rather than a controller/repository.
6. Does it spend, create, roll, or resolve gameplay? Give the operation an idempotency boundary.
7. Does it produce rewards? Route those rewards through the shared event/reward/grant pipeline instead of inventing feature-specific grant semantics.
8. Does it modify player-controlled combat configuration? Enforce the active-run configuration lock.
9. Does it reference authored definitions from runtime state? Store stable authored IDs rather than duplicating authored catalog rows in MySQL.
10. Does the proposed abstraction solve a current concrete problem? If not, prefer the simpler monolith structure.

## Documentation Lifecycle

The `documentation/07-development-path/vnext-*.md` files are decision records for the overhaul and are intentionally useful while implementation is in motion.

After the vNext implementation stabilizes, accepted architectural concepts must be reconciled into the long-lived canonical technical/system documentation under the normal documentation hierarchy, especially `documentation/05-technical/` and the relevant `documentation/02-systems/` documents.

The post-implementation documentation pass should:

- describe the architecture that actually shipped;
- preserve the conceptual boundaries and rationale in this document;
- replace prototype-era canonical descriptions that conflict with vNext;
- avoid leaving vNext decision records as the only source of architectural truth;
- mark decision documents as implemented/superseded or retain them as historical rationale according to the repository's documentation convention;
- include enough guidance that future feature work can determine the correct layer, persistence boundary, content boundary, and transaction/reward model without reverse-engineering the implementation.

Implementation drift discovered during the rebuild should be resolved deliberately: either bring implementation back to the accepted architecture or amend the accepted decision and its eventual canonical documentation. Do not silently let implementation become an undocumented alternate architecture.
