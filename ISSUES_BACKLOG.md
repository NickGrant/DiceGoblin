# ISSUES BACKLOG
----

## Purpose
- `ISSUES_BACKLOG.md` tracks deferred planning issues that are not part of the active execution lane.
- Keep `ISSUES.md` focused on active/current milestone execution context.
- Move items from this file into `ISSUES.md` when they become execution-ready.

## Issue Template
Use the same issue schema as `ISSUES.md`.

## Backlog Issues

---
title: Remove unsafe any-casts from API client team mutation flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `frontend/src/services/apiClient.ts` currently relies on repeated `as any` coercions for session CSRF access and team response typing. Introduce explicit response interfaces and typed helpers to prevent runtime-shape drift and improve compile-time guarantees.
---
title: Add end-to-end player journey document for first-session flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Technical Product Manager] Document the canonical player journey from session bootstrap to first run completion/claim, including failure states and expected UX touchpoints, to align sequencing decisions across roles.
---
title: Refactor WarbandManagementScene logic into testable state module
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `frontend/src/scenes/WarbandManagementScene.ts` contains significant state/interaction logic mixed with rendering orchestration. Extract placement/save state transitions into a pure module to reduce scene complexity and improve test coverage quality.
---
title: Consolidate repeated controller auth/csrf/service bootstrap patterns
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] API controllers repeatedly construct services and duplicate auth/CSRF handling patterns. Introduce shared helpers/base patterns to reduce drift and improve maintainability.
---
title: Remove nested transaction ownership between controllers and repositories
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] Several flows nest controller-level and repository-level transactions. Define clear transaction ownership boundaries to avoid hidden rollback/commit edge cases and simplify reasoning about persistence behavior.
---
title: Introduce stricter typed DTO mapping for profile squad payload assembly
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `ProfileService` and repository payload composition rely on broad array shapes. Add explicit DTO mapping/types for response assembly to reduce runtime shape drift.
---
title: Add backend endpoint contract tests for session/profile/current-run success envelopes
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Frontend contract validators exist, but backend-side regression tests for `GET /api/v1/session`, `GET /api/v1/profile`, and `GET /api/v1/runs/current` success envelopes are missing. Add endpoint-level tests to catch server payload drift at source.
---
title: Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Add an integration test that validates the full mutating lifecycle (`POST /runs` -> `POST /runs/:runId/nodes/:nodeId/resolve` -> `POST /battles/:battleId/claim`) including status transitions and reward/claim contract stability; coordinate expected assertions with Milestone 2 placeholder-to-real reward/combat changes.
---
title: Add CI workflow to run backend and frontend verification gates
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Verification currently relies on local manual execution. Add CI automation for `composer test`, frontend tests, and frontend build to enforce consistent regression gates.
---
title: Add frontend apiClient mutation flow tests for CSRF and error handling behavior
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Add tests for `createTeam/activateTeam/updateTeam/createRun` mutation flows to validate CSRF sourcing behavior and error propagation semantics in `apiClient`.
---
title: Add reusable test DB reset/migration utility for backend integration tests
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Integration tests currently assume schema is preloaded manually. Add a repeatable utility for initializing/resetting test DB state from versioned schema artifacts.
---
title: Add deterministic seed derivation strategy for node resolution
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Combat Systems Reviewer] `RunNodeController` currently uses `random_int` for battle seed generation. Define deterministic seed derivation tied to run/node/user context and add regression tests for reproducibility.
---
title: Add combat ability-handler regression test suite against canonical rules
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Combat Systems Reviewer] Add focused regression tests for active/passive ability handlers to prevent hidden combat-rules drift as deterministic engine integration proceeds.
---
title: Add progression invariants test suite for claim and run-state mutation
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Combat Systems Reviewer] Add invariant tests verifying XP/reward applications, run-unit state mutation, and no-duplication guarantees across repeated claim/resolve requests.
---
title: Add battle reward economy sanity validation fixtures
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Combat Systems Reviewer] Add fixture-based checks for reward output bounds and consistency to detect extreme or malformed reward payloads during progression work.
---
title: Add first-session onboarding and objective framing UX spec
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define onboarding messaging and immediate goals from first login through first run start to reduce confusion and increase early-session engagement.
---
title: Add encounter preview UX for node risk and reward expectations
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Add UX support for encounter preview information (node intent, risk/reward hints) before commitment to improve perceived agency and flow clarity.
---
title: Add post-battle progression summary UX contract
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define post-battle summary UX (XP gains, rewards, squad changes, next-step prompt) to strengthen player feedback loops.
---
title: Define run failure and recovery UX states for partial and total defeat
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Specify UX flows for partial defeat retry and total failure outcomes so recovery behavior feels intentional and understandable.
---
title: Create player-value feature ordering model for upcoming milestones
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define a player-value prioritization rubric (clarity, engagement, retention impact) to guide sequencing decisions across non-combat and combat-adjacent features.
---
title: Define Milestone 6 playability and stability release gate criteria
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Technical Product Manager] Create release-gate criteria for Milestone 6 covering required automated checks, required manual checks, and blocker thresholds so stability sign-off is objective.
---
title: Add critical-path manual playtest script with evidence capture template
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Add a repeatable manual playtest script for first-session through run completion/failure, including pass/fail recording format and defect capture fields to support milestone handoff decisions.
---
title: Harden frontend handling for partial API payloads and stale run state
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] Add resilience handling and explicit user-facing fallback states for partial/late API payloads in active run scenes so transient backend inconsistency does not cascade into broken UI flow.
---
title: Clean up documentation encoding artifacts in MVP systems and UX specs
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Backlog Curator] Remove mojibake/encoding artifacts and normalize UTF-8 rendering across active gameplay docs (at minimum encounter scope, dice system, units/progression, and UX scope) to reduce ambiguity and keep copied terms/contracts stable.
---
title: Define player-friction severity rubric for playability triage
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define a severity rubric for UX/playability defects (confusion, pacing drag, feedback clarity, dead-end flow) to standardize polish prioritization during Milestone 6.
---
title: Add stale-state recovery validation checklist for Milestone 6 handoff
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Define and execute a focused validation checklist for stale run-state and partial payload recovery scenarios to verify resilience changes before Milestone 6 sign-off.
---
title: Reduce frontend production bundle size via scene-level code splitting
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-03
updated: 2026-03-03
description: |
  [Role: Senior Developer] Frontend build currently emits a large primary bundle (~1.5 MB minified warning). Introduce scene-level lazy loading and/or manual chunking strategy to lower initial payload size and keep build warnings actionable.
---
title: Document and enforce frontend build-artifact policy for `frontend/dist`
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-03
updated: 2026-03-03
description: |
  [Role: Technical Product Manager] Define whether production bundle artifacts must be committed or generated in CI/release flow, then codify the rule in docs and checks to avoid accidental drift between source and shipped assets.
