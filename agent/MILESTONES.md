# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Mobile Improvements

**Status:** Active  
**Purpose:** Improve playability and regression coverage for phone-sized viewports before broader testing.

### Goals

- Make the authenticated game shell and fixed bottom HUD usable on mobile.
- Ensure touch-first users can manage squads, formations, units, abilities, and dice without relying on precise drag/drop interactions.
- Add repeatable mobile viewport checks for critical play flows.

### Current Code Context

The latest GitHub code uses the Angular frontend structure under `frontend/src/app`. Mobile concerns are concentrated in the game shell, bottom command strip, page-level SCSS, squad details, unit details, and run node views.

### Exit Criteria

- No key game screen has horizontal overflow on common phone portrait widths.
- The fixed bottom command strip does not hide primary actions or important state.
- Navigation, squad formation, unit loadout editing, dice inventory, shop, run map, run node, and run summary are usable with touch input.
- Mobile QA coverage exists as either a documented checklist or automated viewport regression coverage.

### Related Issues

- MI-004: Add mobile viewport regression checks for key play flows.

## Unit Progression Rework

**Status:** Planned  
**Purpose:** Implement the revised level 10 mastery, level 6 promotion eligibility, passive capstone inheritance, targeting weights, and specialized Tier 2/Tier 3 unit progression model.

### Goals

- Make every unit type max at level 10 while allowing promotion from level 6 onward.
- Add passive level 10 capstone choices that inherit through promotion.
- Make Tier 2 and Tier 3 promotions grant one active and one passive ability immediately.
- Add deterministic targeting weights for marked, wounded, debuffed, backline, and preferred-target behaviors.
- Implement the first specialized branch set for Bruiser, Marksman, Guardian, Bannerbearer, and Saboteur.
- Add the Mascot support branch as the alternate Bannerbearer Tier 2 path.

### Current Code Context

The implementation should use `documentation/02-systems-mvp/11-unit-progression-rework.md` as the authoritative design reference. Likely affected areas include backend unit type seed data, ability registration and handlers, combat targeting, promotion logic, unit detail API responses, promotion UI, and tests around ability registry coverage and battle resolution.

### Exit Criteria

- Unit progression supports level 10 max and level 6 promotion eligibility independently.
- Passive capstone choice is persisted and inherited through promotion.
- Active abilities consume at least one die and expose a die-scaled variable component.
- Defensive stack effects support half-die scaling where appropriate.
- Targeting weights make marked, wounded, debuffed, backline, and preferred targets behave predictably.
- Tier 2 promotion choices grant an active and passive immediately and expose level 10 capstones.
- Mascot is available as a Bannerbearer Tier 2 branch.
- Unit detail and promotion UX clearly communicate promotion eligibility, skipped capstones, mastered capstones, and inherited abilities.
- Backend and frontend tests cover progression, inheritance, ability handlers, targeting behavior, capstone selection, and run-map passive behavior.

### Related Issues

- UPR-001: Add progression data model support for mastery and capstones.
- UPR-002: Add combat primitives for targeting weights, stacks, reactions, and debuff counting.
- UPR-003: Implement Tier 1 and Tier 2 unit ability packages.
- UPR-004: Add capstone and promotion UX.
- UPR-005: Add progression rework test coverage and validation.
