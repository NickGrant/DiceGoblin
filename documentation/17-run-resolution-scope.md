# Run Resolution Scope — MVP

This document defines the **authoritative rules for run failure, retry, abandonment, and post-run cleanup** for the Dice Goblins MVP. Any run outcome or persistence behavior not explicitly defined here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP run resolution system must:
- Encourage tactical attrition and decision-making
- Avoid hard punishment spirals
- Allow recovery from partial failure without trivializing encounters
- Clearly define when a run ends and what state is reset

Run resolution is intended to reinforce resource management within a run, not long-term meta progression.

---

## 2. Health & Status Persistence During a Run

### 2.1 Between-Encounter Persistence

- Units **do not automatically heal** between nodes in a run
- HP, cooldowns, and status effects persist across encounters
- Rest encounters are the primary means of recovery

This makes attrition a core consideration during exploration.

---

## 3. Encounter Failure & Retry Rules

### 3.1 Partial Defeat

If all units participating in an encounter are defeated **but the player has remaining undefeated units in their warband**:

- The encounter may be retried
- No additional energy cost is applied
- The player may attempt the encounter using only their remaining units

The encounter state is otherwise unchanged.

---

### 3.2 Total Defeat

If the player has **no remaining undefeated units** in their warband:

- The run immediately ends
- The run is considered failed

No further encounters may be attempted.

---

## 4. Run Abandonment

- The player may choose to abandon a run at any time
- Abandoning a run produces the **same outcome** as a failed run
- Energy spent to start the run is not refunded

Abandonment exists to prevent soft-locks or unwinnable states.

---

## 5. Run End Outcomes (Unified Resolution)

Whether a run ends due to:
- Total defeat
- Player abandonment

The following resolution steps occur.

### 5.1 Unit XP Adjustment

- Any unit that was defeated during the run:
  - Has its XP reset to the **minimum XP for its current level**
- Units that were not defeated retain their XP

No unit loses levels as a result of a failed run.

---

### 5.2 Unit State Cleanup

After run resolution, **all units**:
- Regain all missing HP
- Recharge any rechargeable abilities
- Lose all status effects

This cleanup occurs regardless of whether the run ended in failure or abandonment.

---

## 6. Successful Run Completion

Successful run completion:
- Uses the same post-run cleanup rules as failed runs
- Differs only in reward application and progression

State cleanup is consistent across all run endings.

---

## 7. Explicit Non-Goals

The MVP run resolution system does **not** include:
- Partial XP rewards on failure
- Permanent injury or death
- Item loss or durability systems
- Difficulty scaling based on failure count
- Alternate failure states or branching outcomes

---

## 8. MVP Validation Criteria

Run resolution is considered MVP-complete when:
- Attrition meaningfully affects encounter choices
- Retry rules are clear and exploitable only within intended bounds
- Failure never leaves units in a broken or unusable state
- Run endings are deterministic and easy to reason about

---

This document is considered **locked** for MVP unless explicitly revised.

