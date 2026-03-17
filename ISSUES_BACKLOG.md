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

title: Continue run panel sizing mismatch with start run
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 18 - Run UI Layout Consistency Pass
description: Continue run panel should match the start run panel dimensions and layout; start run is currently the correct reference.

---

title: Rest screen layout pass for squad and controls
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 18 - Run UI Layout Consistency Pass
description: Apply the same layout improvements used on squad details to rest screen, including a smaller unit list, better grid positioning, improved button spacing/positioning, and removal of helper text in the bottom column and bottom-left area.

---

title: Auto-return to map after finalize rest
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: Finalizing rest should immediately transition back to map without requiring a separate Continue click.

---

title: Mark no-enemies node resolved and show reason
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: In no-enemies error state, mark node as resolved and display the error reason to the player.

---

title: Add back to map action on resolve node
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: Resolve node screen should include an explicit Back to map button.

---

title: Fix abandon run confirmation button overlap
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: Abandon run confirmation dialog buttons overlap and need corrected sizing/positioning.

---

title: Replace create squad native dialog with styled modal
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: Create squad should use an in-game styled dialog instead of a JavaScript native dialog.

---

title: Rename abandon dialog options to abandon and stay
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: Abandon run dialog options should be Abandon and Stay, where Stay behaves as cancel.

---

title: Distinguish locked region appearance in choose region
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 20 - Region Select UX and Readability
description: Locked regions in choose region should have a clearly different visual appearance from unlocked regions.

---

title: Improve region title readability and placement
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 20 - Region Select UX and Readability
description: Region titles should be larger, centered, and rendered over a transparent black background to separate text from image content.

---

title: Require double-click to start unlocked region
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 20 - Region Select UX and Readability
description: Starting an unlocked region should require double-click confirmation.

---

title: Refresh home screen state immediately after abandon
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX
description: After abandoning a run, home screen image and link should update immediately without requiring another scene transition.

---

title: Set choose region default selection and intel CTA
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 20 - Region Select UX and Readability
description: Choose region should default to mountains selected with mountains info in region intel and a Start run button at the bottom; unlocked regions should still be selectable with single-click.

---

title: Define modal abstraction contract and migration plan
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 21 - Reusable Modal Architecture
description: Define BaseModal API and composition model (backdrop, frame, title, body, action row, keyboard lifecycle, close semantics) with concrete migration plan for current ConfirmationDialog and input-enabled modal flows.

---

title: Implement BaseModal and yes/no confirmation variant
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 21 - Reusable Modal Architecture
description: Implement reusable BaseModal and ConfirmModal variant for standard yes/no actions with shared sizing, spacing, button alignment, and callback behavior.

---

title: Implement InputModal by extending confirmation modal
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 21 - Reusable Modal Architecture
description: Extend modal stack with InputModal that reuses confirmation action surface while adding typed input, caret editing, allowed-character filtering, and enter/escape handling.

---

title: Migrate existing modal call sites to new modal hierarchy
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 21 - Reusable Modal Architecture
description: Replace direct ConfirmationDialog usages in map, warband, and squad scenes with BaseModal-derived variants and remove duplicate layout logic.

---

title: Add modal regression tests for lifecycle and input behavior
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 21 - Reusable Modal Architecture
description: Add tests for open/close lifecycle, keyboard attach/detach, cancel/confirm behavior, and input editing/validation edge cases across ConfirmModal and InputModal.

---

title: Define unified button architecture with variant tokens
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 22 - Unified Button and ButtonList System
description: Define SharedButton base API and variant token map that absorbs ActionButton, AcceptButton, RejectButton, and MetalActionButton with consistent baseline dimensions and spacing rules.

---

title: Implement SharedButton with action accept reject and metal variants
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 22 - Unified Button and ButtonList System
description: Implement single reusable button class with style variants, optional icon support, and unified text layout while preserving existing interaction behavior.

---

title: Build UnifiedButtonList replacing existing list wrappers
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 22 - Unified Button and ButtonList System
description: Replace ActionButtonList and MetalActionButtonList with one list container that supports variant-per-row configuration and consistent vertical rhythm.

---

title: Migrate scenes and components to shared button primitives
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 22 - Unified Button and ButtonList System
description: Migrate map, dice, squad, warband, unit, and modal action surfaces to SharedButton and UnifiedButtonList, then remove legacy wrappers.

---

title: Add button layout regression tests and sizing invariants
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 22 - Unified Button and ButtonList System
description: Add tests validating consistent button sizing, variant rendering hooks, list spacing, and no-overlap guarantees in constrained panels.

---

title: Define ListContainer layout contract and card geometry constants
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 23 - ListContainer Normalization and Grid Fill Rules
description: Define canonical ListContainer contract with optional title zone, consistent outer padding, dice-card-sized item geometry, and reserved pagination/footer space.

---

title: Implement deterministic fill-first wrapping algorithm in ListContainer
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 23 - ListContainer Normalization and Grid Fill Rules
description: Update ListContainer/GridListVariant to fill horizontal space item-by-item before wrapping rows, maximizing occupied area without overflow in either axis.

---

title: Ensure pagination visibility and non-overlap guarantees
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 23 - ListContainer Normalization and Grid Fill Rules
description: Reserve pagination lane consistently so next/prev controls and page indicators are never covered by list content at any viewport size.

---

title: Migrate all list-rendering components to ListContainer extension model
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 23 - ListContainer Normalization and Grid Fill Rules
description: Audit and migrate UnitListPanel, SquadListPanel, dice list surfaces, and other list UI components so all list rendering pipelines compose through ListContainer.

---

title: Add list layout regression tests for wrap and padding invariants
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 23 - ListContainer Normalization and Grid Fill Rules
description: Add tests for card wrapping boundaries, title/header offset behavior, stable outer padding, pagination visibility, and deterministic ordering during page changes.

---

title: Extract shared backend integration test base class
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 24 - Backend Test Infrastructure De-duplication
description: Create IntegrationTestCase extending DatabaseTestCase to centralize PDO/session bootstrap, teardown, and common test harness setup currently duplicated across backend integration tests.

---

title: Consolidate backend fixture insertion helpers into reusable factories
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 24 - Backend Test Infrastructure De-duplication
description: Move duplicated insertUser/insertTeam/insertRun/insertRegion helper logic into shared fixtures or base helpers and migrate existing test classes to use them.

---

title: Centralize backend cascading cleanup utilities
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 24 - Backend Test Infrastructure De-duplication
description: Replace per-class deleteByIds/execDelete cleanup patterns with one ordered reusable cleanup utility to reduce brittle teardown duplication.

---

title: Split battle claim mega-test into focused concern tests
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 25 - Backend Test Cohesion and Signal Quality
description: Refactor long multi-concern battle resolution and claim tests into narrower units for XP application, defeat handling, max-level constraints, and idempotency behavior.

---

title: Refactor lifecycle integration test into scenario-focused cases
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 25 - Backend Test Cohesion and Signal Quality
description: Split end-to-end run lifecycle tests that currently combine auth/run/node/claim concerns into scenario-specific tests with targeted assertions and clearer failure diagnostics.

---

title: Strengthen backend contract tests beyond status-only checks
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 25 - Backend Test Cohesion and Signal Quality
description: Expand endpoint envelope assertions to verify response shape and required keys consistently instead of relying mainly on HTTP status and ok flags.

---

title: Introduce shared Phaser scene mock fixtures
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 26 - Frontend Scene Test Harness Consolidation
description: Build reusable test fixtures for FakeScene/FakeContainer/input hooks used by scene tests and remove duplicated inline mock blocks across test files.

---

title: Parameterize repetitive frontend scene error-path tests
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 26 - Frontend Scene Test Harness Consolidation
description: Convert repetitive fallback/error-case tests into table-driven variants to reduce boilerplate and preserve coverage of each distinct error source.

---

title: Consolidate frontend mutation CSRF assertion patterns
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 26 - Frontend Scene Test Harness Consolidation
description: Reduce duplicated mutation CSRF/header assertions via shared helpers or parameterized test utilities while preserving endpoint-specific behavior checks.

---

title: Enforce richer contract-format assertions in frontend validators
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 27 - Assertion Depth and Contract Fidelity
description: Expand validator tests to assert format-level constraints such as timestamp shape, token-like fields, and numeric domain boundaries.

---

title: Add payload-shape assertions to scene routing tests
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 27 - Assertion Depth and Contract Fidelity
description: Ensure scene.start assertions validate payload structure and keys, not only destination scene names.

---

title: Expand adapter and component edge-case coverage
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 27 - Assertion Depth and Contract Fidelity
description: Add targeted edge-case tests for adapter normalization and stateful component behavior to increase confidence in malformed-input handling.

---
