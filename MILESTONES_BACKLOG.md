# MILESTONES BACKLOG
----

## Purpose
- `MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `MILESTONES.md` when they are opened for execution.

## Backlog Milestones

---
name: Milestone 18 - Run UI Layout Consistency Pass
status: not-started
execution_window: closed
is_current: no
issues:
	- Continue run panel sizing mismatch with start run
	- Rest screen layout pass for squad and controls
description: |
	Normalize run-adjacent panel sizing and rest-scene composition so UI density,
	spacing, and hierarchy are consistent with the current visual baseline.

---
name: Milestone 19 - Run Flow and Recovery UX
status: not-started
execution_window: closed
is_current: no
issues:
	- Auto-return to map after finalize rest
	- Mark no-enemies node resolved and show reason
	- Add back to map action on resolve node
	- Fix abandon run confirmation button overlap
	- Replace create squad native dialog with styled modal
	- Rename abandon dialog options to abandon and stay
	- Refresh home screen state immediately after abandon
description: |
	Improve run-state transitions, error recovery messaging, and dialog consistency
	so player flow remains continuous and clearly recoverable across node resolution
	and abandonment paths.

---
name: Milestone 20 - Region Select UX and Readability
status: not-started
execution_window: closed
is_current: no
issues:
	- Distinguish locked region appearance in choose region
	- Improve region title readability and placement
	- Require double-click to start unlocked region
	- Set choose region default selection and intel CTA
description: |
	Refine region-select readability and interaction model with clearer lock states,
	stronger title contrast, and a deliberate selection/start flow.

---
name: Milestone 21 - Reusable Modal Architecture
status: not-started
execution_window: closed
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

---
name: Milestone 22 - Unified Button and ButtonList System
status: not-started
execution_window: closed
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

---
name: Milestone 23 - ListContainer Normalization and Grid Fill Rules
status: not-started
execution_window: closed
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

---
name: Milestone 24 - Backend Test Infrastructure De-duplication
status: not-started
execution_window: closed
is_current: no
issues:
	- Extract shared backend integration test base class
	- Consolidate backend fixture insertion helpers into reusable factories
	- Centralize backend cascading cleanup utilities
description: |
	Reduce backend integration-test boilerplate by standardizing setup, fixture
	factories, and teardown utilities in shared infrastructure so tests are shorter,
	clearer, and easier to extend.

---
name: Milestone 25 - Backend Test Cohesion and Signal Quality
status: not-started
execution_window: closed
is_current: no
issues:
	- Split battle claim mega-test into focused concern tests
	- Refactor lifecycle integration test into scenario-focused cases
	- Strengthen backend contract tests beyond status-only checks
description: |
	Improve backend test diagnosability and value by splitting multi-concern tests
	into focused scenarios and deepening assertions to validate response contracts,
	not just status flags.

---
name: Milestone 26 - Frontend Scene Test Harness Consolidation
status: not-started
execution_window: closed
is_current: no
issues:
	- Introduce shared Phaser scene mock fixtures
	- Parameterize repetitive frontend scene error-path tests
	- Consolidate frontend mutation CSRF assertion patterns
description: |
	Normalize frontend test harness patterns to reduce duplicated mocks and repetitive
	test scaffolding while preserving scene behavior and API mutation coverage.

---
name: Milestone 27 - Assertion Depth and Contract Fidelity
status: not-started
execution_window: closed
is_current: no
issues:
	- Enforce richer contract-format assertions in frontend validators
	- Add payload-shape assertions to scene routing tests
	- Expand adapter and component edge-case coverage
description: |
	Increase test signal quality by asserting payload shape and format fidelity across
	frontend contracts, scene transitions, and adapter/component edge cases.
