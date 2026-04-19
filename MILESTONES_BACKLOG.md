# MILESTONES BACKLOG
----

## Purpose
- `MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `MILESTONES.md` when they are opened for execution.

## Backlog Milestones

name: Milestone 32 - Core Systems Validation and Balance
status: not-started
execution_window: closed
is_current: no
issues:
  - Verify unit promotion changes type abilities and growth correctly
  - Add automated test coverage for promotion transformation correctness
  - Enable and validate promotion access in rest flow and UI states
  - Validate positioning impact produces meaningful tactical differences
  - Evaluate and tune early game difficulty curve using beginner area
  - Reduce late game snowballing and ensure difficulty scaling
description: |
  Deferred validation and balance milestone preserved for return after the
  ability-loadout rework establishes the new baseline behavior.

---

name: Milestone 33 - Economy and System Validation
status: not-started
execution_window: closed
is_current: no
issues:
  - Validate shop transaction safety and edge case handling
  - Validate dice selling restrictions and economic impact
  - Balance currency earn and spend loop for meaningful decisions
description: |
  Deferred economy validation lane that should resume once the new dice-binding
  and starter-state model are stable.

---

name: Milestone 34 - Onboarding and First Session Clarity
status: not-started
execution_window: closed
is_current: no
issues:
  - Add guided onboarding prompts for first session player flow
  - Add dynamic next-step guidance across core scenes
  - Ensure all core systems are introduced during early gameplay
description: |
  Deferred onboarding milestone held until the reworked unit, combat, and
  promotion flows are concrete enough to teach accurately.

---

name: Milestone 35 - Feedback and UX Clarity
status: not-started
execution_window: closed
is_current: no
issues:
  - Fix run summary completeness for survivors defeated and rewards
  - Improve battle outcome clarity and player understanding
  - Improve reward presentation clarity and perceived impact
description: |
  Deferred clarity milestone that depends on the new battle log and progression
  contracts being in place first.

---

name: Milestone 36 - Pre Release Validation
status: not-started
execution_window: closed
is_current: no
issues:
  - Execute full internal playtest loop across all core systems
  - Validate save and load reliability across sessions and states
  - Identify and resolve progression blocking scenarios
description: |
  Deferred full validation milestone to be reopened after the ability-loadout
  rework and its follow-up cleanup pass are complete.

---

name: Milestone 40 - Rework Normalization Pass
status: not-started
execution_window: closed
is_current: no
issues:
  - Normalize rework migrations after schema stabilizes
  - Consolidate legacy combat and loadout test fixtures
  - Refactor repeated scene layout and styling after rework UI lands
description: |
  Deferred cleanup milestone for migration compaction, legacy test consolidation,
  and shared UI/layout refactors after the rework is functionally complete. This
  milestone is intentionally held in backlog until the next evaluation confirms
  the post-rework normalization order.
