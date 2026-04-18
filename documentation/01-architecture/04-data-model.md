# Dice Goblins - Data Model (Authoritative Rework Contract)

Status: active  
Last Updated: 2026-04-18  
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
- `region_items`
- `user_region_items`

They may need minor contract updates, but they are not the primary focus of the rework.

## 3. Unit Types and Enemy Types

### 3.1 unit_types
`unit_types` remain the authored source of:
- role
- base stats
- per-level growth
- max level
- authored ability packages by tier/path

`ability_set_json` should no longer be interpreted as "everything this unit auto-uses in combat."
It should represent authored ability packages that feed:
- unlocked ability inheritance
- default equipped starter or migration loadouts where applicable

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

`unit_instances` should no longer be treated as fully defined by `unit_type_id` plus `unit_dice`.
The unit now also owns combat-configuration state.

### 4.2 Promotion Path History
Promotion history must be queryable well enough to validate sideways promotion eligibility.

This can be represented by:
- richer `unit_promotions` history, or
- explicit path-history state on `unit_instances`, or
- both

Whatever shape is chosen must support validation without relying on player-facing names or lossy inference.

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
Still defines the fixed MVP affix pool and authored affix metadata.

### 6.3 dice_instances
Still represents player-owned dice.

### 6.4 ability-slot binding
The old `unit_dice` contract is superseded.

The canonical target model is a binding from unit + base ability + slot index to die instance.

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

## 8. Battles and Logs

`battles` remain the authoritative battle record.

`battle_logs.log_json` must now be able to explain:
- equipped ability instance fired
- cumulative tick scheduling
- slot values consumed
- empty-slot `1` contribution
- enemy authored loadout participation

The old pooled-dice combat interpretation should not appear in new logs.

## 9. Rewards and Promotions

`unit_promotions` should retain enough detail to explain:
- source unit
- consumed units
- destination type
- optional region item consumption
- promotion path used

This history is important both for player support/debugging and sideways-promotion eligibility.

## 10. Migration Expectations

The rework requires explicit migration treatment for:
- existing `unit_dice` rows
- existing authored unit ability data
- existing enemy template combat definitions
- existing starter grant logic
- active runs or resumable battle snapshots

Recommended migration stance:
- treat active runs as incompatible unless a clear snapshot migration is implemented
- convert legacy unit-dice assignments into base-ability slot assignments deterministically where possible
- author enemy equipped-loadout data before enabling the new scheduler

## 11. Normalization Guidance

As implementation lands, prefer normalization over further drift:
- compact legacy migration history into a smaller set of canonical migrations once the rework stabilizes
- avoid growing parallel pooled-dice and ability-slot schemas long-term
- keep test fixtures aligned to the new normalized model instead of preserving legacy abstractions indefinitely

## 12. Explicitly Superseded Model

The following model is no longer canonical:
- unit-scoped combat dice pools
- combat consumption ordering from a shared pool
- enemy scheduling driven only by modulo speed triggers
- promotion as ability replacement instead of cumulative inheritance
