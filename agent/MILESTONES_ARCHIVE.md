# MILESTONES ARCHIVE
----

## Purpose
- This file is intentionally kept lean.
- Historical milestone records can be retrieved from git when needed.

---
name: Unit Progression Rework
status: complete
issues:
  - UPR-003
  - UPR-004
  - UPR-005
description: Implement the revised level 10 mastery, level 6 promotion eligibility, passive capstone inheritance, targeting weights, and specialized Tier 2/Tier 3 unit progression model.
goals:
  - Make every unit type max at level 10 while allowing promotion from level 6 onward.
  - Add passive level 10 capstone choices that inherit through promotion.
  - Make Tier 2 and Tier 3 promotions grant one active and one passive ability immediately.
  - Add deterministic targeting weights for marked, wounded, debuffed, backline, and preferred-target behaviors.
  - Implement the first specialized branch set for Bruiser, Marksman, Guardian, Bannerbearer, and Saboteur.
  - Add the Mascot support branch as the alternate Bannerbearer Tier 2 path.
current_code_context: The implementation should use documentation/02-systems-mvp/11-unit-progression-rework.md as the authoritative design reference. Likely affected areas include backend unit type seed data, ability registration and handlers, combat targeting, promotion logic, unit detail API responses, promotion UI, and tests around ability registry coverage and battle resolution.
exit_criteria:
  - Unit progression supports level 10 max and level 6 promotion eligibility independently.
  - Passive capstone choice is persisted and inherited through promotion.
  - Active abilities consume at least one die and expose a die-scaled variable component.
  - Defensive stack effects support half-die scaling where appropriate.
  - Targeting weights make marked, wounded, debuffed, backline, and preferred targets behave predictably.
  - Tier 2 promotion choices grant an active and passive immediately and expose level 10 capstones.
  - Mascot is available as a Bannerbearer Tier 2 branch.
  - Unit detail and promotion UX clearly communicate promotion eligibility, skipped capstones, mastered capstones, and inherited abilities.
  - Backend and frontend tests cover progression, inheritance, ability handlers, targeting behavior, capstone selection, and run-map passive behavior.

---
name: Authenticated Shell Fullscreen UX Pass
status: complete
issues:
  - UX-001
  - UX-002
  - UX-003
  - UX-004
description: Shift the authenticated game experience away from page-like layouts toward a responsive full-screen shell that feels like a game client across mobile, tablet, and desktop. The pass should establish a mobile-first layout contract, unify breakpoint behavior at 0-440px, 441-760px, and 761px+, and introduce screen-to-screen transitions that reduce the feel of traditional web page swaps.
goals:
  - Make authenticated screens fill the viewport and feel like a persistent game shell rather than isolated web pages.
  - Define and implement a mobile-first responsive layout system for 0-440px, 441-760px, and 761px+.
  - Introduce reusable route and screen transitions that make navigation feel intentional and game-like.
  - Validate the shell, HUD, navigation, spacing, and content behavior across core screens before broad rollout.
current_code_context: Primary implementation touched frontend shell/layout components, route containers, shared page-frame components, top HUD and navigation behavior, viewport spacing rules, and screen-level SCSS. UX reference docs under documentation/03-ux/ were updated to reflect the new shell and motion rules.
exit_criteria:
  - Authenticated pages use a consistent full-screen shell instead of page-like centered layouts where not intentionally required.
  - The three responsive breakpoints are documented and consistently implemented.
  - Mobile navigation, HUD density, and page framing remain usable without wasting excessive vertical space.
  - Core screens share a reusable transition pattern for route changes and stateful screen reveals.
  - The revised shell is verified across home, warband, inventory, shop, academy, and run-related flows.
