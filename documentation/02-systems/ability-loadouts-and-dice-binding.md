---
Title: "Ability Loadouts and Dice Binding"
Status: Canonical
Last Updated: 2026-08-23
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/combat-resolution.md
  - documentation/02-systems/unit-promotion.md
  - backend/src/Services/UnitLoadoutService.php
  - backend/src/Repositories/UnitRepository.php
  - backend/src/Combat/Abilities/AbilityDefinition.php
Category: 02-systems
Tags:
  - systems
  - units
  - abilities
  - loadouts
  - dice
---

# Ability Loadouts and Dice Binding

## Purpose

This system separates a unit's accumulated ability access from the active abilities it equips for combat and from the dice assigned to those abilities.

The current model has three distinct layers:

1. **Authored ability package** — abilities supplied by a unit type.
2. **Unlocked ability catalog** — abilities the individual unit has accumulated over its progression history.
3. **Equipped active loadout** — the ordered active abilities the unit will schedule in combat.

Dice are bound to slots on the base ability id, not to a generic unit dice pool.

## Ability Types

Abilities are either `active` or `passive`.

### Active abilities

An active ability defines:

- a stable ability id
- speed from `1` to `20`
- zero or more dice slots through `diceCost`
- an authored order value
- a default target preference
- display/catalog metadata and effect parameters

Only active abilities with a positive speed can be placed in the equipped combat loadout.

### Passive abilities

A passive ability has no speed, dice cost, or default target. Passive behavior is applied by combat rules rather than being scheduled as an active action.

The profile model distinguishes current-type authored abilities from inherited passive abilities accumulated from prior progression stages.

## Unlocked Ability Catalog

Unlocked ability access is persisted per unit instance in `unit_instance_unlocked_abilities`.

When a unit is initialized, the current unit type's authored active and passive abilities are added to this catalog. The insertion is idempotent.

Promotion preserves the catalog. Before promotion changes the primary unit's type, the source type is synchronized; after the type changes, the target type's authored package is added. See `unit-promotion.md`.

The catalog is therefore progression history, not merely a mirror of the unit's current type.

## Equipped Active Loadout

The combat loadout is an ordered list persisted in `unit_instance_equipped_abilities`.

### Validation

A valid equipped loadout must:

- contain at least one active ability
- contain only abilities unlocked for that unit
- contain only active abilities with positive speed
- have total speed cost of `20` or less

Ability equip cost is equal to the ability's speed.

Duplicate active abilities are allowed. There is no separate hard count limit beyond the 20-point speed budget.

### Order Matters

Equip order determines combat timing.

At the start of each 20-tick combat round, the scheduler walks the equipped list in order and accumulates each ability's speed:

```text
Loadout speeds: 6, 6, 8
Trigger ticks:   6, 12, 20
```

The current scheduler does **not** fill unused ticks by repeating earlier abilities. Unused ticks remain unused.

This differs from older rework planning material that proposed filler repetition; the current runtime and `combat-resolution.md` are authoritative.

### Initialization

When a unit is first initialized and has no equipped active abilities, the unit type's authored active list becomes its default equipped loadout.

Initialization does not replace a loadout that already exists. As a result, promotion adds newly available abilities without automatically rewriting the primary unit's current loadout.

## Dice Slots

Each active ability exposes one slot per point of its authored `diceCost`.

Bindings are persisted by:

- unit instance id
- ability id
- slot index
- owned dice instance id

A die can be assigned only when:

- the unit exists
- the ability is unlocked for that unit
- the slot index is legal for that ability
- the die belongs to the same player
- the binding does not violate dice-assignment availability rules

## Base-Ability Binding

Dice bind to the base `ability_id`, not to an individual equipped copy.

If the same active ability is equipped multiple times, each copy uses the same configured dice slots whenever it fires.

Example:

```text
Equipped loadout:
1. basic_attack_melee
2. shield_up
3. basic_attack_melee

Dice binding:
basic_attack_melee slot 0 -> die #42
```

Both copies of `basic_attack_melee` use die `#42` in slot `0`.

## Empty Slots

An unfilled player ability slot resolves as an explicit empty slot with one side.

Because that virtual die is a `d1`, it always contributes `1` to the ability's roll.

Empty slots:

- are not owned dice
- have no material, rarity, or affixes
- cannot be sold or salvaged
- still produce a deterministic slot trace in combat logs

## Combat Resolution

For each scheduled active ability:

1. resolve its base ability id and target preference
2. load the configured dice for each legal slot
3. substitute an empty `d1` for any unbound player slot
4. resolve rolls and die effects deterministically
5. combine slot contributions into the ability roll
6. resolve the ability outcome

The battle log records the equipped ability instance index, dice used, slot traces, and roll outcome so the resolved configuration can be audited.

See `combat-resolution.md` for the complete action and die-effect order.

## Mutation Window

Changing equipped abilities or ability-slot dice is a unit mutation.

The backend uses the unit mutation guard to reject loadout and dice-binding changes when the unit is locked into an active run snapshot. Combat therefore resolves from the configuration captured for the run rather than allowing the player's persistent unit configuration to change underneath it.

## Promotion Interaction

Promotion preserves the primary unit's accumulated unlocked catalog and existing equipped active loadout.

The target unit type contributes newly authored abilities to the unit's available catalog. Those newly available active abilities do not automatically displace or append to an already existing loadout.

Secondary units consumed by promotion have their ability-dice bindings removed before deletion.

## Current Versus Legacy Rules

The legacy ability-loadout rework document remains useful for design history but contains rules that no longer match runtime behavior.

Current behavior differs in at least these areas:

- unused round ticks are not filled by repeated hostile abilities
- the current combat system uses persisted equipped ability order directly

When this document and `mvp-reference/09-ability-loadout-combat-rework-plan.md` disagree, use this document and `combat-resolution.md`.

## Related Documents

- `combat-resolution.md` — deterministic scheduling and action resolution
- `target-resolution.md` — how an active ability chooses its target
- `unit-promotion.md` — how ability access persists through promotion
- `mvp-reference/01-dice-system.md` — current dice rarity and affix reference
- `documentation/04-ux/02-warband-management.md` — player-facing unit management responsibilities
