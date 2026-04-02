# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

---
name: Milestone 31 - First Release UI Polish and Release Prep
status: in-progress
execution_window: open
is_current: yes
issues:
  - Rework rest management scene readability and structure
  - Record first-release manual gate evidence and release checklist
description: |
  Convert the gameplay-complete MVP into a first-release-ready build by polishing the
  most player-visible UI surfaces, restoring screenshot-driven review automation, and
  capturing the final manual release evidence and operational checklist needed before
  inviting the first round of external users.

---

name: Milestone 32 - Core Systems Validation and Balance
status: not-started
execution_window: open
is_current: no
issues:
  - Verify unit promotion changes type abilities and growth correctly
  - Add automated test coverage for promotion transformation correctness
  - Enable and validate promotion access in rest flow and UI states
  - Validate positioning impact produces meaningful tactical differences
  - Evaluate and tune early game difficulty curve using beginner area
  - Reduce late game snowballing and ensure difficulty scaling
description: |
  Validate that all core gameplay systems behave correctly and align with design intent.
  This milestone focuses on eliminating systemic risk before exposing the game to
  external players, including promotion correctness, positioning impact, and balance curve.

---

name: Milestone 33 - Economy and System Validation
status: not-started
execution_window: open
is_current: no
issues:
  - Validate shop transaction safety and edge case handling
  - Validate dice selling restrictions and economic impact
  - Balance currency earn and spend loop for meaningful decisions
description: |
  Ensure the in-game economy is stable, non-exploitable, and supports meaningful player
  decisions. This includes validating all shop flows and tuning currency pacing.

---

name: Milestone 34 - Onboarding and First Session Clarity
status: not-started
execution_window: open
is_current: no
issues:
  - Add guided onboarding prompts for first session player flow
  - Add dynamic next-step guidance across core scenes
  - Ensure all core systems are introduced during early gameplay
description: |
  Improve the first-time player experience so external testers can understand the game
  without guidance. Focus on direction, clarity, and early exposure to core systems.

---

name: Milestone 35 - Feedback and UX Clarity
status: not-started
execution_window: open
is_current: no
issues:
  - Fix run summary completeness for survivors defeated and rewards
  - Improve battle outcome clarity and player understanding
  - Improve reward presentation clarity and perceived impact
description: |
  Ensure players can clearly understand outcomes, rewards, and progression. This
  milestone improves trust in the system and supports meaningful player feedback.

---

name: Milestone 36 - Pre Release Validation
status: not-started
execution_window: open
is_current: no
issues:
  - Execute full internal playtest loop across all core systems
  - Validate save and load reliability across sessions and states
  - Identify and resolve progression blocking scenarios
description: |
  Final validation milestone before external testing. Ensures the full gameplay loop
  is stable, recoverable, and free of blockers across all major systems.
