# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

---
name: Frontend Readability and List Scaling
status: in-progress
execution_window: open
is_current: yes
issues:
  - Redesign preload scene spacing when hero logo is present
  - Replace management-screen unit lists with 3-column unit card layout
  - Add optional pagination support for unit, dice, and squad lists
  - Replace dice inventory text list with sprite-based dice grid
  - Increase baseline non-button text size across frontend scenes
description: |
  Improve UI readability and list scalability across management and loading
  screens to support larger inventories/rosters and clearer typography.
entry_criteria: |
  * Core scene navigation and list data loading are functional.
exit_criteria: |
  * Text readability is improved across scenes.
  * List systems support scalable display behavior.
  * Dice and unit presentation are upgraded from plain rows to richer layouts.

---
name: UAT HUD and Warband Micro-Polish
status: not-started
execution_window: closed
is_current: no
issues:
  - Reduce home button icon size to match energy icon
  - Swap HUD name and energy rows so player name appears above energy bar
  - Remove "Current squads" label from Warband Management screen
  - Vertically center squad names and shift text 15px right in Warband Management list
  - Remove "select a unit..." helper text from Warband Management screen
description: |
  Apply low-risk visual cleanup adjustments identified during UAT for HUD and
  warband-management presentation consistency.
entry_criteria: |
  * Primary UX milestones are stable enough to absorb polish tweaks.
exit_criteria: |
  * HUD icon/row ordering and warband text alignment/label cleanup match UAT notes.

