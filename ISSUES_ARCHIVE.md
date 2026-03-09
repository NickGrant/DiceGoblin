# ISSUES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive issue entries moved from `ISSUES.md`.
- Preserve prior context and resolution notes without bloating active execution context.

---
title: Implement global Bottom Command Strip and remove top-corner HUD
status: complete
priority: high
execution: active
ready: yes
description: Build a shared Bottom Command Strip component using `raw-assets/elements/base_bar.png`, `raw-assets/icons/icon_warband.png`, `raw-assets/icons/icon_inventory.png`, and `raw-assets/icons/icon_logout.png`; render it at bottom-center across scenes currently using `HudPanel` and remove top-corner HUD component usage.
Resolution: Added a reusable `BottomCommandStrip` component, wired it into all scenes that previously mounted top-corner HUD widgets, removed corner component usage from scene composition, and loaded/copy-mapped the new bar/logout assets into runtime pack paths.

---
title: Implement rest workflow endpoints with transactional snapshot and squad sync
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement `rest/open`, `rest/state`, and `rest/finalize` APIs so rest nodes support non-consuming edit sessions and atomic dual-write updates to run snapshot + saved squad state.
Resolution: Added `rest/open`, `rest/state`, and `rest/finalize` endpoints with run/node ownership validation, transactional synchronization between active squad membership/formation and run snapshot state, and deterministic downstream unlock handling on finalize.

---
title: Implement backend auto-level application at rest finalize and run cleanup
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement backend-authoritative level-up math execution as an automatic pass on rest finalization and run cleanup, rather than per-claim leveling.
Resolution: Introduced run-scoped auto-level application in `RunRepository` and invoked it both at rest finalization and during run-end cleanup paths, with integration tests covering progression updates.

---
title: Implement manual promotion endpoint with primary-secondary consume semantics
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a promotion API that takes one primary unit id and two distinct secondary unit ids; primary persists/upgrades while secondaries are consumed. Promotion is allowed between runs or during open rest workflow, and must reject ineligible active-run snapshot participants.
Resolution: Added `POST /api/v1/units/:unitInstanceId/promote` with primary/secondary payload validation, distinct secondary enforcement, max-level eligibility checks, active-run rest-context gating, active-snapshot secondary rejection, and transactional consume/update persistence.

---
title: Expose dice equip and unequip gameplay endpoints with rest-only run constraints
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Expose backend equip/unequip endpoints that enforce per-unit slot caps and block active-run modifications outside rest workflow.
Resolution: Added gameplay dice mutation routes and controller flow with rest-context enforcement for active-run snapshot units, and extended repository equip logic to enforce a unit-definition `max_equipped_dice` cap.

---
title: Implement exit-node completion flow and completed run transition
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add run-map support for an always-visible exit node that is only reachable through the boss path and implement backend run transition to `completed` when exit is successfully taken.
Resolution: Added explicit `exit` node support to run graph generation, introduced `POST /api/v1/runs/:runId/exit` completion endpoint with validation/cleanup, and blocked exit-node resolution through generic node resolve flow.

---
title: Implement run-end cleanup for completed status while preserving earned XP
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Extend terminal cleanup orchestration so `completed`, `failed`, and `abandoned` all run cleanup, with completed runs preserving earned XP while clearing active run locks and run-scoped transient state.
Resolution: Wired completed-run cleanup into the new exit endpoint using `applyRunEndCleanup(..., false)` so XP is preserved, while existing failed/abandoned flows continue applying terminal cleanup with appropriate XP-reset behavior.

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

---
title: Add frontend interaction tests for warband placement and save behaviors
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 8 - QA Test Backfill and Strategy
description: |
  [Role: QA Lead] Warband scene behavior in `frontend/src/scenes/WarbandManagementScene.ts` currently lacks automated coverage. Add tests for select/place/clear flows, button enabled states, and error/success toast handling around save and create-team actions.
Resolution: Added targeted Warband scene interaction tests covering select/place/clear behavior and save success/error flows, and made scene module loading test-safe via explicit Phaser import.

---
title: Create Game Designer role focused on playability, appeal, and UX-driven feature sequencing
status: complete
priority: medium
execution: active
ready: yes
milestone: unassigned
description: |
  Define a new `Game Designer` role in `ROLES.md` that evaluates playability and user appeal, with emphasis on UX decisions, game flow quality, and player-centric feature ordering recommendations.
Resolution: Added a `Game Designer` role definition to `ROLES.md` with explicit goals, constraints, risk tolerance, and communication style centered on UX, flow, and feature sequencing.

---
title: Rename team terminology to squads across frontend, backend, and docs
status: complete
priority: medium
execution: active
ready: yes
milestone: unassigned
description: |
  Preferred product terminology is `squads`. Audit and update inconsistent references to `teams` where safe, preserving route/database naming where backward compatibility requires it. Ensure API/docs/client types clearly communicate canonical `squads` wording.
Resolution: Updated user-facing and documentation terminology to prefer `squads` (including Warband scene labels and architecture/glossary text) while preserving compatibility-critical technical identifiers such as `/api/v1/teams` routes and DB table naming.

---
title: Establish post-QA milestone execution order and current milestone selection policy
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] After Milestone 8 completion there is no explicit current/open delivery lane. Define and document milestone execution order, opening rules, and criteria for setting exactly one current milestone to eliminate planning ambiguity.
Resolution: Added `documentation/ROADMAP_EXECUTION_POLICY.md` with explicit milestone order and open/close/current selection rules, and marked Milestone 9 as `in-progress`, `execution_window: open`, and `is_current: yes`.

---
title: Define milestone entry/exit criteria template and apply to active milestones
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] Active milestones list scope but do not consistently define measurable entry/exit gates. Add a concise criteria template and apply it to active milestones to improve delivery predictability.
Resolution: Added milestone template guidance for `entry_criteria` and `exit_criteria` and applied concrete entry/exit criteria to all active milestones in `MILESTONES.md`.

---
title: Resolve MVP XP-source contradiction between encounter and progression specs
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Cross-Role Reconciliation] `documentation/02-systems-mvp/03-encounter-scope.md` and `documentation/02-systems-mvp/02-units-and-progression.md` currently disagree on whether Loot encounters award XP. Reconcile to one canonical rule and update dependent docs/contracts.
Resolution: Reconciled to Combat/Boss-only XP in MVP by updating encounter and loot scope docs to remove Loot XP language and align with units/progression rules.

---
title: Add explicit squads-vs-teams terminology compatibility note in architecture docs
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] Add a concise compatibility note in architecture docs explaining product term `squad` versus compatibility API routes/fields using `teams`, including where each term is expected.
Resolution: Added explicit compatibility notes to frontend and backend architecture contract docs clarifying product-facing `squad` terminology versus route/database `team` compatibility identifiers.

---
title: Remove template-entry parsing ambiguity from active milestones file
status: complete
priority: low
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  The in-file Milestone Template block in `MILESTONES.md` can be misread as an active milestone by lightweight validators. Restructure template examples so automated checks and future tooling parse only real active entries.
Resolution: Replaced the parseable template block with a fenced YAML example so validators can ignore template content while preserving author guidance.

---
title: Archive or remove deprecated documentation worklist artifact
status: complete
priority: low
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  `documentation/worklist.md` is marked deprecated but still present in active docs. Move it to archive or remove it to reduce accidental usage and keep active planning sources unambiguous (`ISSUES.md` + `MILESTONES.md`).
Resolution: Removed `documentation/worklist.md` from the repository and retained historical recovery through git history.

---
title: Add cross-milestone dependency map for run, combat, UX, and QA streams
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Technical Product Manager] Capture dependency links between Milestones 2/3/10/11/13 so implementation order and blockers are explicit before new feature execution.
Resolution: Added `documentation/BACKLOG_DEPENDENCIES.md` with explicit cross-milestone dependency mapping and operational dependency rules.

---
title: Add issue dependency metadata for blocked-by relationships
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Cross-Role Reconciliation] Add optional issue-level dependency metadata (`blocked_by` and `enables`) to make cross-milestone sequencing explicit and reduce hidden ordering risk during multi-role planning passes.
Resolution: Added dependency metadata policy in `documentation/BACKLOG_DEPENDENCIES.md` and updated the issue template with optional `blocked_by` and `enables` fields.

---
title: Resolve empty milestone placeholders or repopulate with scoped issues
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Backlog Curator] Active milestones 4 and 6 currently contain no issues and add planning noise. Either archive them as dormant placeholders or repopulate with concrete scoped issues.
Resolution: Milestone placeholders were repopulated with scoped issues for Milestones 4-6, and the template block in `MILESTONES.md` was made non-parseable to prevent placeholder confusion.

---
title: Add automated lint/check for ISSUES and MILESTONES schema consistency
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Role: Backlog Curator] Add a lightweight validation script to ensure issue/milestone required fields and allowed enums stay consistent.
Resolution: Added `scripts/validate-backlog.mjs` to validate required enums, field presence, issue/milestone references, and `is_current` cardinality; script currently passes.

---
title: Add dependency metadata policy for Milestones 4-6 execution sequencing
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 9 - Product and Backlog Governance
description: |
  [Cross-Role Reconciliation] Extend issue dependency guidance with explicit sequencing notes for Milestones 4-6 so UI, systems, and verification tasks can be executed in a predictable order.
Resolution: Added explicit Milestones 4-6 sequencing policy and readiness rules in `documentation/BACKLOG_DEPENDENCIES.md` to guide execution gating.

---
title: Normalize active issue metadata fields to template standards
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Milestone 9 - Product and Backlog Governance
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Backlog Curator] Active issue entries are inconsistent with template metadata (`owner`, `created`, `updated`). Align active entries with standard fields for easier auditability.
Resolution: Normalized all active issue entries to include `owner`, `created`, and `updated` fields aligned to the active issue template.

---
title: Define backlog triage cadence and status-transition policy
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Milestone 9 - Product and Backlog Governance
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Backlog Curator] Add an explicit triage cadence and status transition policy (`unstarted`/`in-progress`/`blocked`/`reopened`) to keep issue state trustworthy over time.
Resolution: Added `documentation/BACKLOG_TRIAGE_POLICY.md` and mirrored triage/status-transition rules in `AGENTS.md` issue workflow guidance.

---
title: Add required metadata headers to high-impact documentation files
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 9 - Product and Backlog Governance
created: 2026-03-02
updated: 2026-03-02
description: |
  Multiple active docs are missing style-guide metadata headers (`Status`, `Last Updated`, `Owner`, `Depends On`), including key architecture/systems/ux docs. Normalize metadata across high-impact files so documentation provenance and dependency tracking remain auditable.
Resolution: Added required metadata headers to the targeted high-impact docs across overview, architecture, systems, UX, multiplayer, and changelog documents.

---
title: Select and open the next current milestone after Milestone 9 completion
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Post-Governance Simplification and Readiness
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Technical Product Manager] Milestone 9 is archived complete, but no active milestone is currently marked as current/open. Apply `documentation/ROADMAP_EXECUTION_POLICY.md` to choose and open the next execution lane with explicit rationale.
Resolution: Set Milestone 2 as the current active lane (`status: in-progress`, `execution_window: open`, `is_current: yes`) to resume highest-priority delivery sequencing.

---
title: Add a single-command workflow entrypoint for backlog validation
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Post-Governance Simplification and Readiness
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `scripts/validate-backlog.mjs` exists but lacks a standardized task-runner entrypoint. Add a simple repository command path and documentation so validation is easy and consistently executed.
Resolution: Added a root `package.json` script entrypoint (`npm run backlog:validate`) and documented usage in project README.

---
title: Add CI gate to run backlog schema validation on documentation/backlog changes
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Post-Governance Simplification and Readiness
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: QA Lead] Add CI automation for `scripts/validate-backlog.mjs` (or equivalent) to prevent schema/link drift in `ISSUES.md` and `MILESTONES.md` from merging unnoticed.
Resolution: Added `.github/workflows/backlog-validation.yml` to run backlog validation on relevant backlog/documentation changes for pull requests and pushes to `main`.

---
title: Trim active backlog and milestone docs to configured context guardrails
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Post-Governance Simplification and Readiness
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Backlog Curator] Active docs exceed project guardrails (`ISSUES.md` and `MILESTONES.md` line budgets). Apply a trimming pass (archive movement, concise descriptions, optional split strategy) to restore context efficiency.
Resolution: Split deferred planning inventory into `ISSUES_BACKLOG.md` and `MILESTONES_BACKLOG.md`, keeping `ISSUES.md` and `MILESTONES.md` focused on active lanes and back under configured line guardrails.
---
title: Replace placeholder run node resolution with deterministic combat engine integration
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 2 - Server-Side Battle Resolution
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Combat Systems Reviewer] `backend/src/Controllers/RunNodeController.php` currently uses placeholder battle resolution values and random seed generation instead of actual deterministic simulation logic. Integrate the combat engine pipeline and ensure outcomes/logs are generated from canonical unit, ability, and RNG rules.
Resolution: Replaced placeholder resolve logic with a deterministic resolver service that derives seed, outcome, rounds/ticks, rewards, and canonical battle log events from persisted run/team/encounter data, then stores those artifacts in battle tables.

---
title: Implement non-placeholder reward and XP application on battle claim
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 2 - Server-Side Battle Resolution
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Combat Systems Reviewer] `backend/src/Controllers/BattleController.php` returns placeholder arrays for `updated_run_unit_state`, XP application details, and updated units. Implement real reward/XP application tied to run-scoped unit state and battle outcomes to satisfy progression invariants.
Resolution: Implemented first-claim XP application for eligible squad units, excluded defeated or max-level units, persisted a claim snapshot in `battle_rewards.rewards_json` for idempotent re-claims, and now return real `updated_run_unit_state`/`xp`/`updated_units` payloads.
---
title: Persist run-scoped unit attrition state across encounters and resume
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 3 - Run Progression and Attrition
created: 2026-03-02
updated: 2026-03-03
description: |
  Implement and persist run-scoped unit state (HP, cooldowns, status effects, defeated flags) so attrition behavior matches `documentation/02-systems-mvp/05-save-and-resume-scope.md` and survives reconnect/resume.
Resolution: Added run-start squad snapshot seeding into `run_unit_state`, deterministic attrition mutation on claim, and current-run payload exposure of run-unit-state for resume correctness, with integration coverage for state persistence.

---
title: Implement run failure and abandonment resolution rules
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 3 - Run Progression and Attrition
created: 2026-03-02
updated: 2026-03-03
description: |
  Implement terminal run resolution (total defeat and abandon) including XP reset rules for defeated units and post-run cleanup/recovery rules defined in `documentation/02-systems-mvp/06-run-resolution-scope.md`.
Resolution: Implemented `POST /api/v1/runs/:runId/abandon` and total-defeat auto-fail resolution on battle claim, including defeated-unit XP reset and full run-unit-state cleanup (HP restore, cooldown/status clear) validated by integration tests.

---
title: Implement encounter retry flow for partial defeat scenarios
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 3 - Run Progression and Attrition
created: 2026-03-02
updated: 2026-03-03
description: |
  Support retrying encounters after partial defeat using remaining undefeated run units, with no extra energy cost, consistent with run resolution scope documentation.
Resolution: Added claimed-defeat retry handling in node resolution: defeated combat nodes remain available, claimed defeat artifacts are replaced on retry attempts, and retry/idempotency behavior is covered by integration tests.
---
title: Define encounter-flow scene transition matrix and acceptance criteria
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 4 - Encounter Flow UI
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Technical Product Manager] Create a documented transition matrix for Run Map -> Encounter Start -> Combat Viewer -> Loot Claim/Rewards -> Run Map, including allowed transitions, blocked transitions, and acceptance criteria for each node type.
Resolution: Added `documentation/03-ux/03-encounter-flow-transition-matrix.md` with explicit state transitions, blocked paths, and node-type acceptance criteria for combat, boss, loot, and rest flows.

---
title: Define combat viewer event readability contract for desktop and mobile
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 4 - Encounter Flow UI
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Game Designer] Define readability expectations for combat playback controls, event pacing, and log visibility (including collapsible mobile log behavior) to reduce outcome confusion during replay and skip flows.
Resolution: Added `documentation/03-ux/04-combat-viewer-readability.md` with desktop/mobile readability constraints, control layout rules, and QA acceptance checks for playback/log clarity.

---
title: Specify encounter reward-surface rules by encounter type
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 4 - Encounter Flow UI
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Combat Systems Reviewer] Document expected reward/XP surface behavior for combat, loot, rest, and boss encounter outcomes so UI presentation stays consistent with MVP encounter and progression rules.
Resolution: Added `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md` defining encounter-type reward/XP display rules and idempotent claim messaging aligned to MVP progression contracts.
---
title: Implement run map node state rendering and unlock gating contract
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 4 - Encounter Flow UI
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Senior Developer] Implement and align Run Map UI behavior for node icons and statuses (`locked`, `available`, `cleared`) with documented unlock rules so players can reliably understand progression state.
Resolution: Updated Run Map node rendering/interaction contracts so only `available` nodes are clickable, `locked` and `cleared` have distinct visual affordances, and node placement follows backend `meta col/row` layout data when present.

---
title: Add frontend interaction tests for encounter transitions and node-status affordances
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 4 - Encounter Flow UI
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: QA Lead] Add frontend tests validating encounter scene transitions and node-state rendering behavior for combat/loot/rest/boss flows, including negative-path transition guards.
Resolution: Added frontend tests for node-status affordances and map transition guards (`Node.test.ts`, `MapExplorationScene.test.ts`), and validated full frontend suite/build pass in unrestricted environment.
---
title: Define unit and dice management acceptance criteria for MVP information surfaces
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 5 - Unit and Dice Management
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Technical Product Manager] Define acceptance criteria for Unit Details and Dice Details screens (tier/level/xp/max, affix labeling, equipped dice visibility, run-scoped read-only state) to remove ambiguity before implementation.
Resolution: Added `documentation/03-ux/05-unit-dice-details-acceptance.md` with concrete MVP acceptance criteria for unit/dice details, XP/max-level presentation, affix labeling, and run-scoped read-only overlays.
---
title: Implement typed view-model adapters for unit and dice details payloads
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 5 - Unit and Dice Management
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Senior Developer] Introduced typed frontend adapters for unit/dice detail payloads to enforce consistent rendering for XP progress, max-level handling, rarity/slot metadata, and conditional affix labels.
Resolution: Added `frontend/src/adapters/profileViewModels.ts` with typed unit/dice normalization and view-model transforms, wired warband loading through normalized units, and added `frontend/tests/adapters/profileViewModels.test.ts` coverage for max-level XP behavior, affix labeling, and equip-context mapping.
---
title: Add regression tests for warband formation editing and bench-membership save invariants
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 5 - Unit and Dice Management
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: QA Lead] Added regression tests for formation placement/clearing, highlighted vs outlined visual state mapping, and save payload invariants (`unit_ids` plus full 3x3 formation) to protect intentional bench-membership behavior.
Resolution: Expanded `WarbandManagementScene.test.ts` with row-state mapping coverage and payload assertions ensuring full 3x3 formation persistence plus bench membership retention, and validated with full frontend test/build passes.
---
title: Define promotion and dice-management UX sequencing between runs and rest nodes
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 5 - Unit and Dice Management
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Game Designer] Documented player-facing sequencing and messaging for when squad edits, promotion actions, and dice management are available (between runs vs rest nodes) to reduce progression friction.
Resolution: Added `documentation/03-ux/06-promotion-and-dice-management-sequencing.md` defining management availability windows, blocked run-time actions, UI messaging rules, and acceptance criteria for run vs between-run behavior.
---
title: Specify dice pool consumption and refresh visualization cues
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 5 - Unit and Dice Management
created: 2026-03-02
updated: 2026-03-03
description: |
  [Role: Combat Systems Reviewer] Specified how dice consumption order (largest-to-smallest) and pool refresh triggers are surfaced in combat logs/UI so players can reason about ability costs and outcomes.
Resolution: Added `documentation/03-ux/07-dice-pool-consumption-and-refresh-cues.md` with required log events, HUD cue contract, labeling rules, and edge-case presentation requirements aligned to MVP dice rules.
---
title: Add deterministic seed derivation strategy for node resolution
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Combat Systems Reviewer] Defined deterministic seed derivation tied to run/node/user context and added regression tests for reproducibility.
Resolution: Updated `DeterministicRunNodeResolver` to derive `seed_v2` from user/run/run_seed/node/team/encounter context and added integration coverage in `RunBattleIdempotencyTest` asserting the exact derived seed used for battle creation.
---
title: Add combat ability-handler regression test suite against canonical rules
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Combat Systems Reviewer] Added focused regression tests for active/passive ability handlers to prevent hidden combat-rules drift as deterministic engine integration proceeds.
Resolution: Added `AbilityHandlerRegistryCoverageTest` to assert active/passive handler coverage against canonical ability definitions and duplicate-id rejection behavior, expanding backend regression safety for handler registry integrity.
---
title: Add progression invariants test suite for claim and run-state mutation
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Combat Systems Reviewer] Added invariant-focused integration coverage verifying XP application, run-unit-state mutation consistency, and idempotent claim behavior across repeated battle claims.
Resolution: Expanded `BattleResolutionAndClaimIntegrationTest` with progression invariant assertions for HP bounds, defeated-state consistency, single-application XP behavior, and stable claim snapshots across repeated claims.
---
title: Add battle reward economy sanity validation fixtures
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Combat Systems Reviewer] Added fixture-based reward economy checks across combat/rest/loot node resolutions to detect malformed or out-of-bounds XP/currency payout behavior.
Resolution: Added reward economy fixture assertions in `BattleResolutionAndClaimIntegrationTest` validating node-type payout contracts (`rest`, `loot`, `combat`), outcome-bounded combat rewards, and canonical rewards payload shape.
---
title: Add CI workflow to run backend and frontend verification gates
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Added CI automation for backend tests, frontend tests/build, and verification gates to enforce consistent regression checks.
Resolution: Added `.github/workflows/full-verification.yml` to run LLM control checks, backend PHPUnit, frontend Vitest/build, and bundle/doc warning checks on pull requests and main pushes.
---
title: Document and enforce frontend build-artifact policy for `frontend/dist`
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-03
updated: 2026-03-04
description: |
  [Role: Technical Product Manager] Defined frontend build-artifact handling policy and codified it in workflow guidance/checks to avoid accidental source-vs-artifact drift.
Resolution: Updated `AGENTS.md` editing rules to explicitly govern when `frontend/dist` artifacts are committed, and added verification workflow/documentation updates that surface artifact and bundle checks consistently.
---
title: Add backend endpoint contract tests for session/profile/current-run success envelopes
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Frontend contract validators exist, but backend-side regression tests for `GET /api/v1/session`, `GET /api/v1/profile`, and `GET /api/v1/runs/current` success envelopes are missing. Add endpoint-level tests to catch server payload drift at source.
Resolution: Added `ApiControllerEnvelopeContractTest` covering authenticated `/session`, `/profile`, and `/runs/current` success-envelope contracts, and fixed `EnergyRepository::touchLastRegenAtNow` to avoid false missing-row failures on same-second timestamp touches.
---
title: Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Add an integration test that validates the full mutating lifecycle (`POST /runs` -> `POST /runs/:runId/nodes/:nodeId/resolve` -> `POST /battles/:battleId/claim`) including status transitions and reward/claim contract stability; coordinate expected assertions with Milestone 2 placeholder-to-real reward/combat changes.
Resolution: Added `RunLifecycleApiIntegrationTest` to validate lifecycle execution (`/session` bootstrap -> `/runs` -> resolve node -> claim battle), assert `battles.status` transitions (`completed` -> `claimed`), and verify repeated claim responses remain idempotent and contract-stable.
---
title: Add frontend apiClient mutation flow tests for CSRF and error handling behavior
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Add tests for `createTeam/activateTeam/updateTeam/createRun` mutation flows to validate CSRF sourcing behavior and error propagation semantics in `apiClient`.
Resolution: Added `frontend/tests/services/apiClient.mutations.test.ts` covering CSRF sourcing for create/activate/update/run mutations and non-2xx error propagation via the shared request helper.
---
title: Add reusable test DB reset/migration utility for backend integration tests
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Integration tests currently assume schema is preloaded manually. Add a repeatable utility for initializing/resetting test DB state from versioned schema artifacts.
Resolution: Added `backend/scripts/reset-test-db.php` and composer script `test:db:reset` to rebuild the test database from `backend/migrations/schema_all.sql`, and documented usage in `backend/tests/README.md`.
---
title: Define Milestone 6 playability and stability release gate criteria
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Technical Product Manager] Create release-gate criteria for Milestone 6 covering required automated checks, required manual checks, and blocker thresholds so stability sign-off is objective.
Resolution: Added `documentation/05-playability-stability/00-release-gate-criteria.md` defining objective automated/manual gates, blocker thresholds, and milestone-close decision rules.
---
title: Add critical-path manual playtest script with evidence capture template
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Add a repeatable manual playtest script for first-session through run completion/failure, including pass/fail recording format and defect capture fields to support milestone handoff decisions.
Resolution: Added `documentation/05-playability-stability/01-critical-path-playtest-script.md` with a step-by-step execution script and structured YAML evidence capture template.
---
title: Clean up documentation encoding artifacts in MVP systems and UX specs
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Backlog Curator] Remove mojibake/encoding artifacts and normalize UTF-8 rendering across active gameplay docs (at minimum encounter scope, dice system, units/progression, and UX scope) to reduce ambiguity and keep copied terms/contracts stable.
Resolution: Fixed mojibake in `documentation/01-architecture/04-data-model.md` (`3Ã-3` -> `3x3`) and validated the documentation tree for remaining encoding artifacts.
---
title: Define player-friction severity rubric for playability triage
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Game Designer] Define a severity rubric for UX/playability defects (confusion, pacing drag, feedback clarity, dead-end flow) to standardize polish prioritization during Milestone 6.
Resolution: Added `documentation/05-playability-stability/02-player-friction-severity-rubric.md` with severity dimensions, level definitions, and triage guidance.
---
title: Add stale-state recovery validation checklist for Milestone 6 handoff
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Define and execute a focused validation checklist for stale run-state and partial payload recovery scenarios to verify resilience changes before Milestone 6 sign-off.
Resolution: Added `documentation/05-playability-stability/03-stale-state-recovery-checklist.md` with pass criteria, scenario checklist, and evidence template for stale-state validation.
---
title: Harden frontend handling for partial API payloads and stale run state
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 6 - Playability and Stability Pass
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Senior Developer] Add resilience handling and explicit user-facing fallback states for partial/late API payloads in active run scenes so transient backend inconsistency does not cascade into broken UI flow.
Resolution: Updated `MapExplorationScene` to handle stale/partial/error current-run responses with explicit fallback messaging and no crash path, and added regression coverage in `MapExplorationScene.test.ts` for no-run, thrown-request, and error-envelope scenarios.
---
title: Implement Rest Management scene with open-edit-finalize workflow
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a dedicated rest management scene that supports rest open/edit/finalize flow, allows squad edits and dice changes, and returns a per-unit summary (healing and progression deltas) before returning to run map.
Resolution: Added `RestManagementScene` with open/apply/finalize rest workflow wiring, rest-context dice handoff, summary rendering for progression and healing deltas, and map routing integration from rest nodes.
---
title: Implement embedded promotion flow in Unit Details for between-run and rest contexts
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement promotion controls directly in Unit Details with correct context gating: enabled between runs and during open rest workflow, disabled in active-run non-rest contexts.
Resolution: Embedded promotion controls into management flows by adding primary/secondary selection and promote actions in both `WarbandManagementScene` (between-run only) and `RestManagementScene` (rest-context), including client-side compatibility checks and backend-context payloads.
---
title: Implement end-of-run summary scene shell for completed failed and abandoned outcomes
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a shared RunEnd summary shell that renders outcome-specific messaging while always showing rewards, XP/level progression, and surviving/defeated unit breakdown.
Resolution: Added `RunEndSummaryScene` and wired exit-node flow to call `/runs/:runId/exit` and transition into outcome-based summary messaging with required sections for rewards, progression, survivors, and defeated units.
---
title: Implement distinct exit-node visuals and locked-path affordance on run map
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a visually distinct exit-node treatment (portal/door style) and clear path-lock signaling so the node is visible throughout a run but manually unreachable until boss path unlock.
Resolution: Updated encounter-map node rendering so exit nodes keep a distinct visual/tint profile across locked, available, and cleared states while preserving availability gating for click interaction.
---
title: Wire Dice Inventory screen into active-rest management flow
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Keep Dice Inventory as a dedicated screen and integrate it with rest-management context so allowed dice actions are available during rest and blocked in active-run non-rest contexts.
Resolution: Expanded `DiceInventoryScene` to load units/dice, support rest-context equip/unequip mutations, and enforce active-run gating when outside rest context, with return-path wiring back to rest management.
---
title: Wire combat node click flow from map screen
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
created: 2026-03-05
updated: 2026-03-05
description: |
  Clicking a combat node currently showed "Node 'combat' is not wired yet". Implement combat-node transition/handling behavior from MapExplorationScene.
Resolution: Map nodes now call `/api/v1/runs/:runId/nodes/:nodeId/resolve` for combat/loot/boss nodes, surface battle outcome feedback, and refresh progression state after resolution.

---
title: Add abandon run action and confirmation flow
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
created: 2026-03-05
updated: 2026-03-05
description: |
  Run screen had no abandon-run control. Add an abandon action with clear confirmation and backend call/update behavior.
Resolution: Added a map-side `Abandon Run` action with confirmation and backend integration to `POST /api/v1/runs/:runId/abandon`, routing successful responses to RunEndSummaryScene.

---
title: Prevent run-map nodes from rendering beyond visible bounds
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
created: 2026-03-05
updated: 2026-03-05
description: |
  Some map nodes were drawn past the screen edge. Update node scatter/layout bounds and placement logic so all interactive nodes remain fully visible.
Resolution: Node rendering now uses center-anchored icons with explicit display size and clamped placement bounds tied to node radius, preventing edge overflow.

---
title: Show map edge indicators for node unlock paths
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
created: 2026-03-05
updated: 2026-03-05
description: |
  Add visual indicators on run map to show which nodes unlock other nodes (e.g., directional/path edges or equivalent relationship markers).
Resolution: NodeList now renders directional edge lines with arrowheads between graph nodes, visually differentiating unlocked/available paths from locked progression.
---
title: Formalize squad rename API contract for /api/v1/teams/:teamId
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Squad rename was exposed in SquadDetailsScene using best-effort payload behavior. Backend contract support and validation needed formalization.
Resolution: Team update flow now accepts optional `name` on `PUT /api/v1/teams/:teamId`, and backend rename validation is applied through TeamController/TeamRepository.

---
title: Open squad details directly when clicking squad row in warband hub
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Update WarbandManagementScene so selecting/clicking a squad row immediately navigates to SquadDetailsScene.
Resolution: Squad rows now route directly to `SquadDetailsScene` on click, removing the extra open-step interaction.

---
title: Add squad deletion flow with safety gates in SquadDetailsScene
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Add delete squad capability from SquadDetailsScene with constraints around active-run safety and minimum squad count.
Resolution: Implemented backend `DELETE /api/v1/teams/:teamId` plus frontend delete action with confirmation and enforcement of `not only squad` and `not active squad during active run` rules.

---
title: Remove Open Squad button from warband hub actions
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Once squad rows open details directly, remove the redundant Open Squad action button from WarbandManagementScene.
Resolution: Removed Open Squad action from warband hub list and kept direct row navigation as the primary interaction.

---
title: Rename Add Squad action to New Squad and keep it in warband hub action list
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Change the squad-creation action label from `Add Squad` to `New Squad` and keep it in the action button list slot on WarbandManagementScene.
Resolution: Updated warband hub action label to `New Squad`, preserving its position in the action button list.
---
title: Add dedicated SquadListPanel component to replace UnitListPanel casting
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Warband hub rendered squads by adapting squad rows into UnitListPanel shape. A squad-specific component was needed.
Resolution: Added `SquadListPanel` and replaced squad-row casting usage in WarbandManagementScene with dedicated squad rendering behavior.

---
title: Add metal-strip variant of ActionButton and list component for squad display
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
created: 2026-03-05
updated: 2026-03-05
description: |
  Create a `metal_strip` ActionButton variant and companion list component for squad display on warband hub.
Resolution: Implemented `MetalActionButton` + `MetalActionButtonList`, wired `metal_strip` asset key, and used the metal list variant for squad rows in warband management.

---
title: Replace management-screen unit lists with 3-column unit card layout
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Update management-screen unit displays to use cards:
  - square portrait area (portrait asset to be integrated later)
  - level shown in portrait bottom-right
  - unit name under portrait
  - list/grid rendering with 3 cards per row.
Resolution: Replaced management list rendering with `UnitCardGrid` using a 3-column card presentation, square portrait area, level badge, and unit name caption across warband/rest/details flows.

---
title: Add optional pagination support for unit, dice, and squad lists
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Add optional pagination controls and state handling for unit lists, dice lists,
  and squad lists so UI remains usable with large item counts.
Resolution: Added page controls and bounded page-state handling for `UnitCardGrid`, `DiceCardGrid`, and `SquadListPanel` so large rosters and inventories remain navigable.

---
title: Redesign preload scene spacing when hero logo is present
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Current preload/loading scene text appears vertically scrunched when the hero
  logo is visible. Redo preload scene layout spacing and typography so loading
  text remains readable.
Resolution: Updated `PreloadScene` layout to compute logo-aware text baseline positions so loading title/progress/file labels remain readable with hero logo enabled.

---
title: Replace dice inventory text list with sprite-based dice grid
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Dice inventory should render all owned dice as sprite cards/grid entries instead
  of plain text lines. Integrate with shared list scaling behavior (pagination or
  scrolling, whichever is adopted globally).
Resolution: Dice inventory now renders owned dice through `DiceCardGrid` with atlas-backed dice sprites and selectable card interactions instead of plain text rows.

---
title: Increase baseline non-button text size across frontend scenes
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Most non-button text is currently too small. Perform a typography pass across
  scenes to raise baseline text sizes while preserving layout fit and hierarchy.
Resolution: Applied a frontend readability pass to non-button labels/status/helper text in affected scenes and list components to improve legibility without breaking layout.

---
title: Reduce home button icon size to match energy icon
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: resize the home button icon so it matches the energy icon size
  in the HUD/header area for consistent visual hierarchy.
Resolution: Home button now uses a 75x75 icon, matching HUD energy icon scale for consistent top-bar visual weight.

---
title: Swap HUD name and energy rows so player name appears above energy bar
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: in the overlay/HUD panel, swap the vertical order of player
  name and energy so name is rendered on the top row and the energy bar/value
  is rendered on the bottom row.
Resolution: HUD layout now renders player name on the top row and energy bar/value on the lower row in the right-side info area.

---
title: Remove "Current squads" label from Warband Management screen
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: remove the "Current squads" text label from the Warband
  Management screen.
Resolution: Removed the redundant "Current squads" heading from warband management to reduce text clutter and rely on panel framing instead.

---
title: Vertically center squad names and shift text 15px right in Warband Management list
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: adjust squad row label layout so squad names are vertically
  centered within their background strips and moved 15px to the right.
Resolution: Squad row labels were adjusted in the metal-strip list presentation to center vertically within strip visuals and increase left text offset for clearer alignment.

---
title: Remove "select a unit..." helper text from Warband Management screen
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: remove the "select a unit..." helper text from the Warband
  Management screen.
Resolution: Removed obsolete unit-selection helper copy from warband management now that split-flow navigation and card affordances are self-evident.

---
title: Replace management-screen unit lists with 3-column unit card layout
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Update management-screen unit displays to use cards:
  - square portrait area (portrait asset to be integrated later)
  - level shown in portrait bottom-right
  - unit name under portrait
  - list/grid rendering with 3 cards per row.
Resolution: Replaced management list rendering with `UnitCardGrid` using a 3-column card presentation, square portrait area, level badge, and unit name caption across warband/rest/details flows.

---
title: Add optional pagination support for unit, dice, and squad lists
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Add optional pagination controls and state handling for unit lists, dice lists,
  and squad lists so UI remains usable with large item counts.
Resolution: Added page controls and bounded page-state handling for `UnitCardGrid`, `DiceCardGrid`, and `SquadListPanel` so large rosters and inventories remain navigable.

---
title: Redesign preload scene spacing when hero logo is present
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Current preload/loading scene text appears vertically scrunched when the hero
  logo is visible. Redo preload scene layout spacing and typography so loading
  text remains readable.
Resolution: Updated `PreloadScene` layout to compute logo-aware text baseline positions so loading title/progress/file labels remain readable with hero logo enabled.

---
title: Replace dice inventory text list with sprite-based dice grid
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Dice inventory should render all owned dice as sprite cards/grid entries instead
  of plain text lines. Integrate with shared list scaling behavior (pagination or
  scrolling, whichever is adopted globally).
Resolution: Dice inventory now renders owned dice through `DiceCardGrid` with atlas-backed dice sprites and selectable card interactions instead of plain text rows.

---
title: Increase baseline non-button text size across frontend scenes
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-06
description: |
  Most non-button text is currently too small. Perform a typography pass across
  scenes to raise baseline text sizes while preserving layout fit and hierarchy.
Resolution: Applied a frontend readability pass to non-button labels/status/helper text in affected scenes and list components to improve legibility without breaking layout.

---
title: Reduce home button icon size to match energy icon
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: resize the home button icon so it matches the energy icon size
  in the HUD/header area for consistent visual hierarchy.
Resolution: Home button now uses a 75x75 icon, matching HUD energy icon scale for consistent top-bar visual weight.

---
title: Swap HUD name and energy rows so player name appears above energy bar
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: in the overlay/HUD panel, swap the vertical order of player
  name and energy so name is rendered on the top row and the energy bar/value
  is rendered on the bottom row.
Resolution: HUD layout now renders player name on the top row and energy bar/value on the lower row in the right-side info area.

---
title: Remove "Current squads" label from Warband Management screen
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: remove the "Current squads" text label from the Warband
  Management screen.
Resolution: Removed the redundant "Current squads" heading from warband management to reduce text clutter and rely on panel framing instead.

---
title: Vertically center squad names and shift text 15px right in Warband Management list
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: adjust squad row label layout so squad names are vertically
  centered within their background strips and moved 15px to the right.
Resolution: Squad row labels were adjusted in the metal-strip list presentation to center vertically within strip visuals and increase left text offset for clearer alignment.

---
title: Remove "select a unit..." helper text from Warband Management screen
status: complete
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-06
description: |
  UAT follow-up: remove the "select a unit..." helper text from the Warband
  Management screen.
Resolution: Removed obsolete unit-selection helper copy from warband management now that split-flow navigation and card affordances are self-evident.

---
title: Purge and refetch cached player energy after energy-consuming actions
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-05
updated: 2026-03-06
description: |
  After any action that consumes energy, invalidate the frontend-cached player
  energy value and refetch current profile/session energy so UI state remains
  authoritative.
Resolution: Added centralized profile-cache refresh behavior after run creation and node resolution mutations so HUD energy data is invalidated and force-refetched after energy-relevant actions.

---
title: Show frontend blocked-feedback when run start is attempted in locked region
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-05
updated: 2026-03-06
description: |
  When a player attempts to start a run in a region that is not unlocked, show
  explicit in-game frontend feedback indicating the attempt is blocked and why.
Resolution: Region start failures now parse backend error payloads and surface user-facing blocked feedback, including explicit locked-region messaging when `region_locked` is returned.

---
title: Replace JavaScript confirm with in-game abandon-run confirmation dialog
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-05
updated: 2026-03-06
description: |
  Abandon run currently relies on a browser JavaScript confirmation dialog.
  Replace it with an in-game dialog/panel confirmation flow to match scene UX.
Resolution: Map exploration now uses an in-scene abandon confirmation overlay with Cancel/Abandon actions instead of browser `confirm`, matching the game UI flow.

---
title: Redo HUD overlay with energy-state icons and hover tooltip
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-06
updated: 2026-03-06
description: |
  Replace the HUD energy presentation with icon-state + hover details:
  - Use `icon_energy` only at 100% energy.
  - Use `icon_energy_75` for 99%-75%.
  - Use `icon_energy_50` for 74%-50%.
  - Use `icon_energy_25` for 49%-25%.
  - Use `icon_energy_0` below 25%.
  - On hover over the icon, show tooltip text like `Energy: 50 / 50`.
  - Comment out name display in HUD.
  - Remove the current segmented energy bar style display.
Resolution: Added energy-state icon swapping (100/75/50/25/0 thresholds), hover tooltip with exact energy values, removed segmented energy bar rendering, and disabled HUD name rendering while loading new icon assets from frontend public assets.

---
title: UI rebuild with new UX kit layout and scene-wide visual contract
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-06
updated: 2026-03-06
description: |
  Enact a global UI redesign using assets from `raw-assets/new_ux` and the provided
  home-scene layout guide image as the reference contract.

  Requirements:
  - Image source paths start at `raw-assets/new_ux`.
  - Any image used must be copied into project runtime assets.
  - Scene background for all scenes must use `textures/paper.png`.
  - `#e00000` region maps to `ui_kit/corner_left`; home icon renders inside this circle on scenes where home is shown.
  - `#ff0000` region maps to `ui_kit/corner_right`; energy icon renders inside this circle.
  - `#0600ff` region is the Start a Run area.
  - `#00f6ff` region is the Manage Warband area.
  - `#00ff72` region is the Manage Inventory area.
  - Each non-corner area must include:
    - full-width title bar using `textures/red.png`,
    - 12px margin,
    - solid color content box matching that area color filling the remaining allotted space.
  - Clicking anywhere in each non-corner area should navigate to the correct scene.

  Scope includes updating relevant docs, shared layout primitives, and impacted scene implementations.
Resolution: Delivered the first UX rebuild phase: new UX assets were imported, global paper background and corner treatments were wired, HomeScene was rebuilt to the reference layout, and layout/docs were updated to establish the new contract baseline.

---
title: Propagate new UX layout system across non-home scenes
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-06
updated: 2026-03-06
description: |
  Apply the new UX contract beyond HomeScene so non-home scenes use the same visual structure:
  - paper background texture,
  - corner-left home control treatment,
  - corner-right energy corner treatment,
  - region framing with full-width red title bars, 12px margins, and solid content fills.

  Scope includes scene-specific zone framing and layout cleanup for:
  Region Select, Warband Management, Squad Details, Unit Details,
  Dice Inventory, Map Exploration, Rest Management, and Run End Summary.
Resolution: Propagated the new UX framing to non-home scenes using shared dual-zone red-title panels, global paper backgrounds, and updated corner/HUD treatments, while keeping existing scene logic and navigation intact.

---
title: Replace Home Start Run content block with start-run image
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-06
updated: 2026-03-06
description: |
  HomeScene Start Run panel needs targeted UAT update:
  - Rename section title from `Start a Run` to `Start Run`.
  - Replace Start Run content fill with `raw-assets/new_ux/ux_kit/start-run.png`
    copied into runtime assets, fully replacing the solid color square for that area.
Resolution: Copied start-run.png into frontend runtime assets, added ux_start_run asset key, renamed the section title to Start Run, and updated HomeScene so the Start Run content area uses the image instead of a solid fill.

---
title: Extract HomeScene renderNavArea into reusable component
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-06
updated: 2026-03-06
description: |
  Move HomeScene `renderNavArea` logic into a dedicated reusable UI component/object.
  Preserve current behavior: title bar, 12px margin body area, optional image body,
  full-area click navigation, and support for both image and solid-color content.
Resolution: Extracted HomeScene area rendering into reusable HomeNavArea component while preserving title bar, margin/body logic, optional image body support, and full-area click navigation behavior.

---
title: Repair mojibake/encoding corruption in overview and llm-ops docs
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 17 - Documentation Encoding Integrity Sweep
created: 2026-03-07
updated: 2026-03-07
description: |
  Investigate and repair potential encoding-corruption sequences in overview and LLM-ops documentation.
Resolution: Verified the targeted docs are stored as valid UTF-8 (byte-level check) and preserved readable characters; no persisted mojibake corruption remained after normalization.

---
title: Repair mojibake/encoding corruption in systems and ux docs
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 17 - Documentation Encoding Integrity Sweep
created: 2026-03-07
updated: 2026-03-07
description: |
  Investigate and repair encoding-corruption sequences in systems and UX documentation files and verify documentation integrity.
Resolution: Completed a systems/UX documentation sweep and confirmed no stale path references or broken local markdown links; encoding artifacts observed during terminal display were not persisted in source bytes.

---
title: Add documentation lint check for encoding-corruption patterns
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 17 - Documentation Encoding Integrity Sweep
created: 2026-03-07
updated: 2026-03-07
description: |
  Extend docs lint to detect known encoding-corruption patterns so future edits surface mojibake quickly.
Resolution: Extended `scripts/lint-doc-headers.mjs` with encoding-corruption pattern checks and integrated reporting into existing docs lint output; current docs pass cleanly.
---
title: Create unified Node Resolution Scene for non-rest run nodes
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Create a dedicated `NodeResolutionScene` to handle `combat`, `loot`, `boss`, and `exit` node resolution flows so map navigation and resolution UX are centralized outside `MapExplorationScene`.
Resolution: Implemented `NodeResolutionScene` with a unified resolve/exit flow, shared outcome messaging, retry handling, and deterministic navigation to either run summary or map continuation.

---
title: Route map node clicks through centralized node-resolution navigation contract
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `MapExplorationScene` click handlers so non-rest nodes route into the new resolution scene with a consistent payload contract (`runId`, `nodeId`, `nodeType`) and predictable return behavior.
Resolution: Refactored `MapExplorationScene` to route supported non-rest node types through `NodeResolutionScene` using `{ runId, nodeId, nodeType }` payload and return-to-map messaging.

---
title: Move non-rest resolve/exit API orchestration out of MapExplorationScene
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Relocate resolve/exit request orchestration and outcome branching from `MapExplorationScene` into a dedicated controller flow used by the new node-resolution scene.
Resolution: Removed direct resolve/exit orchestration from `MapExplorationScene`; API calls and outcome branching now execute inside `NodeResolutionScene`.

---
title: Define unified node outcome surface for victory, defeat, claim, and errors
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement a single UI outcome surface for node resolution states (including clear retry/failure messaging) so non-rest encounters use consistent player feedback and action prompts.
Resolution: Added a single node outcome surface in `NodeResolutionScene` covering success, defeat, exit, and error/retry states with consistent action affordances.
---
title: Implement core layout shell components from 03-component-specifications
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Build foundational reusable layout shell components defined in `documentation/07-ux-rebuild/03-component-specifications.md` (page frame, title bands, section containers, shared spacing rules).
Resolution: Added `ContentAreaFrame`, enhanced `SectionTitleBar` fallback behavior, and introduced tested layout body-rect logic for consistent title/body shell composition.

---
title: Implement reusable navigation components from 03-component-specifications
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement standardized navigation components (home affordance, scene navigation panels, and navigation action variants) defined by the UX rebuild component specifications.
Resolution: Added reusable navigation primitives (`HomeNavigationPanel`, `HomeCornerButton`, `RegionSelectionPanel`) and wired `HomeNavArea` to the shared panel implementation.

---
title: Implement shared list framework with loading, error, and pagination states
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Create a reusable list foundation component that supports loading/failure/empty states and optional pagination controls, then expose extension points for name-link lists and grid lists.
Resolution: Implemented `ListContainer` state orchestration plus `NameLinkListVariant` and `GridListVariant`, with shared pagination/list-state helpers for consistent loading/empty/error handling.

---
title: Implement standardized action controls and button variants from component spec
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement shared action controls (primary, accept/reject, icon+text variants, grouped button lists) to eliminate divergent button implementations across scenes.
Resolution: Added semantic `AcceptButton` and `RejectButton` variants and extended `ActionButtonList` to support intent-driven button type selection.

---
title: Implement HUD and feedback utility components from component spec
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement reusable HUD and feedback components (energy/status tooltip surfaces, toast/inline feedback wrappers, confirmation/dialog primitives) per component specification.
Resolution: Added reusable `Tooltip`, `ToastMessage`, and `ConfirmationDialog` components and integrated HUD energy-tier icon logic through a shared helper (`resolveEnergyTierIcon`).

---
title: Add component-level test coverage and usage examples for UX rebuild library
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Add tests and concise usage examples for new reusable components to ensure expected behavior and reduce integration ambiguity during scene migration.
Resolution: Added unit tests for list-state, layout-body sizing, and HUD energy-tier logic and authored `documentation/07-ux-rebuild/05-component-library-usage.md` with integration snippets.
---
title: Migrate HomeScene to standardized UX rebuild components
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `HomeScene` to use the new shared component library for navigation areas, headers, and interaction surfaces while preserving start/continue run behavior.
Resolution: HomeScene now uses `HomeCornerButton` and `HomeNavigationPanel` directly, preserving start/continue run routing while removing scene-level dependency on legacy wrapper components.
---
title: Migrate RegionSelectScene and MapExplorationScene to shared component library
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Replace scene-specific UI implementations in `RegionSelectScene` and `MapExplorationScene` with standardized components and shared interaction patterns.
Resolution: RegionSelect and MapExploration now use shared `ContentAreaFrame` layout shells, `HomeCornerButton`, and shared feedback/dialog components (`ToastMessage`, `ConfirmationDialog`) while preserving run start/map flow behaviors.
---
title: Migrate warband management scenes to shared list/action components
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `WarbandManagementScene`, `SquadDetailsScene`, and `UnitDetailsScene` to use unified list, card-grid, and action control components from the UX rebuild library.
Resolution: Migrated warband scenes to shared layout shell components (`ContentAreaFrame`, `HomeCornerButton`) while preserving list/card/action behavior and scene transitions.

---
title: Migrate inventory, rest, and run summary scenes to shared component library
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `DiceInventoryScene`, `RestManagementScene`, and `RunEndSummaryScene` to standardized layout, list, and feedback components to reduce UI drift.
Resolution: Migrated inventory/rest/run-summary scene shells to shared UX rebuild primitives and kept existing gameplay interactions intact.

---
title: Remove superseded scene-local UI implementations after migration
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Delete or deprecate duplicated scene-local UI code that is replaced by shared components, ensuring imports and references are cleaned up.
Resolution: Removed superseded `UxZonePanels` and `HomeNavArea` implementations and updated scene imports to rely on shared component-library primitives.

---
title: Update UX rebuild docs to reflect final component adoption per scene
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Update scene/component mapping docs to show final adopted components and note any intentionally deferred scene-specific exceptions.
Resolution: Updated scene-component mapping documentation with Milestone 16 adoption coverage and current migration status.
---
title: Add regression coverage for consolidated non-rest node resolution flow
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Add focused tests validating scene routing, payload handling, supported node-type behavior, and run-end branching after node resolution consolidation.
Resolution: Added focused `NodeResolutionScene` regression coverage for payload handling, exit vs non-exit resolution paths, map-return and run-summary branching, and verified consolidated routing behavior with passing frontend test/build gates.
---
title: Add end-to-end player journey document for first-session flow
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Technical Product Manager] Document the canonical player journey from session bootstrap to first run completion/claim, including failure states and expected UX touchpoints, to align sequencing decisions across roles.
Resolution: Added `documentation/03-ux/09-first-session-player-journey.md` covering first-session flow stages, touchpoints, and success criteria.

---
title: Add first-session onboarding and objective framing UX spec
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Game Designer] Define onboarding messaging and immediate goals from first login through first run start to reduce confusion and increase early-session engagement.
Resolution: Added `documentation/03-ux/10-first-session-onboarding-and-objective-framing.md` with stage-specific objective framing and onboarding acceptance criteria.

---
title: Add encounter preview UX for node risk and reward expectations
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Game Designer] Add UX support for encounter preview information (node intent, risk/reward hints) before commitment to improve perceived agency and flow clarity.
Resolution: Added `documentation/03-ux/11-encounter-preview-risk-reward.md` defining preview model, surface contract, and copy constraints.

---
title: Add post-battle progression summary UX contract
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Game Designer] Define post-battle summary UX (XP gains, rewards, squad changes, next-step prompt) to strengthen player feedback loops.
Resolution: Added `documentation/03-ux/12-post-battle-progression-summary-contract.md` with required sections, scene contract, and continuation expectations.

---
title: Define run failure and recovery UX states for partial and total defeat
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Game Designer] Specify UX flows for partial defeat retry and total failure outcomes so recovery behavior feels intentional and understandable.
Resolution: Added `documentation/03-ux/13-run-failure-and-recovery-ux-states.md` defining failure-state UX expectations and recovery guidance.

---
title: Create player-value feature ordering model for upcoming milestones
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Game Designer] Define a player-value prioritization rubric (clarity, engagement, retention impact) to guide sequencing decisions across non-combat and combat-adjacent features.
Resolution: Added `documentation/03-ux/14-player-value-feature-ordering-model.md` with scoring rubric and backlog triage usage.

---
title: Remove unsafe any-casts from API client team mutation flow
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Senior Developer] `frontend/src/services/apiClient.ts` currently relies on repeated `as any` coercions for session CSRF access and team response typing. Introduce explicit response interfaces and typed helpers to prevent runtime-shape drift and improve compile-time guarantees.
Resolution: Added explicit team mutation response types and a typed CSRF token helper in `apiClient`, and removed `any` payload coercion from squad update flow.

---
title: Refactor WarbandManagementScene logic into testable state module
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Senior Developer] `frontend/src/scenes/WarbandManagementScene.ts` contains significant state/interaction logic mixed with rendering orchestration. Extract placement/save state transitions into a pure module to reduce scene complexity and improve test coverage quality.
Resolution: Extracted Warband hub state derivation, layout column math, and new-squad name normalization into `warbandManagementState`, with dedicated unit tests for each behavior.

---
title: Reduce frontend production bundle size via scene-level code splitting
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-03
updated: 2026-03-07
description: |
  [Role: Senior Developer] Frontend build currently emits a large primary bundle (~1.5 MB minified warning). Introduce scene-level lazy loading and/or manual chunking strategy to lower initial payload size and keep build warnings actionable.
Resolution: Added deterministic Rollup manual chunking for scene/ui/data/phaser bundles; entry chunk dropped to sub-1 KB while preserving current scene wiring and passing frontend tests/build.

---
title: Consolidate repeated controller auth/csrf/service bootstrap patterns
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Senior Developer] API controllers repeatedly construct services and duplicate auth/CSRF handling patterns. Introduce shared helpers/base patterns to reduce drift and improve maintainability.
Resolution: Added shared `ControllerServiceFactory` for auth/bootstrap graph construction and `RequiresCsrf` controller concern, then migrated API/Auth/Battle/Gameplay/RunNode/Team controllers to use the shared patterns.

---
title: Remove nested transaction ownership between controllers and repositories
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Senior Developer] Several flows nest controller-level and repository-level transactions. Define clear transaction ownership boundaries to avoid hidden rollback/commit edge cases and simplify reasoning about persistence behavior.
Resolution: Added `TeamRepository::updateTeamConfiguration()` as single-owner transactional orchestration and updated `TeamController::updateTeam()` to delegate to it, removing controller-managed transaction nesting.

---
title: Introduce stricter typed DTO mapping for profile squad payload assembly
status: complete
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-07
description: |
  [Role: Senior Developer] `ProfileService` and repository payload composition rely on broad array shapes. Add explicit DTO mapping/types for response assembly to reduce runtime shape drift.
Resolution: Introduced `ProfileDtoMapper` and routed profile payload assembly through typed mapping methods, including normalized squad/formation shaping before API response output.

---
title: Add create-run domain error branch integration coverage
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-07
description: |
  Add backend integration tests for `POST /api/v1/runs` domain-error branches: `run_already_active`, `region_not_found`, `region_disabled`, `region_locked`, missing active squad, and `insufficient_energy` to prevent gameplay regression in run-start gating.
Resolution: Added `ApiControllerCreateRunDomainErrorsTest` covering all listed create-run domain error branches and fixed an uncovered repository edge case where unchanged energy updates incorrectly threw missing-row errors.

---
title: Add resolve-node negative-state branch coverage for ownership and availability rules
status: complete
priority: high
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-07
description: |
  Add integration coverage for `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` error branches including run-not-active, node locked/unavailable, run ownership mismatch, invalid team ownership, and malformed body handling.
Resolution: Added `RunNodeControllerNegativeStateBranchesTest` for run-not-active, locked node, ownership mismatch, and invalid team ownership branches; also aligned request-body parsing with existing controllers by adding `$_POST` fallback for empty request bodies.

---
title: Add claim-battle non-happy-path coverage for claimability and ownership guards
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-07
description: |
  Add integration tests for `POST /api/v1/battles/:battleId/claim` non-happy-path branches (`battle_not_completed`, forbidden ownership access, and invalid outcome-state guard) to protect claim-state invariants.
Resolution: Added `BattleControllerClaimNegativeBranchesTest` covering `battle_not_completed`, ownership-forbidden claim attempts, and invalid-outcome guard behavior.

---
title: Add starter-pack provisioning invariants tests for baseline bootstrap flow
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-07
description: |
  Add tests for `GrantService` baseline provisioning to verify idempotent starter grants and expected seed-data dependencies (starter region/unit/dice definitions) across clean and pre-seeded test DB states.
Resolution: Added `GrantServiceStarterPackInvariantsTest` with seed-dependency assertions plus idempotent and pre-seeded-user provisioning invariants.

---
title: Add gameplay-effect tests for canonical active and passive ability handlers
status: complete
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-07
description: |
  Extend combat unit tests beyond handler registry coverage to assert per-ability gameplay effects (damage/buffs/debuffs/targeting semantics) for canonical active and passive handlers.
Resolution: Added `AbilityHandlerEffectsTest` with active handler interaction assertions (targeting/damage/status calls) and passive derived-stat mutation assertions, and made `CombatContext` extendable for test spies.
