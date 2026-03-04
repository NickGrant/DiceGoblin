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

---
name: Milestone 15 - Backend Gameplay Completion
status: in-progress
execution_window: open
is_current: yes
issues:
  - Implement rest workflow endpoints with transactional snapshot and squad sync
  - Implement backend auto-level application at rest finalize and run cleanup
  - Implement manual promotion endpoint with primary-secondary consume semantics
  - Expose dice equip and unequip gameplay endpoints with rest-only run constraints
description: |
  Complete missing backend gameplay functionality so the intended MVP loop can run end-to-end with authoritative state transitions.
entry_criteria: |
  * Gameplay contracts for run completion, rest workflow, auto-level, promotion, and equipment restrictions are documented.
  * Milestone 6 stability documentation is complete.
exit_criteria: |
  * Backend supports completed-run transition via exit path and terminal cleanup invariants.
  * Rest, promotion, and equipment APIs enforce active-run and ownership constraints.
  * All milestone issues are complete and archived.
