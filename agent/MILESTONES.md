# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Unit Progression Rework

**Status:** Active  
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

- UPR-003: Implement Tier 1 and Tier 2 unit ability packages.
- UPR-004: Add capstone and promotion UX.
- UPR-005: Add progression rework test coverage and validation.
