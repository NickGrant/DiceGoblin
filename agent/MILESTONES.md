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
