# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Expanded Combat Stats

**Status:** Active
**Purpose:** Turn the newly added Precision and Resolve schema fields into real combat, tuning, and player-facing comparison behavior.

### Goals

- Make Precision influence eligible attack reliability and restrained critical-hit outcomes.
- Make Resolve influence harmful-status and control resistance.
- Author conservative Precision and Resolve values for current units and enemies.
- Surface expanded-stat outcomes clearly in battle logs and comparison UI.

### Current Code Context

The schema and DTO foundation landed in the roadmap foundation batch. Primary follow-up work should touch backend combat resolution, seeded unit/enemy stat JSON, combat logs, focused backend tests, and the frontend surfaces that compare unit or enemy stats.

### Exit Criteria

- Neutral Precision and Resolve values preserve the existing baseline feel.
- Non-neutral values create readable tactical differences in combat.
- Player and enemy seed data intentionally includes Precision and Resolve.
- Combat logs and UI make misses, critical hits, and resisted effects understandable.

### Related Issues

- ECS-002: Implement Precision and Resolve combat behavior
- ECS-003: Author Precision and Resolve balance data
- ECS-004: Surface expanded stats in player-facing comparisons
