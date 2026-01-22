# Dice System — MVP (Authoritative)

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

### 2.4 Bonus Slot Capacity (by Rarity)

| Rarity   | Bonus Slots |
|----------|-------------|
| Common   | 0           |
| Uncommon | 1           |
| Rare     | 2           |

Notes:
- Slots only control **affix capacity**.
- Rarity has no other mechanics in MVP.

---

## 3. Affixes (Closed List)

### 3.1 Global Rules
- All MVP affixes cost **exactly 1 slot**
- Affixes are **always-on**
- Affixes must be surfaced in logs/UI in a player-readable way (name + value)

### 3.2 Flat Stat Affixes (Always-On)
- +Attack (flat)
- +Defense (flat)
- +HP (flat)

### 3.3 Percent Stat Affixes (Always-On)
- +Attack %
- +Defense %
- +HP %

### 3.4 Elemental Flat Damage (Always-On)
- +Fire Damage (flat)
- +Ice Damage (flat)

Notes:
- No percent elemental damage
- No elemental interactions (burn, freeze, etc.) in MVP

### 3.5 Conditional Affixes (Always-On, Conditional Trigger)
- Bonus damage on **max die roll**
- Bonus damage when **target is below 50% HP**

Notes:
- Conditions are evaluated per hit
- Conditions are deterministic and visible in combat logs

### 3.6 Total MVP Affix Count

| Category          | Count |
|------------------|-------|
| Flat stats        | 3     |
| Percent stats     | 3     |
| Elemental flat    | 2     |
| Conditional       | 2     |
| **Total**         | **10**|

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

## 6. MVP Validation Criteria

The dice system is MVP-complete when:
- Dice size progression is felt but not dominant
- Flat vs percent stats both have clear use cases
- Conditional affixes create visible spikes in damage
- Players can understand variance via combat logs

---

This document is considered **locked** for MVP unless explicitly revised.
