# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Rework rest management scene readability and structure
status: unstarted
priority: medium
execution: deferred
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Do a more substantial presentation and layout pass on `RestManagementScene`. The current rest flow is functional, but the scene still needs dedicated readability, spacing, and action-hierarchy work before it feels release-ready.

title: Verify unit promotion changes type abilities and growth correctly
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Validate that unit promotion correctly transforms the unit according to design intent. Promotion should change unit type slug, update abilities, reset stats, and apply new growth scaling. This is currently implemented but untested and is a critical progression mechanic.

---

title: Add automated test coverage for promotion transformation correctness
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Extend backend tests to validate that promotion correctly changes unit type, ability set, and stat scaling. Current tests validate success but not correctness of transformation, leaving risk of silent progression bugs.

---

title: Enable and validate promotion access in rest flow and UI states
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Ensure promotion is available in all intended player flows, especially during rest phases. The current UI may block promotion during active runs without clearly exposing the valid interaction window.

---

title: Validate positioning impact produces meaningful tactical differences
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Confirm that frontline and backline positioning meaningfully affect combat outcomes. Current implementation applies modifiers, but needs validation that outcomes are noticeable, consistent, and aligned with intended tactical depth.

---

title: Evaluate and tune early game difficulty curve using beginner area
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Use the new beginner area to ensure early gameplay is consistently winnable and teaches systems effectively. Current experience risks being too punishing before players understand mechanics.

---

title: Reduce late game snowballing and ensure difficulty scaling
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 32 - Core Systems Validation and Balance
description: Address runaway power growth where early success leads to trivial combat. Introduce scaling or friction to maintain challenge and preserve meaningful testing conditions.

---

title: Validate shop transaction safety and edge case handling
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 33 - Economy and System Validation
description: Verify all shop operations (buy unit, buy dice, daily deal, sell dice) handle edge cases correctly including insufficient funds, invalid items, duplicate purchases, and state consistency after transactions.

---

title: Validate dice selling restrictions and economic impact
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 33 - Economy and System Validation
description: Ensure players cannot sell equipped or invalid dice and that sell values are correct. Confirm no exploit loops exist that allow infinite or degenerate currency generation.

---

title: Balance currency earn and spend loop for meaningful decisions
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 33 - Economy and System Validation
description: Tune currency flow so players consistently face meaningful decisions between saving, upgrading, and purchasing. Avoid both scarcity frustration and excess trivialization.

---

title: Add guided onboarding prompts for first session player flow
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 34 - Onboarding and First Session Clarity
description: Introduce lightweight guidance to direct players through their first session including first combat, reward collection, and progression steps. Current tutorial area lacks explicit direction.

---

title: Add dynamic next-step guidance across core scenes
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 34 - Onboarding and First Session Clarity
description: Provide contextual guidance indicating what the player should do next across home, map, and management scenes to prevent confusion or inactivity.

---

title: Ensure all core systems are introduced during early gameplay
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 34 - Onboarding and First Session Clarity
description: Ensure players interact with combat, rewards, promotion, dice, and shop systems within early gameplay to support effective testing and understanding.

---

title: Fix run summary completeness for survivors defeated and rewards
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 35 - Feedback and UX Clarity
description: Ensure run summaries accurately reflect survivors, defeated units, and rewards. Current summaries may display incomplete or empty data, reducing player trust.

---

title: Improve battle outcome clarity and player understanding
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 35 - Feedback and UX Clarity
description: Improve visibility into why a battle was won or lost by surfacing key contributing factors such as positioning, abilities, and damage interactions.

---

title: Improve reward presentation clarity and perceived impact
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 35 - Feedback and UX Clarity
description: Ensure rewards are clearly communicated and that players understand what changed as a result of combat or progression.

---

title: Execute full internal playtest loop across all core systems
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 36 - Pre Release Validation
description: Perform multiple complete runs covering win, loss, resume, shop usage, promotion, and reset scenarios to identify integration issues across systems.

---

title: Validate save and load reliability across sessions and states
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 36 - Pre Release Validation
description: Confirm that all player progress persists correctly across reloads and that no data corruption or state loss occurs.

---

title: Identify and resolve progression blocking scenarios
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 36 - Pre Release Validation
description: Ensure players can always progress or recover from failure states and are not trapped in unwinnable or undefined conditions.
