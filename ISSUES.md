# ISSUES FILE
----

## Purpose
- `ISSUES.md` tracks active work only.
- Keep this file small and current for day-to-day execution context.
- Historical completed issues are moved to `ISSUES_ARCHIVE.md`.

## How to Use
- Valid statuses for active items:
  - `unstarted`
  - `in-progress`
  - `reopened`
  - `blocked`
- Valid priorities for active items:
  - `low`
  - `medium`
  - `high`
- When an issue is resolved:
  - set `status: complete`
  - append `Resolution:` (1-2 sentences)
  - move the full completed entry to `ISSUES_ARCHIVE.md`
  - remove the completed entry from this active file

## Issue Template
Use this block for new issues:

```yaml
title: <short summary>
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
blocked_by:
  - <issue title>
enables:
  - <issue title>
created: 2026-03-02
updated: 2026-03-02
description: |
  <problem, impact, and expected outcome>
```

## Active Issues

### Functional

```yaml
title: Formalize squad rename API contract for /api/v1/teams/:teamId
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - none
enables:
  - Stabilize squad details rename UX
created: 2026-03-05
updated: 2026-03-05
description: |
  Squad rename is now exposed in SquadDetailsScene and currently uses best-effort
  `name` payload on team update. Backend contract support and validation behavior
  should be formalized and documented so rename has deterministic success/failure
  handling.
```

```yaml
title: Add dedicated SquadListPanel component to replace UnitListPanel casting
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - none
enables:
  - Improve warband hub readability and squad-specific row badges/actions
created: 2026-03-05
updated: 2026-03-05
description: |
  Warband hub currently renders squads by adapting squad rows into UnitListPanel
  shape for reuse. Add a squad-specific list component to avoid type casting and
  support richer squad metadata and interactions cleanly.
```

```yaml
title: Add metal-strip variant of ActionButton and list component for squad display
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - none
enables:
  - Improve visual hierarchy between action controls and squad row interactions
created: 2026-03-05
updated: 2026-03-05
description: |
  Create an alternate ActionButton implementation that uses `metal_strip` and
  provide a list component parallel to ActionButtonList for rendering those
  alternate buttons. Use this variant to display squads on WarbandManagementScene.
```

```yaml
title: Open squad details directly when clicking squad row in warband hub
status: in-progress
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - none
enables:
  - Remove explicit Open Squad action button from warband hub
created: 2026-03-05
updated: 2026-03-05
description: |
  Update WarbandManagementScene so selecting/clicking a squad row immediately
  navigates to SquadDetailsScene for that squad, instead of requiring a separate
  open action.
```

```yaml
title: Add squad deletion flow with safety gates in SquadDetailsScene
status: in-progress
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - none
enables:
  - Safe squad cleanup without breaking active-run or minimum-squad constraints
created: 2026-03-05
updated: 2026-03-05
description: |
  Add delete squad capability from SquadDetailsScene with rules:
  - cannot delete the only remaining squad
  - cannot delete a squad currently referenced by an active run context.
  Include clear error messaging for blocked delete attempts.
```

```yaml
title: Remove Open Squad button from warband hub actions
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - Open squad details directly when clicking squad row in warband hub
enables:
  - Simplified squad interaction model on warband hub
created: 2026-03-05
updated: 2026-03-05
description: |
  Once squad rows open details directly, remove the redundant Open Squad action
  button from WarbandManagementScene.
```

```yaml
title: Rename Add Squad action to New Squad and keep it in warband hub action list
status: in-progress
priority: low
execution: active
ready: yes
owner: unassigned
milestone: Warband UX Split Follow-up
blocked_by:
  - none
enables:
  - Consistent action naming on warband hub
created: 2026-03-05
updated: 2026-03-05
description: |
  Change the squad-creation action label from `Add Squad` to `New Squad` and
  keep it in the action button list slot on WarbandManagementScene.
```

```yaml
title: Replace management-screen unit lists with 3-column unit card layout
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Frontend Readability and List Scaling
blocked_by:
  - none
enables:
  - Richer presentation for unit selection flows
created: 2026-03-05
updated: 2026-03-05
description: |
  Update management-screen unit displays to use cards:
  - square portrait area (portrait asset to be integrated later)
  - level shown in portrait bottom-right
  - unit name under portrait
  - list/grid rendering with 3 cards per row.
```

```yaml
title: Add optional pagination support for unit, dice, and squad lists
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Frontend Readability and List Scaling
blocked_by:
  - none
enables:
  - Scalable list rendering for larger inventories and rosters
created: 2026-03-05
updated: 2026-03-05
description: |
  Add optional pagination controls and state handling for unit lists, dice lists,
  and squad lists so UI remains usable with large item counts.
```

```yaml
title: Redesign preload scene spacing when hero logo is present
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Frontend Readability and List Scaling
blocked_by:
  - none
enables:
  - Improve loading screen readability
created: 2026-03-05
updated: 2026-03-05
description: |
  Current preload/loading scene text appears vertically scrunched when the hero
  logo is visible. Redo preload scene layout spacing and typography so loading
  text remains readable.
```

```yaml
title: Wire combat node click flow from map screen
status: in-progress
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
blocked_by:
  - none
enables:
  - Playable combat encounter entry from run map
created: 2026-03-05
updated: 2026-03-05
description: |
  Clicking a combat node currently shows "Node 'combat' is not wired yet".
  Implement combat-node transition/handling behavior from MapExplorationScene.
```

```yaml
title: Add abandon run action and confirmation flow
status: in-progress
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
blocked_by:
  - none
enables:
  - Explicit player exit path for active runs
created: 2026-03-05
updated: 2026-03-05
description: |
  Run screen has no abandon-run control. Add an abandon action with clear
  confirmation and backend call/update behavior.
```

```yaml
title: Prevent run-map nodes from rendering beyond visible bounds
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
blocked_by:
  - none
enables:
  - Reliable node visibility on map screen
created: 2026-03-05
updated: 2026-03-05
description: |
  Some map nodes are drawn past the screen edge. Update node scatter/layout bounds
  and placement logic so all interactive nodes remain fully visible.
```

```yaml
title: Show map edge indicators for node unlock paths
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Run Map UX Completion
blocked_by:
  - none
enables:
  - Clear player understanding of run progression graph
created: 2026-03-05
updated: 2026-03-05
description: |
  Add visual indicators on run map to show which nodes unlock other nodes
  (e.g., directional/path edges or equivalent relationship markers).
```

```yaml
title: Replace dice inventory text list with sprite-based dice grid
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Frontend Readability and List Scaling
blocked_by:
  - Add optional pagination support for unit, dice, and squad lists
enables:
  - Visual parity with dice art system in inventory UX
created: 2026-03-05
updated: 2026-03-05
description: |
  Dice inventory should render all owned dice as sprite cards/grid entries instead
  of plain text lines. Integrate with shared list scaling behavior (pagination or
  scrolling, whichever is adopted globally).
```

```yaml
title: Increase baseline non-button text size across frontend scenes
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Frontend Readability and List Scaling
blocked_by:
  - none
enables:
  - Improved readability of labels, helpers, and state messages
created: 2026-03-05
updated: 2026-03-05
description: |
  Most non-button text is currently too small. Perform a typography pass across
  scenes to raise baseline text sizes while preserving layout fit and hierarchy.
```

### Documentation








