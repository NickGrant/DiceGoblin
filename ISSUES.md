# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Replace management-screen unit lists with 3-column unit card layout
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-05
description: |
  Update management-screen unit displays to use cards:
  - square portrait area (portrait asset to be integrated later)
  - level shown in portrait bottom-right
  - unit name under portrait
  - list/grid rendering with 3 cards per row.

---
title: Add optional pagination support for unit, dice, and squad lists
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-05
description: |
  Add optional pagination controls and state handling for unit lists, dice lists,
  and squad lists so UI remains usable with large item counts.

---
title: Redesign preload scene spacing when hero logo is present
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-05
description: |
  Current preload/loading scene text appears vertically scrunched when the hero
  logo is visible. Redo preload scene layout spacing and typography so loading
  text remains readable.

---
title: Replace dice inventory text list with sprite-based dice grid
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-05
description: |
  Dice inventory should render all owned dice as sprite cards/grid entries instead
  of plain text lines. Integrate with shared list scaling behavior (pagination or
  scrolling, whichever is adopted globally).

---
title: Increase baseline non-button text size across frontend scenes
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Frontend Readability and List Scaling
created: 2026-03-05
updated: 2026-03-05
description: |
  Most non-button text is currently too small. Perform a typography pass across
  scenes to raise baseline text sizes while preserving layout fit and hierarchy.

---
title: Reduce home button icon size to match energy icon
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-05
description: |
  UAT follow-up: resize the home button icon so it matches the energy icon size
  in the HUD/header area for consistent visual hierarchy.

---
title: Swap HUD name and energy rows so player name appears above energy bar
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-05
description: |
  UAT follow-up: in the overlay/HUD panel, swap the vertical order of player
  name and energy so name is rendered on the top row and the energy bar/value
  is rendered on the bottom row.

---
title: Remove "Current squads" label from Warband Management screen
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-05
description: |
  UAT follow-up: remove the "Current squads" text label from the Warband
  Management screen.

---
title: Vertically center squad names and shift text 15px right in Warband Management list
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-05
description: |
  UAT follow-up: adjust squad row label layout so squad names are vertically
  centered within their background strips and moved 15px to the right.

---
title: Remove "select a unit..." helper text from Warband Management screen
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: UAT HUD and Warband Micro-Polish
created: 2026-03-05
updated: 2026-03-05
description: |
  UAT follow-up: remove the "select a unit..." helper text from the Warband
  Management screen.

---
title: Purge and refetch cached player energy after energy-consuming actions
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-05
updated: 2026-03-05
description: |
  After any action that consumes energy, invalidate the frontend-cached player
  energy value and refetch current profile/session energy so UI state remains
  authoritative.

---
title: Show frontend blocked-feedback when run start is attempted in locked region
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-05
updated: 2026-03-05
description: |
  When a player attempts to start a run in a region that is not unlocked, show
  explicit in-game frontend feedback indicating the attempt is blocked and why.

---
title: Replace JavaScript confirm with in-game abandon-run confirmation dialog
status: in-progress
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: unassigned
created: 2026-03-05
updated: 2026-03-05
description: |
  Abandon run currently relies on a browser JavaScript confirmation dialog.
  Replace it with an in-game dialog/panel confirmation flow to match scene UX.

