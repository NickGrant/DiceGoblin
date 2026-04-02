# MILESTONES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive milestones moved from `MILESTONES.md`.
- Preserve milestone resolution history without bloating active execution context.

---
name: Milestone 31 - First Release UI Polish and Release Prep
status: complete
execution_window: open
is_current: no
issues:
  - Tighten run-map combat-result and run-summary readability
  - Restore deterministic scene screenshot review workflow against current local frontend
  - Improve warband unit and dice management readability for first release
  - Polish first-session presentation across landing home and region select
  - Redesign home shell and rename inventory/currency surfaces
  - Record first-release manual gate evidence and release checklist
description: |
  Convert the gameplay-complete MVP into a first-release-ready build by polishing the
  most player-visible UI surfaces, restoring screenshot-driven review automation, and
  capturing the final manual release evidence and operational checklist needed before
  inviting the first round of external users.
Resolution: Completed the first-release polish lane by stabilizing screenshot review, tightening the highest-visibility scenes, capturing manual release evidence, and documenting the explicit release checklist; the remaining rest-scene redesign was intentionally deferred into backlog follow-up work.

---
name: MVP Closeout Tooling and Combat UX
status: complete
execution_window: open
is_current: no
issues:
  - Complete combat viewer playback controls and tighten timeline spacing
  - Build dev panel and account reset tooling
  - Fix backend battle claim negative-branch coverage
  - Align MVP docs with shipped UX scope
description: |
  Close the remaining MVP-closeout gaps by finishing combat replay controls,
  adding debug/operator tooling and account reset support, repairing the backend
  claim test lane, and aligning docs to the actual shipped scope.
Resolution: Completed the combat replay control pass, added debug grant/reset tooling with a dedicated dev panel, repaired the backend negative-branch test lane, and updated MVP docs to match the shipped scope with dedicated dice-details explicitly deferred.

---
name: Milestone 29 - Run Node Resolution Action Scheduling
status: complete
execution_window: open
is_current: no
issues:
  - Fix run node action scheduler so all units act per tick speeds
description: |
  Correct deterministic run-node combat action timing so event generation follows
  per-ability speed ticks across all living units rather than limiting each round
  to one sampled action per side.
Resolution: Completed deterministic per-tick action scheduling across all living units using active-ability speeds, removed passive abilities from action event emission, and validated with backend integration coverage.

---
name: Milestone 28 - UAT Dialog and Interaction Hardening
status: complete
execution_window: open
is_current: no
issues:
  - Capture additional UAT findings for milestone 28
description: |
  Track user-acceptance hardening items focused on replacing browser-native dialogs
  with shared in-game modal and button components and validating interaction polish.
Resolution: Completed the UAT hardening wave, including dialog/button alignment and node-resolution UX refinements, then closed the milestone intake placeholder.

---
name: Milestone 30 - Dice Affix Generation and Inventory UX
status: complete
execution_window: open
is_current: no
issues:
  - Define dice affix rules fixed pool and contract updates
  - Add backend affix data model seed data and existing-dice backfill path
  - Implement rarity-based immutable affix assignment on dice creation
  - Surface dice affixes in profile payloads and inventory hover details
  - Add dice inventory sorting and filtering controls
description: |
  Introduce the first playable dice-affix system end to end by defining rarity-based
  affix counts and caps, seeding a fixed starter affix pool, assigning immutable
  affixes when dice are created, and exposing affix details plus inventory
  filtering/sorting in the frontend so acquired dice are understandable to players.
Resolution: Completed the first end-to-end dice-affix implementation across schema, backend generation/backfill, combat usage, profile contracts, and Dice Inventory UX with screenshot-backed UI validation.

---
name: Milestone 24 - Backend Test Infrastructure De-duplication
status: complete
execution_window: open
is_current: no
issues:
  - Extract shared backend integration test base class
  - Consolidate backend fixture insertion helpers into reusable factories
  - Centralize backend cascading cleanup utilities
description: |
  Reduce backend integration-test boilerplate by standardizing setup, fixture
  factories, and teardown utilities in shared infrastructure so tests are shorter,
  clearer, and easier to extend.
Resolution: Consolidated backend integration suites onto shared support infrastructure (`IntegrationTestCase`) with common setup/fixture/cleanup behavior, reducing duplication across endpoint tests.

---
name: Milestone 23 - ListContainer Normalization and Grid Fill Rules
status: complete
execution_window: open
is_current: no
issues:
  - Define ListContainer layout contract and card geometry constants
  - Implement deterministic fill-first wrapping algorithm in ListContainer
  - Ensure pagination visibility and non-overlap guarantees
  - Migrate all list-rendering components to ListContainer extension model
  - Add list layout regression tests for wrap and padding invariants
description: |
  Standardize list rendering around ListContainer so card grids fill available
  space predictably, preserve consistent padding, and keep pagination controls clear
  of content overlap.
Resolution: Completed list-contract and layout normalization, deterministic grid wrapping, pagination-lane reservation, component migrations to shared list primitives, and targeted list-layout regression coverage.

---
name: Milestone 22 - Unified Button and ButtonList System
status: complete
execution_window: open
is_current: no
issues:
  - Define unified button architecture with variant tokens
  - Implement SharedButton with action accept reject and metal variants
  - Build UnifiedButtonList replacing existing list wrappers
  - Migrate scenes and components to shared button primitives
  - Add button layout regression tests and sizing invariants
description: |
  Consolidate button primitives into one variant-driven base implementation and a
  single list wrapper to enforce consistent dimensions, spacing, and visual rhythm
  across all action surfaces.
Resolution: Completed shared button/token architecture and unified list wrapper, migrated scene surfaces to common button primitives, and stabilized action-surface behavior with regression coverage.

---
name: Milestone 21 - Reusable Modal Architecture
status: complete
execution_window: open
is_current: no
issues:
  - Define modal abstraction contract and migration plan
  - Implement BaseModal and yes/no confirmation variant
  - Implement InputModal by extending confirmation modal
  - Migrate existing modal call sites to new modal hierarchy
  - Add modal regression tests for lifecycle and input behavior
description: |
  Normalize modal behavior under a composable base class so confirmation and input
  flows share layout, lifecycle, and interaction rules while reducing duplicated
  scene-level dialog wiring.
Resolution: Completed modal hierarchy standardization (`BaseModal`/`ConfirmModal`/`InputModal`), migrated call sites to shared modal surfaces, and added modal lifecycle/input regression tests.

---
name: Milestone 18 - Run UI Layout Consistency Pass
status: complete
execution_window: open
is_current: no
issues:
  - Continue run panel sizing mismatch with start run
  - Rest screen layout pass for squad and controls
description: |
  Normalize run-adjacent panel sizing and rest-scene composition so UI density,
  spacing, and hierarchy are consistent with the current visual baseline.
Resolution: Completed run-panel sizing alignment and rest-scene layout consistency pass in frontend scenes with associated UI behavior updates.

---
name: Milestone 20 - Region Select UX and Readability
status: complete
execution_window: open
is_current: no
issues:
  - Distinguish locked region appearance in choose region
  - Improve region title readability and placement
  - Require double-click to start unlocked region
  - Set choose region default selection and intel CTA
description: |
  Refine region-select readability and interaction model with clearer lock states,
  stronger title contrast, and a deliberate selection/start flow.
Resolution: Completed locked-vs-unlocked visual differentiation, improved title contrast and placement, enforced double-click activation for unlocked regions, and verified default mountains selection with Intel panel Start Run CTA behavior.

---
name: Milestone 19 - Run Flow and Recovery UX
status: complete
execution_window: open
is_current: no
issues:
  - Mark no-enemies node resolved and show reason
  - Fix abandon run confirmation button overlap
  - Replace create squad native dialog with styled modal
  - Refresh home screen state immediately after abandon
description: |
  Improve run-state transitions, error recovery messaging, and dialog consistency
  so player flow remains continuous and clearly recoverable across node resolution
  and abandonment paths.
Resolution: Hardened no-enemies resolution handling against API reason-code variants, stabilized abandon confirmation modal sizing to prevent overlap, verified styled create-squad modal usage, and forced fresh home-state profile fetch after abandon with added regression coverage.

---
name: Milestone 26 - Frontend Scene Test Harness Consolidation
status: complete
execution_window: open
is_current: no
issues:
  - Introduce shared Phaser scene mock fixtures
  - Parameterize repetitive frontend scene error-path tests
  - Consolidate frontend mutation CSRF assertion patterns
description: |
  Normalize frontend test harness patterns to reduce duplicated mocks and repetitive
  test scaffolding while preserving scene behavior and API mutation coverage.
Resolution: Consolidated scene test doubles into shared Phaser fixtures, converted repetitive map-scene fallback assertions to table-driven coverage, and centralized mutation CSRF header assertions with helper utilities.

---
name: Milestone 27 - Assertion Depth and Contract Fidelity
status: complete
execution_window: open
is_current: no
issues:
  - Enforce richer contract-format assertions in frontend validators
  - Add payload-shape assertions to scene routing tests
  - Expand adapter and component edge-case coverage
description: |
  Increase test signal quality by asserting payload shape and format fidelity across
  frontend contracts, scene transitions, and adapter/component edge cases.
Resolution: Added validator enforcement for token/timestamp/range formats, strengthened scene routing payload-shape assertions, and expanded adapter/component malformed-input edge-case coverage.

---
name: Milestone 25 - Backend Test Cohesion and Signal Quality
status: complete
execution_window: open
is_current: no
issues:
  - Split battle claim mega-test into focused concern tests
  - Refactor lifecycle integration test into scenario-focused cases
  - Strengthen backend contract tests beyond status-only checks
description: |
  Improve backend test diagnosability and value by splitting multi-concern tests
  into focused scenarios and deepening assertions to validate response contracts,
  not just status flags.
Resolution: Split the monolithic battle/lifecycle integration coverage into focused suites with a shared battle fixture base and strengthened endpoint envelope contract assertions, with backend verification green.

---
name: Encounter Map Resolution and UX Hardening
status: complete
execution_window: open
is_current: yes
  - Align encounter map node-state contract across backend and frontend
  - Stabilize encounter map node placement and edge readability
  - Finalize encounter map interaction flow for combat, rest, and exit nodes
  - Harden map fallback and stale-run recovery messaging
  - Add encounter map frontend regression tests for NodeList construction and transitions
  - Add node-resolution scene regression tests for action-state transitions
  - Fix icon click hitboxes for consistent full-icon interaction
  - Replace icon resize hover effect with improved hover state feedback
description: |
  Consolidate encounter-map behavior, contracts, and UX reliability so run navigation,
  node resolution entry points, and map readability are stable before the next gameplay
  resolution pass.

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
