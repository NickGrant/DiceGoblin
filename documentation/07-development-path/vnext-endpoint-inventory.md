---
Title: "vNext Endpoint Inventory"
Status: Accepted
Last Updated: 2026-09-13
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-api-contract-model.md
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
Category: 07-development-path
Tags:
  - vnext
  - api
  - endpoints
  - backend
  - phaser
---

# vNext Endpoint Inventory

## Decision

vNext will replace the prototype's page-oriented and compatibility-heavy API surface with a smaller domain-oriented API designed for a persistent Phaser client.

Queries expose authoritative mutable runtime state. Commands represent explicit player intent and return the authoritative state changed by the command rather than requiring a full profile refresh.

The endpoint inventory in this document is the accepted starting contract for vNext. Specialized interactive node mechanics may add narrowly scoped sub-routes when their gameplay requires authoritative intermediate state.

## General Rules

- API routes remain versioned under `/api/v1/...` unless a later API-version decision changes this.
- OAuth entry routes remain under `/auth/...`.
- Authentication remains cookie/session based.
- Mutations use CSRF protection.
- Query endpoints do not mutate player state.
- Commands that spend resources, create durable assets, roll randomness, or resolve gameplay use idempotency boundaries.
- Successful mutations return the affected authoritative state and current `player_revision` rather than requiring a global profile refresh.
- Authored JSON remains the authority for static definitions, labels, descriptions, art references, formulas, and catalogs. API payloads should prefer stable IDs plus mutable instance state instead of duplicating authored content.
- Collection endpoints return compact presentation-ready summaries. Detail endpoints return the full mutable state needed by an individual-object screen.
- Player-facing API terminology uses `squad`, not the prototype `team` compatibility terminology.

## Authentication and Account

### Existing login flows retained conceptually

- `GET /auth/discord/start`
- `GET /auth/discord/callback`
- `POST /api/v1/auth/local/register`
- `POST /api/v1/auth/local/login`
- `POST /api/v1/auth/local/password-reset/request`
- `POST /api/v1/auth/local/password-reset/confirm`
- `POST /api/v1/auth/logout`
- `GET /api/v1/session`

### Account management

- `GET /api/v1/account`
  - Returns account/profile information needed by the Angular account shell.
  - Includes connected authentication methods without exposing credential internals.

- `POST /api/v1/account/local-credentials`
  - Adds direct email/password access to an already authenticated account, such as a Discord-first account.
  - Must attach credentials to the existing user rather than creating another user.

- `GET /auth/discord/link/start`
- `GET /auth/discord/link/callback`
  - Connect Discord to an already authenticated account.
  - These routes are distinct from ordinary Discord login/account creation.

Automatic account merging is not part of the vNext baseline. Email matching may identify a likely duplicate, but linking requires proof of control of the existing account.

## Game Bootstrap

- `GET /api/v1/game/bootstrap`

Bootstrap is intentionally substantial enough to enter the Camp without many immediate requests, but it is not a replacement for the old catch-all profile payload.

Expected bootstrap state includes:

- account summary: user ID, display name, role
- `user_state`: Teeth, Raw Chaos, current Energy, calculated Energy maximum, Energy regeneration timing, active squad ID
- current `player_revision`
- owned unlock IDs needed for initial navigation/feature availability
- active run summary, when one exists
- active squad with its nine positions and compact unit summaries sufficient to render the initial Camp experience
- session/CSRF metadata required by the client
- content/build version metadata when useful for detecting client/server incompatibility

Bootstrap does not need to include every unit, die, saved squad, Codex entry, objective, Shop offer, Academy option, Wrong Machine recipe, run graph, or battle playback record.

## Units

### Queries

- `GET /api/v1/units`
  - Returns compact unit summaries suitable for roster views, squad construction, filtering, and selectors.
  - Does not return every progression/configuration detail for every unit.

Typical summary concerns include instance ID, name, unit type ID, kin ID, level, lifecycle status, and compact derived data needed by roster presentation.

- `GET /api/v1/units/:unitId`
  - Returns the full mutable unit detail required by the individual unit screen.
  - Includes identity, level/XP, current unit type, kin, calculated mutable/derived combat state as appropriate, promotion history, unlocked abilities, current action/loadout order, equipped dice bindings, and configuration-lock state.

- `GET /api/v1/units/:unitId/promotion-options`
  - Returns server-calculated promotions currently available to the unit.
  - Promotion options are calculated actions, not persisted unit state, so they remain separate from normal unit detail.

### Commands

- `PATCH /api/v1/units/:unitId/name`
  - Renames one unit.

- `POST /api/v1/units/:unitId/promote`
  - Performs one promotion transaction.
  - Updates current unit type/progression state and promotion history atomically.

- `PUT /api/v1/units/:unitId/loadout`
  - Replaces the unit's complete player-controlled combat configuration atomically.
  - The submitted configuration covers equipped/unlocked action selection as applicable, action ordering, and all dice bindings to abilities/actions.
  - The backend validates ownership, ability unlocks, ordering, die ownership, slot legality, and global physical-die uniqueness before committing. A die may move within the configured unit's atomic replacement, but a die bound to another unit is rejected rather than reassigned.

Capstone-specific mutation is not required. Capstone choices resolve into ability grants and are represented by normal ability ownership.

Per-slot die assignment/clearing endpoints are intentionally removed in favor of complete loadout replacement.

## Dice and Items

### Queries

- `GET /api/v1/dice`
  - Returns owned die instances in a compact form.
  - A die instance is primarily identified by instance ID, size, authored profile ID, lifecycle status, and any ownership/equipment summary needed by the client.
  - Material, rarity, aspects, and size eligibility come from the authored profile/material/aspect catalogs.

- `GET /api/v1/items`
  - Returns mutable stackable inventory quantities keyed by authored item IDs.

A dedicated die detail endpoint is not required initially because the persistent die representation is intentionally small and the authored profile resolves most presentation/mechanics information locally.

### Commands

- `POST /api/v1/dice/:diceId/sell`
- `POST /api/v1/dice/:diceId/salvage`
  - Perform authoritative lifecycle/economy transitions.
  - Equipped or active-run-locked dice cannot be mutated illegally.

Known consumable actions remain contextual rather than using an arbitrary generic `use item` mutation:

- `POST /api/v1/energy/restore`
- `POST /api/v1/runs/:runId/units/:unitId/heal`

The request identifies the applicable owned consumable/item. Additional contextual consumable commands may be added only as mechanics require them.

## Squads

### Queries

- `GET /api/v1/squads`
  - Returns all saved squads and their compact nine-position configurations.

### Commands

- `POST /api/v1/squads`
  - Creates a saved squad.
  - Requires `Idempotency-Key`.
  - Accepts exactly `name` plus a nine-element `formation`; each position is a canonical positive unit-instance ID string or `null`.

- `PUT /api/v1/squads/:squadId`
  - Replaces the complete squad configuration atomically.
  - Uses the same complete `name` and nine-element `formation` body as creation.
  - Configuration uses positions `0` through `8`.
  - A unit may belong to multiple different saved squads.
  - A position may contain at most one unit in a squad.

- `POST /api/v1/squads/:squadId/activate`
  - Sets the player's active saved squad.

- `DELETE /api/v1/squads/:squadId`
  - Deletes a saved squad subject to domain validation.

While a run is active, mutations to player-controlled combat configuration used by the run are rejected. This includes relevant squad membership/positioning, unit action loadouts, promotion-related changes, and equipped dice.

## Shop

### Query

- `GET /api/v1/shop`
  - Returns authoritative current Shop availability, prices, availability rules, and any player-specific purchase state necessary to transact.
  - Static authored presentation data comes from local JSON.

### Command

- `POST /api/v1/shop/purchase`
  - Purchases an authored Shop offer.
  - Spending, randomization where applicable, event resolution, reward application, and durable asset creation occur transactionally.
  - Response includes exact spend, finalized outputs/rewards, created instance IDs where applicable, affected balances/inventory, unlock changes if any, and `player_revision`.

## Academy

### Query

- `GET /api/v1/academy`
  - Returns player-specific availability, prerequisite satisfaction, ownership, and prices for authored Academy upgrades.

### Command

- `POST /api/v1/academy/upgrade`
  - Applies one authored Academy upgrade identified by stable upgrade ID.
  - The endpoint is intentionally generic across Academy upgrade categories such as unit-type unlocks and Energy-capacity upgrades.
  - Raw Chaos spend and resulting unlock/reward application are authoritative and transactional.

## Wrong Machine

### Query

- `GET /api/v1/wrong-machine`
  - Returns current player-specific reconstruction options, costs, eligibility, and target-selection rules for authored recipes.

### Command

- `POST /api/v1/wrong-machine/reconstruct`
  - Executes one deliberate reconstruction transaction.
  - Supports the accepted distinction between first kin reconstruction and repeat reconstruction of an already-restored kin.
  - Spending, exact/random unit determination, unlock grants, and unit creation occur once under an idempotency boundary.

## Objectives and Bounties

Bounties are a special authored objective type and do not receive a separate persistence or API family.

### Query

- `GET /api/v1/objectives`
  - Returns player-specific objective availability, accepted/active/completed state, current numeric progress, needed progress, and relevant stable IDs.
  - Authored descriptions, categories, and reward definitions remain in JSON.

### Command

- `POST /api/v1/objectives/:objectiveId/accept`
  - Accepts an objective when the authored objective requires explicit acceptance.

There is no `sync` endpoint in the target model. Objective progress updates as authoritative gameplay occurs.

There is no ordinary `claim` endpoint. Objective completion resolves and applies rewards authoritatively; completion UI presents the finalized result.

## Codex

- `GET /api/v1/codex`
  - Returns owned Codex entry IDs and acquisition state needed by the Codex UI.

There is no generic dialogue-seen mutation. Important learned knowledge is represented by unique Codex rewards. Replaying content whose Codex reward is already owned produces no duplicate reward.

## Runs

### Query

- `GET /api/v1/runs/current`
  - Authenticated read returning `{ run: null, player_revision }` when no active run exists.
  - Otherwise returns the persisted active run root plus ordered nodes, safe Farm positions, edges, participating unit IDs/nullable current HP, and `player_revision`.
  - Does not regenerate or repair topology, materialize Energy, increment revision, or expose private generation configuration/metadata.

The currently implemented Farm run state includes:

- run ID, region ID, status
- generated nodes and node completion/availability state
- generated edges/connectivity
- participating unit run state including current HP

Run modifiers and current/pending encounter state remain deferred until their owning mechanics require them.

The run payload references authored IDs rather than duplicating static node/region definitions.

### Commands

- `POST /api/v1/runs`
  - Starts a new run.
  - Requires authentication, CSRF, and `Idempotency-Key`.
  - Accepts exactly `{ "region_id": "region.the_farm" }`; the server selects `user_state.active_squad_id` and its committed unit/loadout/dice state.
  - Validates the active squad and run eligibility.
  - Consumes Energy exactly once when run creation commits successfully.
  - Generates/persists run topology and run-specific mutable state.
  - Returns the created run summary, authoritative Energy view, and resulting `player_revision`; the private generated topology remains outside this response.

- `POST /api/v1/runs/:runId/abandon`
  - Requires authentication and CSRF, but no idempotency key.
  - Transitions an owned active run to `abandoned`, retains its graph/participation history, changes no Energy state, and increments `player_revision` once.
  - Retrying an already-abandoned owned run succeeds without changing its terminal timestamp or revision. Missing and foreign run IDs share one non-disclosing not-found response.

- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve`
  - Currently resolves only an available ordinary Combat node with a persisted canonical encounter reference.
  - Requires authentication, CSRF, an `Idempotency-Key`, canonical positive path IDs, and no request body.
  - The server assembles the complete combat snapshot and deterministic seed from locked run/Warband state plus private authored content. The client supplies no combat facts.
  - Returns the finalized battle ID/summary, completed node timestamp, newly available direct child IDs, terminal player HP keyed by owned unit ID, run lifecycle facts, and `player_revision`.
  - Does not return the normalized input, seed, hidden enemy configuration, rewards, or playback events. Exact retries return the persisted response without rerunning combat.

Successful run exit is treated as normal node resolution rather than requiring a separate generic `/exit` command.

### Interactive node mechanics

Some node types legitimately require multiple authoritative interactions and may receive narrowly scoped sub-routes. The baseline does not force these mechanics through an arbitrary generic action endpoint.

Existing examples that may retain or evolve specialized contracts include Rest and Chaos encounters. Their final endpoint names and payloads may be finalized during the Farm vertical slice when their vNext mechanics are reviewed.

Rule:

> A node receives specialized command endpoints only when the gameplay itself requires persistent intermediate authoritative state.

## Battles

- `GET /api/v1/battles/:battleId/playback`
  - Retrieves the already-resolved authoritative playback record for an active/incomplete run.
  - Used for replay recovery after reconnect/interruption.

There is no normal battle-resolution command separate from node resolution. PHP resolves combat as part of the node command and Phaser animates the returned result.

There is no battle `claim` endpoint. Battle/event rewards are finalized and applied during authoritative resolution before presentation.

Battle playback logs are retained while the owning run is active and become eligible for scheduled cleanup after the run reaches a terminal success/failure/abandon state.

## Reward and Mutation Response Model

Ordinary rewards do not require a player claim mutation.

The normal sequence is:

1. validate the command
2. resolve randomness/gameplay exactly once
3. finalize reward-bearing events
4. apply durable grants/spends transactionally
5. increment `player_revision` for persistent player-state changes
6. return the finalized authoritative result
7. Phaser presents the result

A reward screen's Continue/Acknowledge action is presentation state, not ownership state.

Future reward mechanics requiring player choice may introduce a persisted unresolved-offer model. This is not part of the baseline.

## Player Revision

Bootstrap and successful persistent mutations expose `player_revision`.

`player_revision` is a monotonically increasing stale-cache signal stored with dynamic user state. It is not event sourcing and is not initially used as an optimistic-lock prerequisite on commands.

Initial client behavior may simply cache the latest revision. It can later support reconnect recovery, stale-domain refresh, multi-tab/session detection, and debugging without changing the core API contract.

## Mutation Granularity

vNext uses a hybrid mutation strategy:

- discrete gameplay actions use explicit fine-grained command endpoints
- editable configurations use whole-aggregate replacement

Examples of discrete commands include purchase, promotion, reconstruction, run start, node resolution, squad activation, and consumable use.

Examples of whole-aggregate configuration replacement include squad composition/positions and unit action/dice loadouts.

The API does not expose arbitrary `save player state` or generic field-patch endpoints that would allow the client to mutate server-owned state without domain-specific validation.

## Operations, Debug, and Admin

Operational surfaces remain separate from the player-facing game contract.

- `GET /api/v1/health` remains appropriate for health monitoring.
- Debug/dev-only grant/reset tools may continue to exist behind environment/runtime controls, but they should be redesigned against vNext authored IDs and storage rather than preserving prototype catalog assumptions.
- Future admin routes are authorized using the user's `role` value and should live in an explicit admin boundary rather than overloading normal player endpoints.

## Prototype Routes Intentionally Not Carried Forward

The clean vNext baseline does not preserve compatibility merely for the current Angular client. In particular, the following concepts are intentionally removed or replaced:

- catch-all `GET /api/v1/profile` as the normal game state source
- `/teams` terminology
- capstone-specific mutation
- individual die-slot assign/clear mutations
- dialogue-seen mutation
- separate bounty `sync`
- ordinary bounty/objective `claim`
- battle `claim`
- generic successful-run `exit` mutation when exit can be represented by resolving the exit node
- mutation-followed-by-full-profile-refresh behavior

Git history and the existing implementation preserve prototype behavior for reference; they are not vNext compatibility requirements.

## Deferred Endpoint Details

The following do not block the contract and should be finalized when implementing their domain:

- exact Rest/Chaos interactive node sub-routes and payloads
- exact pagination/filter parameters if unit/dice collections eventually require them
- precise error-code vocabulary, including configuration-lock and stale-state errors
- exact cleanup/retention durations for idempotency, reward-event, lifecycle, and battle-playback records
- exact response envelope conventions and error-envelope shape
- whether some read endpoints eventually adopt HTTP cache validators such as ETags

These are implementation-level refinements unless they introduce a new product-state transition or authority boundary.
