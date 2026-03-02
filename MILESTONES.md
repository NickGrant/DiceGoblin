# MILESTONES FILE
----

## Purpose
- `MILESTONES.md` tracks active milestone groupings.
- A milestone is a named collection of issue titles plus a milestone status.
- Milestones are optional; issues do not need to belong to a milestone.

## How to Use
- Valid milestone statuses:
  - `not-started`
  - `in-progress`
  - `complete`
  - `blocked`
- Required milestone fields:
  - `name`
  - `status`
  - `issues` (list of issue titles from `ISSUES.md`)
- Milestone rule:
  - if a milestone has no issues, status must be `not-started`
- When a milestone is complete:
  - set `status: complete`
  - append `Resolution:` (1-2 sentences)
  - move the full milestone entry to `MILESTONES_ARCHIVE.md`
  - remove the completed entry from this active file

## Milestone Template
Use this block for new milestones:
---
name: <milestone name>
status: not-started
issues:
  - <issue title>
description: |
  <scope and exit criteria summary>

## Active Milestones

---
name: Milestone 2 - Server-Side Battle Resolution
status: not-started
issues:
  - Replace placeholder run node resolution with deterministic combat engine integration
  - Implement non-placeholder reward and XP application on battle claim
  - Add idempotency regression tests for run node resolve and battle claim
description: |
  Deliver deterministic battle resolution and real claim/reward flow with idempotency guarantees.

---
name: Milestone 3 - Run Progression and Attrition
status: not-started
issues: []
description: |
  Track run progression state, attrition persistence, and run-end behavior once related execution issues are added.

---
name: Milestone 4 - Encounter Flow UI
status: not-started
issues:
  - Add frontend interaction tests for warband placement and save behaviors
description: |
  Complete encounter-flow UX surfaces and ensure end-to-end playable progression through UI.

---
name: Milestone 5 - Unit and Dice Management
status: not-started
issues:
  - Remove unsafe any-casts from API client team mutation flow
description: |
  Improve unit/dice management depth, robustness, and client contract quality.

---
name: Milestone 6 - Playability and Stability Pass
status: not-started
issues:
  - Add backend integration tests for team create/activate/update with CSRF and ownership rules
description: |
  Raise release-readiness confidence with verification coverage and stability hardening.
