# MILESTONES BACKLOG
----

## Purpose
- `agent/MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `agent/MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `agent/MILESTONES.md` when they are opened for execution.

## Backlog Milestones

## Balance Simulation and Telemetry

**Status:** Planned
**Purpose:** Add repository-local tools for measuring combat, run, reward, and progression balance before and after gameplay tuning changes.

### Goals

- Build deterministic backend simulation commands for battles, runs, and progression goals.
- Produce machine-readable JSON plus concise human-readable summaries.
- Track win rate, battle length, HP pressure, reward throughput, item pacing, and time-to-goal percentiles.
- Make balance-affecting PRs easier to review with before/after evidence.
- Preserve manual playtesting as the source for clarity, fun, and player-feel validation.

### Exit Criteria

- A Docker-friendly simulation command can run large deterministic batches without the frontend.
- Simulation fixtures cover fresh account, starter squad, region-appropriate squad, and at least one overleveled comparison profile.
- Reports include p50, p75, p90, and worst-observed values for required progression goals.
- Documentation explains how to interpret simulation output and attach it to balance PRs.
