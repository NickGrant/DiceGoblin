---
Title: "vNext API Contract Model"
Status: Accepted
Last Updated: 2026-09-12
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
Category: 07-development-path
Tags:
  - vnext
  - api
  - backend
  - phaser
  - contracts
  - idempotency
---

# vNext API Contract Model

## Decision

vNext will expose a backend API designed for a persistent Phaser game client rather than a page-oriented Angular application. Angular remains responsible for authentication/account shell behavior and hosts the Phaser runtime; once the game is running, Phaser communicates directly with the PHP API.

The PHP backend remains authoritative for all persistent state changes, gameplay resolution, purchases, progression, rewards, run generation, combat, dice/unit mutations, and authentication/session validity.

The API should be designed around domain resources for reads and explicit player-intent commands for mutations.

## Core Contract Rules

- API routes remain versioned under `/api/v1/...` unless a later migration decision intentionally introduces a new version.
- OAuth entry routes remain under `/auth/...`.
- Authentication remains cookie/session based.
- Mutating requests use CSRF protection.
- GET/query endpoints do not mutate player state.
- Commands represent explicit player intent and are validated against authoritative server state.
- Commands that spend resources, create durable assets, roll randomness, or resolve gameplay use an idempotency boundary.
- Retry of the same idempotency key returns the original finalized result rather than executing again.
- Successful command responses return the authoritative state affected by that command rather than forcing the client to reload a catch-all profile payload.
- Authored content remains Git-tracked JSON; API payloads reference authored content with stable IDs and include mutable/runtime state as needed.

## Game Bootstrap

Phaser receives a dedicated game bootstrap payload when the game runtime starts.

Bootstrap should be large enough to render the initial Camp experience without a burst of dependent requests, but should not recreate the current catch-all profile endpoint.

Bootstrap should include at least:

- account identity required by the game:
  - user ID
  - display name
  - role
- dynamic player state:
  - Teeth
  - Raw Chaos
  - current Energy
  - calculated Energy maximum
  - Energy regeneration timing needed for presentation
  - active squad ID
  - current `player_revision`
- progression summary:
  - currently owned/unlocked stable IDs needed to determine major game availability
- active-run summary, when one exists:
  - run ID
  - region ID
  - run status
- active-squad state:
  - squad ID and name
  - all nine positions
  - enough mutable unit state to render the initial Camp squad
- session/system metadata required by Phaser, including current CSRF/session information and deployed content/application version where useful.

Bootstrap should not include every owned unit, every die, the complete Codex, all saved squads, all objectives, Shop stock, Academy catalog, Wrong Machine state, a full active-run graph, or battle playback unless a concrete initial-screen requirement later proves one of those belongs there.

## Lazy Domain Queries

Large or screen-specific data should be loaded on demand by game domain rather than by UI widget or Phaser screen implementation.

Expected query domains include:

- units
- dice
- squads
- objectives
- Codex ownership
- Shop state
- Academy state
- Wrong Machine state
- current run
- battle playback while retained

The backend should avoid presentation-specific routes such as `camp/sidebar` or `warband/modal`. Phaser decides how domain state is assembled into screens.

## Query and Command Boundary

The API should maintain a clear conceptual distinction:

- **Queries** request current authoritative state and do not mutate it.
- **Commands** ask the server to attempt a specific gameplay or configuration action.

This is a contract discipline, not a requirement to implement formal CQRS infrastructure.

Examples of queries include retrieving units, squads, current run state, objectives, Codex ownership, or available Shop/Academy actions.

Examples of commands include starting or abandoning a run, resolving a node, purchasing an offer, promoting a unit, reconstructing a goblin, activating a squad, consuming an item, or updating an editable configuration.

## Mutation Granularity

vNext uses a hybrid mutation strategy.

### Discrete Gameplay Commands

A discrete gameplay action is represented by one explicit command.

Examples include:

- rename a unit
- promote a unit
- activate a squad
- purchase a Shop offer
- purchase/unlock an Academy upgrade
- perform Wrong Machine reconstruction
- consume a consumable
- start a run
- abandon/exit a run
- resolve a run node

These endpoints should accept only the inputs appropriate to the command and should not expose arbitrary field mutation.

### Whole-Configuration Mutations

Editable configurations should be submitted as complete desired aggregates rather than as a sequence of tiny UI mutations.

Examples include:

- squad composition and all nine positions
- a unit's complete ability loadout/order
- the unit's equipped dice bindings associated with its abilities

The server validates the complete proposed configuration and commits it atomically or rejects it as a whole.

This prevents invalid intermediate states such as duplicate squad positions, partially swapped units, incomplete ability ordering, or a die being temporarily bound to multiple locations while the client performs a multi-step UI gesture.

Whole-configuration APIs do not imply a visible Save button. Phaser may autosave immediately after a UI interaction by submitting the newly calculated complete configuration.

### Arbitrary Patching Is Avoided

The backend should not expose generic player-state or unit patch endpoints that allow clients to mutate arbitrary fields.

For example, the client should issue a `promote unit` command or an `update loadout` configuration rather than patching fields such as level, unit type, equipped dice, or progression state directly.

## Mutation Responses

Successful mutation responses should return the authoritative state affected by the command.

Examples:

- a purchase returns finalized spend, rewards/assets created, resulting balances, and newly granted unlocks where applicable;
- a promotion returns the authoritative updated unit and newly granted abilities;
- a squad update returns the accepted squad configuration;
- a unit-loadout update returns the accepted unit combat configuration;
- a run-node resolution returns the resulting run/node state, battle result/playback when applicable, finalized rewards, affected unit HP, modifier changes, newly available nodes, and resulting run status.

The client should not POST a mutation and then fetch the entire player profile solely to discover what changed.

## Player Revision

`user_state` maintains a monotonically increasing `player_revision` value.

Each committed mutation that meaningfully changes persistent player state increments the revision. Bootstrap and successful mutation responses include the current revision.

The initial purpose is a lightweight stale-cache and synchronization signal for Phaser. It may later help detect reconnect drift, changes made by another browser session/tab, or unexpected client/server disagreement.

`player_revision` is not initially used as an optimistic-lock precondition. The server still validates every command against current authoritative state. Historical revisions do not need to be retained.

## Run Aggregate

An active run is treated as a coherent backend-owned aggregate.

The current-run query should expose enough runtime state for Phaser to reconstruct the run presentation, including:

- run ID
- authored region ID
- status
- participating/locked squad identity
- generated nodes and node state
- graph edges
- current availability of nodes
- current HP for participating units
- active run modifiers and their mutable scalar values where applicable
- unresolved/current encounter information needed to resume safely

The schema does not need separate chosen-path history because node completion plus generated graph connectivity determines which adjacent eligible nodes become available.

## Run Configuration Locking

Once a run begins, every player-controlled combat configuration used by that run is locked until the run reaches a terminal state.

While a run is active, the player cannot mutate participating configuration that could change the authoritative combat setup, including:

- squad membership and positions
- active squad switching where it would alter the current run
- unit promotion state
- unit ability ownership/loadout/order where player-controlled
- equipped dice bindings for participating units

The backend enforces these restrictions. Phaser presentation alone is not trusted to provide the lock.

## Node and Battle Resolution

Node resolution is one authoritative server operation.

For a combat node, Phaser requests node resolution; PHP validates the node and run state, resolves the full battle, applies resulting gameplay state, resolves resulting events/rewards, and returns the complete finalized outcome required for presentation.

Phaser does not call the backend for individual rolls, attacks, ticks, targeting decisions, or damage applications.

Battle playback is an output of authoritative resolution, not a simulation protocol.

Playback data remains retrievable while its run is active so an interrupted client can replay/resume presentation. After the run reaches a terminal success/failure state, battle playback becomes eligible for the scheduled cleanup policy defined by the storage model.

## Reward Application and Presentation

Ordinary rewards do not require a separate player claim mutation.

When an authoritative event resolves:

1. the event is finalized exactly once;
2. reward probabilities are rolled exactly once;
3. grants are applied transactionally/idempotently;
4. the API response returns the finalized reward receipt;
5. Phaser presents the result to the player.

By the time Phaser displays a reward such as a region unlock, currency grant, unit, die, item, Codex entry, or permanent unlock, ownership has already been committed.

A Continue/acknowledge action on a reward screen is presentation only.

If a future mechanic offers a player a choice between unresolved rewards, that should be modeled separately as a durable pending offer rather than by reintroducing claim semantics for ordinary rewards.

## Idempotency

Commands that can spend resources, create durable assets, invoke randomness, or resolve gameplay use a client-generated request idempotency key or equivalent explicit request identity.

This includes at least:

- run creation
- Shop purchases
- Academy purchases/unlocks
- Wrong Machine reconstruction
- node resolution
- promotion or other retry-sensitive durable progression
- consumable use when inventory is spent
- other commands that create unit/die/item instances or finalize randomized results

The backend retains the finalized request/result record for the operational retry window. Repeating the same idempotency key returns the original result without spending again, rerolling, or creating duplicate assets. Keys are scoped to the authenticated user; reusing a key for a different normalized request is a conflict.

Squad creation is the first concrete vNext use of this contract. `POST /api/v1/squads` requires an `Idempotency-Key` containing 8-128 case-sensitive ASCII characters from letters, digits, `.`, `_`, `:`, and `-`. Its finalized record is retained until a future scheduled operational cleanup policy is introduced; requests must not depend on an exact cleanup duration.

These records are operational correctness data rather than permanent player-visible history and are eligible for scheduled cleanup under the storage model.

## Authentication Boundary

The vNext API preserves direct local account authentication and Discord OAuth while supporting multiple authentication methods attached to one Dice Goblins user.

The game API operates against the resolved `users.id`; gameplay state is independent of whether the current session was established through Discord or local credentials.

Account linking and credential-management flows remain part of the Angular/account shell rather than Phaser gameplay UI unless a later product decision intentionally moves them into the game.

## Intentionally Deferred Implementation Details

The following do not block the accepted API architecture:

- exact REST route names for every vNext command/query;
- exact DTO/property naming;
- exact HTTP status/error envelope conventions;
- exact cleanup/retention durations;
- whether battle playback is represented as one JSON payload or a more segmented representation;
- whether additional resource-specific cache validators are introduced alongside `player_revision`;
- whether a future API version replaces `/api/v1` during migration.

These should be selected during endpoint and implementation design without weakening the boundaries established here.

## Consequence for the Existing API

The existing catch-all profile contract and compatibility-era target fields are not the vNext design authority. Useful domain algorithms and current endpoint behavior may be preserved where they fit these decisions, but vNext should not retain large profile refreshes, legacy claim semantics, arbitrary patching, or UI-oriented orchestration merely for compatibility.
