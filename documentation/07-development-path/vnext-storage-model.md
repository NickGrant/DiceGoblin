---
Title: "vNext Storage Model"
Status: Accepted
Last Updated: 2026-09-12
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
  - documentation/07-development-path/vnext-currency-economy-model.md
  - documentation/07-development-path/vnext-energy-model.md
  - documentation/07-development-path/vnext-progression-state-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
Category: 07-development-path
Tags:
  - vnext
  - database
  - storage
  - mysql
  - authentication
  - runtime-state
---

# vNext Storage Model

## Decision

vNext will rebuild the MySQL schema around mutable player and runtime state rather than carrying forward the prototype database as a representation of the entire game.

Authored game definitions are stored in Git-tracked JSON and are referenced from MySQL by stable string IDs. MySQL does not duplicate authored catalogs unless a future requirement demonstrates a concrete need for a derived runtime index.

The guiding boundary is:

> Git-tracked JSON defines the game. MySQL stores a player's mutable copy of the game and the temporary runtime state required to resolve it safely.

The vNext schema starts from a clean baseline. Existing runtime/player data does not need to migrate and the prototype migration chain is not replayed.

## Authored IDs and Referential Integrity

Runtime records may reference authored definitions such as:

- `region.mountains`
- `unit_type.saboteur`
- `kin.pig`
- `item.pig_ear`
- `ability.example`
- `dice_profile.glass_example`
- `modifier.example`
- `codex.whim.arrival`

These identifiers are durable contracts. Display names may change freely; stable IDs should not be renamed casually after release.

Because the definitions do not live in MySQL, database foreign keys cannot validate these references. The PHP content registry and CI content validation are responsible for rejecting missing or invalid authored IDs.

## Account and Authentication Storage

### `users`

`users` represents the Dice Goblins player account independently from the method used to authenticate.

Representative fields:

- `id`
- `display_name`
- `avatar_url`
- `role`
- `created_at`
- `updated_at`

`role` defaults to `user`. `admin` is the planned second role. The initial vNext design does not require a general RBAC or role-definition system.

Role assignment is server-controlled and is not an ordinary profile mutation.

### `user_local_credentials`

Stores direct-account authentication credentials separately from the player record.

Representative fields:

- `user_id`
- normalized `email`
- `password_hash`
- `created_at`
- `updated_at`

Constraints:

- one local credential record per user
- email is unique
- raw passwords are never stored

### `user_external_identities`

Stores external authentication identities.

Representative fields:

- `id`
- `user_id`
- `provider`
- `provider_user_id`
- `provider_email`, nullable and informational
- `created_at`
- `updated_at`

Discord is the initial provider.

Constraints:

- unique (`provider`, `provider_user_id`)
- one user may have both local credentials and a Discord identity

### Account linking

A Discord-created account may later add local email/password credentials. A locally created account may later connect Discord. Both authentication paths resolve to the same `users.id` and therefore the same game state.

A matching email may be used to detect that an account may already exist, but email matching alone must not silently merge or link accounts. The player must prove control of the existing account before a new authentication method is attached.

The initial vNext design focuses on preventing duplicate accounts and linking additional authentication methods. Merging two already-established game accounts is not required.

### `password_reset_tokens`

Retain dedicated password-reset-token storage for local credentials. Tokens must remain hashed, expiring, and single-use/supersedable.

No database-backed session table is required by this decision unless the selected authentication/session implementation later needs one.

## Dynamic Player State

### `user_state`

`user_state` is the one-to-one mutable account-level state row for values that change frequently but do not deserve separate domain tables.

Representative fields:

- `user_id`
- `teeth`
- `raw_chaos`
- `energy_current`
- `energy_last_regen_at`
- `active_squad_id`, nullable until a squad exists
- `updated_at`

Teeth and Raw Chaos use the same currency semantics but are stored as explicit values because the current game has exactly these two wallet currencies.

Energy remains semantically separate from currency even though its current value is stored on the same dynamic state row.

`energy_max` is not persisted. It is derived from the authored base maximum plus applicable permanent unlock/Academy bonuses.

## Progression and Collection State

### `user_unlocks`

Stores permanent access/capability ownership.

Representative fields:

- `user_id`
- `unlock_id`
- `granted_at`

Constraint:

- unique (`user_id`, `unlock_id`)

Examples include region access, facilities/features, unit types, and restored kin.

### `user_codex_entries`

Stores unique Codex ownership separately from capability unlocks.

Representative fields:

- `user_id`
- `codex_id`
- `acquired_at`

Constraint:

- unique (`user_id`, `codex_id`)

Codex entries may also represent important information learned through dialogue and can be used as authored dialogue prerequisites.

### `user_objectives`

Stores mutable objective progress. Bounties are a special authored objective type and do not require a separate player-bounty persistence model.

Representative fields:

- `user_id`
- `objective_id`
- `current`
- `needed`
- `status`
- lifecycle timestamps as required

The initial objective model assumes numeric `current / needed` progress. Additional progress shapes are deferred until a concrete objective needs them.

### `user_items`

Stores stackable owned inventory.

Representative fields:

- `user_id`
- `item_id`
- `quantity`

Constraint:

- unique (`user_id`, `item_id`)

Item definitions, including consumable behavior and reconstruction ingredients, remain authored JSON.

## Unit Storage

### `unit_instances`

Stores player-owned goblin instances.

Representative fields:

- `id`
- `user_id`
- `unit_type_id`
- `kin_id`
- `display_name`
- `level`
- `xp`
- `lifecycle_status`
- lifecycle timestamps as required

The current `unit_type_id` represents the unit's present promoted type. Promotion history is stored separately.

Unit-type and kin definitions are JSON-authored stable IDs rather than MySQL catalog references.

### `unit_promotions`

Stores durable promotion history so the backend can determine how a unit reached its current type/path without relying on lossy inference.

Representative fields:

- `id`
- `unit_id`
- `from_unit_type_id`
- `to_unit_type_id`
- `promoted_at`

Promotion changes `unit_instances.unit_type_id`; this table preserves the path/history.

### `unit_abilities`

Stores abilities permanently unlocked by an individual unit.

Representative fields:

- `unit_id`
- `ability_id`
- `unlocked_at`
- optional source/provenance when useful

Constraint:

- unique (`unit_id`, `ability_id`)

Capstone choices do not require separate capstone state. A capstone that grants an ability is represented by the resulting ability unlock.

### `unit_ability_loadout`

Stores the player's equipped/ordered action configuration for a unit.

Representative fields:

- `unit_id`
- `ability_id`
- `equip_order`

Exact duplicate/equip-count rules remain gameplay validation rather than authored DB catalogs.

### `unit_ability_dice`

Records which owned die an individual unit rolls for a particular ability slot.

Representative fields:

- `unit_id`
- `ability_id`
- `slot_index`
- `dice_instance_id`

Constraint:

- unique (`unit_id`, `ability_id`, `slot_index`)
- unique (`dice_instance_id`), so one physical die is bound to at most one ability slot across the Warband

This relationship is the authoritative answer to: "When Unit A uses Ability 1, which die does it roll?"

## Dice Storage

### Authored dice profiles

vNext dice use an authored profile catalog rather than persisting aspect combinations directly on each die instance.

A dice profile is JSON-authored and includes concepts such as:

- stable profile ID
- material ID
- explicit rarity
- aspect IDs
- allowed die sizes

Material and rarity are independent concepts. A material does not imply a rarity.

Aspects may restrict which die sizes they support. Profile validation ensures that the profile's allowed sizes are compatible with all of its components.

Large numbers of authored profiles are acceptable and may be generated ahead of time, then checked into Git like other game content.

### `dice_instances`

The persistent owned die is intentionally small.

Representative fields:

- `id`
- `user_id`
- `size`
- `profile_id`
- `lifecycle_status`
- lifecycle timestamps as required

The profile supplies material, rarity, aspects, and size eligibility. These values are not duplicated onto the runtime instance.

## Squads

### `squads`

Stores multiple saved player squads.

Representative fields:

- `id`
- `user_id`
- `name`
- timestamps as required

Players may maintain purpose-specific squads such as a Swamp Squad or Sneaky Squad and switch the active squad while not in a run.

### `squad_units`

Stores squad membership and the fixed nine-position formation.

Representative fields:

- `squad_id`
- `unit_id`
- `position` in the range 0-8

Constraints:

- unique (`squad_id`, `position`)
- a unit may appear in multiple different saved squads
- the same unit should not occupy multiple positions in the same squad

The active squad reference lives on `user_state`.

The first saved squad becomes active atomically. Later creation preserves the current active squad. Deleting the active squad is permitted only when it is the sole saved squad, leaving the reference null; otherwise the player must activate a replacement first.

### `idempotency_requests`

Stores finalized retry receipts for concrete idempotent commands. The current key is `(user_id, idempotency_key)` and the row records the operation type, normalized request hash, finalized result JSON, and creation timestamp. These operational rows are retained until scheduled cleanup is introduced; they are not player-visible history.

## Active-Run Configuration Lock

When a run begins, player-controlled combat configuration for the participating squad is locked until the run reaches a terminal success/failure state.

While a run is active, the player cannot:

- switch the active squad
- change the participating squad's membership or positions
- alter participating units' player-controlled action/loadout configuration
- change participating units' equipped dice
- perform player-controlled unit progression/configuration changes that would alter the active run's combat configuration

The backend enforces this lock by checking active run participation. A separate snapshot of every unit/loadout is not required solely to enforce the lock.

## Run Storage

### `runs`

Stores the active/generated run and its lifecycle.

Representative fields:

- `id`
- `user_id`
- `region_id`
- `squad_id`
- `status`
- lifecycle timestamps

Region definitions remain JSON-authored.

Active combat runs use `active`. A finalized combat defeat or stalemate transitions the run to terminal `failed` and sets `ended_at`; finalized battle, graph, and run-unit HP records remain available internally even though the active-run query then returns no run. Combat victory leaves the run active for its newly available direct child.

### `run_nodes`

Stores generated node instances and mutable node state.

Representative fields:

- `id`
- `run_id`
- authored node/type reference as required
- generated node metadata required by the run
- `status` or equivalent completion state
- `completed_at`, nullable

A relational node table is required because the backend must be able to determine which individual nodes have been completed.

### `run_edges`

Stores generated graph connectivity.

Representative fields:

- `id`
- `run_id`
- `from_node_id`
- `to_node_id`
- rendering/path metadata only when generated runtime state requires it

The player does not need separate chosen-path history. Accessibility is derived from graph adjacency plus completed/unlocked node state.

### `run_unit_state`

Stores run-scoped mutable state for participating units.

Representative fields:

- `run_id`
- `unit_id`
- `current_hp`
- other genuinely run-scoped unit state only when a concrete mechanic requires it

There is no separate run-consumable inventory in the current design. Normal owned consumables remain in `user_items`.

### `run_modifiers`

Stores temporary modifiers acquired during a run.

Representative fields:

- generated `id`
- `run_id`
- `modifier_id`
- optional numeric `value`
- timestamps/source metadata only when useful

`modifier_id` references an authored JSON definition. The optional value supports one mutable scalar when needed, such as a modifier that begins with five remaining applications and decrements after use.

Modifiers that eventually require multiple independently mutable values should receive a richer domain model rather than turning `run_modifiers` into arbitrary unstructured state.

## Battle Storage

### `battles`

Stores one immutable finalized battle associated with a run node. The row contains the exact normalized deterministic input, a compact participant identity/presentation manifest, and the exact versioned result with its ordered playback events. This keeps historical playback independent of later changes to Warband or authored content.

Representative fields:

- `id`
- `run_id`
- `run_node_id`
- `engine_version`
- `playback_version`
- normalized input snapshot JSON
- participant manifest JSON
- result/playback JSON
- `created_at`

The same-run composite foreign key binds each battle to its owning run node, and a unique `(run_id, run_node_id)` key permits at most one finalized battle per node. The versions also remain in ordinary columns for compatibility inspection and must match the result payload.

There is no separate `battle_playback` table in the current model. Playback is a one-to-one immutable part of the versioned result, so splitting it would fragment the authoritative record or duplicate the event stream without a current query or lifecycle need.

Finalized battle records are retained for the lifetime of the active run. Once the run reaches terminal success or failure, they become eligible for scheduled cleanup. Long-term player-visible battle history is not a current requirement.

## Reward and Transaction Safety

### `resolved_events`

Stores finalized reward-bearing event results long enough to guarantee that reward rolls and grant application are not repeated.

Representative fields:

- `id`
- `user_id`
- `event_id`
- source identity/reference
- idempotency identity
- `status`
- finalized reward result payload
- `resolved_at`
- `applied_at`

The finalized result is immutable. Retries reuse it rather than rerolling authored probabilities.

These records are operational correctness/debugging data, not player-visible permanent history, and become eligible for scheduled cleanup after a safe retention window.

### `idempotency_requests`

Retry-sensitive gameplay mutations that can spend or create durable state should have a durable request boundary where the event record alone is insufficient.

Representative uses include:

- run creation / Energy spending
- Shop purchases
- Academy transactions
- Wrong Machine reconstruction
- other operations where a retry must return the original committed result rather than execute again

Representative fields:

- `user_id`
- `idempotency_key`
- `operation_type`
- `status`
- result payload when required
- lifecycle timestamps

Constraint:

- unique (`user_id`, `idempotency_key`)

These records are also eligible for scheduled cleanup after a safe retry/debugging window.

## Lifecycle Status and Scheduled Cleanup

Owned instance records such as units and dice should not necessarily be physically deleted at the moment they leave player ownership.

The initial vNext policy is hybrid:

1. transition the instance to a terminal lifecycle status such as sold, salvaged, consumed, or retired as appropriate;
2. retain it temporarily for transactional safety and debugging;
3. remove old terminal records through a scheduled cleanup job once they are outside the required support/retry window.

The exact lifecycle vocabulary and retention durations are implementation/operations decisions and do not need to be fixed in the product architecture.

Scheduled cleanup is also appropriate for:

- battle playback after run completion
- finalized reward/event records after their retry/debugging window
- idempotency request records after their retry/debugging window
- other explicitly temporary runtime/audit payloads

Cleanup must never remove state still required by an active run or an operation that can legitimately be retried.

## Baseline Table Inventory

The accepted initial vNext storage domains are:

```text
ACCOUNT / AUTH
  users
  user_local_credentials
  user_external_identities
  password_reset_tokens

DYNAMIC PLAYER STATE
  user_state

PROGRESSION / COLLECTION
  user_unlocks
  user_codex_entries
  user_objectives
  user_items

UNITS / DICE
  unit_instances
  unit_promotions
  unit_abilities
  unit_ability_loadout
  dice_instances
  unit_ability_dice

SQUADS
  squads
  squad_units

RUNS
  runs
  run_nodes
  run_edges
  run_unit_state
  run_modifiers

COMBAT
  battles

TRANSACTION SAFETY
  resolved_events
  idempotency_requests
```

This is a domain contract, not final SQL. Tables may gain ordinary operational columns, indexes, or constraints during implementation without reopening the product architecture so long as their responsibilities remain consistent with this model.

## Tables Intentionally Removed from the vNext Conceptual Model

The initial vNext schema should not recreate database catalogs for authored content such as:

- regions
- unit-type definitions
- enemy templates
- kin definitions
- item definitions
- dice materials
- dice aspects
- dice profiles
- reward definitions/tables
- encounter templates
- unlock definitions
- Codex definitions
- dialogue definitions
- Shop offers
- Academy upgrades
- Wrong Machine recipes
- bounty/objective definitions

The initial vNext schema also does not require generic persistence for:

- region completion
- first-kin ownership
- kin discovery projections
- dialogue-seen flags
- story-history flags
- generic player progression
- chosen run paths
- capstone choices separate from the abilities they grant
- separate bounty progress outside the objective model
- separate currency and Energy tables
- run-specific consumable inventory

## Deferred Implementation Details

The following do not block schema design and are intentionally deferred until implementation or operational testing:

- exact SQL data types and index choices
- exact lifecycle-status vocabulary
- cleanup retention durations
- optional support/debugging provenance fields
- session persistence if later required by the authentication implementation
- additional objective progress shapes beyond numeric `current / needed`
- specialized run-modifier state if a future modifier cannot be represented by one optional numeric value
- any future administrative account-merge workflow

## Consequences

The vNext database becomes significantly smaller and more focused than the prototype schema.

It stores mutable player ownership, progression, run state, combat state, and transactional safety records while leaving authored game definitions in Git-tracked JSON.

This removes duplicate catalog ownership, avoids migration-driven balance/content changes, and prevents multiple persistence concepts from representing the same progression fact.

The model also establishes the storage foundation needed for the Phaser-first client and PHP-authoritative backend without requiring backward compatibility with prototype-era tables.
