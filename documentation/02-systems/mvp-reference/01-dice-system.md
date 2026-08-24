---
Title: "Dice System - Alpha Launch (Authoritative Rework Contract)"
Status: Legacy Reference
Last Updated: 2026-08-23
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/mvp-reference/00-combat-system.md
  - documentation/02-systems/mvp-reference/07-combat-math-and-modifiers.md
Category: 02-systems
Tags:
  - systems
  - mvp-reference
---

# Dice System - Alpha Launch (Authoritative Rework Contract)

This document is the authoritative specification for the alpha-launch dice system.  
Any dice mechanic not explicitly included here is out of scope for the alpha launch.

## 1. Design Goals

The dice system must:
- keep dice as a primary progression vector
- bind dice choices directly to ability behavior
- make empty configuration readable and deterministic
- preserve rarity and affix differentiation without requiring a pooled-combat model

## 2. Dice Entities

### 2.1 Enabled Sizes
- d4
- d6
- d8
- d10

### 2.2 Excluded Sizes
- d12 or higher in alpha-launch combat inventory
- die-size mutation systems
- reroll crafting systems

### 2.3 Enabled Rarities
- Common
- Uncommon
- Rare
- Epic
- Legendary

### 2.4 Bonus Slot Capacity by Rarity

| Rarity | Bonus Slots |
|--------|-------------|
| Common | 0 |
| Uncommon | 1 |
| Rare | 2 |
| Epic | 3 |
| Legendary | 4 |

Notes:
- rarity controls affix capacity, not ability-slot count
- affixes are rolled on die creation and remain fixed

## 3. Affixes

### 3.1 Global Rules
- Alpha-launch affixes still each cost exactly 1 affix slot.
- Affixes remain attached to dice instances.
- Affix rarity may not exceed parent die rarity.
- Affix effects apply when that die is read from an equipped ability slot.

### 3.2 Closed Alpha Launch Affix Pool
- `Atk+`
- `Guard+`
- `Bulwark+`
- `Precision+`
- `Execute`
- `Explode`

Detailed numerical behavior remains defined in combat math and backend resolution code.

## 4. Ability Slot Binding

### 4.1 Core Rule
- Dice are equipped to ability slots, not to a unit-wide combat pool.
- An ability exposes as many slots as its roll behavior consumes.
- Each slot holds either:
  - an equipped die instance, or
  - an implicit empty value of `1`

### 4.2 Shared Binding on Duplicate Equips
- Dice bind to the base ability instance on the unit.
- If the same ability is equipped multiple times in the loadout, every copy uses the same configured slot assignments.
- Slot index remains meaningful whenever an ability resolves multiple rolls separately.

### 4.3 Empty Slots
- Empty slots always resolve as `1`.
- Empty slots are not phantom dice.
- Empty slots should be visible in UI and logs as explicit placeholders rather than hidden fallbacks.

## 5. Starter Unit Seeding

- Initial player units should begin with a valid default equipped ability loadout.
- All starter-equipped ability slots should be filled with common `d4` dice.
- The starting roster should demonstrate the new system by example rather than presenting blank slots.

## 6. Inventory and Ownership Rules

- Dice remain persistent inventory objects owned by the player.
- Dice inventory must support identifying:
  - owning unit
  - owning ability
  - slot index within that ability
- Unequip and re-equip flows operate at the ability-slot level.

## 7. Removed Runtime Model

The rework removes these previous runtime rules:
- shared per-unit combat dice pools
- largest-to-smallest pool consumption
- immediate pool refresh on smallest-die consumption
- refresh-driven combat throughput

Those rules are no longer part of the alpha-launch contract.

## 8. Economy Valuation

Dice value remains backend-authoritative for shops, inventory, and sell payouts.

### 8.1 Base Value by Size

| Size | Base Value |
|------|------------|
| d4 | 12 |
| d6 | 18 |
| d8 | 28 |
| d10 | 34 |
| d12 | 42 |
| d20 | 60 |

### 8.2 Die Rarity Bonus

| Die Rarity | Added Multiplier |
|------------|------------------|
| common | 0.00 |
| uncommon | 0.15 |
| rare | 0.35 |
| epic | 0.65 |
| legendary | 0.90 |

### 8.3 Affix Premiums

| Affix Rarity | Added Multiplier |
|--------------|------------------|
| common | 0.70 |
| uncommon | 0.85 |
| rare | 1.00 |
| epic | 1.25 |
| legendary | 1.50 |

### 8.4 Final Formula

`value = round(base_size_value * (1 + die_rarity_bonus + sum(all affix premiums)))`

### 8.5 Raw Chaos Salvage

Unequipped owned dice may be salvaged into Raw Chaos instead of sold for teeth.

- salvage deletes the die instance
- equipped dice cannot be salvaged
- Raw Chaos payout is backend-authoritative
- payout scales from die size, die rarity, and affix rarity

This is the Wrong Machine v1 currency foundation. Fabrication recipes, catalyst costs, and any die-modification spend rules remain follow-up work.

## 9. Explicit Non-Goals

This dice rework does not add:
- die fusion or upgrading
- mutable affix editing
- set bonuses
- unit-specific affix restrictions
- advanced procedural slot interactions beyond authored ability behavior

## 10. Alpha Launch Validation Criteria

The dice system is correct for this rework when:
- ability slots, not unit pools, determine combat dice resolution
- duplicate equipped abilities clearly share the same slot configuration
- empty slots always resolve as `1`
- starter units begin with common `d4` slot assignments on their default abilities
- combat logs and UI make die usage understandable without referencing a hidden pool
