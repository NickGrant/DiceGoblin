# ISSUES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive issue entries moved from `ISSUES.md`.
- Preserve prior context and resolution notes without bloating active execution context.

---
title: Add documentation archive lane and wire it into context exclusion rules
status: complete
priority: medium
description: |
  [Backlog Curator Follow-up] Introduce `documentation/archive/` and move superseded planning/decision docs out of active paths. Update `LLM_CONTEXT.md` to explicitly exclude the archive lane by default while allowing on-demand loading for historical context.
Resolution: Added `documentation/archive/README.md` and updated `LLM_CONTEXT.md` default exclusions to include `documentation/archive/` and deprecated `documentation/worklist.md`.

---
title: Normalize documentation encoding and style policy
status: complete
priority: medium
description: |
  [Backlog Curator Follow-up] Add explicit documentation standards (UTF-8, punctuation conventions, heading/metadata style, line wrapping) and apply a one-time cleanup pass to eliminate mojibake and formatting drift.
Resolution: Added `documentation/STYLE_GUIDE.md` and normalized high-impact active docs to remove common mojibake and standardize portable punctuation.

---
title: Add documentation changelog for contract and roadmap edits
status: complete
priority: low
description: |
  [Backlog Curator Follow-up] Create `documentation/CHANGELOG.md` to track major documentation deltas (API contracts, data model changes, scope shifts) so context changes are discoverable without scanning many files.
Resolution: Added `documentation/CHANGELOG.md` with initial entries covering recent governance and documentation-structure changes.

---
title: Align backend API contract doc with implemented endpoints and payloads
status: complete
priority: high
description: |
  [Role: Technical Product Manager] `documentation/01-architecture/03-backend-api-contracts.md` is out of sync with current backend implementation in `backend/public/index.php` and response payload shape in `backend/src/Services/ProfileService.php`. Mismatches include logout path (`/api/v1/auth/logout` vs `/auth/logout`), run endpoint naming (`/api/v1/runs/current` vs `/api/v1/runs/active`), profile key naming (`squads` vs `teams`), and team update path/payload (`PUT /api/v1/teams/:teamId` with cell strings vs `/formation` row/col payload). Update the contract doc and examples to prevent implementation drift.
Resolution: Updated API contract docs with implemented/planned endpoint labels, corrected auth/run/team endpoint paths, and aligned profile terminology toward `squads`.

---
title: Document warband management UX flow and constraints in system docs
status: complete
priority: medium
description: |
  [Role: Technical Product Manager] The newly implemented warband management interaction in `frontend/src/scenes/WarbandManagementScene.ts` is not represented in `documentation/03-ux/` and not clearly linked to `documentation/02-systems-mvp/` scope. Add a concise UX/system contract for selection, placement, clearing, save behavior, and error states so product and implementation remain aligned.
Resolution: Added dedicated warband UX/system contract doc and linked it from the main UX scope document and documentation index.

---
title: Migrate open roadmap milestones from deprecated worklist into executable backlog
status: complete
priority: high
description: |
  [Migration] `documentation/worklist.md` was deprecated in favor of `ISSUES.md`. Ensure remaining open roadmap scope is represented as active issue batches for:
  - Milestone 2: server-side battle resolution and rewards/XP completion
  - Milestone 3: run progression, attrition persistence, and run-end handling
  - Milestone 4: encounter flow UI (setup, replay, completion)
  - Milestone 5: unit and dice management depth
  - Milestone 6: playability and stability pass
  Close this migration issue once each milestone has concrete tracked child issues.
Resolution: Introduced `MILESTONES.md` with active milestones and linked issue-title collections for Milestones 2-6, plus `MILESTONES_ARCHIVE.md` for completed milestone history.

---
title: Align frontend scene/state contract docs with current implemented scene flow
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 7 - Documentation Integrity
description: |
  [Role: Technical Product Manager] `documentation/01-architecture/02-frontend-state-and-scene-contracts.md` no longer matches the implemented frontend scene set and current runtime flow. Update scene inventory, contracts, and transition matrix to reflect actual code paths and explicitly label planned scenes.
Resolution: Rewrote frontend scene/state contract documentation to match configured scenes, current transitions, and explicit planned-not-implemented scene boundaries.

---
title: Extend style-guide normalization pass to legacy overview and glossary docs
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 7 - Documentation Integrity
description: |
  [Role: Senior Developer] `documentation/00-overview/01-core-gameplay-loop.md` and `documentation/00-overview/02-glossary.md` still contain mojibake and stale punctuation despite style policy. Complete repository-wide markdown normalization to satisfy `documentation/STYLE_GUIDE.md`.
Resolution: Normalized legacy overview/glossary docs to UTF-8-clean text and refreshed core loop wording to match current terminology.

---
title: Add documentation QA checklist for code-contract drift checks
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 7 - Documentation Integrity
description: |
  [Role: QA Lead] Add a repeatable docs QA checklist (route map verification, payload key verification, status-label verification for Implemented vs Planned) and define when it must run to prevent silent drift between code and docs.
Resolution: Added `documentation/QA_CHECKLIST.md` with repeatable route/payload/scene/terminology verification steps and usage timing.

---
title: Clarify archive-loading policy for milestone history in AGENTS startup behavior
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 7 - Documentation Integrity
description: |
  [Role: Backlog Curator] `AGENTS.md` startup behavior explicitly mentions conditional loading of `ISSUES_ARCHIVE.md` but not `MILESTONES_ARCHIVE.md`. Clarify and align archive-loading policy so both issue and milestone historical context are handled consistently.
Resolution: Updated startup behavior to explicitly load `MILESTONES_ARCHIVE.md` only when historical milestone context is required.

---
title: Reconcile combat progression wording across encounter/combat/reward docs
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 7 - Documentation Integrity
description: |
  [Role: Combat Systems Reviewer] Review and reconcile progression wording across `documentation/02-systems-mvp/00-combat-system.md`, `03-encounter-scope.md`, `04-loot-and-drop-scope.md`, and `06-run-resolution-scope.md` so XP/reward semantics and mid-run squad-edit constraints are internally consistent.
Resolution: Reconciled progression boundary and XP timing language across combat/encounter/loot/run-resolution docs and aligned mid-run snapshot editing wording to squad terminology.

---
title: Enforce CSRF validation for POST /api/v1/runs
status: complete
priority: high
execution: active
ready: yes
milestone: unassigned
description: |
  `ApiController::createRun` currently has CSRF validation commented out even though mutating endpoints require CSRF by contract. Re-enable CSRF validation and ensure frontend request headers are aligned.
Resolution: Re-enabled CSRF validation in `ApiController::createRun` and confirmed the frontend run-creation request already sends `X-CSRF-Token`.

---
title: Enforce strict formation cell validation on team update endpoints
status: complete
priority: high
execution: active
ready: yes
milestone: unassigned
description: |
  [Role: Senior Developer] Team formation cells are currently validated only by non-empty length (`backend/src/Repositories/TeamRepository.php`), which allows invalid values outside the intended 3x3 grid. Restrict accepted cells to canonical values (A1..C3) in backend validation and return `validation_error` for out-of-range cells.
Resolution: Tightened formation cell validation in `TeamRepository::setFormationCell` to accept only canonical `A1..C3` coordinates and continue returning `validation_error` on invalid inputs.

---
title: Validate formation unit membership against submitted team unit_ids
status: complete
priority: high
execution: active
ready: yes
milestone: unassigned
description: |
  [Role: Senior Developer] `TeamController::updateTeam` persists `unit_ids` and formation separately, but there is no invariant check that every `formation.unit_instance_id` exists in the submitted membership. This can produce inconsistent team state. Add validation to reject formation entries for units not present in the target membership set.
Resolution: Added controller-side invariant enforcement so `updateTeam` rejects formation placements for units missing from submitted `unit_ids` with a validation error.

---
title: Establish backend API integration test harness (PHPUnit + fixtures)
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] `backend/` currently has no test runner or integration harness. Add a backend test foundation (composer dev deps, PHPUnit config/bootstrap, isolated test database setup, and fixture helpers) so endpoint regression tests can run deterministically.
Resolution: Added backend PHPUnit scaffolding (`composer.json`, `phpunit.xml.dist`, bootstrap), reusable transaction-based DB fixture test base, smoke fixture test, and test-runner documentation.

---
title: Establish frontend interaction test harness for Phaser scenes
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] `frontend/package.json` has no test script and the repo has no frontend test framework configured. Add a supported harness (e.g., Vitest + environment strategy for scene/component testing) and baseline test utilities for Phaser scene logic.
Resolution: Added Vitest-based frontend test harness (scripts, config, setup file), installed test dependencies, and verified the new `npm run test` command passes with a baseline smoke test.

---
title: Create repository testing strategy and verification matrix document
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Add `documentation/TESTING_STRATEGY.md` defining test tiers (unit/integration/contract/manual), ownership, execution cadence, minimum required checks per change type, and release-blocking criteria.
Resolution: Added `documentation/TESTING_STRATEGY.md` and linked it from `documentation/README.md` to codify test tiers, coverage expectations, cadence, and release-blocking criteria.

---
title: Resolve frontend TypeScript build failures in API response handling and scene typing
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  `npm run build` in `frontend/` currently fails due union-response narrowing issues (`ApiResponse<T>` `.data` access), invalid Phaser typing usage (`Phaser.Stage`), and strict-index typing errors in node placement utilities. Restore green TypeScript build by applying correct guards and type-safe usage.
Resolution: Added explicit API-response narrowing guards, corrected `HomeButton` constructor typing to `Phaser.Scene`, fixed strict index-safe swapping in `NodeList`, and verified `npm run build` now succeeds.

---
title: Add API contract regression tests for session/profile/run payload invariants
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Add regression coverage for `GET /api/v1/session`, `GET /api/v1/profile`, and `GET /api/v1/runs/current` to validate stable response envelope shape, required keys, and key naming used by frontend state bootstrap.
Resolution: Added runtime API contract validators for session/profile/current-run payloads, wired them into `apiClient`, and added Vitest regression coverage for accepted/rejected contract shapes.

---
title: Add backend integration tests for team create/activate/update with CSRF and ownership rules
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] There is no automated verification for new team mutation endpoints (`POST /api/v1/teams`, `POST /api/v1/teams/:teamId/activate`, `PUT /api/v1/teams/:teamId`). Add integration tests covering CSRF rejection, unauthorized access, invalid IDs, cross-user access denial, and successful state transitions.
Resolution: Added backend integration tests for team mutations at repository and controller layers, covering active-team transitions, ownership denial, and unauthorized/invalid-CSRF rejection for create/activate/update flows.

---
title: Add negative-path integration tests for run creation and mutation CSRF enforcement
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Backfill integration tests that assert mutating endpoints reject missing/invalid CSRF tokens and unauthorized sessions, starting with `POST /api/v1/runs` and extending to other mutation routes as shared helpers land.
Resolution: Added API controller mutation-security tests for `POST /api/v1/runs` to verify unauthenticated and invalid-CSRF requests are rejected with expected status codes/error contracts.

---
title: Add idempotency regression tests for run node resolve and battle claim
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Core progression safety depends on idempotency of `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` and `POST /api/v1/battles/:battleId/claim`. Add regression tests to verify repeated requests do not duplicate battle generation, rewards, or state mutation.
Resolution: Added end-to-end idempotency regression coverage that calls resolve/claim twice and verifies no duplicate battle, reward, or log rows; also fixed MariaDB JSON insert compatibility in battle log/reward repositories.
