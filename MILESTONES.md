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
name: Warband UX Split Follow-up
status: in-progress
execution_window: open
is_current: yes
issues:
  - Formalize squad rename API contract for /api/v1/teams/:teamId
  - Add dedicated SquadListPanel component to replace UnitListPanel casting
  - Add metal-strip variant of ActionButton and list component for squad display
  - Open squad details directly when clicking squad row in warband hub
  - Add squad deletion flow with safety gates in SquadDetailsScene
  - Remove Open Squad button from warband hub actions
  - Rename Add Squad action to New Squad and keep it in warband hub action list
description: |
  Complete the post-split warband UX behavior so squad interaction flow is
  direct, safe, and visually consistent with the button system.
entry_criteria: |
  * Warband hub, squad details, and unit details screens are available.
exit_criteria: |
  * Squad rows open details directly.
  * Redundant open action is removed and action labels are finalized.
  * Squad delete/rename behavior is fully validated against backend rules.
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
  Resolve major map-screen interaction and readability gaps that currently block
  complete run navigation and player understanding.
entry_criteria: |
  * Map exploration scene is stable and loading run state.
exit_criteria: |
  * Combat nodes are wired.
  * Abandon run path exists.
  * Nodes stay within bounds and unlock relationships are visible.
```

```yaml
name: Frontend Readability and List Scaling
status: in-progress
execution_window: open
is_current: no
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
