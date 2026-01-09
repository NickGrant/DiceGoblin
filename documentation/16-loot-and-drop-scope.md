# Loot & Drop Scope — MVP

This document defines the **authoritative loot generation model** for the Dice Goblins MVP. It specifies how rewards are determined, which reward categories exist, and how Tier 3 promotion items are distributed. Any loot mechanic not explicitly described here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP loot system must:
- Provide predictable but flexible reward pacing
- Support unit and dice acquisition without over-saturation
- Gate Tier 3 progression through biome-specific boss rewards
- Remain data-driven and table-based

Loot is intended to reinforce the core loop, not to act as a long-term economy system.

---

## 2. Loot Tables (Core Model)

All loot is generated via **loot tables**.

### Key Principles
- Encounters do not directly grant items
- Encounters specify **how many rolls** are made and **which tables** are rolled
- Loot tables define *what* can be rewarded
- Encounter definitions define *how much* is rewarded

This separation allows tuning without changing encounter logic.

---

## 3. Loot Table Tiers

The MVP supports exactly **two loot table tiers**:

- **Tier 1 Loot Table**
- **Tier 2 Loot Table**

No Tier 3 loot table exists in MVP.

### Tier Usage
- Tier 1 tables represent common, early, or baseline rewards
- Tier 2 tables represent higher-impact, rarer rewards

---

## 4. Loot Categories (Closed List)

Each loot table may roll from the following categories:

1. **Dice**
2. **Units**
3. **Currency**

No other reward categories exist in MVP.

Explicitly Excluded:
- Crafting materials
- Consumables
- Cosmetics
- Meta-progression resources

---

## 5. Encounter → Loot Mapping

Each encounter defines its reward as a combination of:
- Loot table tier
- Number of rolls

### Example Reward Profiles

- **Easy Reward**:
  - 1 roll on Tier 1 loot table

- **Medium Reward**:
  - 2–3 rolls on Tier 1 loot table
  - *or* 1 roll on Tier 2 loot table

Encounter definitions choose from these patterns; the loot system itself remains agnostic.

---

## 6. Boss Encounter Rewards

Boss encounters follow all standard loot rules, with additional logic.

### Boss Loot Rules
- Boss encounters grant normal loot table rolls as defined by the encounter
- Boss encounters have an additional **percentage chance** to reward a biome-specific Tier 3 promotion item

### Biome-Specific Tier 3 Items

- Mountains Biome: **Roc Egg**
- Swamps Biome: **Gator Head**

Rules:
- Tier 3 items only drop from bosses
- Tier 3 item drop chance is defined per boss
- Tier 3 items are not guaranteed unless explicitly configured

---

## 7. Units as Loot

When a loot roll results in a unit:
- The unit is generated at Tier 1
- The unit starts at level 1
- Units do not drop at Tier 2 or Tier 3 in MVP

Unit drops are intended to support roster growth, not bypass progression.

---

## 8. Dice as Loot

When a loot roll results in a die:
- Die size and rarity are determined by the loot table
- Dice obey all constraints defined in `dice-system-scope.md`

No dice modification or upgrading occurs at drop time.

---

## 9. Currency as Loot

Currency is granted as a flat amount determined by the loot table.

Rules:
- No scaling based on player state
- No bonuses or multipliers in MVP

Currency exists solely to support MVP systems that require it.

---

## 10. Explicit Non-Goals

The MVP loot system does **not** include:
- Pity timers
- Drop streak protection
- Smart loot targeting
- Inventory limits
- Player choice during loot resolution

---

## 11. MVP Validation Criteria

The loot system is considered MVP-complete when:
- Loot tables can be tuned without code changes
- Players reliably gain new units and dice through play
- Tier 3 promotion items feel rare but achievable
- Reward pacing feels consistent across runs

---

This document is considered **locked** for MVP unless explicitly revised.

