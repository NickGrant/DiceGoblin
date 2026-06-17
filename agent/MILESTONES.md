# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Watcher Testing

**Status:** Active  
**Purpose:** Prepare a small set of guide and navigation improvements for watcher/user testing.

### Goals

- Make the guide useful to both anonymous visitors and logged-in players.
- Let logged-in players see which guide unlocks they have already acquired.
- Give players an obvious way to reach the guide from inside the authenticated game shell.

### Exit Criteria

- Logged-in users can open the guide without losing session or run state.
- Anonymous guide access still works.
- Acquired unlocks are clearly marked when a logged-in user views the guide.
- Unacquired unlocks remain understandable without hiding useful guide content.
- The in-game UI includes clear navigation to the guide and a clear way back to the game.

### Related Issues

- WT-003: Add navigation from inside the game to the guide.

## Mobile Improvements

**Status:** Planned  
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

- MI-001: Make the game shell and bottom HUD responsive on phones.
- MI-002: Make formation management usable on touch devices.
- MI-003: Make unit loadout editing touch-friendly.
- MI-004: Add mobile viewport regression checks for key play flows.
