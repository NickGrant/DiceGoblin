# Combat Math & Modifiers — MVP (Authoritative)

Status: active  
Last Updated: 2026-03-02  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/00-combat-system.md`, `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/02-units-and-progression.md`


This document defines the **numeric constants**, **order of operations**, and **damage/stat formulas** for MVP combat.

It is authoritative alongside:
- Combat System & Content — MVP
- Dice System — MVP
- Units & Progression — MVP

Any math or modifier not defined here is **out of scope** for MVP.

---

## 1. Global Conventions

### 1.1 Integers and Rounding
- All combat-relevant quantities are integers at rest (HP, Attack, Defense, damage amounts)
- When a formula produces a fractional value, **floor** (round down) is used unless explicitly stated otherwise

### 1.2 Ordering Rule
When multiple modifiers apply, resolve in this order:
1) Base stats (tier/level)
2) Always-on dice affixes (flat then percent)
3) Status modifiers (flat then percent)
4) Positioning multipliers
5) Conditional affixes / conditional modifiers
6) Final clamps (min/max damage)

---

## 2. Grid Orientation and Position Definitions

### 2.1 Player Side Orientation
- Player front row is `r = 0`
- Player back row is `r = 2`

### 2.2 Enemy Side Orientation
To avoid mirrored confusion, “front/back” is defined **relative to side**:
- Enemy front row is also `r = 0` (closest to the enemy’s frontline)
- Enemy back row is `r = 2`

If your UI renders enemies upside-down or mirrored, it must still map to this convention in the simulation/log.

---

## 3. Core Constants (MVP Defaults)

These constants are intended to be tunable but **must be centralized here**.

### 3.1 Positioning Multipliers
Applies only to attacks tagged as `melee`:

- Front row melee damage dealt multiplier: `MELEE_FRONT_DEALT = 1.10`
- Front row damage taken multiplier (all damage): `FRONT_TAKEN = 1.10`
- Back row melee damage taken multiplier: `MELEE_BACK_TAKEN = 0.90`

Notes:
- “Front row damage taken” applies to **all** damage sources for simplicity.
- “Back row reduced melee damage taken” applies only to melee-tagged attacks.

### 3.2 Minimum Damage
- Minimum final damage for a successful damaging hit: `MIN_DAMAGE = 1`

(If you later add “glancing/blocked” outcomes, revise this.)

### 3.3 Status Defaults (if not specified by ability)
- Poison duration: `POISON_DEFAULT_DURATION_ROUNDS = 2`
- Bolstered duration: `BOLSTERED_DEFAULT_DURATION_ROUNDS = 2`
- Sleep duration: `SLEEP_DEFAULT_DURATION_ROUNDS = 1`

(Abilities may override.)

---

## 4. Stat Construction (Attack / Defense / Max HP)

### 4.1 Base Stats
A unit’s base stats come from its UnitType growth model at its current:
- tier
- level

Assume the engine can compute:
- `baseAttack`
- `baseDefense`
- `baseMaxHp`

### 4.2 Dice Affix Contributions
Dice affixes contribute either:
- flat bonuses: `attackFlat`, `defenseFlat`, `hpFlat`
- percent bonuses: `attackPct`, `defensePct`, `hpPct`
- flat elemental damage: `fireFlat`, `iceFlat`
- conditional flags: `onMaxRoll`, `onTargetBelowHalf`

### 4.3 Status Contributions (MVP)
Statuses may contribute:
- `bolsteredDefensePct` (percent)

No other status changes stats in MVP.

### 4.4 Final Stat Formulas
Compute for each stat:

1) Add flats:
- `attackPrePct = baseAttack + attackFlat`
- `defensePrePct = baseDefense + defenseFlat`
- `hpPrePct = baseMaxHp + hpFlat`

2) Sum percent modifiers (dice + statuses):
- `attackPctTotal = attackPct`  
- `defensePctTotal = defensePct + bolsteredDefensePct`  
- `hpPctTotal = hpPct`

3) Apply percent (floor):
- `attackTotal = floor(attackPrePct * (1 + attackPctTotal))`
- `defenseTotal = floor(defensePrePct * (1 + defensePctTotal))`
- `maxHpTotal  = floor(hpPrePct * (1 + hpPctTotal))`

Notes:
- Percent values are expressed as decimals (e.g., 0.10 for +10%).
- Percent modifiers are **additive within a stat** for MVP.

---

## 5. Dice Rolls and Damage Inputs

### 5.1 Roll Total
When an action executes with `diceCost = N`:
- Consume N dice (per Dice System)
- Roll each consumed die once
- `rollTotal = sum(rollResults)`

If `diceCost = 0`:
- `rollTotal = 0`

### 5.2 “Max Roll” Condition
The conditional affix “Bonus damage on max die roll” triggers if:
- At least one consumed die result equals its die size (e.g., a d8 rolling 8)

---

## 6. Damage Model (MVP)

### 6.1 Damage Tags
Each damaging ability must define a tag:
- `melee` or `ranged` or `special`

MVP positioning modifiers apply only to `melee` (unless explicitly overridden by an ability later).

### 6.2 Base Physical Damage (Pre-Defense)
For a damaging action, define:

`physicalPreDefense = attackTotal + rollTotal`

(If you later want ability scaling, add `abilityAttackScale` and/or `abilityFlatDamage` here. MVP can keep this simple.)

### 6.3 Defense Mitigation
`physicalPostDefense = max(0, physicalPreDefense - defenseTotal)`

### 6.4 Elemental Flat Damage
If the attacker has dice affixes that add elemental flat damage:
- `elementalFlat = fireFlat + iceFlat`

In MVP:
- Elemental flat damage **bypasses Defense** (it is added after mitigation)
- Elemental damage has no interactions (no burn/freeze)

### 6.5 Conditional Bonus Damage (MVP Defaults)
Two conditional affixes exist; define their effects here.

#### A) Bonus Damage on Max Roll
If triggered:
- `bonusMaxRoll = floor(rollTotal * 0.25)`

Else:
- `bonusMaxRoll = 0`

#### B) Bonus Damage vs Target Below 50% HP
If target current HP is strictly below half of max:
- `belowHalfMultiplier = 1.15`
Else:
- `belowHalfMultiplier = 1.00`

### 6.6 Positioning Multipliers
Determine two multipliers:

**Attacker dealt multiplier** (melee only):
- If attack is `melee` and attacker is in front row: multiply by `MELEE_FRONT_DEALT`
- Else: 1.0

**Target taken multiplier**:
- If target is in front row: multiply by `FRONT_TAKEN`
- Additionally, if attack is `melee` and target is in back row: multiply by `MELEE_BACK_TAKEN`

### 6.7 Final Damage
Compute per target:

1) Sum components:
`raw = physicalPostDefense + elementalFlat + bonusMaxRoll`

2) Apply conditional multiplier:
`raw2 = floor(raw * belowHalfMultiplier)`

3) Apply positioning:
`raw3 = floor(raw2 * dealtMultiplier * takenMultiplier)`

4) Apply minimum damage (if the action is intended to deal damage):
`finalDamage = max(MIN_DAMAGE, raw3)`  
(If `raw3` is 0 but the action is “damage,” MIN_DAMAGE enforces progress.)

### 6.8 Logging Requirements
For each damage event, logs should be able to reflect:
- dice consumed + roll results
- final damage amount
- whether max-roll condition triggered
- whether below-half multiplier applied

You may either log the intermediate values or provide a `notes` string.

---

## 7. Status Math (MVP)

### 7.1 Poison
Type: DoT debuff

Trigger:
- During Status Phase, when `tick % statusSpeed === 0`

Stacking:
- Does not stack
- Re-apply updates duration to max(remaining, newDuration)

Damage formula (per trigger):
`poisonDamage = max(1, floor(sourceAttackTotal * 0.20))`

Application notes:
- Poison damage is considered `special` (not melee/ranged)
- Poison damage is affected by `FRONT_TAKEN` if the target is in the front row

### 7.2 Bolstered
Type: Defensive buff

Effect:
- Increases Defense by a percentage: `bolsteredDefensePct`

Stacking:
- Does not stack
- Strongest instance applies (largest percent)

Duration:
- Duration-based; expires after duration rounds

### 7.3 Sleep
Type: Control debuff (disable)

Effect:
- Prevents the unit from acting (Action Phases)

Termination:
- Ends immediately when the unit takes damage
- Also ends when duration expires

Stacking:
- Does not stack
- Re-apply updates duration to max(remaining, newDuration)

Timing rule (“does not participate in tick where sleep ends”):
- If Sleep ends at any point during tick T (expiration or damage), the unit is considered **ineligible to act for tick T**
- The unit may act again starting tick T+1

---

## 8. Out of Scope (MVP)
This document explicitly does not define:
- Critical hits, accuracy/evasion, glancing blows
- Resistance, immunity, cleansing
- AoE math
- Status interactions (poison + fire, etc.)
- Percent elemental damage
- Shielding, damage reflection, lifesteal

---

## 9. MVP Validation Checklist (Math)
Combat math is MVP-complete when:
- Two identical simulations (same seed/log) produce identical outcomes
- Positioning materially changes TTK for melee-tagged attacks
- Bolstered visibly alters damage via Defense percent
- Poison triggers at correct tick intervals and is readable in logs
- Conditional affixes create recognizable spikes without overwhelming the baseline

---

This document is considered **locked** for MVP unless explicitly revised.
