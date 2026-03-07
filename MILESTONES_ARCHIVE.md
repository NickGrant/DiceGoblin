# MILESTONES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive milestones moved from `MILESTONES.md`.
- Preserve milestone resolution history without bloating active execution context.

---
name: Milestone 15 - Backend Gameplay Completion
status: complete
execution_window: open
is_current: yes
issues:
  - Implement exit-node completion flow and completed run transition
  - Implement run-end cleanup for completed status while preserving earned XP
  - Implement rest workflow endpoints with transactional snapshot and squad sync
  - Implement backend auto-level application at rest finalize and run cleanup
  - Implement manual promotion endpoint with primary-secondary consume semantics
  - Expose dice equip and unequip gameplay endpoints with rest-only run constraints
description: |
  Complete missing backend gameplay functionality so the intended MVP loop can run end-to-end with authoritative state transitions.
Resolution: Implemented exit-node run completion, terminal cleanup invariants, rest open/state/finalize workflow, backend auto-level integration, promotion consume semantics, and rest-gated dice mutation endpoints with integration coverage for key success/error paths.

---
name: Milestone 7 - Documentation Integrity
status: complete
execution_window: open
is_current: yes
issues:
  - Align frontend scene/state contract docs with current implemented scene flow
  - Extend style-guide normalization pass to legacy overview and glossary docs
  - Add documentation QA checklist for code-contract drift checks
  - Clarify archive-loading policy for milestone history in AGENTS startup behavior
  - Reconcile combat progression wording across encounter/combat/reward docs
description: |
  Resolve active documentation drift and establish repeatable doc QA so execution docs remain reliable before resuming feature milestones.
Resolution: Completed documentation drift pass across architecture/overview/system docs, added QA checklist workflow, and aligned archive-loading policy and progression wording.

---
name: Milestone 8 - QA Test Backfill and Strategy
status: complete
execution_window: open
is_current: yes
issues:
  - Establish backend API integration test harness (PHPUnit + fixtures)
  - Establish frontend interaction test harness for Phaser scenes
  - Add API contract regression tests for session/profile/run payload invariants
  - Add negative-path integration tests for run creation and mutation CSRF enforcement
  - Add backend integration tests for team create/activate/update with CSRF and ownership rules
  - Add idempotency regression tests for run node resolve and battle claim
  - Add frontend interaction tests for warband placement and save behaviors
  - Create repository testing strategy and verification matrix document
description: |
  Stand up automated QA infrastructure and baseline regression coverage while defining an explicit testing strategy that governs verification expectations for future feature work.
Resolution: Completed QA harness setup across backend/frontend, added API contract and endpoint mutation security coverage, validated resolve/claim idempotency behavior, and documented repository-wide testing strategy.

---
name: Milestone 9 - Product and Backlog Governance
status: complete
execution_window: open
is_current: yes
issues:
  - Establish post-QA milestone execution order and current milestone selection policy
  - Define milestone entry/exit criteria template and apply to active milestones
  - Add cross-milestone dependency map for run, combat, UX, and QA streams
  - Add issue dependency metadata for blocked-by relationships
  - Normalize active issue metadata fields to template standards
  - Resolve empty milestone placeholders or repopulate with scoped issues
  - Define backlog triage cadence and status-transition policy
  - Add automated lint/check for ISSUES and MILESTONES schema consistency
  - Add dependency metadata policy for Milestones 4-6 execution sequencing
  - Resolve MVP XP-source contradiction between encounter and progression specs
  - Add explicit squads-vs-teams terminology compatibility note in architecture docs
  - Add required metadata headers to high-impact documentation files
  - Remove template-entry parsing ambiguity from active milestones file
  - Archive or remove deprecated documentation worklist artifact
description: |
  Tighten roadmap governance, milestone clarity, and backlog hygiene so future delivery lanes stay executable and auditable.
Resolution: Completed governance hardening for execution order, dependency modeling, backlog schema validation, issue metadata consistency, and documentation metadata/terminology hygiene; milestone artifacts are now structured for predictable cross-milestone execution.

---
name: Milestone 14 - Post-Governance Simplification and Readiness
status: complete
execution_window: open
is_current: yes
issues:
  - Select and open the next current milestone after Milestone 9 completion
  - Add a single-command workflow entrypoint for backlog validation
  - Add CI gate to run backlog schema validation on documentation/backlog changes
  - Trim active backlog and milestone docs to configured context guardrails
description: |
  Capture role-based simplification and readiness follow-ups discovered after completing milestone governance hardening.
Resolution: Opened Milestone 2 as the current lane, added local/CI backlog validation entrypoints, and split deferred planning inventory into dedicated backlog files to restore active-doc guardrail compliance.
---
name: Milestone 2 - Server-Side Battle Resolution
status: complete
execution_window: open
is_current: yes
issues:
  - Replace placeholder run node resolution with deterministic combat engine integration
  - Implement non-placeholder reward and XP application on battle claim
description: |
  Deliver deterministic battle resolution and real claim/reward flow with idempotency guarantees.
Resolution: Completed deterministic node resolution and non-placeholder claim reward/XP application, with integration verification via full backend test suite (`phpunit`, 13 passing tests including deterministic resolve and idempotent claim XP coverage).
---
name: Milestone 3 - Run Progression and Attrition
status: complete
execution_window: open
is_current: yes
issues:
  - Persist run-scoped unit attrition state across encounters and resume
  - Implement run failure and abandonment resolution rules
  - Implement encounter retry flow for partial defeat scenarios
description: |
  Track run progression state, attrition persistence, and run-end behavior with explicit retry/failure handling.
Resolution: Implemented run-unit-state snapshot/attrition persistence, run fail/abandon cleanup with defeated-unit XP reset, and claimed-defeat encounter retry flow, validated by expanded backend integration coverage (`phpunit`, 17 passing tests).
---
name: Milestone 4 - Encounter Flow UI
status: complete
execution_window: open
is_current: yes
issues:
  - Define encounter-flow scene transition matrix and acceptance criteria
  - Implement run map node state rendering and unlock gating contract
  - Add frontend interaction tests for encounter transitions and node-status affordances
  - Define combat viewer event readability contract for desktop and mobile
  - Specify encounter reward-surface rules by encounter type
description: |
  Complete encounter-flow UX surfaces and ensure end-to-end playable progression through UI.
Resolution: Completed encounter-flow transition/readability/reward contracts, aligned run-map node affordances with backend status semantics, and added frontend interaction tests for node status + transition guards; frontend test/build gates pass outside sandbox (`npm run test`, `npm run build`).
---
name: Milestone 5 - Unit and Dice Management
status: complete
execution_window: open
is_current: yes
issues:
  - Define unit and dice management acceptance criteria for MVP information surfaces
  - Implement typed view-model adapters for unit and dice details payloads
  - Add regression tests for warband formation editing and bench-membership save invariants
  - Define promotion and dice-management UX sequencing between runs and rest nodes
  - Specify dice pool consumption and refresh visualization cues
description: |
  Improve unit/dice management depth, robustness, and client contract quality.
Resolution: Completed unit/dice acceptance criteria, typed client adapters, and regression tests, then finalized UX sequencing and dice pool visualization contracts to lock run-vs-management behavior and combat readability expectations.
---
name: Milestone 12 - Combat Determinism and Progression Integrity
status: complete
execution_window: open
is_current: yes
issues:
  - Add deterministic seed derivation strategy for node resolution
  - Add combat ability-handler regression test suite against canonical rules
  - Add progression invariants test suite for claim and run-state mutation
  - Add battle reward economy sanity validation fixtures
description: |
  Strengthen combat determinism and progression correctness before higher-level tuning and content expansion.
Resolution: Completed deterministic seed derivation hardening, canonical handler coverage regression checks, progression invariant claim tests, and reward economy fixture validation across node types, raising confidence in run/battle integrity behavior.
---
name: Milestone 11 - QA Coverage and Automation
status: complete
execution_window: open
is_current: yes
issues:
  - Add backend endpoint contract tests for session/profile/current-run success envelopes
  - Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
  - Add frontend apiClient mutation flow tests for CSRF and error handling behavior
  - Add reusable test DB reset/migration utility for backend integration tests
description: |
  Expand automated verification depth and establish repeatable CI-backed confidence gates.
Resolution: Completed endpoint contract and lifecycle integration coverage, added frontend mutation CSRF/error tests, and shipped a versioned-schema test DB reset utility (`composer test:db:reset`) with all verification gates green.
---
name: Milestone 6 - Playability and Stability Pass
status: complete
execution_window: open
is_current: yes
issues:
  - Define Milestone 6 playability and stability release gate criteria
  - Add critical-path manual playtest script with evidence capture template
  - Harden frontend handling for partial API payloads and stale run state
  - Clean up documentation encoding artifacts in MVP systems and UX specs
  - Define player-friction severity rubric for playability triage
  - Add stale-state recovery validation checklist for Milestone 6 handoff
description: |
  Raise release-readiness confidence with verification coverage and stability hardening.
Resolution: Completed Milestone 6 release gate definition, playtest and stale-state validation artifacts, friction-severity triage rubric, encoding cleanup, and frontend run-state fallback hardening with regression tests and full verification gates passing.
---
name: Milestone 16 - Frontend Gameplay Completion
status: complete
execution_window: open
is_current: yes
issues:
  - Implement Rest Management scene with open-edit-finalize workflow
  - Implement embedded promotion flow in Unit Details for between-run and rest contexts
  - Implement end-of-run summary scene shell for completed failed and abandoned outcomes
  - Implement distinct exit-node visuals and locked-path affordance on run map
  - Wire Dice Inventory screen into active-rest management flow
description: |
  Complete missing frontend gameplay interfaces and flow wiring so players can execute full run lifecycle interactions with clear summaries and node affordances.
Resolution: Implemented rest management and run-end frontend flows, embedded promotion and dice mutation actions with rest-context gating, and completed exit-node affordance updates plus API mutation test coverage; TypeScript validation passes while Vite-based test/build commands remain sandbox-blocked (`spawn EPERM`).
---
name: Run Map UX Completion
status: complete
execution_window: open
is_current: yes
issues:
  - Wire combat node click flow from map screen
  - Add abandon run action and confirmation flow
  - Prevent run-map nodes from rendering beyond visible bounds
  - Show map edge indicators for node unlock paths
description: |
  Resolve major map-screen interaction and readability gaps that currently block
  complete run navigation and player understanding.
Resolution: Wired combat/loot/boss node resolution from map interactions, added explicit abandon-run action with confirmation, clamped node rendering within scene bounds, and rendered directional unlock-path indicators on the run graph.
---
name: Warband UX Split Follow-up
status: complete
execution_window: open
is_current: yes
issues:
  - Formalize squad rename API contract for /api/v1/teams/:teamId
  - Add dedicated SquadListPanel component to replace UnitListPanel casting
  - Add metal-strip variant of ActionButton and list component for squad display
  - Open squad details directly when clicking squad row in warband hub
  - Add squad deletion flow with safety gates in SquadDetailsScene
  - Remove Open Squad button from warband hub actions
  - Rename Add Squad action to New Squad and keep it in warband hub action list
description: |
  Complete the post-split warband UX behavior so squad interaction flow is direct, safe, and visually consistent with the button system.
Resolution: Implemented direct squad click-through, removed redundant open action, renamed new-squad action, added backend-supported delete flow with safety gates, formalized rename contract, and replaced squad rendering with dedicated metal-strip list components.

---
name: Frontend Readability and List Scaling
status: complete
execution_window: open
is_current: no
issues:
  - Redesign preload scene spacing when hero logo is present
  - Replace management-screen unit lists with 3-column unit card layout
  - Add optional pagination support for unit, dice, and squad lists
  - Replace dice inventory text list with sprite-based dice grid
  - Increase baseline non-button text size across frontend scenes
description: |
  Improve UI readability and list scalability across management and loading
  screens to support larger inventories/rosters and clearer typography.
entry_criteria: |
  * Core scene navigation and list data loading are functional.
exit_criteria: |
  * Text readability is improved across scenes.
  * List systems support scalable display behavior.
  * Dice and unit presentation are upgraded from plain rows to richer layouts.
Resolution: Completed readability/list-scaling delivery: hero-logo preload spacing, 3-column unit-card presentation, paginated list foundations, dice sprite-grid rendering, and broader non-button text legibility improvements.

---
name: UAT HUD and Warband Micro-Polish
status: complete
execution_window: open
is_current: no
issues:
  - Reduce home button icon size to match energy icon
  - Swap HUD name and energy rows so player name appears above energy bar
  - Remove "Current squads" label from Warband Management screen
  - Vertically center squad names and shift text 15px right in Warband Management list
  - Remove "select a unit..." helper text from Warband Management screen
description: |
  Apply low-risk visual cleanup adjustments identified during UAT for HUD and
  warband-management presentation consistency.
entry_criteria: |
  * Primary UX milestones are stable enough to absorb polish tweaks.
exit_criteria: |
  * HUD icon/row ordering and warband text alignment/label cleanup match UAT notes.
Resolution: Completed HUD/warband micro-polish pass with icon-size normalization, HUD row order updates, and warband label/text cleanup and alignment refinements from UAT notes.

---
name: Milestone 17 - Documentation Encoding Integrity Sweep
status: complete
execution_window: open
is_current: yes
issues:
  - Repair mojibake/encoding corruption in overview and llm-ops docs
  - Repair mojibake/encoding corruption in systems and ux docs
  - Add documentation lint check for encoding-corruption patterns
description: |
  Remove text-encoding corruption artifacts from project-authored markdown and add a guardrail check so future edits do not reintroduce mojibake sequences.
entry_criteria: |
  * Documentation corpus has been scanned for mojibake patterns.
  * Fix scope is limited to project-authored markdown (excluding vendor/node_modules).
exit_criteria: |
  * No mojibake patterns remain in project-authored markdown.
  * Automated docs lint warns/fails on known corruption patterns.
Resolution: Completed documentation integrity sweep, validated internal markdown links and renamed-path references, and added lint-time encoding-corruption detection to prevent recurrence.
---
name: Milestone 15 - Build UX Rebuild Component Library
status: complete
execution_window: open
is_current: yes
issues:
  - Implement core layout shell components from 03-component-specifications
  - Implement reusable navigation components from 03-component-specifications
  - Implement shared list framework with loading, error, and pagination states
  - Implement standardized action controls and button variants from component spec
  - Implement HUD and feedback utility components from component spec
  - Add component-level test coverage and usage examples for UX rebuild library
description: |
  Build the reusable component library defined in `documentation/07-ux-rebuild/03-component-specifications.md` so scenes can migrate to consistent UI primitives.
entry_criteria: |
  * Component specification document is accepted as implementation baseline.
  * Asset dependencies for base component visuals are available in runtime assets.
exit_criteria: |
  * Specified core components are implemented and test-covered.
  * Components are ready for scene-by-scene migration without ad hoc UI duplication.
Resolution: Implemented reusable layout/navigation/list/action/HUD-feedback primitives, added component-level behavior tests and usage guidance, and prepared Milestone 16 migration work on an open current execution window.
---
name: Milestone 16 - Scene Migration to UX Rebuild Components
status: complete
execution_window: open
is_current: yes
issues:
  - Migrate HomeScene to standardized UX rebuild components
  - Migrate RegionSelectScene and MapExplorationScene to shared component library
  - Migrate warband management scenes to shared list/action components
  - Migrate inventory, rest, and run summary scenes to shared component library
  - Remove superseded scene-local UI implementations after migration
  - Update UX rebuild docs to reflect final component adoption per scene
description: |
  Migrate existing scenes onto the shared UX rebuild component library and retire superseded local UI implementations.
entry_criteria: |
  * Milestone 15 component implementations are available and verified.
  * Scene migration order and UX acceptance expectations are defined.
exit_criteria: |
  * Target scenes use shared components as primary UI implementation path.
  * Legacy duplicated scene-local UI code is removed or explicitly deprecated.
Resolution: Completed scene-by-scene migration to shared layout/navigation/feedback primitives, removed superseded scene-local wrappers, and validated frontend build + test gates with all tests passing.
---
name: Milestone 14 - Run Node Resolution Consolidation
status: complete
execution_window: open
is_current: yes
issues:
  - Add regression coverage for consolidated non-rest node resolution flow
description: |
  Consolidate non-rest node resolution behavior into a dedicated flow so map exploration focuses on navigation while resolution UX and API orchestration stay centralized and consistent.
entry_criteria: |
  * Existing map exploration and run-end behavior is stable enough to refactor routing.
  * Resolution requirements for combat/loot/boss/exit outcomes are documented.
exit_criteria: |
  * Non-rest node handling no longer duplicates resolve logic inside `MapExplorationScene`.
  * Resolution outcomes use one unified scene/flow with consistent messaging and return behavior.
Resolution: Added dedicated regression coverage for consolidated non-rest node-resolution behavior and validated routing/payload/run-end branching with fully passing frontend tests and build checks.
---
name: Milestone 13 - Player Experience and UX Flow
status: complete
execution_window: open
is_current: yes
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
Resolution: Delivered first-session journey/onboarding, encounter preview, post-battle summary, failure/recovery state, and player-value sequencing docs, then linked UX index references for ongoing planning/execution use.

---
name: Milestone 10 - Engineering Maintainability and Contracts
status: complete
execution_window: open
is_current: yes
issues:
  - Remove unsafe any-casts from API client team mutation flow
  - Refactor WarbandManagementScene logic into testable state module
  - Consolidate repeated controller auth/csrf/service bootstrap patterns
  - Remove nested transaction ownership between controllers and repositories
  - Introduce stricter typed DTO mapping for profile squad payload assembly
  - Reduce frontend production bundle size via scene-level code splitting
description: |
  Reduce technical debt in client/server architecture and enforce stronger typing and maintainability patterns.
entry_criteria: |
  * Milestones 2/3/4/5/6 scope has stabilized enough to avoid high churn during refactors.
  * API/scene contract docs are current enough to guide safe maintainability work.
exit_criteria: |
  * Maintainability/typing issues are complete and archived.
  * Contract-sensitive refactors pass regression verification.
Resolution: Completed typed frontend squad mutation contracts, extracted testable warband hub state logic, introduced shared backend controller bootstrap/CSRF patterns, centralized team update transaction ownership in repository orchestration, and added typed profile DTO mapping via `ProfileDtoMapper`.
