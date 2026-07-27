# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Encounter Primitive Framework

**Status:** Active

Define reusable hazard and shrine primitives before expanding content breadth, keeping effects backend-authoritative and aligned with seed/catalog ownership rules.

Success criteria:

- Hazard and shrine primitive vocabularies are documented and implemented.
- Hazard population respects region eligibility, weighting, and run graph guarantees.
- Representative primitive resolution is covered by backend tests.

### Related Issues

- Populate hazard nodes from authored rules

## Encounter Content Pack

**Status:** Planned

Seed the initial roadmap content pack for hazards, shrines, and expanded chaos reels once the primitive framework is stable.

Success criteria:

- Ten hazards are authored through approved primitives.
- Ten shrines are authored through approved primitives.
- Chaos reels reach the target launch breadth or a documented launch-equivalent set.
- Seed validation or backend tests prove enabled entries can resolve safely.

### Related Issues

- Seed initial hazard catalog
- Seed initial shrine catalog
- Expand chaos reel catalogs

## General Inventory and Consumables

**Status:** Planned

Use the generic item foundation to add between-encounter healing and energy consumables after the core progression and encounter work is stable.

Success criteria:

- Healing consumables can be used outside combat.
- Energy consumables respect caps and pacing.
- Spending and restoration are backend-authoritative and covered by tests.

### Related Issues

- Add between-encounter unit healing consumables
- Add player energy recovery consumables
