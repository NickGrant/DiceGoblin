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
milestone: Milestone 5 - Unit and Dice Management
description: |
  [Role: Senior Developer] `frontend/src/services/apiClient.ts` currently relies on repeated `as any` coercions for session CSRF access and team response typing. Introduce explicit response interfaces and typed helpers to prevent runtime-shape drift and improve compile-time guarantees.

---
title: Add backend integration tests for team create/activate/update with CSRF and ownership rules
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] There is no automated verification for new team mutation endpoints (`POST /api/v1/teams`, `POST /api/v1/teams/:teamId/activate`, `PUT /api/v1/teams/:teamId`). Add integration tests covering CSRF rejection, unauthorized access, invalid IDs, cross-user access denial, and successful state transitions.

---
title: Add frontend interaction tests for warband placement and save behaviors
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Warband scene behavior in `frontend/src/scenes/WarbandManagementScene.ts` currently lacks automated coverage. Add tests for select/place/clear flows, button enabled states, and error/success toast handling around save and create-team actions.

---
title: Add idempotency regression tests for run node resolve and battle claim
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Core progression safety depends on idempotency of `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` and `POST /api/v1/battles/:battleId/claim`. Add regression tests to verify repeated requests do not duplicate battle generation, rewards, or state mutation.

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
title: Rename team terminology to squads across frontend, backend, and docs
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: unassigned
description: |
  Preferred product terminology is `squads`. Audit and update inconsistent references to `teams` where safe, preserving route/database naming where backward compatibility requires it. Ensure API/docs/client types clearly communicate canonical `squads` wording.

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
title: Add API contract regression tests for session/profile/run payload invariants
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Add regression coverage for `GET /api/v1/session`, `GET /api/v1/profile`, and `GET /api/v1/runs/current` to validate stable response envelope shape, required keys, and key naming used by frontend state bootstrap.

---
title: Add negative-path integration tests for run creation and mutation CSRF enforcement
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Backfill integration tests that assert mutating endpoints reject missing/invalid CSRF tokens and unauthorized sessions, starting with `POST /api/v1/runs` and extending to other mutation routes as shared helpers land.

### Documentation


