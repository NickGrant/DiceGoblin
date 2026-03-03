# Save & Resume Scope — MVP

Status: active  
Last Updated: 2026-03-02  
Owner: Systems Design + Backend  
Depends On: `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`


This document defines the **authoritative persistence, save, and resume rules** for the Dice Goblins MVP. It covers run continuity, combat log replay, and the minimum server-side state required to resume play at any time.

---

## 1. Design Goals

The MVP save/resume system must:
- Allow a player to safely leave and return to an active run at any time
- Ensure server-authoritative outcomes (no client re-simulation)
- Prevent duplicate reward claims and double-resolving combat
- Keep persistence minimal and data-driven

---

## 2. Active Run Constraint

- Each user may have **at most one active run** at a time.
- A run remains resumable until it reaches a terminal state:
  - `completed` (success)
  - `abandoned`
  - `failed` (terminal failure; see run resolution)

Starting a new run while one is active is disallowed.

---

## 3. Resume Semantics

### 3.1 Resume Guarantee

A player must be able to resume an active run after:
- Closing the browser
- Disconnecting
- Refreshing the page

Resume restores:
- Current map (nodes + edges + statuses)
- Current warband state within the run (HP, cooldowns, status effects, defeated flags)
- Cleared/available node progression
- Any completed battles with their stored logs

### 3.2 Resume Entry Point

On resume, the client loads:
- The active run state
- The map and node statuses

The player is returned to the map exploration UI. If there is an unresolved node, it is shown as available rather than forcing an immediate encounter.

---

## 4. State Authority & Determinism

### 4.1 Server-Authoritative Rule

- The backend is the single source of truth for run and combat state.
- The client is a renderer and controller.

### 4.2 Determinism Recommendation (MVP)

For MVP, use **server snapshot persistence** for run-scoped unit state and node progression.

Rationale:
- You already persist run maps (`run_nodes`, `run_edges`) and battles (`battles`, `battle_logs`).
- Snapshotting avoids needing to re-simulate exploration or reconstruct unit HP/status from logs.
- It is simpler, more robust, and easier to debug.

Seeds may still exist for debugging and reproducibility, but **resume does not depend on re-simulation**.

---

## 5. Combat Resolution & Replay

### 5.1 Single-Resolution Rule

- Combat is calculated server-side.
- Each fight (run node) may be resolved **exactly once** into a canonical outcome and log.

Enforcement:
- One `battles` row per (`run_id`, `node_id`).

### 5.2 Replay Rule

- The client may replay combat at any time.
- Replay uses the **stored battle log** only.
- No re-simulation occurs on the client.

### 5.3 Rewards & Idempotency

- Rewards are applied via a single claim step.
- Claiming must be idempotent:
  - A battle may be claimed at most once.
  - Repeated claim requests must not duplicate rewards.

---

## 6. Minimum Persisted State (MVP Contract)

The following state must be persisted to support full resume:

### 6.1 Run State
- Active run record (`region_runs`)
- Map graph and node status (`run_nodes`, `run_edges`)

### 6.2 Run-Scoped Unit State (Required)

Because HP, cooldowns, and status effects persist across encounters, MVP must persist **run-scoped unit state** separate from the permanent unit instance.

Required fields (conceptual):
- `run_id`
- `unit_instance_id`
- `current_hp`
- `cooldowns_json` (or recharge flags)
- `status_effects_json`
- `is_defeated` (within-run flag)

### 6.3 Battles
- Battle outcome and metadata (`battles`)
- Canonical combat log (`battle_logs`)
- Generated rewards to be claimed (`battle_rewards`) OR deterministic reward reconstruction rules

---

## 7. Explicit Non-Goals

The MVP save/resume system does **not** include:
- Offline progression
- Mid-combat reconnect to an in-progress simulation (combat resolves atomically server-side)
- Cross-device conflict resolution (beyond one active run)
- Client-side authoritative caching

---

## 8. MVP Validation Criteria

Save/resume is MVP-complete when:
- An active run can be resumed after refresh with no state loss
- Node statuses and map remain consistent across sessions
- HP/status/cooldowns persist correctly across encounters and across reconnects
- Each combat encounter is computed once and replayable from logs
- Claiming rewards is idempotent

---

This document is considered **locked** for MVP unless explicitly revised.

