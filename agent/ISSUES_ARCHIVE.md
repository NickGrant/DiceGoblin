# ISSUES ARCHIVE
----

## Purpose
- This file is intentionally kept lean.
- Historical issue records can be retrieved from git when needed.

---
id: UPR-003
title: Implement Tier 1 and Tier 2 unit ability packages
status: complete
priority: high
milestone: Unit Progression Rework
description: The unit roster needs updated ability packages so Tier 2 branches feel specialized and capstone choices create long-term lineage decisions. Tier 1 units need passive capstone choices, while Tier 2 branches need immediate active/passive unlocks plus level 10 passive capstones.
acceptance_criteria:
  - Implement Bruiser capstones: Brawl Hardened and Finisher.
  - Implement Enforcer promotion package: Skullcrack and Menacing Follow-Through.
  - Implement Enforcer capstones: No Mercy and Brutal Suppression.
  - Implement Pit Fighter promotion package: Desperate Swing and Counterpunch.
  - Implement Pit Fighter capstones: Last Goblin Standing and Crowd Favorite, with Crowd Favorite capped at 5 stacks.
  - Implement Marksman capstones: Patient Aim and Pick Your Mark, with targeting support.
  - Implement Deadeye promotion package: Piercing Shot and Vantage Point.
  - Implement Deadeye capstones: Kill Lane and Armor Gap.
  - Implement Trapper promotion package: Mark Target and Treasure Sense.
  - Implement Trapper capstones: Exposed Weaknesses and Barbed Mark.
  - Implement Guardian capstones: Bodyguard and Hold the Line.
  - Implement Bulwark promotion package: Taunting Guard and Shield Set.
  - Implement Bulwark capstones: Unmoving and Wall of Scrap.
  - Implement Shieldbreaker promotion package: Crack Armor and Find the Gap.
  - Implement Shieldbreaker capstones: Shatter Plate and Break Open.
  - Implement Bannerbearer capstones: Rally Rhythm and Patch Job.
  - Implement Warcaller promotion package: Warcry and Battle Tempo.
  - Implement Warcaller capstones: Chant of Violence and Mob Mentality.
  - Implement Mascot promotion package: Lucky Chant and Attention Hog.
  - Implement Mascot capstones: Dumb Luck and Morale Goblin.
  - Implement Saboteur capstones: Toxic Tools and Spiteful Reflex.
  - Implement Trickshot promotion package: Disarming Shot and Opportunist.
  - Implement Trickshot capstones: Disabling Hit and Clean Shot.
  - Implement Plaguehand promotion package: Poison Cloud and Nerve Toxin.
  - Implement Plaguehand capstones: Lingering Cloud and Sickly Weakness.
  - Do not introduce Quartermaster as part of this rework.
  - Keep all tuning conservative and centralized enough for later balancing.
current_code_references:
  - documentation/02-systems-mvp/11-unit-progression-rework.md
  - backend/migrations/30_seed_unit_types.sql
  - backend/src/Combat/Abilities/AbilityRegistry.php
  - backend/tests/Unit/Combat/AbilityHandlerRegistryCoverageTest.php

---
id: UPR-004
title: Add capstone and promotion UX
status: complete
priority: high
milestone: Unit Progression Rework
description: The player needs to understand the promote-early versus master-first decision. Since normal level-up is not interactive, the UI needs clear capstone selection and promotion-preview behavior without adding friction to every level gain.
acceptance_criteria:
  - Unit detail view shows current level, max level, promotion eligibility, mastery state, selected capstone, and inherited abilities.
  - Level 10 units can choose one passive capstone from two options.
  - If a level 10 unit starts promotion without a selected capstone, the UI requires capstone choice before confirming promotion.
  - Promotion preview warns when promoting before level 10 will skip the current unit type capstone.
  - Promotion preview shows the active and passive abilities granted immediately by the selected Tier 2 or Tier 3 type.
  - Promotion preview shows inherited passives and capstones clearly.
  - Copy uses player-facing language for wounded, capstone, mastered, inherited, and promotion eligibility.
  - The UX remains usable on mobile after the current mobile milestone work.
current_code_references:
  - documentation/02-systems-mvp/11-unit-progression-rework.md
  - frontend/src/app/pages/unit-details-page/*
  - frontend/src/app/pages/squad-details-page/*
  - backend/tests/Integration/GameplayUnitDetailsEndpointTest.php

---
id: UPR-005
title: Add progression rework test coverage and validation
status: complete
priority: high
milestone: Unit Progression Rework
description: The progression rework touches data, combat, promotion, API responses, targeting, run-map behavior, and frontend UX. It needs dedicated regression coverage so later balancing does not break core lineage rules.
acceptance_criteria:
  - Add backend tests for level 10 max level and level 6 promotion eligibility.
  - Add backend tests for capstone selection, persistence, and promotion inheritance.
  - Add backend tests that all new abilities are registered and have handlers.
  - Add combat tests for active abilities consuming dice and scaling at least one variable component.
  - Add combat tests for half-die defensive stack scaling.
  - Add combat tests for one-attack stack consumption and clearing.
  - Add combat tests for once-per-round reaction limits.
  - Add combat tests for target weighting with marked, wounded, debuffed, backline, and preferred targets.
  - Add tests for Treasure Sense revealing at most one hidden treasure node per run.
  - Add frontend tests or documented QA coverage for capstone selection and promotion preview.
  - Run the relevant verification commands from agent/QUALITY_GATES.md and report any failures.
current_code_references:
  - documentation/02-systems-mvp/11-unit-progression-rework.md
  - agent/QUALITY_GATES.md
  - backend/tests/Unit/Combat/AbilityHandlerRegistryCoverageTest.php
  - backend/tests/Integration/BattleNodeResolutionIntegrationTest.php
  - backend/tests/Integration/GameplayUnitDetailsEndpointTest.php

---
id: UX-001
title: Define full-screen shell and viewport layout contract
status: complete
priority: high
milestone: Authenticated Shell Fullscreen UX Pass
description: The authenticated frontend still reads like a set of web pages in a container instead of a continuous game shell. We need a single viewport/layout contract that defines how the HUD, content region, safe areas, vertical spacing, and screen framing behave before we tune individual pages.
acceptance_criteria:
  - Document the target shell behavior for authenticated screens, including viewport usage, safe-area handling, and persistent HUD spacing.
  - Refactor shared shell/page-frame layout so major authenticated screens fill the available viewport intentionally.
  - Remove accidental page-like margins and container behavior where they break the game-like presentation.
  - Keep desktop behavior aligned with the current top-header direction while preparing for mobile-first responsive work.
current_code_references:
  - frontend/src/app/layout/game-shell/*
  - frontend/src/app/layout/bottom-command-strip/*
  - frontend/src/app/shared/ui/dg-page-frame/*
  - frontend/src/app/app.scss
  - documentation/03-ux/08-page-layout-zones.md

---
id: UX-002
title: Implement mobile-first breakpoint system across authenticated UI
status: complete
priority: high
milestone: Authenticated Shell Fullscreen UX Pass
description: The shell and core screens need a deliberate mobile-first responsive system rather than ad hoc screen-size fixes. The UI should explicitly target 0-440px, 441-760px, and 761px+ and use those breakpoints to control layout density, HUD behavior, navigation, spacing, and content hierarchy.
acceptance_criteria:
  - Define the three canonical breakpoints and their intended layout behavior in UX docs and shared styles.
  - Update the authenticated shell and top HUD to follow the mobile-first breakpoint strategy.
  - Apply the breakpoint strategy to core authenticated screens so layout, spacing, and hierarchy remain consistent.
  - Avoid duplicate or conflicting breakpoint logic across related screens where a shared rule can be used instead.
current_code_references:
  - frontend/src/app/layout/bottom-command-strip/*
  - frontend/src/app/layout/game-shell/*
  - frontend/src/app/pages/home-page/*
  - frontend/src/app/pages/warband-page/*
  - frontend/src/app/pages/shop-page/*
  - documentation/03-ux/08-page-layout-zones.md
  - documentation/03-ux/09-first-session-player-journey.md

---
id: UX-003
title: Create reusable game-like screen transition system
status: complete
priority: medium
milestone: Authenticated Shell Fullscreen UX Pass
description: Route changes and screen reveals currently feel like standard website navigation. We need a lightweight but reusable transition system that adds game-like continuity without slowing interaction or obscuring critical state changes.
acceptance_criteria:
  - Define a transition vocabulary for route changes, page entry, and key panel reveals.
  - Implement reusable transition hooks or classes that can be applied across authenticated screens.
  - Ensure transitions are fast, readable, and can be reduced or disabled when accessibility or clarity requires it.
  - Apply the system to a representative set of core screens so the shell feels cohesive rather than one-off animated.
current_code_references:
  - frontend/src/app/layout/game-shell/*
  - frontend/src/app/app.html
  - frontend/src/app/app.scss
  - documentation/03-ux/03-encounter-flow-transition-matrix.md
  - documentation/03-ux/00-ux-and-debug-scope.md
