---
Title: "Dice Goblins - Data Model and Target-State Migration Contract"
Status: Canonical
Last Updated: 2026-08-23
Owner: Engineering
Depends On:
  - backend/migrations/schema_all.sql
  - documentation/02-systems/combat-resolution.md
  - documentation/02-systems/kin-reconstruction.md
  - documentation/05-technical/09-seed-catalog-ownership.md
Category: 05-technical
Tags:
  - technical
---

# Dice Goblins - Data Model and Target-State Migration Contract

## Purpose

Define the canonical technical data-model direction for current combat/loadout behavior and repeatable kin reconstruction while preserving the current dice rarity, material, and affix model.

This document distinguishes two forms of truth:

- **Implemented schema:** the physical tables and compatibility columns currently present in `backend/migrations/schema_all.sql` and consumed by runtime code.
- **Canonical target state:** the schema direction that approved migrations and service changes must move toward.

When the implemented schema and an approved target contract disagree, the difference is implementation drift. Documentation must describe that drift explicitly rather than treating legacy storage as a competing design.

## 1. Guiding Principles

- The backend is authoritative for persistent player, unit, run, combat, reward, and reconstruction state.
- Player-facing names are labels, not identifiers.
- Combat and reconstruction inputs must be persistable, auditable, and safe to retry.
- Prefer explicit relational structures where data needs validation, joins, migration, or administrative inspection.
- Behavior-bearing authored rows use stable keys; executable behavior remains code-owned through registries and handlers.
- Owned-unit state is the authority for whether a player owns a kin.
- Derived discovery, Codex, or reward-eligibility state must be repairable from durable ownership.
- Dice retain their current size/rarity, material, and permanent-affix data model unless a future redesign is explicitly approved.

## 2. Stable Global Tables

These areas remain structurally stable even when individual contracts require additional fields or compatibility cleanup:

- `users`
- `player_state`
- `energy_state`
- `regions`
- `region_unlocks`
- `region_runs`
- `run_nodes`
- `run_edges`
- `teams`
- `team_units`
- `team_formation`
- `run_unit_state`
- `run_team_formation`
- `encounter_templates`
- `loot_tables`
- `battles`
- `battle_logs`
- `battle_rewards`
- `chaos_encounter_results`
- `items`
- `user_items`
- `user_unlocks`
- `user_grants`
- `region_items`
- `user_region_items`

`items` and `user_items` are the generic progression-inventory path for lineage materials, boss catalysts, machine inputs, unlock keys, and consumables. `region_items` and `user_region_items` remain legacy compatibility tables and must not be extended for new progression rewards.

`user_unlocks` remains appropriate for explicit feature and unit-type unlocks. A kin-related row may persist discovery or ordinary-reward eligibility as a derived account projection, but it is not the source of truth for kin ownership. Basic Goblin is implicit for every account and does not require a row.

`user_grants` or another durable request ledger may support idempotent account grants. Repeatable reconstruction requires a request-level idempotency record that distinguishes a retry from a new deliberate recipe use.

## 3. Authentication and Accounts

### 3.1 `users`

`users` is the canonical local account row regardless of authentication provider. Discord OAuth and local credentials both resolve to a local `users.id`.

Current credential fields include:

- `discord_id` for Discord OAuth identities; nullable for local-only users
- `local_email` for normalized email sign-in; nullable for Discord-only users
- `password_hash` for local credential verification; nullable for Discord-only users
- `display_name` and `avatar_url` for player-facing identity

Related credential storage:

- `password_reset_tokens` stores one-hour hashed reset tokens, with `used_at` marking consumed or superseded tokens

Rules:

- never store raw passwords
- never store raw password-reset tokens
- never expose credential fields through session or profile payloads
- keep provider-specific identifiers unique when present

## 4. Unit Types and Enemy Types

### 4.1 `unit_types`

`unit_types` remain the authored source of:

- role
- base stats
- per-level growth for attack, defense, max HP, precision, and resolve
- max level
- promotion eligibility
- capstone choices
- authored ability packages by tier or path

`ability_set_json` represents authored ability packages that feed:

- unlocked-ability inheritance
- promotion-entry grants
- starter or migration loadouts where applicable

It must not be interpreted as the complete ordered set that a unit automatically executes in combat.

`promotion_grants_json` was removed in migration 71. Promotion previews may still expose a derived `promotion_grants` response object.

`max_equipped_dice` was removed in migration 72. Dice capacity is derived from equipped active abilities and their registered dice-slot requirements.

### 4.2 `enemy_templates`

`enemy_templates` remain the authored source of:

- base stats
- role
- XP reward
- ability catalog
- authored equipped-ability order

All enemies of one enemy type use the same authored loadout unless a later system explicitly introduces variants.

The equipped loadout may remain in validated JSON or move to a normalized child table when editing, reporting, or parity requirements justify it.

## 5. Unit Instances and Kin Identity

### 5.1 `unit_instances`

`unit_instances` are the persistent player-owned unit objects and own:

- display name
- current unit-type reference
- tier
- level
- XP
- combat configuration
- persistent kin identity
- locking or protection flags where applicable

Current storage may still expose legacy `splice_variant_*` columns and relationships. Target-state services and payloads use `kin_*` terminology. Storage renaming is a compatibility migration and must not be conflated with the behavioral contract.

A unit's persisted kin identity is durable ownership evidence. The system must not infer that a player does not own a kin merely because a secondary discovery or unlock row is absent.

### 5.2 First-Ownership Projections

The transition from no owned unit of a kin to at least one owned unit may update several projections:

- kin discovery state
- Codex ownership
- ordinary unit-grant or recruitment eligibility
- milestone presentation state

These projections may use `user_unlocks`, a future dedicated kin-state table, or a unified Codex ownership store. Whatever storage is selected must satisfy these rules:

- owned units remain the authority for ownership
- projections are idempotent
- missing projections can be repaired from owned units
- a projection cannot authorize deletion or replacement of a legitimate unit
- first-ownership effects do not repeat on later units of the same kin

### 5.3 Promotion Path History

Promotion history must support sideways-promotion validation and player-support audit needs.

This can be represented by richer `unit_promotions` history, explicit path-history state on `unit_instances`, or both. Validation must not rely on player-facing names or lossy inference.

### 5.4 Capstone State

Capstone selection requires explicit per-unit persistence separate from authored unit-type data.

Recommended shape:

#### `unit_instance_capstone_choices`

- `unit_instance_id`
- `source_unit_type_id`
- `ability_id`
- `created_at`
- `updated_at`

The selected capstone should also participate in the normal unlocked-ability path so inheritance remains consistent after promotion.

## 6. Ability Persistence

The schema distinguishes three layers:

1. authored ability packages on unit types
2. cumulative unlocked abilities on unit instances
3. ordered equipped abilities on unit instances

### 6.1 `unit_instance_unlocked_abilities`

Stores which base abilities a unit can equip.

Representative fields:

- `unit_instance_id`
- `ability_slug` or `ability_id`
- `source_tier`
- `source_unit_type_id`
- `created_at`

### 6.2 `unit_instance_equipped_abilities`

Stores the ordered loadout.

Representative fields:

- `id`
- `unit_instance_id`
- `ability_slug` or `ability_id`
- `equip_order`
- `speed_cost`
- `created_at`
- `updated_at`

Duplicate rows are allowed when duplicate equips are allowed. Equip-budget validation remains server-side.

## 7. Dice Inventory and Ability-Slot Binding

### 7.1 `dice_definitions`

The current dice definition model owns authored die characteristics including:

- sides
- rarity
- affix-slot capacity
- backend-owned valuation inputs

The current alpha-launch combat sizes are `d4`, `d6`, `d8`, and `d10`.

### 7.2 Materials

Material remains a separate die property in the current game. Material data may influence identity or authored behavior, but it does not replace rarity or permanent affixes.

A future canonical dice-content catalog should reconcile material keys, display identity, and behavior without assuming a material-only redesign.

### 7.3 `affix_definitions`

`affix_definitions` defines permanent dice-affix metadata used by the current rarity-driven model.

Affixes remain attached to die instances, and rarity continues to control affix capacity. Their concrete player-facing catalog is currently deferred, but the storage is active game data rather than migration-only input.

### 7.4 `dice_instances`

`dice_instances` remain the player-owned dice inventory records and preserve the die's current size/definition, material data, rarity relationship, and per-instance affix relationships as applicable.

Instance identity is required for ownership, equipment, sale, salvage, audit, and permanent affix assignment.

### 7.5 Ability-Slot Binding

The old `unit_dice` contract has been removed. Dice bind to a base ability slot through `unit_ability_dice`.

Representative fields:

- `unit_instance_id`
- `ability_slug` or `ability_id`
- `slot_index`
- `dice_instance_id`
- `created_at`
- `updated_at`

Primary key:

- (`unit_instance_id`, `ability_slug` or `ability_id`, `slot_index`)

Repeated equipped copies of the same base ability read from the same slot rows. Empty-slot fallback behavior belongs to combat-system documentation.

## 8. Starter Unit Seeding

Initial unit grants seed:

- generated display names
- default unlocked abilities
- default equipped ability order
- common `d4` dice for starter ability slots that require dice

Bootstrap or account-seed services own this work. The frontend must not synthesize starter inventory as durable state.

## 9. Wrong Machine Reconstruction

### 9.1 Recipe Identity

A reconstruction operation uses a stable recipe key such as `reconstruct_pig_kin`.

Recipe content owns:

- required feature
- output kin
- output unit count
- eligible output unit-type pool
- item costs
- Raw Chaos cost
- enabled state

The current Pig Kin values are owned by `documentation/02-systems/kin-reconstruction.md`. Technical storage must consume those authored values rather than establish a competing balance source.

### 9.2 Repeatable Production

Every committed recipe completion creates one new unit. A kin discovery or eligibility row is not the recipe's primary output and must not be used to block later deliberate recipe uses.

The transaction must atomically:

1. validate access, recipe, capacity, items, and currency
2. resolve the output unit type once
3. spend all authored inputs
4. create the unit with the recipe's kin
5. determine whether the account owned that kin before the transaction
6. apply first-ownership projections when required
7. persist the complete idempotent result
8. commit all changes together

### 9.3 Reconstruction Request Ledger

Repeatable reconstruction requires a durable idempotency boundary separate from kin discovery.

Recommended shape:

#### `wrong_machine_reconstruction_requests`

- `id`
- `user_id`
- `idempotency_key`
- `recipe_key`
- `status`: pending, completed, or failed
- `result_json`
- `created_at`
- `completed_at`

Constraints:

- unique (`user_id`, `idempotency_key`)
- a completed retry returns the stored result
- a new deliberate production uses a new idempotency key
- the output unit type and produced unit id are part of the stored result

An existing generic idempotency ledger may be reused if it can enforce these semantics without conflating a retry with permanent kin discovery.

### 9.4 First-Ownership State

When the transaction creates the first owned unit of a kin, the same commit may add or repair:

- kin discovery
- Codex ownership
- ordinary unit-grant eligibility
- milestone flags required for presentation

Later reconstruction transactions do not repeat those side effects but still spend the full recipe and create a unit.

### 9.5 Current Implementation Drift

The current Wrong Machine service predates the approved repeatable-production contract. As of this document update, implementation still:

- uses a one-time lineage-unlock row to block later reconstruction
- uses legacy Pig Kin costs instead of the canonical recipe values
- returns success without a unit when the lineage row already exists
- lacks a request-level idempotency key
- selects the output unit type through a fresh random call rather than a retry-stable resolution
- presents lineage unlock as the primary state transition

Those behaviors are migration work. They do not redefine the target contract.

## 10. Bounty Board Foundation

### 10.1 `bounty_definitions`

Stores authored contract templates:

- stable slug
- title and description
- category
- `objective_json`
- `reward_json`
- enabled and sort-order fields

### 10.2 `user_bounties`

Stores accepted player bounty state:

- user and definition references
- accepted, completed, claimed, or abandoned status
- idempotent progress data
- lifecycle timestamps

Progress remains backend-authored and idempotent.

## 11. Pattern-Based Run Map Catalog

`backend/data/run-patterns/` is the authoring source for Pattern V1. Pattern V2 content is database-owned and seeded through forward-only migrations because production cannot depend on command-line catalog synchronization.

### 11.1 `run_pattern_definitions`

Stores immutable authored pattern versions, lifecycle status, definition JSON, and content hash.

### 11.2 `run_pattern_region_rules`

Stores region and phase eligibility, weights, depth bounds, limits, cooldowns, and optional modifiers.

### 11.3 `run_generation_profiles`

Stores active region profile versions, bounds, budgets, requirements, retry policy, weight policy, and content hash.

### 11.4 `region_runs` Provenance

Generated runs may record generator version, generation-profile version, pattern-catalog hash, attempt count, and generation summary. Fixed authored runs may record `fixed-v1` provenance while sharing the frontend renderer contract.

### 11.5 `run_edges` Rendering Metadata

`run_edges.meta_json` may store ordered `through` grid coordinates for authored connector routes. Traversal remains defined by `from_node_id` and `to_node_id`.

## 12. Battles and Logs

`battles` remain the authoritative battle records.

`battle_logs.log_json` must be capable of explaining:

- equipped ability instance fired
- cumulative tick scheduling
- slot values consumed
- empty-slot fallback contribution when applicable
- participating die identity needed to explain the action
- permanent affix triggers and outcomes when applicable
- enemy authored loadout participation

### 12.1 `chaos_encounter_results`

Stores one persisted chaos result per run node, including deterministic seed, reel state, reward multiplier, finalized reward payload, reroll state, and finalization timestamp.

Refreshes return the existing row. Finalization applies the stored payout once and returns the same result on retry.

## 13. Rewards and Promotions

`unit_promotions` should retain enough detail to explain:

- source unit
- consumed units
- destination type
- optional item consumption
- promotion path used

Reward and shop records that create dice remain compatible with the current size, rarity, material, and affix generation model. Sale and salvage values are backend-authoritative and may include rarity and affix premiums.

## 14. Migration Expectations

### 14.1 Ability Loadout

Runtime dice assignment migrated to `unit_ability_dice`, and migration 70 removed `unit_dice`.

Remaining combat/loadout concerns include authored enemy loadouts, starter grant alignment, and active-run compatibility when scheduler behavior changes.

### 14.2 Kin Reconstruction Migration

The migration sequence should:

1. treat owned units as kin-ownership authority
2. backfill or repair discovery and eligibility projections from existing units
3. replace one-time lineage gating with repeatable recipe validation
4. add request-level idempotency
5. adopt the canonical Pig Kin recipe values
6. return the produced unit as the primary result
7. allow later recipes to create additional units
8. preserve compatibility fields only until clients use the new result contract

No dice material-only migration is approved. Rarity and permanent affix storage remain part of the current dice model.

## 15. Normalization Guidance

As implementation lands:

- avoid long-lived parallel legacy and target models
- keep test fixtures aligned to approved contracts
- use compatibility aliases at API boundaries rather than duplicating behavioral authority
- keep content values in canonical content/system sources and technical storage contracts in this document
- compact migration history only after the relevant target model is stable and production-safe

## 16. Explicitly Superseded Models

The following are not canonical target-state behavior:

- unit-scoped shared combat dice pools
- combat consumption ordering from a shared pool
- enemy scheduling driven only by modulo speed triggers
- promotion as ability replacement instead of cumulative inheritance
- lineage-unlock rows as the authority for kin ownership
- one-time reconstruction that stops producing units after first discovery

Permanent per-instance dice affixes and rarity-controlled affix capacity are **not** superseded by this document.
