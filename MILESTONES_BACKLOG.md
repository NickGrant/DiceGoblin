# MILESTONES BACKLOG
----

## Purpose
- `MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `MILESTONES.md` when they are opened for execution.

## Backlog Milestones

---
name: Milestone 6 - Playability and Stability Pass
status: not-started
execution_window: closed
is_current: no
issues:
  - Define Milestone 6 playability and stability release gate criteria
  - Add critical-path manual playtest script with evidence capture template
  - Harden frontend handling for partial API payloads and stale run state
  - Clean up documentation encoding artifacts in MVP systems and UX specs
  - Define player-friction severity rubric for playability triage
  - Add stale-state recovery validation checklist for Milestone 6 handoff
description: |
  Raise release-readiness confidence with verification coverage and stability hardening.
entry_criteria: |
  * Milestones 4 and 5 have shipped core UX/management behavior needed for stability assessment.
  * Milestone 6 release-gate criteria are documented and agreed.
exit_criteria: |
  * Stability/resilience and playtest evidence issues are complete and archived.
  * No unresolved high-priority Milestone 6 blockers remain.
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
entry_criteria: |
  * Milestones 2/3/4/5/6 scope has stabilized enough to avoid high churn during refactors.
  * API/scene contract docs are current enough to guide safe maintainability work.
exit_criteria: |
  * Maintainability/typing issues are complete and archived.
  * Contract-sensitive refactors pass regression verification.
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
entry_criteria: |
  * Target endpoint and lifecycle contracts for current implementation scope are defined.
  * Test database setup and harness expectations are documented.
exit_criteria: |
  * QA automation issues are complete and archived.
  * Required CI and integration verification gates are green.
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
entry_criteria: |
  * Core progression flow from Milestones 2-6 is functionally available for UX refinement.
  * Player journey and onboarding scope boundaries are documented.
exit_criteria: |
  * UX flow and sequencing issues are complete and archived.
  * Player-facing docs define end-to-end first-session clarity expectations.
