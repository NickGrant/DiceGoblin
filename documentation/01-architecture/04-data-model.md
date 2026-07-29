# Dice Goblins - Data Model (Authoritative Rework Contract)

Status: active  
Last Updated: 2026-07-25
Owner: Backend/Data  
Depends On: `backend/migrations/schema_all.sql`, `documentation/02-systems-mvp/00-combat-system.md`, `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/02-units-and-progression.md`

This document defines the canonical target data model for the current combat/loadout rework.  
Implementation may land incrementally, but new schema and migration work should move toward this contract rather than the superseded pooled-dice model.

## 1. Guiding Principles

- The backend is authoritative.
- Player-facing names are labels, not identifiers.
- Combat inputs must be persistable and replayable.
- Prefer explicit relational structures over broad JSON where the data must be edited, validated, or migrated.
- Enemy combat behavior should be data-authored per enemy type, not hand-scripted per enemy instance.

## 2. Stable Global Tables

These areas remain structurally stable for the rework:
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
- `region_items`
- `user_region_items`

They may need minor contract updates, but they are not the primary focus of the rework.

`items` and `user_items` are the generic progression inventory path for lineage materials, boss catalysts, machine catalysts, unlock keys, and later consumables. `user_unlocks` stores explicit account-level lineage unlocks under the `lineage` namespace; Basic Goblin is implicit for every account and should not need a row. `region_items` and `user_region_items` remain legacy compatibility tables and should not be extended for new progression rewards.

### 2.1 users

`users` is the canonical local account row regardless of provider. Discord OAuth and local credentials both resolve to a local `users.id`.

Current credential fields:
- `discord_id` for Discord OAuth identities; nullable for local-only users
- `local_email` for normalized email sign-in; nullable for Discord-only users
- `password_hash` for local credential verification; nullable for Discord-only users
- `display_name` and `avatar_url` for player-facing identity

Related credential table:
- `password_reset_tokens` stores one-hour hashed reset tokens for local accounts, with `used_at` marking consumed or superseded tokens

Rules:
- never store raw passwords
- never store raw password reset tokens
- never expose credential fields through session or profile payloads
- keep provider-specific identifiers unique when present

## 3. Unit Types and Enemy Types

### 3.1 unit_types
`unit_types` remain the authored source of:
- role
- base stats
- per-level growth for attack, defense, max HP, precision, and resolve
- max level
- promotion eligibility level
- level-10 capstone choices
- authored ability packages by tier/path

`ability_set_json` should no longer be interpreted as "everything this unit auto-uses in combat."
It should represent authored ability packages that feed:
- unlocked ability inheritance
- promotion-entry grants when the unit type is selected as a destination
- default equipped starter or migration loadouts where applicable

`promotion_grants_json` was removed in migration 71. Promotion previews may still expose a `promotion_grants` response object, but it is derived from the destination type's `ability_set_json`.

`max_equipped_dice` was removed in migration 72. Dice capacity is derived from equipped active abilities and their registered dice-slot requirements.

### 3.2 enemy_templates
`enemy_templates` remain the authored source of:
- base stats
- role
- xp reward
- ability catalog

Enemy definitions must additionally own an authored equipped-ability order for combat scheduling.
All enemies of one enemy type use that same authored loadout.

Recommended contract:
- keep `ability_set_json` for authored enemy ability catalog
- add explicit equipped-loadout storage, either:
  - `equipped_abilities_json`, or
  - a normalized child table if editing/reporting needs justify it

## 4. Unit Instances

### 4.1 unit_instances
`unit_instances` remain the player's persistent unit object and should additionally own:
- `display_name`
- persistent current type reference
- tier
- level
- xp
- any locking/protection flags

`unit_instances` should no longer be treated as fully defined by only `unit_type_id`.
The unit now also owns combat-configuration state.

### 4.2 Promotion Path History
Promotion history must be queryable well enough to validate sideways promotion eligibility.

This can be represented by:
- richer `unit_promotions` history, or
- explicit path-history state on `unit_instances`, or
- both

Whatever shape is chosen must support validation without relying on player-facing names or lossy inference.

### 4.3 Capstone Lineage State
Capstone selection needs explicit persistence separate from authored unit-type data.

Recommended shape:

### unit_instance_capstone_choices
Columns:
- `unit_instance_id`
- `source_unit_type_id`
- `ability_id`
- `created_at`
- `updated_at`

Notes:
- the row is keyed by unit lineage plus authored source type, not only current type
- the selected capstone should also be present in `unit_instance_unlocked_abilities` so inheritance continues to use the normal unlocked-ability path after promotion

## 5. Ability Persistence

The schema now needs to distinguish three layers:
1. authored ability packages on unit types
2. cumulative unlocked abilities on unit instances
3. ordered equipped abilities on unit instances

Recommended normalized shape:

### unit_instance_unlocked_abilities
Stores which base abilities a unit has access to.

Columns:
- `unit_instance_id`
- `ability_slug` or `ability_id`
- `source_tier`
- `source_unit_type_id`
- `created_at`

### unit_instance_equipped_abilities
Stores ordered loadout entries.

Columns:
- `id`
- `unit_instance_id`
- `ability_slug` or `ability_id`
- `equip_order`
- `speed_cost`
- `created_at`
- `updated_at`

Notes:
- duplicate rows are allowed for duplicate equips
- equip budget validation should happen server-side

## 6. Dice Inventory and Ability-Slot Binding

### 6.1 dice_definitions
Still defines:
- sides
- rarity
- slot capacity

### 6.2 affix_definitions
Still defines the fixed alpha-launch affix pool and authored affix metadata.

### 6.3 dice_instances
Still represents player-owned dice.

### 6.4 ability-slot binding
The old `unit_dice` contract has been removed. Dice are now bound to a base ability slot instead of a generic unit slot.

The canonical model is a binding from unit + base ability + slot index to die instance.

Recommended shape:

### unit_ability_dice
Columns:
- `unit_instance_id`
- `ability_slug` or `ability_id`
- `slot_index`
- `dice_instance_id`
- `created_at`
- `updated_at`

PK:
- (`unit_instance_id`, `ability_slug` or `ability_id`, `slot_index`)

Notes:
- this table binds dice to the base ability configuration
- repeated equipped copies of the same ability all read from the same slot rows
- absent rows are interpreted as empty slots that resolve as `1`

## 7. Starter Unit Seeding

Initial unit grants must seed:
- generated display names
- default unlocked abilities
- default equipped ability order
- common `d4` dice into all starter ability slots

This should be handled in bootstrap or account-seed logic, not as implicit frontend-only setup.

## 8. Bounty Board Foundation

### 8.1 bounty_definitions

`bounty_definitions` stores authored contract templates that can appear on a future bounty board.

Current foundation columns:
- stable `slug`
- player-facing title and description
- category: hunting, region, or challenge
- `objective_json` for event/filter/target requirements
- `reward_json` for backend-authored payout details
- enabled and sort-order fields for board curation

### 8.2 user_bounties

`user_bounties` stores the player's accepted bounty state.

Current foundation columns:
- `user_id`
- `bounty_definition_id`
- status: accepted, completed, claimed, or abandoned
- `progress_json` for idempotent backend-owned progress
- accepted, completed, claimed, and updated timestamps

Notes:
- active-slot limits, board rotation, reward claims, and progress event handling are future service work
- bounty progress must be backend-authored and idempotent once those services are introduced

## 9. Pattern-Based Run Map Catalog

`backend/data/run-patterns/` is the authoring source of truth for `pattern-v1` run-map patterns, region rules, and generation profiles.
`pattern-v2` content is database-owned and seeded through forward-only migrations because production cannot rely on command-line catalogue sync.
The database tables below are the runtime lookup surface for both generators, but their source-of-truth differs by generator version: V1 mirrors repository JSON, while V2 is authored in migrations.

### 9.1 run_pattern_definitions

Stores immutable authored pattern versions.

Columns:
- stable `slug`
- positive `version`
- lifecycle `status`: draft, enabled, or disabled
- `definition_json` containing authored pattern content; V1 uses local graph/sockets/transforms, while V2 uses grid cells, connector cells, explicit connections, and perimeter exits
- `content_hash` for drift detection

Uniqueness:
- (`slug`, `version`)
- `content_hash`

### 9.2 run_pattern_region_rules

Stores region and phase eligibility for pattern versions.

Columns:
- `pattern_definition_id`
- `region_id`
- `generator_version`
- `base_weight`
- `allowed_phase`
- depth bounds
- max-per-run and cooldown limits
- `enabled`
- optional `weight_modifiers_json`

### 9.3 run_generation_profiles

Stores active region profile versions for a generator version.

Columns:
- `region_id`
- `generator_version`
- `profile_version`
- `enabled`
- `bounds_json`
- `budgets_json`
- `requirements_json`
- `retry_policy_json`
- `weight_policy_json`
- `content_hash`

### 9.4 region_runs provenance

Pattern-generated runs can record:
- `generator_version`
- `generation_profile_version`
- `pattern_catalog_hash`
- `generation_attempt`
- `generation_summary_json`

Fixed authored runs can also record `fixed-v1` provenance so Farm and Mystic Cave share the same frontend renderer metadata contract as Pattern-V2 regions.

These fields are nullable so existing lane-generated and fixed authored runs remain compatible while regions are migrated gradually.

### 9.5 run_edges rendering metadata

`run_edges.meta_json` stores optional renderer-facing metadata for persisted graph edges. Pattern-V2 connector cells compile into `through` waypoint coordinates in this field, which lets the shared run-map renderer draw authored connector routes without creating runtime `connector` nodes.

The current contract is intentionally small:
- `through`: ordered grid coordinates shaped like `{ "x": 4, "y": 2 }`

Old runs and generators can leave this field null; edge traversal still depends only on `from_node_id` and `to_node_id`.

## 9. Battles and Logs

`battles` remain the authoritative battle record.

`battle_logs.log_json` must now be able to explain:
- equipped ability instance fired
- cumulative tick scheduling
- slot values consumed
- empty-slot `1` contribution
- enemy authored loadout participation

The old pooled-dice combat interpretation should not appear in new logs.

### 9.1 chaos_encounter_results

`chaos_encounter_results` stores persisted slot-machine-style chaos encounter results for generated `chaos` run nodes.

Current foundation columns:
- owning `user_id`, `run_id`, and one unique `node_id`
- `status`: generated, manipulated, or confirmed
- deterministic `seed`
- `reels_json` containing the three authored reel outputs
- `reward_multiplier` derived from the generated risk score
- `finalized_rewards_json` containing the backend-authored payout applied when the node is completed
- `rerolled_reel_index` and `manipulation_count` for one allowed player reroll
- `finalized_at` for the durable completion timestamp

Rules:
- one run node can have only one generated chaos result
- refreshes return the existing row instead of rerolling
- one reroll may change exactly one reel, then manipulation is spent
- reward scaling is derived from the same persisted reel result that communicates risk
- finalization applies the stored reward payload once, marks the node cleared, and returns the same payout on retry
- full chaos combat generation remains follow-up work

## 10. Rewards and Promotions

`unit_promotions` should retain enough detail to explain:
- source unit
- consumed units
- destination type
- optional region item consumption
- promotion path used

This history is important both for player support/debugging and sideways-promotion eligibility.

## 11. Migration Expectations

The ability-loadout rework migrated runtime dice assignment to `unit_ability_dice` and removed the legacy `unit_dice` table in migration 70.

Remaining migration concerns for future combat/loadout changes:
- existing authored unit ability data
- existing enemy template combat definitions
- existing starter grant logic
- active runs or resumable battle snapshots

Recommended migration stance:
- treat active runs as incompatible unless a clear snapshot migration is implemented
- author enemy equipped-loadout data before enabling scheduler changes

## 12. Normalization Guidance

As implementation lands, prefer normalization over further drift:
- compact legacy migration history into a smaller set of canonical migrations once the rework stabilizes
- avoid growing parallel pooled-dice and ability-slot schemas long-term
- keep test fixtures aligned to the new normalized model instead of preserving legacy abstractions indefinitely

## 13. Explicitly Superseded Model

The following model is no longer canonical:
- unit-scoped combat dice pools
- combat consumption ordering from a shared pool
- enemy scheduling driven only by modulo speed triggers
- promotion as ability replacement instead of cumulative inheritance
