# Run Resolution Scope — Alpha Launch

Status: active  
Last Updated: 2026-03-02  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/02-systems-mvp/05-save-and-resume-scope.md`


This document defines the **authoritative rules for run failure, retry, abandonment, and post-run cleanup** for the Dice Goblins alpha launch. Any run outcome or persistence behavior not explicitly defined here is **out of scope** for the alpha launch.

---

## 1. Design Goals

The alpha-launch run resolution system must:
- Encourage tactical attrition and decision-making
- Avoid hard punishment spirals
- Clearly define when a run ends and what state is reset

Run resolution is intended to reinforce resource management within a run, not long-term meta progression.

---

## 2. Health & Status Persistence During a Run

### 2.1 Between-Encounter Persistence

- Units **do not automatically heal** between nodes in a run
- HP persists across encounters and is applied as the starting HP for the next combat
- Cooldowns and status effects are cleared after each combat resolves
- Rest encounters are the primary means of recovery

On rest finalize:
- all run-snapshot units are fully healed,
- defeated flags are cleared,
- cooldowns and status effects are reset.

This makes attrition a core consideration during exploration.

---

## 3. Encounter Failure Rules

For current alpha-launch behavior, any combat or boss encounter outcome of `defeat` is treated as terminal:

- The run immediately ends
- The run is considered failed
- No encounter retries are allowed within that run

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
- Combat/boss defeat
- Player abandonment

The following resolution steps occur.

### 5.1 Unit XP Adjustment
- Any unit that was defeated during the run:
  - Has its XP reset to 0 (XP is progress-within-current-level)
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

The alpha-launch run resolution system does **not** include:
- Partial XP rewards on failure
- Permanent injury or death
- Item loss or durability systems
- Difficulty scaling based on failure count
- Alternate failure states or branching outcomes

---

## 8. Alpha Launch Validation Criteria

Run resolution is considered alpha-launch complete when:
- Attrition meaningfully affects encounter choices
- Retry rules are clear and exploitable only within intended bounds
- Failure never leaves units in a broken or unusable state
- Run endings are deterministic and easy to reason about

---

This document is considered **locked** for the alpha launch unless explicitly revised.
