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
  - `execution_window` (`open` | `closed`)
  - `is_current` (`yes` | `no`)
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
execution_window: closed
is_current: no
issues:
  - <issue title>
description: |
  <scope and exit criteria summary>

## Active Milestones

---
name: Milestone 2 - Server-Side Battle Resolution
status: not-started
execution_window: closed
is_current: no
issues:
  - Replace placeholder run node resolution with deterministic combat engine integration
  - Implement non-placeholder reward and XP application on battle claim
description: |
  Deliver deterministic battle resolution and real claim/reward flow with idempotency guarantees.

---
name: Milestone 3 - Run Progression and Attrition
status: not-started
execution_window: closed
is_current: no
issues:
  - Persist run-scoped unit attrition state across encounters and resume
  - Implement run failure and abandonment resolution rules
  - Implement encounter retry flow for partial defeat scenarios
description: |
  Track run progression state, attrition persistence, and run-end behavior with explicit retry/failure handling.

---
name: Milestone 4 - Encounter Flow UI
status: not-started
execution_window: closed
is_current: no
issues:
description: |
  Complete encounter-flow UX surfaces and ensure end-to-end playable progression through UI.

---
name: Milestone 5 - Unit and Dice Management
status: not-started
execution_window: closed
is_current: no
issues:
description: |
  Improve unit/dice management depth, robustness, and client contract quality.

---
name: Milestone 6 - Playability and Stability Pass
status: not-started
execution_window: closed
is_current: no
issues:
description: |
  Raise release-readiness confidence with verification coverage and stability hardening.

---
name: Milestone 9 - Product and Backlog Governance
status: not-started
execution_window: closed
is_current: no
issues:
  - Establish post-QA milestone execution order and current milestone selection policy
  - Define milestone entry/exit criteria template and apply to active milestones
  - Add cross-milestone dependency map for run, combat, UX, and QA streams
  - Add issue dependency metadata for blocked-by relationships
  - Normalize active issue metadata fields to template standards
  - Resolve empty milestone placeholders or repopulate with scoped issues
  - Define backlog triage cadence and status-transition policy
  - Add automated lint/check for ISSUES and MILESTONES schema consistency
description: |
  Tighten roadmap governance, milestone clarity, and backlog hygiene so future delivery lanes stay executable and auditable.

---
name: Milestone 10 - Engineering Maintainability and Contracts
status: not-started
execution_window: closed
is_current: no
issues:
  - Remove unsafe any-casts from API client team mutation flow
  - Refactor WarbandManagementScene logic into testable state module
  - Consolidate repeated controller auth/csrf/service bootstrap patterns
  - Remove nested transaction ownership between controllers and repositories
  - Introduce stricter typed DTO mapping for profile squad payload assembly
description: |
  Reduce technical debt in client/server architecture and enforce stronger typing and maintainability patterns.

---
name: Milestone 11 - QA Coverage and Automation
status: not-started
execution_window: closed
is_current: no
issues:
  - Add backend endpoint contract tests for session/profile/current-run success envelopes
  - Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
  - Add CI workflow to run backend and frontend verification gates
  - Add frontend apiClient mutation flow tests for CSRF and error handling behavior
  - Add reusable test DB reset/migration utility for backend integration tests
description: |
  Expand automated verification depth and establish repeatable CI-backed confidence gates.

---
name: Milestone 12 - Combat Determinism and Progression Integrity
status: not-started
execution_window: closed
is_current: no
issues:
  - Add deterministic seed derivation strategy for node resolution
  - Add combat ability-handler regression test suite against canonical rules
  - Add progression invariants test suite for claim and run-state mutation
  - Add battle reward economy sanity validation fixtures
description: |
  Strengthen combat determinism and progression correctness before higher-level tuning and content expansion. Depends on placeholder-removal implementation in Milestone 2.

---
name: Milestone 13 - Player Experience and UX Flow
status: not-started
execution_window: closed
is_current: no
issues:
  - Add end-to-end player journey document for first-session flow
  - Add first-session onboarding and objective framing UX spec
  - Add encounter preview UX for node risk and reward expectations
  - Add post-battle progression summary UX contract
  - Define run failure and recovery UX states for partial and total defeat
  - Create player-value feature ordering model for upcoming milestones
description: |
  Improve player-facing flow quality, clarity, and engagement through focused UX and sequencing artifacts.
