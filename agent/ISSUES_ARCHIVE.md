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
