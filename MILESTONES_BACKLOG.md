# MILESTONES BACKLOG
----

## Purpose
- `MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `MILESTONES.md` when they are opened for execution.

## Backlog Milestones

---
name: Milestone 16 - Frontend Gameplay Completion
status: not-started
execution_window: closed
is_current: no
issues:
  - Implement Rest Management scene with open-edit-finalize workflow
  - Implement embedded promotion flow in Unit Details for between-run and rest contexts
  - Implement end-of-run summary scene shell for completed failed and abandoned outcomes
  - Implement distinct exit-node visuals and locked-path affordance on run map
  - Wire Dice Inventory screen into active-rest management flow
description: |
  Complete missing frontend gameplay interfaces and flow wiring so players can execute full run lifecycle interactions with clear summaries and node affordances.
entry_criteria: |
  * UX and architecture docs define rest management, run-end summary, and exit-node interaction rules.
  * Backend contracts for rest workflow and exit completion are documented.
exit_criteria: |
  * Frontend supports rest management, embedded promotion, and run-end summary behavior.
  * Exit node visuals/locking cues are implemented and validated.
  * All milestone issues are complete and archived.
---
name: Milestone 15 - Backend Gameplay Completion
status: not-started
execution_window: closed
is_current: no
issues:
  - Implement exit-node completion flow and completed run transition
  - Implement run-end cleanup for completed status while preserving earned XP
  - Implement rest workflow endpoints with transactional snapshot and squad sync
  - Implement backend auto-level application at rest finalize and run cleanup
  - Implement manual promotion endpoint with primary-secondary consume semantics
  - Expose dice equip and unequip gameplay endpoints with rest-only run constraints
description: |
  Complete missing backend gameplay functionality so the intended MVP loop can run end-to-end with authoritative state transitions.
entry_criteria: |
  * Gameplay contracts for run completion, rest workflow, auto-level, promotion, and equipment restrictions are documented.
  * Milestone 6 stability documentation is complete.
exit_criteria: |
  * Backend supports completed-run transition via exit path and terminal cleanup invariants.
  * Rest, promotion, and equipment APIs enforce active-run and ownership constraints.
  * All milestone issues are complete and archived.
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
