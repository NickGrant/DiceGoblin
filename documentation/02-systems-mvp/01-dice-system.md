# Dice System — MVP (Authoritative)

Status: active  
Last Updated: 2026-03-21  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/00-combat-system.md`, `documentation/02-systems-mvp/07-combat-math-and-modifiers.md`


This document is the **authoritative specification** of the Dice Goblins dice system for the MVP.  
Any dice mechanic not explicitly included here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP dice system must:
- Make dice the primary progression vector
- Support meaningful build differentiation
- Remain readable in combat logs and UI
- Avoid combinatorial explosion

---

## 2. Dice Entities

### 2.1 Die Sizes (Enabled)
- d4
- d6
- d8
- d10

### 2.2 Die Sizes (Excluded)
- d12 or higher
- Any effect that modifies die size
- Any effect that rolls additional dice outside the pool

### 2.3 Dice Rarity (Enabled)
- Common
- Uncommon
- Rare
- Epic
- Legendary

### 2.4 Bonus Slot Capacity (by Rarity)

| Rarity   | Bonus Slots |
|----------|-------------|
| Common   | 0           |
| Uncommon | 1           |
| Rare     | 2           |
| Epic     | 3           |
| Legendary| 4           |

Notes:
- Slots only control **affix capacity**.
- The rarity ladder is fixed for MVP: each rarity above `common` adds exactly one slot.
- A die may only roll affixes whose rarity is less than or equal to the die rarity.
- Affixes are rolled once when the die is created and never rerolled or edited afterward.

---

## 3. Affixes (Closed List)

### 3.1 Global Rules
- All MVP affixes cost **exactly 1 slot**
- The affix pool is fixed for the first playable version
- Affixes must be surfaced in logs/UI in a player-readable way (name, rarity, kind, value/effect)

### 3.2 Fixed MVP Affix Pool
- `Atk+` | `common` | `passive` | `+1 damage on attack rolls`
- `Guard+` | `common` | `passive` | `+1 defense`
- `Bulwark+` | `uncommon` | `passive` | `+10% defense`
- `Precision+` | `uncommon` | `passive` | `+10% attack`
- `Execute` | `rare` | `triggered` | `+15% damage when target is below 50% HP`
- `Explode` | `rare` | `triggered` | `When this die rolls max, roll it again once and combine`

Notes:
- `Explode` can trigger at most once per action roll.
- The first version does not include rerolling, affix crafting, or mutable affix loadouts.

---

## 4. Dice Pools (Runtime Rules)

Dice pools model “equipped dice available for ability activation.”

### 4.1 Pool Composition
- Each unit has a **dice pool** composed of its **equipped dice**
- Each die in the pool is a discrete resource that can be consumed by abilities with `diceCost > 0`

### 4.2 Consumption Rules
When an ability executes with `diceCost = N`:
- Consume **N dice** from the pool
- Dice are consumed **largest → smallest** (e.g., d10 before d8)
- If multiple dice are required, consume multiple dice

### 4.3 Refresh Rules
- When the **smallest die** in the pool is consumed, the pool **immediately refreshes** (returns to full)
- If multiple dice are requested and there are not enough dice remaining:
  1) Take the maximum available dice
  2) Refresh the pool
  3) Take the remaining dice needed

### 4.4 Triggered Refreshes
- Abilities *may* explicitly trigger pool refresh in the future
- For MVP, treat this as **out of scope unless an ability explicitly lists it**

---

## 5. Explicit Non-Goals (MVP)

The MVP dice system does **not** include:
- Dice upgrading or fusing
- Dice reroll mechanics
- Affix synergies or set bonuses
- Unit-specific affix restrictions
- Position- or turn-based affix conditions

---

## 6. Economy Valuation

Dice value is backend-authoritative and is used for shop pricing visibility, inventory display, and sell payouts.

### 6.1 Base Value by Size

| Size | Base Value |
|------|------------|
| d4   | 12         |
| d6   | 18         |
| d8   | 28         |
| d10  | 34         |
| d12  | 42         |
| d20  | 60         |

### 6.2 Die Rarity Bonus

Apply this once per die:

| Die Rarity | Added Multiplier |
|------------|------------------|
| common     | 0.00             |
| uncommon   | 0.15             |
| rare       | 0.35             |
| epic       | 0.65             |
| legendary  | 0.90             |

### 6.3 Affix Premiums

Add one premium per rolled affix:

| Affix Rarity | Added Multiplier |
|--------------|------------------|
| common       | 0.70             |
| uncommon     | 0.85             |
| rare         | 1.00             |
| epic         | 1.25             |
| legendary    | 1.50             |

### 6.4 Final Formula

`value = round(base_size_value * (1 + die_rarity_bonus + sum(all affix premiums)))`

Notes:
- Common shop dice use their base value because they have no affixes.
- The daily deal remains an uncommon die with an uncommon affix, so it prices cleanly at 2x the matching common die size.
- Selling an unequipped die returns `floor(value / 2)` soft currency.

---

## 7. MVP Validation Criteria

The dice system is MVP-complete when:
- Dice size progression is felt but not dominant
- Flat vs percent stats both have clear use cases
- Triggered affixes create visible spikes in damage
- Players can understand variance via combat logs

---

This document is considered **locked** for MVP unless explicitly revised.
