---
Title: "Seed Catalog Ownership"
Status: Canonical
Last Updated: 2026-08-02
Owner: Engineering
Depends On:
  - documentation/05-technical/04-data-model.md
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/02-systems/08-dice-material-model.md
  - documentation/02-systems/09-kin-reconstruction.md
  - backend/migrations/schema_all.sql
Category: 05-technical
Tags:
  - technical
---

# Seed Catalog Ownership

## Purpose

Define when game data should live in the database, in code/config, or in a hybrid contract where data rows identify content and code executes behavior.

This document is the decision aid for cleanup work after the read-only seed browser. It does not remove tables by itself; implementation changes still need focused issues and forward-only migrations.

## Ownership Models

### Database-Owned

Use database-owned tables for player state, mutable runtime state, relationship tables, operational logs, and authored catalogs that need querying, joins, admin inspection, or future balancing.

Typical signs:

- per-user or per-run rows
- audit or player-support value
- foreign-key or join requirements
- likely debug-panel inspection
- environment or content-pack variation

### Code/Config-Owned

Use code or versioned config for small stable primitives and rules that are awkward to edit live and safer when reviewed like source.

Typical signs:

- tiny enum-like list
- rarely changes independently
- every change requires code and test review
- no player-specific state
- no meaningful admin-query need

### Hybrid-Owned

Use hybrid ownership when a row describes content but code owns executable behavior.

The contract is a stable slug:

- a database or config row says the thing exists and stores display or tuning metadata
- a code registry owns executable behavior
- parity tests ensure enabled rows do not point at missing handlers and handlers do not drift from catalog definitions

## Current Table Classification

| Table | Ownership | Reason | Near-term action |
| --- | --- | --- | --- |
| `users` | Database-owned | Account identity and auth state. | Keep DB. |
| `password_reset_tokens` | Database-owned | Security-sensitive operational state. | Keep DB. |
| `player_state` | Database-owned | Mutable per-user currencies and progression state. | Keep DB. |
| `energy_state` | Database-owned | Mutable per-user regeneration state. | Keep DB. |
| `user_grants` | Database-owned | Idempotency and audit ledger for grants. | Keep DB; reuse only where it can distinguish retries from new deliberate actions. |
| `wrong_machine_reconstruction_requests` | Planned database-owned | Request-level idempotency and audit for repeatable asset production. | Add or map to an equivalent durable ledger before enabling repeat reconstruction. |
| `user_unlocks` | Database-owned | Per-user feature and unit-type unlocks plus compatibility projections. | Keep DB; kin rows may represent discovery or ordinary-grant eligibility, but owned units remain kin-ownership authority. |
| `user_items` | Database-owned | Per-user generic inventory counts. | Keep DB. |
| `region_unlocks` | Database-owned | Per-user progression state. | Keep DB. |
| `user_region_items` | Database-owned legacy | Per-user legacy region-item counts. | Keep for compatibility; do not extend for new rewards. |
| `teams` | Database-owned | Player-managed squad containers. | Keep DB. |
| `team_units` | Database-owned | Player-managed squad membership. | Keep DB. |
| `team_formation` | Database-owned | Player-managed formation placement. | Keep DB. |
| `unit_instances` | Database-owned | Player-owned unit state and durable kin ownership. | Keep DB; migrate naming from splice compatibility toward kin terminology deliberately. |
| `unit_promotions` | Database-owned | Progression history and support/debug trail. | Keep DB. |
| `unit_instance_unlocked_abilities` | Database-owned | Per-unit learned ability catalog. | Keep DB. |
| `unit_instance_equipped_abilities` | Database-owned | Mutable per-unit loadout order. | Keep DB. |
| `unit_instance_capstone_choices` | Database-owned | Persistent per-unit capstone selection state. | Keep DB. |
| `unit_ability_dice` | Database-owned | Runtime binding from unit ability slots to owned dice. | Keep DB. |
| `dice_instances` | Database-owned | Player-owned dice inventory and equipment identity. | Keep DB; target instances reference one size and one material, with rarity derived from material. |
| `dice_instance_affixes` | Database-owned legacy | Rolled per-die affix values in the current implementation. | Freeze as migration input and remove after valid size-material conversion. |
| `region_runs` | Database-owned | Mutable run lifecycle state. | Keep DB. |
| `run_nodes` | Database-owned with code-backed enum pressure | Runtime node graph with enum-like node types. | Keep DB; keep node-type constants versioned and validated. |
| `run_edges` | Database-owned | Runtime graph connectivity. | Keep DB. |
| `run_unit_state` | Database-owned | Runtime combat/run HP and status state. | Keep DB. |
| `battles` | Database-owned | Combat instance lifecycle. | Keep DB. |
| `battle_logs` | Database-owned | Support/debug replay artifact. | Keep DB. |
| `battle_rewards` | Database-owned | Claimable reward state. | Keep DB. |
| `chaos_encounter_results` | Database-owned with hybrid future | Persisted reel result and reward finalization state. | Keep DB; add slug parity if symbols become behavior-bearing catalog rows. |
| `shop_daily_deals` | Database-owned | Per-user rotating shop state. | Keep DB. |
| `regions` | Database-owned catalog | Authored progression catalog with joins and debug value. | Keep DB; consider structured source seeding if SQL becomes painful. |
| `items` | Database-owned catalog | Generic item catalog for lineage materials, boss catalysts, machine inputs, unlock keys, and consumables. | Keep DB; candidate for structured source seeds as content grows. |
| `region_items` | Database-owned legacy catalog | Older region-linked item catalog. | Keep for compatibility; use `items` for new progression work. |
| `unit_types` | Hybrid-owned catalog | Rows store stats, progression metadata, capstones, and ability packages; behavior is code-owned. | Keep DB; strengthen registry parity tests. |
| `enemy_templates` | Hybrid-owned catalog | Rows store enemy stats and loadouts; combat behavior is code-owned. | Keep DB; strengthen ability/loadout parity tests. |
| `encounter_templates` | Hybrid-owned catalog | Rows store encounter composition; generation and combat rules are code-owned. | Keep DB; validate referenced content. |
| `loot_tables` | Database-owned catalog | Authored reward weights need inspection and balancing. | Keep DB; candidate for structured source seeds. |
| `run_pattern_definitions` | Database-owned catalog | Pattern V2 tiles need production-safe migration seeding and debug inspection. | Keep DB; treat exports as diagnostics, not a second source. |
| `run_pattern_region_rules` | Database-owned catalog | Region-specific pattern selection and rollout rules. | Keep DB; seed through migrations. |
| `run_generation_profiles` | Database-owned catalog | Runtime topology budgets, bounds, and requirements. | Keep DB; seed through migrations. |
| `bounty_definitions` | Hybrid-owned catalog | Rows store objectives and rewards; evaluation behavior is code-owned. | Keep DB; enforce objective-handler parity. |
| `splice_variants` | Hybrid-owned legacy-named catalog | Rows currently store kin modifiers and display copy. | Keep during compatibility period; new contracts use kin terminology and must not infer ownership from catalog rows. |
| `affix_definitions` | Hybrid-owned legacy catalog | Rows store legacy affix metadata while code owns behavior. | Freeze new content; convert, merge, relocate, or remove before retirement. |
| `dice_definitions` | Code/config-owned size primitive candidate | Current table mixes sides, independent rarity, and affix capacity. | Separate size/base-value behavior during material migration; stop extending rarity or affix capacity. |
| `dice_materials` | Planned hybrid-owned catalog | Target rows store material identity, rarity, effects, stacking, valuation, tags, and enabled state. | Seed from the canonical material catalog and enforce handler parity. |
| `dice_material_allowed_sizes` | Planned database-owned relationship | Explicit material-size compatibility requires validation and querying. | Add with the material catalog unless equivalent validated structured storage is deliberately chosen. |
| `unit_dice` | Removed | Legacy generic unit dice binding replaced by `unit_ability_dice`. | No action; migration 70 removed it. |

## Dice Material Target Contract

The target dice ownership split is:

- size vocabulary and base values are small stable primitives
- material definitions are authored hybrid-owned content
- material behavior is executed by code through a stable effect key
- die instances store ownership, equipment, size, and material identity
- rarity is derived from material
- allowed sizes are explicit
- permanent affix relationship rows are not retained

A material row remains inspectable and balanceable without embedding executable logic in SQL. Its effect key and parameters resolve to registered behavior, and its allowed sizes resolve only to active size primitives.

## Dice Migration Direction

Implementation reconciliation should avoid a long-lived hybrid between old and new dice models.

The migration should:

1. author and seed the material catalog
2. add material references and size-eligibility validation
3. deterministically map every owned die to one valid size-material pair
4. preserve ownership and ability-slot bindings
5. switch generation, combat, valuation, shop, salvage, profile, and Codex behavior to material identity
6. stop reading independent rarity and per-instance affixes
7. remove legacy affix relationships and definitions after compatibility ends

Legacy affix tables may remain temporarily for rollback or audit, but no target-state feature should depend on them after cutover.

## Kin Reconstruction Target Contract

The ownership split for reconstruction is:

- recipe definitions are authored system/content data
- `items`, `user_items`, and `player_state` own spendable inputs
- `unit_instances` own the durable produced unit and its kin
- discovery, Codex, and ordinary-grant eligibility are derived first-ownership projections
- a request ledger owns idempotent retry state

A permanent kin-discovery or eligibility row must not act as the uniqueness constraint for a repeatable recipe. It may prevent duplicate first-ownership side effects, but it must not prevent later unit production.

A reconstruction request record should retain enough result data to return the same produced unit, cost, unit-type resolution, and first-discovery outcome on retry.

## Recommended Cleanup Order

1. Reconcile Wrong Machine storage and service behavior with repeatable production and request-level idempotency.
2. Backfill or repair kin discovery/eligibility projections from durable owned units.
3. Add material catalog parity validation for keys, handlers, rarity, allowed sizes, and generation coverage.
4. Reconcile dice storage and migrate owned dice without breaking ability-slot bindings.
5. Remove independent rarity, affix-capacity, affix-generation, and affix-valuation behavior.
6. Continue parity work for other hybrid behavior-bearing slugs.
7. Move large catalog authoring out of raw SQL only after inspection and parity tests make review safe.
8. Keep all player, run, inventory, battle, auth, and audit state database-owned.

## Hybrid Contract Tests

Hybrid-owned tables should have focused tests for:

- every enabled catalog slug referenced by code exists in seeded data
- every enabled behavior-bearing row has a registered handler
- JSON references resolve to valid catalog rows
- disabled or future rows are excluded from runtime selection

Dice materials additionally require tests for:

- exactly one supported rarity per enabled material
- at least one active allowed size per enabled material
- valid active-size references
- registered effect handler or explicit neutral behavior
- non-empty eligible material pools for every selectable size and reward source
- no invalid owned size-material pairs

Kin reconstruction additionally requires tests for:

- every deliberate recipe completion creates one unit
- the same idempotency key returns the same result
- a new idempotency key can produce another unit
- first-ownership projections occur once
- projection repair works from durable unit ownership
- discovery state does not suppress ingredient drops or repeat production

The debug seed browser remains an inspection surface for database-backed values. It should not become an editor until ownership, validation, and rollback rules are defined separately.
