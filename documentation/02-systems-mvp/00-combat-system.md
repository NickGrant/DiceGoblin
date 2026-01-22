# Combat System & Content — MVP (Authoritative)

This document is the **authoritative specification** for MVP combat rules **and** combat content scope.  
Numeric constants and formulas are defined in **Combat Math & Modifiers — MVP**.

Any combat mechanic, status effect, or ability behavior not explicitly defined here (or in Combat Math & Modifiers) is **out of scope** for MVP.

---

## 1. Design Goals

The MVP combat system must:
- Validate automated, deterministic combat resolution
- Exercise frontline vs backline positioning
- Support build expression through dice and abilities
- Remain readable through logs and UI

---

## 2. Combat Grid

- Each side has its own fixed **3x3 grid** (Player, Enemy, Neutral if applicable)
- **No hard blocking**; targeting is not prevented by occupancy (“soft screening”)
- An occupancy grid is fully derivable from unit positions

### 2.1 Positions
- Positions are represented as `{ r: 0|1|2, c: 0|1|2 }`
- “Front row” and “back row” are defined in **Combat Math & Modifiers — MVP** to prevent orientation ambiguity

---

## 3. Positioning Effects (Rules)

Positioning provides systematic advantages/disadvantages:

- **Front Row**
  - Increased melee damage dealt
  - Increased damage received

- **Back Row**
  - Reduced melee damage taken
  - Ranged/special bonuses are allowed only if explicitly defined by abilities (none are implied)

Numeric multipliers are defined in **Combat Math & Modifiers — MVP**.

---

## 4. Round / Tick / Speed

### 4.1 Timeline
- **Speed** range: `1..20`
- A **round** is exactly **20 ticks**
- Tick index is **1..20** (inclusive)

### 4.2 Trigger Rule
- Abilities and some status effects have a **speed** value
- An item triggers on a tick when:

`tick % speed === 0`

Examples:
- Speed 4 triggers at ticks 4, 8, 12, 16, 20 → **5 times per round**
- Speed 11 triggers at tick 11 only → **1 time per round**
- Lower speed means **more frequent** actions

### 4.3 Multi-Trigger / Same Tick
- Multiple actions can execute on the same tick
- If a unit dies, it performs **no further actions**, including actions later in the same tick

---

## 5. Tick Processing Order (Per Tick)

For each tick, phases execute in this exact order:

1) Player Status Phase  
2) Enemy Status Phase  
3) Neutral Status Phase  
4) Player Action Phase  
5) Enemy Action Phase  
6) Neutral Action Phase  

---

## 6. Unit Action Order

Within an Action Phase:
- Units act in ascending `unitId` order
- If multiple actions occur on the same tick, they execute by their `order` property (ascending)

---

## 7. Automation & Determinism

- No player-controlled movement during combat in MVP
- Units decide actions based on internal logic
- Combat resolution is deterministic aside from dice rolls (which must be reproducible via seed + roll index in logs)

---

## 8. Ability Scope (MVP)

### 8.1 Abilities per Unit Type
Each unit type supports:
- **2 Active Abilities**
  - One must be the unit’s base attack
  - One must be a specialty action
- **Up to 2 Passive Abilities**

No unit may exceed **4 total abilities** in MVP.

### 8.2 Enabled Ability Categories
- Direct damage abilities
- Defensive/self-buff abilities
- Simple debuff application abilities

### 8.3 Explicitly Excluded
- Summoning
- Terrain modification
- Ability chains/combos
- Multi-step or choice-driven abilities
- Area-of-effect abilities
- Reaction-based abilities

### 8.4 Ability Data Contract (MVP-Minimum)
- `diceCost: number` (0 or greater)
- `speed: number` (1–20)
- `type: "active" | "passive"`
- `order: number`

Optional properties (recommended for clarity):
- `tags: string[]` (e.g., `["melee"]`, `["ranged"]`, `["special"]`)
- `targeting: ...` (single-target rules live with the ability definition)

---

## 9. Status Effects (Closed List)

Exactly **three** status effects exist in MVP.

### 9.1 Poison (Damage-over-Time Debuff)
Rules:
- Deals damage at a fixed interval
- Triggers when `tick % statusSpeed === 0` during the Status Phase
- Damage value is deterministic based on source stats (see Combat Math & Modifiers)
- Does not stack; on re-apply, duration becomes the **max** of current remaining and new duration

### 9.2 Bolstered (Defensive Buff)
Rules:
- Increases Defense by a percentage (see Combat Math & Modifiers)
- Does not stack; strongest instance applies
- Duration-based

### 9.3 Sleep (Control Debuff / Disable)
Rules:
- Prevents the affected unit from acting
- Ends immediately when the unit takes damage
- Also ends when duration expires
- Does not stack; on re-apply, duration becomes the **max** of current remaining and new duration
- Unit does **not** participate in the tick where Sleep ends (see Combat Math & Modifiers for timing)

---

## 10. Global Status Rules (MVP)

- Evaluated server-side
- All applications, ticks, and removals are logged
- Status effects may not modify dice behavior in MVP
- No resistance, immunity, or cleansing systems exist in MVP
- Each status application includes:
  - `statusSpeed`
  - `durationRounds`
- Status effects can trigger multiple times per round based on speed

---

## 11. MVP Validation Criteria

Combat is MVP-complete when:
- All three statuses can be applied and resolved correctly
- Sleep reliably prevents actions and ends on damage
- Bolstered meaningfully changes time-to-kill
- Poison damage is visible and understandable in logs
- Combat outcomes are explainable to players

---

This document is considered **locked** for MVP unless explicitly revised.
