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
owner: unassigned
milestone: unassigned
created: 2026-03-02
updated: 2026-03-02
description: |
  <problem, impact, and expected outcome>

## Active Issues

### Functional

---
title: Align backend API contract doc with implemented endpoints and payloads
status: unstarted
priority: high
description: |
  [Role: Technical Product Manager] `documentation/01-architecture/03-backend-api-contracts.md` is out of sync with current backend implementation in `backend/public/index.php` and response payload shape in `backend/src/Services/ProfileService.php`. Mismatches include logout path (`/api/v1/auth/logout` vs `/auth/logout`), run endpoint naming (`/api/v1/runs/current` vs `/api/v1/runs/active`), profile key naming (`squads` vs `teams`), and team update path/payload (`PUT /api/v1/teams/:teamId` with cell strings vs `/formation` row/col payload). Update the contract doc and examples to prevent implementation drift.

---
title: Document warband management UX flow and constraints in system docs
status: unstarted
priority: medium
description: |
  [Role: Technical Product Manager] The newly implemented warband management interaction in `frontend/src/scenes/WarbandManagementScene.ts` is not represented in `documentation/03-ux/` and not clearly linked to `documentation/02-systems-mvp/` scope. Add a concise UX/system contract for selection, placement, clearing, save behavior, and error states so product and implementation remain aligned.

---
title: Enforce strict formation cell validation on team update endpoints
status: unstarted
priority: high
description: |
  [Role: Senior Developer] Team formation cells are currently validated only by non-empty length (`backend/src/Repositories/TeamRepository.php`), which allows invalid values outside the intended 3x3 grid. Restrict accepted cells to canonical values (A1..C3) in backend validation and return `validation_error` for out-of-range cells.

---
title: Validate formation unit membership against submitted team unit_ids
status: unstarted
priority: high
description: |
  [Role: Senior Developer] `TeamController::updateTeam` persists `unit_ids` and formation separately, but there is no invariant check that every `formation.unit_instance_id` exists in the submitted membership. This can produce inconsistent team state. Add validation to reject formation entries for units not present in the target membership set.

---
title: Remove unsafe any-casts from API client team mutation flow
status: unstarted
priority: medium
description: |
  [Role: Senior Developer] `frontend/src/services/apiClient.ts` currently relies on repeated `as any` coercions for session CSRF access and team response typing. Introduce explicit response interfaces and typed helpers to prevent runtime-shape drift and improve compile-time guarantees.

---
title: Add backend integration tests for team create/activate/update with CSRF and ownership rules
status: unstarted
priority: high
description: |
  [Role: QA Lead] There is no automated verification for new team mutation endpoints (`POST /api/v1/teams`, `POST /api/v1/teams/:teamId/activate`, `PUT /api/v1/teams/:teamId`). Add integration tests covering CSRF rejection, unauthorized access, invalid IDs, cross-user access denial, and successful state transitions.

---
title: Add frontend interaction tests for warband placement and save behaviors
status: unstarted
priority: medium
description: |
  [Role: QA Lead] Warband scene behavior in `frontend/src/scenes/WarbandManagementScene.ts` currently lacks automated coverage. Add tests for select/place/clear flows, button enabled states, and error/success toast handling around save and create-team actions.

---
title: Add idempotency regression tests for run node resolve and battle claim
status: unstarted
priority: high
description: |
  [Role: QA Lead] Core progression safety depends on idempotency of `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` and `POST /api/v1/battles/:battleId/claim`. Add regression tests to verify repeated requests do not duplicate battle generation, rewards, or state mutation.

---
title: Migrate open roadmap milestones from deprecated worklist into executable backlog
status: unstarted
priority: high
description: |
  [Migration] `documentation/worklist.md` was deprecated in favor of `ISSUES.md`. Ensure remaining open roadmap scope is represented as active issue batches for:
  - Milestone 2: server-side battle resolution and rewards/XP completion
  - Milestone 3: run progression, attrition persistence, and run-end handling
  - Milestone 4: encounter flow UI (setup, replay, completion)
  - Milestone 5: unit and dice management depth
  - Milestone 6: playability and stability pass
  Close this migration issue once each milestone has concrete tracked child issues.

---
title: Replace placeholder run node resolution with deterministic combat engine integration
status: unstarted
priority: high
description: |
  [Role: Combat Systems Reviewer] `backend/src/Controllers/RunNodeController.php` currently uses placeholder battle resolution values and random seed generation instead of actual deterministic simulation logic. Integrate the combat engine pipeline and ensure outcomes/logs are generated from canonical unit, ability, and RNG rules.

---
title: Implement non-placeholder reward and XP application on battle claim
status: unstarted
priority: high
description: |
  [Role: Combat Systems Reviewer] `backend/src/Controllers/BattleController.php` returns placeholder arrays for `updated_run_unit_state`, XP application details, and updated units. Implement real reward/XP application tied to run-scoped unit state and battle outcomes to satisfy progression invariants.

### Documentation

