# ISSUES FILE
----

## Purpose
- `ISSUES.md` tracks active work only.
- Keep this file small and current for day-to-day execution context.
- Historical completed issues are moved to `ISSUES_ARCHIVE.md`.

## How to Use
- Valid statuses for active items:
  - `unstarted`
  - `in-progress`
  - `reopened`
  - `blocked`
- Valid priorities for active items:
  - `low`
  - `medium`
  - `high`
- When an issue is resolved:
  - set `status: complete`
  - append `Resolution:` (1-2 sentences)
  - move the full completed entry to `ISSUES_ARCHIVE.md`
  - remove the completed entry from this active file

## Issue Template
Use this block for new issues:
---
title: <short summary>
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
created: 2026-03-02
updated: 2026-03-02
description: |
  <problem, impact, and expected outcome>

## Active Issues

### Functional

---
title: Remove unsafe any-casts from API client team mutation flow
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 10 - Engineering Maintainability and Contracts
description: |
  [Role: Senior Developer] `frontend/src/services/apiClient.ts` currently relies on repeated `as any` coercions for session CSRF access and team response typing. Introduce explicit response interfaces and typed helpers to prevent runtime-shape drift and improve compile-time guarantees.

---
title: Replace placeholder run node resolution with deterministic combat engine integration
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 2 - Server-Side Battle Resolution
description: |
  [Role: Combat Systems Reviewer] `backend/src/Controllers/RunNodeController.php` currently uses placeholder battle resolution values and random seed generation instead of actual deterministic simulation logic. Integrate the combat engine pipeline and ensure outcomes/logs are generated from canonical unit, ability, and RNG rules.

---
title: Implement non-placeholder reward and XP application on battle claim
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 2 - Server-Side Battle Resolution
description: |
  [Role: Combat Systems Reviewer] `backend/src/Controllers/BattleController.php` returns placeholder arrays for `updated_run_unit_state`, XP application details, and updated units. Implement real reward/XP application tied to run-scoped unit state and battle outcomes to satisfy progression invariants.

---
title: Persist run-scoped unit attrition state across encounters and resume
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 3 - Run Progression and Attrition
description: |
  Implement and persist run-scoped unit state (HP, cooldowns, status effects, defeated flags) so attrition behavior matches `documentation/02-systems-mvp/05-save-and-resume-scope.md` and survives reconnect/resume.

---
title: Implement run failure and abandonment resolution rules
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 3 - Run Progression and Attrition
description: |
  Implement terminal run resolution (total defeat and abandon) including XP reset rules for defeated units and post-run cleanup/recovery rules defined in `documentation/02-systems-mvp/06-run-resolution-scope.md`.

---
title: Implement encounter retry flow for partial defeat scenarios
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 3 - Run Progression and Attrition
description: |
  Support retrying encounters after partial defeat using remaining undefeated run units, with no extra energy cost, consistent with run resolution scope documentation.

---
title: Establish post-QA milestone execution order and current milestone selection policy
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] After Milestone 8 completion there is no explicit current/open delivery lane. Define and document milestone execution order, opening rules, and criteria for setting exactly one current milestone to eliminate planning ambiguity.

---
title: Define milestone entry/exit criteria template and apply to active milestones
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] Active milestones list scope but do not consistently define measurable entry/exit gates. Add a concise criteria template and apply it to active milestones to improve delivery predictability.

---
title: Add end-to-end player journey document for first-session flow
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 13 - Player Experience and UX Flow
description: |
  [Role: Technical Product Manager] Document the canonical player journey from session bootstrap to first run completion/claim, including failure states and expected UX touchpoints, to align sequencing decisions across roles.

---
title: Add cross-milestone dependency map for run, combat, UX, and QA streams
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] Capture dependency links between Milestones 2/3/10/11/13 so implementation order and blockers are explicit before new feature execution.

---
title: Add issue dependency metadata for blocked-by relationships
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Cross-Role Reconciliation] Add optional issue-level dependency metadata (`blocked_by` and `enables`) to make cross-milestone sequencing explicit and reduce hidden ordering risk during multi-role planning passes.

---
title: Refactor WarbandManagementScene logic into testable state module
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 10 - Engineering Maintainability and Contracts
description: |
  [Role: Senior Developer] `frontend/src/scenes/WarbandManagementScene.ts` contains significant state/interaction logic mixed with rendering orchestration. Extract placement/save state transitions into a pure module to reduce scene complexity and improve test coverage quality.

---
title: Consolidate repeated controller auth/csrf/service bootstrap patterns
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 10 - Engineering Maintainability and Contracts
description: |
  [Role: Senior Developer] API controllers repeatedly construct services and duplicate auth/CSRF handling patterns. Introduce shared helpers/base patterns to reduce drift and improve maintainability.

---
title: Remove nested transaction ownership between controllers and repositories
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 10 - Engineering Maintainability and Contracts
description: |
  [Role: Senior Developer] Several flows nest controller-level and repository-level transactions. Define clear transaction ownership boundaries to avoid hidden rollback/commit edge cases and simplify reasoning about persistence behavior.

---
title: Introduce stricter typed DTO mapping for profile squad payload assembly
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 10 - Engineering Maintainability and Contracts
description: |
  [Role: Senior Developer] `ProfileService` and repository payload composition rely on broad array shapes. Add explicit DTO mapping/types for response assembly to reduce runtime shape drift.

---
title: Add backend endpoint contract tests for session/profile/current-run success envelopes
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 11 - QA Coverage and Automation
description: |
  [Role: QA Lead] Frontend contract validators exist, but backend-side regression tests for `GET /api/v1/session`, `GET /api/v1/profile`, and `GET /api/v1/runs/current` success envelopes are missing. Add endpoint-level tests to catch server payload drift at source.

---
title: Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 11 - QA Coverage and Automation
description: |
  [Role: QA Lead] Add an integration test that validates the full mutating lifecycle (`POST /runs` -> `POST /runs/:runId/nodes/:nodeId/resolve` -> `POST /battles/:battleId/claim`) including status transitions and reward/claim contract stability; coordinate expected assertions with Milestone 2 placeholder-to-real reward/combat changes.

---
title: Add CI workflow to run backend and frontend verification gates
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 11 - QA Coverage and Automation
description: |
  [Role: QA Lead] Verification currently relies on local manual execution. Add CI automation for `composer test`, frontend tests, and frontend build to enforce consistent regression gates.

---
title: Add frontend apiClient mutation flow tests for CSRF and error handling behavior
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 11 - QA Coverage and Automation
description: |
  [Role: QA Lead] Add tests for `createTeam/activateTeam/updateTeam/createRun` mutation flows to validate CSRF sourcing behavior and error propagation semantics in `apiClient`.

---
title: Add reusable test DB reset/migration utility for backend integration tests
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 11 - QA Coverage and Automation
description: |
  [Role: QA Lead] Integration tests currently assume schema is preloaded manually. Add a repeatable utility for initializing/resetting test DB state from versioned schema artifacts.

---
title: Normalize active issue metadata fields to template standards
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Backlog Curator] Active issue entries are inconsistent with template metadata (`owner`, `created`, `updated`). Align active entries with standard fields for easier auditability.

---
title: Resolve empty milestone placeholders or repopulate with scoped issues
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Backlog Curator] Active milestones 4 and 6 currently contain no issues and add planning noise. Either archive them as dormant placeholders or repopulate with concrete scoped issues.

---
title: Define backlog triage cadence and status-transition policy
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Backlog Curator] Add an explicit triage cadence and status transition policy (`unstarted`/`in-progress`/`blocked`/`reopened`) to keep issue state trustworthy over time.

---
title: Add automated lint/check for ISSUES and MILESTONES schema consistency
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Backlog Curator] Add a lightweight validation script to ensure issue/milestone required fields and allowed enums stay consistent.

---
title: Add deterministic seed derivation strategy for node resolution
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 12 - Combat Determinism and Progression Integrity
description: |
  [Role: Combat Systems Reviewer] `RunNodeController` currently uses `random_int` for battle seed generation. Define deterministic seed derivation tied to run/node/user context and add regression tests for reproducibility.

---
title: Add combat ability-handler regression test suite against canonical rules
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 12 - Combat Determinism and Progression Integrity
description: |
  [Role: Combat Systems Reviewer] Add focused regression tests for active/passive ability handlers to prevent hidden combat-rules drift as deterministic engine integration proceeds.

---
title: Add progression invariants test suite for claim and run-state mutation
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 12 - Combat Determinism and Progression Integrity
description: |
  [Role: Combat Systems Reviewer] Add invariant tests verifying XP/reward applications, run-unit state mutation, and no-duplication guarantees across repeated claim/resolve requests.

---
title: Add battle reward economy sanity validation fixtures
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 12 - Combat Determinism and Progression Integrity
description: |
  [Role: Combat Systems Reviewer] Add fixture-based checks for reward output bounds and consistency to detect extreme or malformed reward payloads during progression work.

---
title: Add first-session onboarding and objective framing UX spec
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 13 - Player Experience and UX Flow
description: |
  [Role: Game Designer] Define onboarding messaging and immediate goals from first login through first run start to reduce confusion and increase early-session engagement.

---
title: Add encounter preview UX for node risk and reward expectations
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 13 - Player Experience and UX Flow
description: |
  [Role: Game Designer] Add UX support for encounter preview information (node intent, risk/reward hints) before commitment to improve perceived agency and flow clarity.

---
title: Add post-battle progression summary UX contract
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 13 - Player Experience and UX Flow
description: |
  [Role: Game Designer] Define post-battle summary UX (XP gains, rewards, squad changes, next-step prompt) to strengthen player feedback loops.

---
title: Define run failure and recovery UX states for partial and total defeat
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 13 - Player Experience and UX Flow
description: |
  [Role: Game Designer] Specify UX flows for partial defeat retry and total failure outcomes so recovery behavior feels intentional and understandable.

---
title: Create player-value feature ordering model for upcoming milestones
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 13 - Player Experience and UX Flow
description: |
  [Role: Game Designer] Define a player-value prioritization rubric (clarity, engagement, retention impact) to guide sequencing decisions across non-combat and combat-adjacent features.

### Documentation


