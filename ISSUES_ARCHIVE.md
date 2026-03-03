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
