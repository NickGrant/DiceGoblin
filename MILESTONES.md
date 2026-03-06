# MILESTONES FILE
----

## Purpose
- `MILESTONES.md` tracks active milestone groupings.
- A milestone is a named collection of issue titles plus a milestone status.
- Milestones are optional; issues do not need to belong to a milestone.

## How to Use
- Valid milestone statuses:
  - `not-started`
  - `in-progress`
  - `complete`
  - `blocked`
- Required milestone fields:
  - `name`
  - `status`
  - `execution_window` (`open` | `closed`)
  - `is_current` (`yes` | `no`)
  - `issues` (list of issue titles from `ISSUES.md`)
- Milestone rule:
  - if a milestone has no issues, status must be `not-started`
- When a milestone is complete:
  - set `status: complete`
  - append `Resolution:` (1-2 sentences)
  - move the full milestone entry to `MILESTONES_ARCHIVE.md`
  - remove the completed entry from this active file

## Milestone Template
Use this structure for new milestones:
```yaml
name: <milestone name>
status: not-started
execution_window: closed
is_current: no
issues:
  - <issue title>
description: |
  <scope and exit criteria summary>
entry_criteria: |
  * <required condition to start>
exit_criteria: |
  * <required condition to complete>
```

## Active Milestones

```yaml
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
```

```yaml
name: Warband UX Split Follow-up
status: in-progress
execution_window: open
is_current: no
issues:
  - Formalize squad rename API contract for /api/v1/teams/:teamId
  - Add dedicated SquadListPanel component to replace UnitListPanel casting
  - Add metal-strip variant of ActionButton and list component for squad display
  - Open squad details directly when clicking squad row in warband hub
  - Add squad deletion flow with safety gates in SquadDetailsScene
  - Remove Open Squad button from warband hub actions
  - Rename Add Squad action to New Squad and keep it in warband hub action list
description: |
  Complete the warband management split-flow follow-up work so squad browsing,
  navigation, naming, and deletion behaviors are production-ready and coherent.
entry_criteria: |
  * Warband hub and squad details scenes are functionally reachable.
exit_criteria: |
  * Squad rows open details directly.
  * Open button is removed and New Squad naming is consistent.
  * Deletion safety rules and rename contract behavior are defined.
```

```yaml
name: Run Map UX Completion
status: in-progress
execution_window: open
is_current: no
issues:
  - Wire combat node click flow from map screen
  - Add abandon run action and confirmation flow
  - Prevent run-map nodes from rendering beyond visible bounds
  - Show map edge indicators for node unlock paths
description: |
  Close remaining run-map interaction and readability gaps so players can
  reliably navigate, understand, and exit active runs.
entry_criteria: |
  * Map scene renders and node selection is available.
exit_criteria: |
  * Combat nodes resolve via intended flow.
  * Abandon-run path exists with confirmation.
  * Nodes stay within visible bounds and unlock paths are visually clear.
```

```yaml
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
```

