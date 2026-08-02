---
Title: "Seed Catalog Ownership"
Status: Canonical
Last Updated: 2026-08-02
Owner: Engineering
Depends On:
  - documentation/05-technical/04-data-model.md
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/02-systems/08-dice-material-model.md
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
- audit/support value
- needs foreign keys or joins
- likely to be inspected through the debug panel
- may vary by environment or future content pack

### Code/Config-Owned

Use code or versioned config for small stable primitives and rules that are awkward to edit live and are safer when reviewed like source.

Typical signs:
- tiny enum-like list
- rarely changes independently
- every change requires code/test review anyway
- no player-specific state
- no meaningful admin query need

### Hybrid-Owned

Use hybrid ownership when a row describes content, but code owns executable behavior.

The contract is a stable slug:
- DB/config row says the thing exists and stores display/tuning metadata.
- Code registry has a handler for behavior-bearing slugs.
- Tests enforce parity so enabled rows do not point at missing handlers and handlers do not drift from catalog definitions.

## Current Table Classification

| Table | Ownership | Reason | Near-Term Action |
| --- | --- | --- | --- |
| `users` | Database-owned | Account identity and auth state. | Keep DB. |
| `password_reset_tokens` | Database-owned | Security-sensitive operational state. | Keep DB. |
| `player_state` | Database-owned | Mutable per-user currencies and progression state. | Keep DB. |
| `energy_state` | Database-owned | Mutable per-user regeneration state. | Keep DB. |
| `user_grants` | Database-owned | Idempotency/audit ledger for grants. | Keep DB. |
| `user_unlocks` | Database-owned | Per-user feature and catalog unlock state, including explicit lineage unlocks under the `lineage` namespace. | Keep DB; Basic Goblin remains an implicit default without a row. |
| `user_items` | Database-owned | Per-user generic inventory counts. | Keep DB. |
| `region_unlocks` | Database-owned | Per-user progression state. | Keep DB. |
| `user_region_items` | Database-owned legacy | Per-user legacy region-item counts. | Keep DB for compatibility; do not extend for new progression rewards. |
| `teams` | Database-owned | Player-managed squad containers. | Keep DB. |
| `team_units` | Database-owned | Player-managed team membership. | Keep DB. |
| `team_formation` | Database-owned | Player-managed formation placement. | Keep DB. |
| `unit_instances` | Database-owned | Player-owned unit state. | Keep DB. |
| `unit_promotions` | Database-owned | Progression history and support/debug trail. | Keep DB. |
| `unit_instance_unlocked_abilities` | Database-owned | Per-unit learned ability catalog. | Keep DB. |
| `unit_instance_equipped_abilities` | Database-owned | Mutable per-unit loadout order. | Keep DB. |
| `unit_instance_capstone_choices` | Database-owned | Persistent lineage choice state. | Keep DB. |
| `unit_ability_dice` | Database-owned | Runtime binding from unit ability slots to owned dice. | Keep DB. |
| `dice_instances` | Database-owned | Player-owned dice inventory and equipment identity. | Keep DB; target instances should reference one size and one material, with rarity derived from material. |
| `dice_instance_affixes` | Database-owned legacy | Rolled per-die affix values in the current implementation. Permanent instance affixes are absent from the target model. | Freeze as migration input; stop extending the model and remove after owned dice are converted to valid size-material pairs. |
| `region_runs` | Database-owned | Mutable run lifecycle state. | Keep DB. |
| `run_nodes` | Database-owned with code-backed enum pressure | Runtime node graph, but `node_type` is currently an enum-like primitive. | Keep table DB; consider moving node-type constants to code/config with migration discipline. |
| `run_edges` | Database-owned | Runtime graph connectivity. | Keep DB. |
| `run_unit_state` | Database-owned | Runtime combat/run HP and status state. | Keep DB. |
| `battles` | Database-owned | Combat instance lifecycle. | Keep DB. |
| `battle_logs` | Database-owned | Support/debug replay artifact. | Keep DB. |
| `battle_rewards` | Database-owned | Claimable reward state. | Keep DB. |
| `chaos_encounter_results` | Database-owned with hybrid future | Persisted reel result and reward finalization state; symbol effects may become behavior-bearing. | Keep DB; add slug parity if symbols become catalog rows. |
| `shop_daily_deals` | Database-owned | Per-user rotating shop state. | Keep DB. |
| `regions` | Database-owned catalog | Authored progression catalog with unlock joins and debug inspection value. | Keep DB; seed from structured source later if SQL becomes painful. |
| `items` | Database-owned catalog | Generic item catalog for lineage materials, boss catalysts, machine catalysts, unlock keys, and future consumables. | Keep DB; candidate for structured source seeds once content grows. |
| `region_items` | Database-owned legacy catalog | Older region-linked item catalog referenced by legacy profile/debug surfaces. | Keep DB for compatibility; superseded by `items` for new progression work. |
| `unit_types` | Hybrid-owned catalog | Rows store stats, progression metadata, capstone choices, and a single authored ability package in `ability_set_json`; ability behavior and dice-slot capacity are code-owned through the ability registry. | Keep DB; add stronger slug/ability registry parity tests. |
| `enemy_templates` | Hybrid-owned catalog | Rows store enemy stats/loadouts; combat behavior and abilities are code-owned. | Keep DB; add stronger ability/loadout parity tests. |
| `encounter_templates` | Hybrid-owned catalog | Rows store authored encounter composition; node generation and combat rules are code-owned. | Keep DB; validate referenced regions/enemies/ability slugs. |
| `loot_tables` | Database-owned catalog | Authored reward weights need inspection and balancing. | Keep DB; candidate for structured source seeds. |
| `run_pattern_definitions` | Database-owned catalog | V2 run-map tiles need production-safe migration seeding and debug inspection; JSON files must not become a second source of truth for V2. | Keep DB; seed V2 rows through forward-only migrations and treat exports as diagnostics only. |
| `run_pattern_region_rules` | Database-owned catalog | Region-specific pattern selection and rollout rules must be deployable without command-line sync on production. | Keep DB; seed V2 rules through migrations. |
| `run_generation_profiles` | Database-owned catalog | Generator budgets, bounds, and requirements control runtime map topology and need environment-visible inspection. | Keep DB; seed V2 profiles through migrations. |
| `bounty_definitions` | Hybrid-owned catalog | Rows store objectives/rewards; objective evaluation behavior is code-owned. | Keep DB; enforce objective-kind handler parity. |
| `splice_variants` | Hybrid-owned catalog | Rows store modifiers and display copy; future passive behavior would be code-owned. | Keep DB; enforce passive/effect slugs if added. |
| `affix_definitions` | Hybrid-owned legacy catalog | Rows store legacy affix metadata and values while code owns affix behavior. Permanent affixes are retired from the target dice model. | Freeze new content; use rows to drive an explicit convert, merge, relocate, or remove decision, then retire the catalog. |
| `dice_definitions` | Code/config-owned size primitive candidate | The current table mixes sides, independent rarity, and affix-slot capacity. The target model needs active size primitives and base values, while rarity belongs to materials and affix capacity disappears. | Do not extend rarity or slot-capacity behavior; separate size primitives during the material migration. |
| `dice_materials` | Planned hybrid-owned catalog | Target rows should store stable material key, display data, rarity, allowed sizes, effect parameters, stacking metadata, valuation, tags, and enabled state; code owns executable effect handlers. | Add only after the canonical Dice Material Catalog is authored; enforce material-key and behavior-handler parity. |
| `unit_dice` | Removed | Legacy generic unit dice binding replaced by `unit_ability_dice`. | No action; migration 70 drops it. |

## Dice Material Target Contract

The target dice ownership split is:

- size vocabulary and base size values are small stable primitives
- material definitions are authored hybrid-owned content
- material behavior is executed by code through a stable material key or effect-handler key
- die instances store ownership, equipment, size, and material identity
- rarity is derived from material
- permanent affix relationship rows are not retained

A material catalog row should remain inspectable and balanceable without embedding executable logic in SQL. Its effect key and parameters must resolve to registered behavior, and its allowed-size data must resolve only to active size primitives.

## Dice Migration Direction

Implementation reconciliation should proceed without creating a long-lived hybrid between old and new dice models.

The migration should:

1. author and seed the material catalog
2. add target material references and validation
3. deterministically map every owned die to one valid size-material pair
4. preserve equipment bindings and ownership
5. switch generation, combat, valuation, shop, salvage, profile, and Codex behavior to material identity
6. stop reading independent rarity and per-instance affixes
7. remove legacy affix relationships and definitions after compatibility is no longer required

Legacy affix tables may remain temporarily for rollback or audit, but no target-state feature should depend on them after the cutover.

## Recommended Cleanup Order

1. Author the Dice Material Catalog and its stable behavior vocabulary.
2. Add parity validation for material keys, effect handlers, rarity, allowed sizes, and generation coverage.
3. Reconcile dice storage and migrate owned dice without breaking ability-slot bindings.
4. Remove independent rarity, affix-capacity, affix-generation, and affix-valuation behavior.
5. Continue parity work for other hybrid behavior-bearing slugs.
6. Move large catalog authoring out of raw SQL only after the seed browser and parity tests make review safe.
7. Keep all player, run, inventory, battle, auth, and audit tables database-owned.

## Hybrid Contract Tests

Hybrid-owned tables should have focused tests for:
- every enabled catalog slug referenced by code exists in seeded data
- every enabled catalog row that names behavior has a registered handler
- every JSON field that references another catalog row resolves successfully
- disabled or future rows are explicitly excluded from runtime selection

Dice materials additionally require tests for:
- every enabled material has exactly one supported rarity
- every enabled material has at least one active allowed size
- every allowed size resolves to an active size primitive
- every enabled material's effect key resolves to a registered handler or explicit neutral behavior
- every selectable size and reward source has a non-empty eligible material pool
- no die instance contains an invalid size-material pair

The debug seed browser remains the inspection surface for database-backed values. It should not become an editor until ownership, validation, and rollback rules are defined separately.
