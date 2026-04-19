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
title: Rework rest management scene readability and structure
status: unstarted
priority: medium
execution: deferred
ready: yes
milestone: unassigned
description: Do a more substantial presentation and layout pass on `RestManagementScene`. The current rest flow is functional, but the scene still needs dedicated readability, spacing, and action-hierarchy work before it feels release-ready. This was explicitly deferred at Milestone 31 closeout so the release-prep lane could finish without treating the rest screen as a blocker.

---
title: Verify unit promotion changes type abilities and growth correctly
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Validate that unit promotion correctly transforms the unit according to design intent. Promotion should change unit type slug, update abilities, reset stats, and apply new growth scaling. This validation lane was deferred when the deeper ability-loadout rework was opened.

---
title: Add automated test coverage for promotion transformation correctness
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Extend backend tests to validate that promotion correctly changes unit type, ability set, and stat scaling. This was deferred behind the more foundational rework because promotion behavior is being redesigned.

---
title: Enable and validate promotion access in rest flow and UI states
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Ensure promotion is available in all intended player flows, especially during rest phases. This remains important but is deferred until the new promotion model is implemented.

---
title: Validate positioning impact produces meaningful tactical differences
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Confirm that frontline and backline positioning meaningfully affect combat outcomes. This validation work will be revisited after the scheduler and dice-resolution rewrite stabilizes.

---
title: Evaluate and tune early game difficulty curve using beginner area
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Use the new beginner area to ensure early gameplay is consistently winnable and teaches systems effectively. This balance pass is deferred until the reworked combat model exists.

---
title: Reduce late game snowballing and ensure difficulty scaling
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Address runaway power growth where early success leads to trivial combat. This remains a later balance concern after the core combat rework is implemented.

---
title: Validate shop transaction safety and edge case handling
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 33 - Economy and System Validation
description: Verify all shop operations handle edge cases correctly. This validation lane is deferred while the combat and unit model are being reworked.

---
title: Validate dice selling restrictions and economic impact
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 33 - Economy and System Validation
description: Ensure players cannot sell equipped or invalid dice and that sell values are correct. This remains deferred until the new ability-slot dice model settles.

---
title: Balance currency earn and spend loop for meaningful decisions
status: unstarted
priority: medium
execution: deferred
ready: yes
milestone: Milestone 33 - Economy and System Validation
description: Tune currency flow so players consistently face meaningful decisions. This will be revisited once the new starter loadouts and dice behavior are in place.

---
title: Add guided onboarding prompts for first session player flow
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 34 - Onboarding and First Session Clarity
description: Introduce lightweight guidance for the first session. This is deferred until the core unit and combat interactions are stable enough to teach.

---
title: Add dynamic next-step guidance across core scenes
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 34 - Onboarding and First Session Clarity
description: Provide contextual guidance indicating what the player should do next across home, map, and management scenes. This remains deferred until the reworked flows are in place.

---
title: Ensure all core systems are introduced during early gameplay
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 34 - Onboarding and First Session Clarity
description: Ensure players interact with combat, rewards, promotion, dice, and shop systems within early gameplay. This depends on the reworked systems landing first.

---
title: Fix run summary completeness for survivors defeated and rewards
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 35 - Feedback and UX Clarity
description: Ensure run summaries accurately reflect survivors, defeated units, and rewards. This remains deferred while upstream combat and progression contracts are changing.

---
title: Improve battle outcome clarity and player understanding
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 35 - Feedback and UX Clarity
description: Improve visibility into why a battle was won or lost by surfacing key contributing factors. This work will benefit from the new battle log contract first.

---
title: Improve reward presentation clarity and perceived impact
status: unstarted
priority: medium
execution: deferred
ready: yes
milestone: Milestone 35 - Feedback and UX Clarity
description: Ensure rewards are clearly communicated and that players understand what changed. This stays deferred while the unit and combat rework changes upstream expectations.

---
title: Execute full internal playtest loop across all core systems
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 36 - Pre Release Validation
description: Perform multiple complete runs covering win, loss, resume, shop usage, promotion, and reset scenarios. This remains a downstream validation milestone after the rework is implemented.

---
title: Validate save and load reliability across sessions and states
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 36 - Pre Release Validation
description: Confirm that all player progress persists correctly across reloads and that no data corruption or state loss occurs. This is deferred until the new persistence model is implemented.

---
title: Identify and resolve progression blocking scenarios
status: unstarted
priority: high
execution: deferred
ready: yes
milestone: Milestone 36 - Pre Release Validation
description: Ensure players can always progress or recover from failure states and are not trapped in unwinnable or undefined conditions. This will be revisited after the reworked run, combat, and promotion flows land.

---
title: Normalize rework migrations after schema stabilizes
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 40 - Rework Normalization Pass
description: Compact transitional migration history once the rework schema is stable and remove no-longer-needed compatibility structures related to pooled dice and interim loadout persistence.

---
title: Consolidate legacy combat and loadout test fixtures
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 40 - Rework Normalization Pass
description: Refactor or remove tests and fixtures that preserve old modulo-scheduler or pooled-dice assumptions, replacing them with shared builders for the new unit and enemy loadout model.

---
title: Refactor repeated scene layout and styling after rework UI lands
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 40 - Rework Normalization Pass
description: Review repeated scene panel layout, typography, and style logic after the ability-loadout UI is implemented and consolidate the patterns into clearer shared helpers or components.
