# Combat Content Scope — MVP

This document defines the **authoritative combat content scope** for the Dice Goblins MVP. Any combat mechanic, status effect, or ability behavior not explicitly defined here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP combat system must:
- Validate automated, deterministic combat resolution
- Exercise frontline vs backline positioning
- Support meaningful build expression through dice and abilities
- Remain readable through logs and UI

Combat content is intentionally constrained to reduce balance and cognitive load while fully testing the combat engine.

---

## 2. Ability Scope

### 2.1 Abilities per Unit

Each unit type supports:
- **2 Active Abilities**
- One of those active abilities should be the unit's base attack
- One of those active abilities should be a specialty action for that unit
- **Up to 2 Passive Abilities**

No unit may exceed **4 total abilities** in MVP.

### 2.2 Ability Types

Enabled:
- Direct damage abilities
- Defensive/self-buff abilities
- Simple debuff application abilities

Explicitly Excluded:
- Summoning
- Terrain modification
- Ability chains or combos
- Multi-step or choice-driven abilities

### 2.3 Ability Data Contract
- diceCost: number (0 or greater)
- speed: number (1-20)
- type: active | passive
- order: number

---

## 3. Status Effect Scope (Closed List)

Exactly **three** status effects exist in MVP.

### 3.1 Poison

**Type:** Damage-over-Time (Debuff)

Rules:
- Deals damage at a fixed interval
- Triggers when tick % statusSpeed === 0 during Status Phase
- Damage value is deterministic based on source stats
- Does not stack; updates duration to greatest between current remaining time and duration of additional effects

Purpose:
- Validates ongoing effect resolution
- Tests combat pacing and log clarity

---

### 3.2 Bolstered

**Type:** Defensive Buff

Rules:
- Increases Defense by a percentage
- Does not stack; strongest instance applies
- Duration-based

Purpose:
- Validates buff math and percent modifiers
- Provides non-damage support value

---

### 3.3 Sleep

**Type:** Control Debuff (Disable)

Rules:
- Prevents the affected unit from acting
- Ends immediately when the unit takes damage
- Also ends when duration expires
- Does not stack; updates duration to greatest between current remaining time and duration of additional effects
- unit does not participate in the tick where sleep ends

Purpose:
- Validates control effects with conditional termination
- Tests AI behavior when disabled

---

## 4. Status Effect Rules (Global)

- Status effects are evaluated server-side
- All applications, ticks, and removals are logged
- Status effects may not modify dice behavior in MVP
- No resistance, immunity, or cleansing systems exist in MVP
- each status effect is given a speed and duration in rounds when it is applied
- can trigger multiple times per round based on speed

---

## 5. Positioning Interaction

Combat positioning rules apply uniformly:

- **Front Row:**
  - Increased melee damage dealt
  - Increased damage taken

- **Back Row:**
  - Reduced melee damage taken
  - Eligible for ranged bonuses (if applicable)

No status effects are position-dependent in MVP.

---

## 6. Explicit Non-Goals

The MVP combat content does **not** include:
- Stacking debuffs
- Crowd control diminishing returns
- Area-of-effect abilities
- Reaction-based abilities
- Status interactions (e.g., poison + fire)
- Accuracy, evasion, or critical hit systems

---

## 7. MVP Validation Criteria

Combat content is considered MVP-complete when:
- All three status effects can be applied and resolved correctly
- Sleep reliably prevents actions and ends on damage
- Bolstered meaningfully changes time-to-kill
- Poison damage is visible and understandable in logs
- Combat outcomes are explainable to players

---

This document is considered **locked** for MVP unless explicitly revised.

