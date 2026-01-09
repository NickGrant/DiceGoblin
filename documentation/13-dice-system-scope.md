# Dice System Scope — MVP

This document defines the **closed, authoritative scope** of the Dice Goblins dice system for the MVP. Any dice-related mechanic, affix, or rule not explicitly listed here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP dice system must:
- Validate dice as the primary progression vector
- Support meaningful build differentiation
- Remain readable in combat logs and UI
- Avoid combinatorial explosion

The system is intentionally constrained to exercise math, pacing, and clarity rather than depth.

---

## 2. Die Sizes

### Enabled
- d4
- d6
- d8
- d10

### Explicitly Excluded
- d12 or higher
- Effects that modify die size
- Effects that roll additional dice outside the pool

---

## 3. Dice Rarity

### Enabled
- Common
- Uncommon
- Rare

### Slot Capacity
| Rarity | Slots |
|------|-------|
| Common | 0 |
| Uncommon | 1 |
| Rare | 2 |

### Explicitly Excluded
- Very Rare
- Legendary
- Rarity-specific mechanics beyond slot count

---

## 4. Affix Taxonomy (Closed List)

All MVP affixes cost **1 slot**.

### 4.1 Flat Stat Affixes (Always On)
- +Attack (flat)
- +Defense (flat)
- +HP (flat)

### 4.2 Percent Stat Affixes (Always On)
- +Attack %
- +Defense %
- +HP %

### 4.3 Elemental Flat Damage
- +Fire Damage (flat)
- +Ice Damage (flat)

Notes:
- No percent elemental damage
- No elemental interactions (burn, freeze, etc.) in MVP

### 4.4 Conditional Affixes
- Bonus damage on **max die roll**
- Bonus damage when **target is below 50% HP**

Notes:
- Conditions are evaluated per hit
- Conditions are deterministic and visible in combat logs

---

## 5. Total MVP Affix Count

| Category | Count |
|--------|-------|
| Flat stats | 3 |
| Percent stats | 3 |
| Elemental flat | 2 |
| Conditional | 2 |
| **Total** | **10** |

---

## 6. Explicit Non-Goals

The MVP dice system does **not** include:
- Dice upgrading or fusing
- Dice reroll mechanics
- Affix synergies or set bonuses
- Unit-specific affix restrictions
- Position- or turn-based affix conditions

---

## 7. MVP Validation Criteria

The dice system is considered MVP-complete when:
- Dice size progression is felt but not dominant
- Flat vs percent stats both have clear use cases
- Conditional affixes create visible damage spikes
- Players can understand damage variance via logs

---

This document is considered **locked** for MVP unless explicitly revised.

