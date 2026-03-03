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
